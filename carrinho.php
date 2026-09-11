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

// Sem credencial do Mercado Pago nao existe cobranca, entao a loja nao pode
// aceitar o pedido. Havendo credencial mas sem a lista de meios (falha
// momentanea da API), o cliente ainda paga pelo Checkout Pro e a compra segue.
$lojaPodeCobrar = mp_is_ready();
$escolheNaLoja  = $lojaPodeCobrar && !empty($mpTypes);

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

$formErros = [];
$formErrosPagamento = [];
$form = [];

if (isset($_POST['place_order']) && !empty($cartItems)) {
    // Todos os dados são pedidos a cada compra: nada fica guardado entre visitas.
    foreach (['name','email','phone','doc','cep','street','number','complement','district','city','state'] as $campo) {
        $form[$campo] = trim((string) ($_POST['cust_' . $campo] ?? ''));
    }
    $form['state'] = strtoupper(substr($form['state'], 0, 2));
    $form['address'] = trim(sprintf(
        '%s, %s%s - %s, %s / %s - CEP %s',
        $form['street'], $form['number'],
        $form['complement'] !== '' ? ' (' . $form['complement'] . ')' : '',
        $form['district'], $form['city'], $form['state'], $form['cep']
    ));

    // Validação
    if ($form['name'] === '' || !str_contains($form['name'], ' ')) $formErros[] = 'Informe seu nome completo.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL))         $formErros[] = 'Informe um e-mail válido.';
    if (strlen(only_digits($form['phone'])) < 10)                   $formErros[] = 'Informe um telefone com DDD.';
    if (strlen(only_digits($form['doc'])) !== 11)                   $formErros[] = 'Informe um CPF válido (11 dígitos).';
    if (strlen(only_digits($form['cep'])) !== 8)                    $formErros[] = 'Informe um CEP válido (8 dígitos).';
    if ($form['street'] === '')                                     $formErros[] = 'Informe o endereço (rua).';
    if ($form['number'] === '')                                     $formErros[] = 'Informe o número.';
    if ($form['district'] === '')                                   $formErros[] = 'Informe o bairro.';
    if ($form['city'] === '')                                       $formErros[] = 'Informe a cidade.';
    if (strlen($form['state']) !== 2)                               $formErros[] = 'Informe o estado (UF).';
    if (empty($_POST['lgpd_consent']))                              $formErros[] = 'É necessário aceitar a Política de Privacidade para concluir a compra.';

    // A forma de pagamento e obrigatoria e precisa ser uma das oferecidas.
    $mpTypeEscolhido = trim((string) ($_POST['mp_type'] ?? ''));
    if (!$lojaPodeCobrar) {
        $formErrosPagamento[] = 'A loja está temporariamente sem meio de pagamento disponível. Nenhuma cobrança foi feita — tente novamente mais tarde.';
    } elseif ($escolheNaLoja && ($mpTypeEscolhido === '' || !isset($mpTypes[$mpTypeEscolhido]))) {
        $formErrosPagamento[] = 'Escolha a forma de pagamento.';
    }
    $formErros = array_merge($formErros, $formErrosPagamento);
}

// Em qual etapa do checkout a página deve reabrir depois de um erro do servidor:
// dado de cadastro volta para a etapa 1, forma de pagamento para a 2 e o
// aceite pendente fica na 3.
$errosDeDados = array_filter(
    $formErros,
    fn($e) => !str_contains($e, 'Política de Privacidade') && !in_array($e, $formErrosPagamento, true)
);
$stepInicial = 1;
if ($errosDeDados)              $stepInicial = 1;
elseif ($formErrosPagamento)    $stepInicial = 2;
elseif ($formErros)             $stepInicial = 3;

if (isset($_POST['place_order']) && !empty($cartItems) && !$formErros) {
    $customer = $form;
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

    $_SESSION['cart'] = [];
    unset($_SESSION['coupon']);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

    if (mp_is_ready() && $mpType !== '' && isset($mpTypes[$mpType])) {
        // ---- Checkout transparente: pagamento direto pela API do Mercado Pago ----
        [$firstName, $lastName] = split_full_name($customer['name']);
        $fone     = only_digits($customer['phone']);
        $ddd      = substr($fone, 0, 2);
        $foneNum  = substr($fone, 2);
        // O MP usa nomes de campo diferentes em cada bloco de endereço
        $endereco = [ // payer.address (boleto)
            'zip_code'      => only_digits($customer['cep']),
            'street_name'   => $customer['street'],
            'street_number' => $customer['number'],
            'neighborhood'  => $customer['district'],
            'city'          => $customer['city'],
            'federal_unit'  => $customer['state'],
        ];
        $enderecoEntrega = [ // additional_info.shipments.receiver_address
            'zip_code'      => only_digits($customer['cep']),
            'street_name'   => $customer['street'],
            'street_number' => $customer['number'],
            'city_name'     => $customer['city'],
            'state_name'    => $customer['state'],
            'apartment'     => $customer['complement'],
        ];

        // Todos os dados do formulário alimentam o pagamento
        $payload = [
            'transaction_amount' => round((float) $grandTotal, 2),
            'description'        => 'Pedido ' . $placedOrder['order_code'] . ' - Duás',
            'external_reference' => $placedOrder['order_code'],
            'payer' => [
                'email'          => $customer['email'],
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'identification' => ['type' => 'CPF', 'number' => only_digits($customer['doc'])],
                'phone'          => ['area_code' => $ddd, 'number' => $foneNum],
                'address'        => $endereco,
            ],
            'additional_info' => [
                'items' => array_map(fn($oi) => [
                    'id'          => (string) $oi['product_id'],
                    'title'       => $oi['product_name'],
                    'quantity'    => (int) $oi['quantity'],
                    'unit_price'  => (float) $oi['unit_price'],
                ], get_order_items($oid)),
                'payer' => [
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'phone'      => ['area_code' => $ddd, 'number' => $foneNum],
                    'address'    => [
                        'zip_code'      => $endereco['zip_code'],
                        'street_name'   => $endereco['street_name'],
                        'street_number' => $endereco['street_number'],
                    ],
                ],
                'shipments' => ['receiver_address' => array_filter($enderecoEntrega, fn($v) => $v !== '')],
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
            if ($payload['token'] === '')             $formErrors[] = 'Não recebemos os dados do cartão. Revise o número, validade e CVV.';
            if ($payload['payment_method_id'] === '') $formErrors[] = 'Não identificamos a bandeira do cartão.';
        } elseif ($mpType === 'bank_transfer') {
            $payload['payment_method_id'] = 'pix';
            // O MP exige yyyy-MM-dd'T'HH:mm:ss.SSSZ (com milissegundos)
            $payload['date_of_expiration'] = date('Y-m-d\TH:i:s.000P', strtotime('+1 day'));
        } elseif ($mpType === 'ticket' || $mpType === 'atm') {
            $payload['payment_method_id'] = trim($_POST['mp_bol_method'] ?? '') ?: ($mpTypes[$mpType][0]['id'] ?? 'bolbradesco');
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
        // Sem meio de pagamento nao ha cobranca: o pedido JAMAIS pode ser dado
        // como pago. Fica pendente e a loja e avisada pelo painel.
        $checkoutError = 'Não foi possível iniciar o pagamento: a loja está sem meio de pagamento configurado. '
            . 'Nenhuma cobrança foi feita e o pedido ficou pendente.';
        order_log($oid, 'payment', 'pending', 'pending',
            'Pedido recebido sem meio de pagamento configurado. Nenhuma cobranca foi criada.');
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
                <a href="pecas.php" class="btn btn-primary">Continuar comprando</a>
                <a href="contato.php" class="btn btn-secondary">Falar com o atendimento</a>
            <?php endif; ?>
        </div>

        <?php if ($order): ?>
            <p style="font-size: 0.78rem; color: var(--color-text-muted); margin-top: 20px; line-height: 1.6;">
                Guarde o número do pedido. Todas as atualizações &mdash; confirmação do pagamento,
                separação e envio com o código de rastreio &mdash; chegam por e-mail em
                <strong><?php echo htmlspecialchars($order['customer_email'] ?: 'seu e-mail'); ?></strong>.
            </p>
        <?php endif; ?>
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

                    <?php if ($formErros): ?>
                        <div class="checkout-erros">
                            <strong>Revise antes de continuar:</strong>
                            <ul>
                                <?php foreach ($formErros as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <ol class="ck-stepper" id="ckStepper">
                        <li data-for="1"><span class="ck-num">1</span> Seus dados</li>
                        <li data-for="2"><span class="ck-num">2</span> Forma de pagamento</li>
                        <li data-for="3"><span class="ck-num">3</span> Confirmar e pagar</li>
                    </ol>

                    <form action="carrinho.php" method="POST" id="checkoutForm" data-start-step="<?php echo (int) $stepInicial; ?>">

                    <!-- ===== Etapa 1: dados do cliente e entrega ===== -->
                    <section class="ck-step" data-step="1">
                        <h3 style="font-size: 1.2rem; margin-bottom: 6px; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">Seus Dados</h3>
                        <p style="font-size: 0.78rem; color: var(--color-text-muted); margin-bottom: 16px;">
                            Pedimos estes dados a cada compra &mdash; a loja não guarda cadastro nem senha.
                            Você acompanha o pedido pelo e-mail informado.
                        </p>

                        <div class="ck-grid">
                            <div class="ck-full">
                                <label>Nome completo *</label>
                                <input type="text" name="cust_name" class="form-input" value="<?php echo htmlspecialchars($form['name'] ?? ''); ?>" placeholder="Como no documento" autocomplete="name" required>
                            </div>
                            <div>
                                <label>E-mail *</label>
                                <input type="email" name="cust_email" class="form-input" value="<?php echo htmlspecialchars($form['email'] ?? ''); ?>" placeholder="voce@exemplo.com" autocomplete="email" required>
                            </div>
                            <div>
                                <label>Telefone com DDD *</label>
                                <input type="tel" name="cust_phone" class="form-input" value="<?php echo htmlspecialchars($form['phone'] ?? ''); ?>" placeholder="(11) 99999-9999" autocomplete="tel" required>
                            </div>
                            <div>
                                <label>CPF *</label>
                                <input type="text" name="cust_doc" class="form-input" value="<?php echo htmlspecialchars($form['doc'] ?? ''); ?>" placeholder="000.000.000-00" inputmode="numeric" required>
                            </div>
                        </div>

                        <h3 style="font-size: 1.2rem; margin: 28px 0 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">Endereço de Entrega</h3>

                        <div class="ck-grid">
                            <div>
                                <label>CEP *</label>
                                <input type="text" name="cust_cep" id="ckCep" class="form-input" value="<?php echo htmlspecialchars($form['cep'] ?? ''); ?>" placeholder="00000-000" inputmode="numeric" autocomplete="postal-code" required>
                            </div>
                            <div>
                                <label>Número *</label>
                                <input type="text" name="cust_number" class="form-input" value="<?php echo htmlspecialchars($form['number'] ?? ''); ?>" placeholder="123" required>
                            </div>
                            <div class="ck-full">
                                <label>Rua / logradouro *</label>
                                <input type="text" name="cust_street" id="ckStreet" class="form-input" value="<?php echo htmlspecialchars($form['street'] ?? ''); ?>" autocomplete="address-line1" required>
                            </div>
                            <div>
                                <label>Complemento</label>
                                <input type="text" name="cust_complement" class="form-input" value="<?php echo htmlspecialchars($form['complement'] ?? ''); ?>" placeholder="Apto, bloco&hellip;">
                            </div>
                            <div>
                                <label>Bairro *</label>
                                <input type="text" name="cust_district" id="ckDistrict" class="form-input" value="<?php echo htmlspecialchars($form['district'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label>Cidade *</label>
                                <input type="text" name="cust_city" id="ckCity" class="form-input" value="<?php echo htmlspecialchars($form['city'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label>Estado (UF) *</label>
                                <input type="text" name="cust_state" id="ckState" class="form-input" value="<?php echo htmlspecialchars($form['state'] ?? ''); ?>" maxlength="2" placeholder="SP" required>
                            </div>
                        </div>

                        <div class="ck-step-nav">
                            <button type="button" class="btn btn-primary btn-lg" data-goto="2">Continuar para pagamento</button>
                        </div>
                    </section>

                    <!-- ===== Etapa 2: escolha da forma de pagamento ===== -->
                    <section class="ck-step" data-step="2">
                        <h3 style="font-size: 1.2rem; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">Forma de Pagamento</h3>

                        <div class="mp-pay" style="margin-bottom: 24px;">

                            <?php if ($escolheNaLoja): ?>
                                <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: 10px;">Pagamento processado pelo Mercado Pago. Escolha abaixo como quer pagar.</p>

                                <div class="mp-type-list">
                                    <?php foreach ($mpTypes as $type => $methods): ?>
                                        <label class="mp-type">
                                            <?php // Nenhuma opcao vem marcada: a escolha tem de ser do cliente. ?>
                                            <input type="radio" name="mp_type" value="<?php echo htmlspecialchars($type); ?>" required <?php echo ($_POST['mp_type'] ?? '') === $type ? 'checked' : ''; ?>>
                                            <span class="mp-type-name"><?php echo htmlspecialchars(mp_type_label($type)); ?></span>
                                            <span class="mp-type-brands">
                                                <?php foreach (array_slice($methods, 0, 6) as $m): ?>
                                                    <?php if (!empty($m['thumbnail'])): ?>
                                                        <img src="<?php echo htmlspecialchars($m['thumbnail']); ?>" alt="<?php echo htmlspecialchars($m['name']); ?>" title="<?php echo htmlspecialchars($m['name']); ?>">
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </span>
                                        </label>
                                        <?php endforeach; ?>
                                </div>
                            <?php elseif ($lojaPodeCobrar): ?>
                                <p style="font-size: 0.82rem; color: var(--color-text-muted);">Você será direcionado ao ambiente seguro do <strong>Mercado Pago</strong> para escolher como pagar e concluir o pagamento.</p>
                            <?php else: ?>
                                <div class="checkout-erros" style="margin-bottom:0;">
                                    <strong>Pagamento indisponível no momento</strong>
                                    <p style="margin-top:6px;">A loja está sem meio de pagamento configurado, então não é possível concluir a compra agora. Seu carrinho continua salvo. Se preferir, fale com a gente pelo <a href="contato.php">contato</a>.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="ck-step-nav">
                            <button type="button" class="btn btn-outline" data-goto="1">Voltar</button>
                            <?php if ($lojaPodeCobrar): ?>
                                <button type="button" class="btn btn-primary btn-lg" data-goto="3">Continuar</button>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- ===== Etapa 3: dados do pagamento e confirmacao ===== -->
                    <section class="ck-step" data-step="3">
                        <h3 style="font-size: 1.2rem; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">Confirmar e Pagar</h3>

                        <p class="ck-recap" id="ckRecap" hidden></p>

                        <div class="mp-pay" style="margin-bottom: 24px;">
                            <?php if ($escolheNaLoja): ?>

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
                                        <p style="font-size: 0.82rem; color: var(--color-text-muted);">
                                            Usamos os dados que você informou na etapa 1 para gerar a cobrança.
                                            Ao finalizar, aparece o <strong>QR Code Pix</strong>; o pedido é liberado assim que o pagamento é compensado.
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <!-- Boleto -->
                                <?php if (isset($mpTypes['ticket']) || isset($mpTypes['atm'])): ?>
                                    <?php $boletoType = isset($mpTypes['ticket']) ? 'ticket' : 'atm'; ?>
                                    <div class="mp-fields" data-type="<?php echo $boletoType; ?>" hidden>
                                        <input type="hidden" name="mp_bol_method" value="<?php echo htmlspecialchars($mpTypes[$boletoType][0]['id'] ?? 'bolbradesco'); ?>">
                                        <p style="font-size: 0.82rem; color: var(--color-text-muted);">
                                            O boleto é emitido com o nome, CPF e endereço informados na etapa 1.
                                            A compensação leva até 2 dias úteis e o pedido é liberado após o pagamento.
                                        </p>
                                    </div>
                                <?php endif; ?>

                            <?php endif; ?>
                        </div>

                        <div class="lgpd-box">
                            <label class="lgpd-check">
                                <input type="checkbox" name="lgpd_consent" value="1" required <?php echo !empty($_POST['lgpd_consent']) ? 'checked' : ''; ?>>
                                <span>
                                    Li e aceito a <a href="politica-privacidade.php" target="_blank" rel="noopener">Política de Privacidade</a>.
                                    Autorizo o uso dos meus dados para processar o pagamento, emitir a nota e entregar o pedido.
                                </span>
                            </label>
                            <p class="lgpd-nota">
                                Os dados do cartão são digitados em campo criptografado e enviados direto ao
                                <strong>Mercado Pago</strong>, que é o responsável pelo processamento e pela guarda dessas
                                informações. <strong>A Duás não recebe nem armazena número de cartão ou código de segurança.</strong>
                            </p>
                        </div>

                        <input type="hidden" name="place_order" value="1">

                        <div class="ck-step-nav">
                            <button type="button" class="btn btn-outline" data-goto="2">Voltar</button>
                            <?php if ($lojaPodeCobrar): ?>
                                <button type="submit" class="btn btn-primary btn-lg" id="mpSubmitBtn">
                                    Pagar R$ <?php echo number_format($grandTotal, 2, ',', '.'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </section>
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
            submitLabel: <?php echo json_encode('Pagar R$ ' . number_format($grandTotal, 2, ',', '.')); ?>
        };
    </script>
    <script src="js/checkout-mp.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
