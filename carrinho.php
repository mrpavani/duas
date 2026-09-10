<?php
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/orders.php';

$page_title = "Sacola & Finalização de Pedido";
$summary = get_cart_summary();
$cartItems = $_SESSION['cart'] ?? [];

$couponMessage = '';
$couponApplied = $_SESSION['coupon'] ?? null;

if (isset($_POST['apply_coupon'])) {
    $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
    $promo = get_promotion_by_code($code);
    $check = promotion_check($promo, (float) $summary['total']);
    if ($check['ok']) {
        $_SESSION['coupon'] = $code;
        $couponApplied = $code;
        $couponMessage = 'Cupom ' . $code . ' aplicado com sucesso!';
    } else {
        unset($_SESSION['coupon']);
        $couponApplied = null;
        $couponMessage = $check['message'];
    }
}

$appliedPromo = $couponApplied ? get_promotion_by_code($couponApplied) : null;
$discountAmount = 0.0;
if ($appliedPromo && promotion_check($appliedPromo, (float) $summary['total'])['ok']) {
    $discountAmount = promotion_discount($appliedPromo, (float) $summary['total']);
} elseif ($couponApplied) {
    unset($_SESSION['coupon']);
    $couponApplied = null;
    if (!$couponMessage) $couponMessage = 'O cupom aplicado não é mais válido.';
}

$finalTotal = max(0, $summary['total'] - $discountAmount);

// Frete (usa a 1ª forma de entrega ativa; grátis se a regra vigente foi atingida)
$shippingMethods = get_active_shipping_methods();
if ($summary['hasFreeShipping']) {
    $shippingLabel = 'Frete grátis';
    $shippingCost = 0.0;
} elseif ($shippingMethods) {
    $shippingLabel = $shippingMethods[0]['label'];
    $shippingCost = $shippingMethods[0]['flat_rate'] !== null ? (float) $shippingMethods[0]['flat_rate'] : 24.90;
} else {
    $shippingLabel = 'Correios';
    $shippingCost = 24.90;
}
$grandTotal = $finalTotal + $shippingCost;

$paymentMethods = get_active_payment_methods();

// Mercado Pago: gateway cadastrado + tipos de pagamento que a conta oferece
$mpGateway = null;
foreach ($paymentMethods as $pm) {
    if ($pm['provider'] === 'mercado_pago') { $mpGateway = $pm; break; }
}
$mpTypes = mp_is_ready() ? mp_available_types() : [];
$mpCfg   = mp_is_ready() ? mp_config() : null;

/** "João da Silva Souza" => ["João", "da Silva Souza"] */
function split_full_name(string $name): array
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') return ['Cliente', 'Duás'];
    $parts = explode(' ', $name);
    $first = array_shift($parts);
    return [$first, $parts ? implode(' ', $parts) : $first];
}
function only_digits(string $v): string
{
    return preg_replace('/\D+/', '', $v);
}

// ---------------------------------------------------------------------------
// Retorno do Checkout Pro (Mercado Pago)
// ---------------------------------------------------------------------------
$mpResult = null;
if (isset($_GET['mp_return'])) {
    $order = get_order($_GET['mp_return']);
    if ($order) {
        $payId = $_GET['payment_id'] ?? $_GET['collection_id'] ?? ($order['mp_payment_id'] ?? null);
        if ($payId) {
            $res = mp_get_payment((string) $payId);
            if ($res['ok']) {
                $applied = apply_mp_payment_to_order($order, $res['data']);
                $order = get_order((int) $order['id']);
                $mpResult = $applied['friendly'] + ['errors' => $applied['errors'], 'order' => $order];
            } else {
                $mpResult = ['tone' => 'error', 'title' => 'Não foi possível confirmar o pagamento',
                             'message' => $res['error'] ?: 'Erro ao consultar o Mercado Pago.',
                             'errors' => [], 'order' => $order];
            }
        } else {
            $st = $_GET['status'] ?? $_GET['collection_status'] ?? 'pending';
            $mpResult = mp_friendly((string) $st, null) + ['errors' => [], 'order' => $order];
        }
    }
}

// ---------------------------------------------------------------------------
// Finalização do pedido
// ---------------------------------------------------------------------------
$orderCompleted = false;
$placedOrder = null;
$checkoutError = null;

if (isset($_POST['place_order']) && !empty($cartItems)) {
    $customer = [
        'name'    => trim($_POST['cust_name'] ?? ''),
        'email'   => trim($_POST['cust_email'] ?? ''),
        'doc'     => trim($_POST['cust_doc'] ?? ''),
        'address' => trim($_POST['cust_address'] ?? ''),
    ];
    $mpType = trim($_POST['mp_type'] ?? '');

    $totals = [
        'subtotal' => (float) $summary['total'],
        'discount' => (float) $discountAmount,
        'shipping' => (float) $shippingCost,
        'total'    => (float) $grandTotal,
        'coupon'   => $couponApplied,
    ];

    $placedOrder = create_order_from_cart($cartItems, $totals, $customer, $mpGateway, $shippingLabel);
    $oid = (int) $placedOrder['id'];

    if ($appliedPromo && $discountAmount > 0) {
        db()->prepare('UPDATE promotions SET used_count = used_count + 1 WHERE id = ?')->execute([$appliedPromo['id']]);
    }

    // Espelha em "Meus Pedidos" (conta.php)
    $sessItems = [];
    foreach (get_order_items($oid) as $oi) {
        $sessItems[] = ['name' => $oi['product_name'], 'size' => $oi['size'], 'qty' => $oi['quantity'], 'price' => $oi['unit_price']];
    }
    array_unshift($_SESSION['orders'], [
        'id' => $placedOrder['order_code'], 'date' => date('d/m/Y'),
        'status' => 'Aguardando pagamento', 'total' => (float) $placedOrder['total'], 'items' => $sessItems,
    ]);

    $_SESSION['cart'] = [];
    unset($_SESSION['coupon']);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

    if (mp_is_ready() && $mpType !== '' && isset($mpTypes[$mpType])) {
        // ---- Checkout transparente: pagamento direto pela API do Mercado Pago ----
        [$firstName, $lastName] = split_full_name($customer['name']);
        $payload = [
            'transaction_amount' => round((float) $grandTotal, 2),
            'description'        => 'Pedido ' . $placedOrder['order_code'] . ' - Duás',
            'external_reference' => $placedOrder['order_code'],
            'payer' => [
                'email'      => $customer['email'],
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ],
        ];
        // O MP recusa notification_url que não seja HTTPS público (ambiente local)
        if ($notifyUrl = mp_public_url($base . '/api/mp-webhook.php')) {
            $payload['notification_url'] = $notifyUrl;
        }
        $formErrors = [];

        if (mp_is_card_type($mpType)) {
            $payload['token']             = trim($_POST['mp_token'] ?? '');
            $payload['payment_method_id'] = trim($_POST['mp_payment_method_id'] ?? '');
            $payload['installments']      = $mpType === 'credit_card' ? max(1, (int) ($_POST['mp_installments'] ?? 1)) : 1;
            if (!empty($_POST['mp_issuer_id'])) $payload['issuer_id'] = trim($_POST['mp_issuer_id']);
            $payload['payer']['identification'] = ['type' => 'CPF', 'number' => only_digits($_POST['mp_card_doc'] ?? $customer['doc'])];
            if ($payload['token'] === '')             $formErrors[] = 'Não recebemos os dados do cartão. Revise o número, validade e CVV.';
            if ($payload['payment_method_id'] === '') $formErrors[] = 'Não identificamos a bandeira do cartão.';
        } elseif ($mpType === 'bank_transfer') {
            $payload['payment_method_id'] = 'pix';
            $payload['payer']['first_name'] = trim($_POST['mp_pix_name'] ?? $firstName) ?: $firstName;
            $payload['payer']['identification'] = ['type' => 'CPF', 'number' => only_digits($_POST['mp_pix_doc'] ?? $customer['doc'])];
            // O MP exige yyyy-MM-dd'T'HH:mm:ss.SSSZ (com milissegundos)
            $payload['date_of_expiration'] = date('Y-m-d\TH:i:s.000P', strtotime('+1 day'));
        } elseif ($mpType === 'ticket' || $mpType === 'atm') {
            $payload['payment_method_id'] = trim($_POST['mp_bol_method'] ?? '') ?: ($mpTypes[$mpType][0]['id'] ?? 'bolbradesco');
            $payload['payer']['identification'] = ['type' => 'CPF', 'number' => only_digits($_POST['mp_bol_doc'] ?? $customer['doc'])];
            $payload['payer']['address'] = [
                'zip_code'      => only_digits($_POST['mp_bol_cep'] ?? ''),
                'street_name'   => trim($_POST['mp_bol_street'] ?? ''),
                'street_number' => trim($_POST['mp_bol_number'] ?? ''),
                'neighborhood'  => trim($_POST['mp_bol_hood'] ?? ''),
                'city'          => trim($_POST['mp_bol_city'] ?? ''),
                'federal_unit'  => strtoupper(trim($_POST['mp_bol_uf'] ?? '')),
            ];
            foreach (['zip_code' => 'CEP', 'street_name' => 'rua', 'street_number' => 'número', 'city' => 'cidade', 'federal_unit' => 'UF'] as $k => $lbl) {
                if ($payload['payer']['address'][$k] === '') $formErrors[] = 'Informe o campo ' . $lbl . ' para gerar o boleto.';
            }
        }

        if ($formErrors) {
            order_log($oid, 'payment', 'pending', 'pending', 'Dados de pagamento incompletos: ' . implode(' | ', $formErrors));
            $mpResult = ['tone' => 'error', 'title' => 'Revise os dados de pagamento',
                         'message' => 'Faltam informações que o Mercado Pago exige para este tipo de pagamento.',
                         'errors' => $formErrors, 'order' => $placedOrder, 'extra' => []];
        } else {
            $res = mp_create_payment($placedOrder, $payload);
            if ($res['ok'] && !empty($res['data']['status'])) {
                $applied = apply_mp_payment_to_order($placedOrder, $res['data']);
                $placedOrder = get_order($oid);
                $mpResult = $applied['friendly'] + [
                    'errors' => $applied['errors'],
                    'order'  => $placedOrder,
                    'extra'  => mp_payment_extra($res['data']),
                ];
            } else {
                $errs = mp_error_messages($res['data']);
                if (!$errs) $errs = [$res['error'] ?: 'O Mercado Pago não retornou uma confirmação para este pagamento.'];
                order_log($oid, 'payment', 'pending', 'pending', 'Falha ao criar pagamento: ' . implode(' | ', $errs), ['data' => $res['data']]);
                $placedOrder = get_order($oid);
                $mpResult = ['tone' => 'error', 'title' => 'Pagamento não aprovado',
                             'message' => 'O Mercado Pago não conseguiu processar este pagamento.',
                             'errors' => $errs, 'order' => $placedOrder, 'extra' => []];
            }
        }
        $orderCompleted = true;
    } elseif (mp_is_ready() && $mpGateway) {
        // ---- Fallback: Checkout Pro (redirect) quando a lista de métodos não veio ----
        $items = array_map(
            fn($oi) => ['name' => $oi['product_name'], 'qty' => $oi['quantity'], 'price' => $oi['unit_price']],
            get_order_items($oid)
        );
        $pref = mp_create_preference(
            $placedOrder, $items,
            $base . '/carrinho.php?mp_return=' . urlencode($placedOrder['order_code']),
            $base . '/api/mp-webhook.php'
        );
        if ($pref['ok'] && $pref['init_point']) {
            db()->prepare('UPDATE orders SET mp_preference_id = ?, mp_init_point = ? WHERE id = ?')
                ->execute([$pref['preference_id'], $pref['init_point'], $oid]);
            order_log($oid, 'payment', 'pending', 'pending', 'Cliente redirecionado ao Checkout Pro do Mercado Pago.');
            header('Location: ' . $pref['init_point']);
            exit;
        }
        $checkoutError = $pref['error'] ?: 'Não foi possível iniciar o pagamento no Mercado Pago.';
        order_log($oid, 'payment', 'pending', 'pending', 'Falha ao criar preferência: ' . $checkoutError, ['data' => $pref['data']]);
        $orderCompleted = true;
    } else {
        simulate_order_paid($placedOrder);
        $placedOrder = get_order($oid);
        $orderCompleted = true;
    }
}

require_once __DIR__ . '/includes/header.php';

/** Renderiza um cartão de resultado (aprovado / pendente / recusado). */
function render_result_card(string $tone, string $title, string $message, array $errors, ?array $order, array $extra = []): void
{
    $toneMap = [
        'ok'    => ['#E8F5E9', '#2E7D32', '✓'],
        'info'  => ['#E3F2FD', '#1565C0', 'ℹ'],
        'warn'  => ['#FFF8E1', '#B26A00', '⏳'],
        'error' => ['#FBE9E7', '#C62828', '!'],
    ];
    [$bg, $fg, $icon] = $toneMap[$tone] ?? $toneMap['warn'];
    ?>
    <div style="max-width: 660px; margin: 0 auto; background: var(--color-bg-neutral); border: 1px solid var(--color-border); padding: 44px 32px; text-align: center;">
        <div style="width: 60px; height: 60px; border-radius: 50%; background: <?php echo $bg; ?>; color: <?php echo $fg; ?>; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 700; margin: 0 auto 18px;">
            <?php echo $icon; ?>
        </div>
        <h1 style="font-size: 1.9rem; margin-bottom: 10px;"><?php echo htmlspecialchars($title); ?></h1>
        <p style="color: var(--color-text-muted); margin-bottom: 20px;"><?php echo htmlspecialchars($message); ?></p>

        <?php if ($order): ?>
            <div style="background: #fff; border: 1px solid var(--color-border); padding: 18px 20px; text-align: left; font-size: 0.9rem; margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span>Pedido</span><strong>#<?php echo htmlspecialchars($order['order_code']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span>Pagamento</span><strong style="color: <?php echo $fg; ?>;"><?php echo htmlspecialchars(payment_label_ptbr($order['payment_status'])); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Total</span><strong>R$ <?php echo number_format((float) $order['total'], 2, ',', '.'); ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div style="background: #FBE9E7; border: 1px solid #F3C9C4; color: #C62828; text-align: left; padding: 16px 18px; margin-bottom: 24px; font-size: 0.88rem;">
                <strong style="display:block; margin-bottom: 6px;">O que aconteceu:</strong>
                <ul style="margin: 0; padding-left: 18px; list-style: disc;">
                    <?php foreach ($errors as $err): ?><li style="margin-bottom: 4px;"><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($extra['pix_qr_base64']) || !empty($extra['pix_code'])): ?>
            <div style="background: #fff; border: 1px solid var(--color-border); padding: 20px; margin-bottom: 24px; text-align: center;">
                <strong style="display:block; margin-bottom: 12px;">Pague com Pix para confirmar</strong>
                <?php if (!empty($extra['pix_qr_base64'])): ?>
                    <img src="data:image/png;base64,<?php echo htmlspecialchars($extra['pix_qr_base64']); ?>" alt="QR Code Pix" style="width: 200px; height: 200px; margin: 0 auto 12px;">
                <?php endif; ?>
                <?php if (!empty($extra['pix_code'])): ?>
                    <p style="font-size: 0.78rem; color: var(--color-text-muted); margin-bottom: 6px;">Ou copie o código Pix (copia e cola):</p>
                    <textarea readonly id="pixCode" style="width:100%; height: 64px; font-size: 0.72rem; padding: 8px; border: 1px solid var(--color-border); resize: none;"><?php echo htmlspecialchars($extra['pix_code']); ?></textarea>
                    <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 8px;" onclick="navigator.clipboard.writeText(document.getElementById('pixCode').value); this.textContent='Código copiado';">Copiar código Pix</button>
                <?php endif; ?>
                <?php if (!empty($extra['pix_ticket_url'])): ?>
                    <div style="margin-top: 10px;"><a href="<?php echo htmlspecialchars($extra['pix_ticket_url']); ?>" target="_blank" rel="noopener" class="link-underline">Abrir página de pagamento</a></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($extra['boleto_url'])): ?>
            <div style="background: #fff; border: 1px solid var(--color-border); padding: 20px; margin-bottom: 24px;">
                <strong style="display:block; margin-bottom: 10px;">Boleto gerado</strong>
                <a href="<?php echo htmlspecialchars($extra['boleto_url']); ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Abrir / imprimir boleto</a>
                <?php if (!empty($extra['boleto_barcode'])): ?>
                    <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 10px; word-break: break-all;">Linha digitável: <?php echo htmlspecialchars($extra['boleto_barcode']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <?php if ($tone === 'error'): ?>
                <a href="pecas.php" class="btn btn-primary">Voltar às compras</a>
                <a href="contato.php" class="btn btn-secondary">Falar com o atendimento</a>
            <?php else: ?>
                <a href="conta.php" class="btn btn-primary">Acompanhar em Meus Pedidos</a>
                <a href="pecas.php" class="btn btn-secondary">Continuar comprando</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>

<div class="section" style="padding-top: 40px;">
    <div class="container">

        <?php if ($mpResult): ?>
            <?php render_result_card($mpResult['tone'], $mpResult['title'], $mpResult['message'], $mpResult['errors'], $mpResult['order'], $mpResult['extra'] ?? []); ?>

        <?php elseif ($orderCompleted): ?>
            <?php
            $ps = $placedOrder['payment_status'] ?? 'pending';
            if ($checkoutError) {
                render_result_card('error', 'Não foi possível iniciar o pagamento',
                    'Seu pedido #' . $placedOrder['order_code'] . ' foi registrado, mas houve um problema ao falar com o Mercado Pago.',
                    [$checkoutError], $placedOrder);
            } elseif ($ps === 'approved') {
                render_result_card('ok', 'Pagamento aprovado!',
                    'Obrigada pela compra. Seu pedido #' . $placedOrder['order_code'] . ' já entrou na fila de separação do nosso atelier.',
                    [], $placedOrder);
            } else {
                render_result_card('warn', 'Pedido recebido',
                    'Assim que o pagamento do pedido #' . $placedOrder['order_code'] . ' for confirmado, iniciamos a separação.',
                    [], $placedOrder);
            }
            ?>

        <?php elseif (empty($cartItems)): ?>
            <div style="text-align: center; padding: 80px 0; max-width: 500px; margin: 0 auto;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin: 0 auto 20px auto; opacity: 0.4;">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <h2 style="font-family: var(--font-heading); margin-bottom: 12px;">Sua sacola está vazia</h2>
                <p style="color: var(--color-text-muted); margin-bottom: 24px;">Adicione peças à sua sacola para dar prosseguimento ao pedido.</p>
                <a href="pecas.php" class="btn btn-primary btn-lg">Explorar Coleção de Peças</a>
            </div>

        <?php else: ?>
            <div class="section-header text-left" style="margin-bottom: 32px;">
                <span class="subtitle">Finalização de Compra</span>
                <h1>Sua Sacola & Checkout</h1>
            </div>

            <div class="checkout-layout">
                <div>
                    <h3 style="font-size: 1.2rem; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">Itens Selecionados</h3>

                    <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 40px;">
                        <?php foreach ($cartItems as $key => $item): ?>
                            <?php
                            $product = get_product_by_id_or_slug($item['productId']);
                            if (!$product) continue;
                            $unitPrice = $product['salePrice'] ?? $product['price'];
                            ?>
                            <div class="cart-item" style="grid-template-columns: 90px 1fr auto;">
                                <img src="<?php echo htmlspecialchars($product['images'][0]); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 90px; height: 110px; object-fit: cover;">
                                <div>
                                    <h4 style="font-size: 1rem;"><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <div style="font-size: 0.85rem; color: var(--color-text-muted); margin: 4px 0;">Tamanho: <strong><?php echo htmlspecialchars($item['size']); ?></strong></div>
                                    <div style="font-weight: 600;">R$ <?php echo number_format($unitPrice, 2, ',', '.'); ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="remove-item-btn js-remove-item" data-product-id="<?php echo $product['id']; ?>" data-size="<?php echo htmlspecialchars($item['size']); ?>">Excluir</span>
                                    <div class="qty-control" style="margin-top: 12px;">
                                        <button class="qty-btn js-qty-change" data-action="decrease" data-product-id="<?php echo $product['id']; ?>" data-size="<?php echo htmlspecialchars($item['size']); ?>">-</button>
                                        <span class="qty-val"><?php echo $item['quantity']; ?></span>
                                        <button class="qty-btn js-qty-change" data-action="increase" data-product-id="<?php echo $product['id']; ?>" data-size="<?php echo htmlspecialchars($item['size']); ?>">+</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form action="carrinho.php" method="POST" id="checkoutForm">
                        <h3 style="font-size: 1.2rem; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">Dados de Entrega & Pagamento</h3>

                        <div class="checkout-fields">
                            <div>
                                <label style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 6px;">Nome Completo</label>
                                <input type="text" name="cust_name" class="form-input" value="Mariana Silva" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 6px;">CPF</label>
                                <input type="text" name="cust_doc" class="form-input" value="123.456.789-00" required>
                            </div>
                        </div>

                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 6px;">E-mail</label>
                            <input type="email" name="cust_email" class="form-input" value="mariana.silva@exemplo.com" required>
                        </div>

                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 6px;">Endereço de Entrega</label>
                            <input type="text" name="cust_address" class="form-input" value="Rua Oscar Freire, 980 - Apto 42, Jardins - São Paulo / SP" required>
                        </div>

                        <div class="mp-pay" style="margin-bottom: 24px;">
                            <label style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 8px;">Forma de Pagamento</label>

                            <?php if (mp_is_ready() && $mpTypes): ?>
                                <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: 10px;">Pagamento processado pelo Mercado Pago. Apenas as opções abaixo estão disponíveis para esta loja.</p>

                                <div class="mp-type-list">
                                    <?php $first = true; foreach ($mpTypes as $type => $methods): ?>
                                        <label class="mp-type">
                                            <input type="radio" name="mp_type" value="<?php echo htmlspecialchars($type); ?>" <?php echo $first ? 'checked' : ''; ?>>
                                            <span class="mp-type-name"><?php echo htmlspecialchars(mp_type_label($type)); ?></span>
                                            <span class="mp-type-brands">
                                                <?php foreach (array_slice($methods, 0, 6) as $m): ?>
                                                    <?php if (!empty($m['thumbnail'])): ?>
                                                        <img src="<?php echo htmlspecialchars($m['thumbnail']); ?>" alt="<?php echo htmlspecialchars($m['name']); ?>" title="<?php echo htmlspecialchars($m['name']); ?>">
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </span>
                                        </label>
                                        <?php $first = false; endforeach; ?>
                                </div>

                                <!-- Cartão (crédito / débito / pré-pago) -->
                                <?php if (array_intersect(MP_CARD_TYPES, array_keys($mpTypes))): ?>
                                    <div class="mp-fields" data-type="card" hidden>
                                        <div class="mp-grid">
                                            <div class="mp-col-full">
                                                <label>Número do cartão</label>
                                                <input type="text" class="form-input" name="mp_card_number" inputmode="numeric" autocomplete="cc-number" placeholder="0000 0000 0000 0000">
                                            </div>
                                            <div class="mp-col-full">
                                                <label>Nome impresso no cartão</label>
                                                <input type="text" class="form-input" name="mp_card_name" autocomplete="cc-name" placeholder="Como está no cartão">
                                            </div>
                                            <div>
                                                <label>Validade (MM/AA)</label>
                                                <input type="text" class="form-input" name="mp_card_exp" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/AA">
                                            </div>
                                            <div>
                                                <label>Código de segurança (CVV)</label>
                                                <input type="text" class="form-input" name="mp_card_cvv" inputmode="numeric" autocomplete="cc-csc" placeholder="000">
                                            </div>
                                            <div>
                                                <label>CPF do titular</label>
                                                <input type="text" class="form-input" name="mp_card_doc" inputmode="numeric" placeholder="000.000.000-00">
                                            </div>
                                            <div class="mp-installments-wrap" hidden>
                                                <label>Parcelas</label>
                                                <select class="form-input" name="mp_installments">
                                                    <option value="1">1x sem juros</option>
                                                </select>
                                            </div>
                                        </div>
                                        <input type="hidden" name="mp_token">
                                        <input type="hidden" name="mp_payment_method_id">
                                        <input type="hidden" name="mp_issuer_id">
                                    </div>
                                <?php endif; ?>

                                <!-- Pix -->
                                <?php if (isset($mpTypes['bank_transfer'])): ?>
                                    <div class="mp-fields" data-type="bank_transfer" hidden>
                                        <div class="mp-grid">
                                            <div>
                                                <label>Nome completo</label>
                                                <input type="text" class="form-input" name="mp_pix_name" placeholder="Titular da conta">
                                            </div>
                                            <div>
                                                <label>CPF</label>
                                                <input type="text" class="form-input" name="mp_pix_doc" inputmode="numeric" placeholder="000.000.000-00">
                                            </div>
                                        </div>
                                        <p style="font-size: 0.78rem; color: var(--color-text-muted); margin-top: 8px;">Após finalizar, você recebe um QR Code Pix. O pedido é liberado assim que o pagamento é compensado.</p>
                                    </div>
                                <?php endif; ?>

                                <!-- Boleto -->
                                <?php if (isset($mpTypes['ticket']) || isset($mpTypes['atm'])): ?>
                                    <?php $boletoType = isset($mpTypes['ticket']) ? 'ticket' : 'atm'; ?>
                                    <div class="mp-fields" data-type="<?php echo $boletoType; ?>" hidden>
                                        <input type="hidden" name="mp_bol_method" value="<?php echo htmlspecialchars($mpTypes[$boletoType][0]['id'] ?? 'bolbradesco'); ?>">
                                        <div class="mp-grid">
                                            <div><label>Nome completo</label><input type="text" class="form-input" name="mp_bol_name" placeholder="Nome do pagador"></div>
                                            <div><label>CPF</label><input type="text" class="form-input" name="mp_bol_doc" inputmode="numeric" placeholder="000.000.000-00"></div>
                                            <div><label>CEP</label><input type="text" class="form-input" name="mp_bol_cep" inputmode="numeric" placeholder="00000-000"></div>
                                            <div><label>Número</label><input type="text" class="form-input" name="mp_bol_number" placeholder="123"></div>
                                            <div class="mp-col-full"><label>Rua / logradouro</label><input type="text" class="form-input" name="mp_bol_street"></div>
                                            <div><label>Bairro</label><input type="text" class="form-input" name="mp_bol_hood"></div>
                                            <div><label>Cidade</label><input type="text" class="form-input" name="mp_bol_city"></div>
                                            <div><label>UF</label><input type="text" class="form-input" name="mp_bol_uf" maxlength="2" placeholder="SP"></div>
                                        </div>
                                        <p style="font-size: 0.78rem; color: var(--color-text-muted); margin-top: 8px;">O boleto tem compensação em até 2 dias úteis. O pedido é liberado após o pagamento.</p>
                                    </div>
                                <?php endif; ?>

                            <?php elseif (mp_is_ready()): ?>
                                <p style="font-size: 0.82rem; color: var(--color-text-muted);">Você será direcionado ao ambiente seguro do Mercado Pago para concluir o pagamento.</p>
                            <?php elseif ($mpGateway): ?>
                                <p style="font-size: 0.82rem; color: var(--color-text-muted);">Mercado Pago cadastrado sem credenciais &mdash; o pagamento será aprovado em <strong>modo de teste</strong>.</p>
                            <?php else: ?>
                                <p style="font-size: 0.85rem; color: var(--color-error);">Nenhum meio de pagamento ativo. Cadastre o Mercado Pago em <strong>/admin</strong>.</p>
                            <?php endif; ?>
                        </div>

                        <input type="hidden" name="place_order" value="1">
                        <button type="submit" class="btn btn-primary btn-full btn-lg" id="mpSubmitBtn">
                            Concluir e Pagar R$ <?php echo number_format($grandTotal, 2, ',', '.'); ?>
                        </button>
                    </form>
                </div>

                <div style="background: var(--color-bg-neutral); padding: 32px; border: 1px solid var(--color-border); position: sticky; top: 100px;">
                    <h3 style="font-size: 1.2rem; margin-bottom: 20px;">Resumo do Pedido</h3>

                    <form action="carrinho.php" method="POST" style="margin-bottom: 24px;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 6px;">Possui Cupom de Desconto?</label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" name="coupon_code" class="form-input" placeholder="ex: DUAS10" value="<?php echo htmlspecialchars($couponApplied ?? ''); ?>">
                            <button type="submit" name="apply_coupon" class="btn btn-secondary btn-sm">Aplicar</button>
                        </div>
                        <?php if ($couponMessage): ?>
                            <div style="font-size: 0.8rem; color: <?php echo $discountAmount > 0 ? '#2E7D32' : '#D32F2F'; ?>; margin-top: 6px;">
                                <?php echo htmlspecialchars($couponMessage); ?>
                            </div>
                        <?php endif; ?>
                    </form>

                    <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.95rem; border-top: 1px solid var(--color-border); padding-top: 16px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Subtotal</span>
                            <span>R$ <?php echo number_format($summary['total'], 2, ',', '.'); ?></span>
                        </div>

                        <?php if ($discountAmount > 0): ?>
                            <div style="display: flex; justify-content: space-between; color: #2E7D32;">
                                <span>Desconto<?php echo $couponApplied ? ' (Cupom ' . htmlspecialchars($couponApplied) . ')' : ''; ?></span>
                                <span>- R$ <?php echo number_format($discountAmount, 2, ',', '.'); ?></span>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between;">
                            <span>Frete<?php echo $shippingLabel ? ' (' . htmlspecialchars($shippingLabel) . ')' : ''; ?></span>
                            <span><?php echo $shippingCost > 0 ? 'R$ ' . number_format($shippingCost, 2, ',', '.') : '<strong style="color:#2E7D32;">GRÁTIS</strong>'; ?></span>
                        </div>

                        <div style="display: flex; justify-content: space-between; font-size: 1.3rem; font-weight: 700; border-top: 2px solid var(--color-primary); padding-top: 16px; margin-top: 8px;">
                            <span>Total</span>
                            <span>R$ <?php echo number_format($grandTotal, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div style="margin-top: 20px; font-size: 0.75rem; color: var(--color-text-muted); text-align: center; line-height: 1.5;">
                        🔒 Pagamento processado com segurança pelo Mercado Pago.<br>Embalagem presenteável em todas as entregas.
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </div>
</div>

<?php if (!$mpResult && !$orderCompleted && !empty($cartItems) && mp_is_ready() && $mpTypes): ?>
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <script>
        window.DUAS_MP = {
            publicKey: <?php echo json_encode($mpCfg['public_key'] ?? ''); ?>,
            amount: <?php echo json_encode(round((float) $grandTotal, 2)); ?>,
            cardTypes: <?php echo json_encode(MP_CARD_TYPES); ?>,
            submitLabel: <?php echo json_encode('Concluir e Pagar R$ ' . number_format($grandTotal, 2, ',', '.')); ?>
        };
    </script>
    <script src="js/checkout-mp.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
