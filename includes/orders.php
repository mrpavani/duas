<?php
// ==========================================================================
// DUÁS - PEDIDOS: criação a partir do carrinho, log de eventos e aplicação
// do retorno de pagamento (Mercado Pago) ao pedido.
// ==========================================================================

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/mercadopago.php';

// Fluxo de separação/envio, na ordem
const FULFILLMENT_FLOW = ['aguardando_pagamento', 'a_separar', 'em_separacao', 'separado', 'enviado', 'entregue'];

function fulfillment_label(string $s): string
{
    return [
        'aguardando_pagamento' => 'Aguardando pagamento',
        'a_separar'            => 'A separar',
        'em_separacao'         => 'Em separação',
        'separado'             => 'Separado / pronto',
        'enviado'             => 'Enviado',
        'entregue'            => 'Entregue',
        'cancelado'          => 'Cancelado',
    ][$s] ?? $s;
}

function payment_label_ptbr(string $s): string
{
    return mp_status_label($s)['label'];
}

/**
 * Registra um evento no histórico do pedido.
 */
function order_log(int $orderId, string $type, ?string $from, ?string $to, string $message, array $meta = [], ?int $adminId = null): void
{
    db()->prepare('INSERT INTO order_events (order_id, event_type, from_status, to_status, message, meta, admin_id) VALUES (?,?,?,?,?,?,?)')
        ->execute([$orderId, $type, $from, $to, $message, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, $adminId]);
}

function get_order($idOrCode): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = :id OR order_code = :code LIMIT 1');
    $stmt->execute([':id' => is_numeric($idOrCode) ? (int) $idOrCode : 0, ':code' => (string) $idOrCode]);
    return $stmt->fetch() ?: null;
}

function get_order_items(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function get_order_events(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_events WHERE order_id = ? ORDER BY id DESC');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

/**
 * Cria um pedido no banco a partir do carrinho da sessão.
 *
 * @param array $cart        $_SESSION['cart']
 * @param array $totals      ['subtotal','discount','shipping','total','coupon']
 * @param array $customer    ['name','email','phone','doc','cep','street','number',
 *                           'complement','district','city','state','address']
 * @param array|null $paymentMethod  linha de payment_methods escolhida
 * @param string $shippingLabel
 * @return array  registro do pedido recém-criado (get_order)
 */
function create_order_from_cart(array $cart, array $totals, array $customer, ?array $paymentMethod, string $shippingLabel): array
{
    $pdo = db();
    $code = 'DUAS-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

    $provider = $paymentMethod['provider'] ?? null;
    $pLabel   = $paymentMethod['label'] ?? null;
    $pRef     = isset($paymentMethod['id']) ? (int) $paymentMethod['id'] : null;
    $v = fn(string $k) => trim((string) ($customer[$k] ?? '')) ?: null;

    $pdo->prepare('
        INSERT INTO orders
            (order_code, status, payment_status, payment_provider, payment_label, payment_method_ref,
             fulfillment_status, shipping_label, subtotal, discount, shipping, total, coupon_code, placed_at,
             customer_name, customer_email, customer_phone, customer_doc, shipping_address,
             shipping_cep, shipping_street, shipping_number, shipping_complement,
             shipping_district, shipping_city, shipping_state, consent_at, consent_ip)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(),
             ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ')->execute([
        $code, 'Recebido', 'pending', $provider, $pLabel, $pRef,
        'aguardando_pagamento', $shippingLabel,
        $totals['subtotal'], $totals['discount'], $totals['shipping'], $totals['total'], $totals['coupon'] ?: null,
        $v('name'), $v('email'), $v('phone'), $v('doc'), $v('address'),
        $v('cep'), $v('street'), $v('number'), $v('complement'),
        $v('district'), $v('city'), $v('state'),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $insItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, size, quantity, unit_price) VALUES (?,?,?,?,?,?)');
    foreach ($cart as $item) {
        $product = get_product_by_id_or_slug($item['productId']);
        if (!$product) continue;
        $insItem->execute([
            $orderId, $product['id'], $product['name'], $item['size'],
            (int) $item['quantity'], $product['salePrice'] ?? $product['price'],
        ]);
    }

    order_log($orderId, 'system', null, 'pending', 'Pedido recebido. Aguardando confirmação do pagamento.', [
        'payment_method' => $pLabel, 'provider' => $provider,
    ]);

    return get_order($orderId);
}

/**
 * Aplica a resposta de um pagamento do Mercado Pago ao pedido:
 * atualiza status, detalhe, JSON bruto e avança a separação quando aprovado.
 *
 * @return array{payment_status:string,friendly:array,errors:string[]}
 */
function apply_mp_payment_to_order(array $order, array $payment): array
{
    $pdo = db();
    $orderId = (int) $order['id'];

    $status  = (string) ($payment['status'] ?? 'pending');
    $detail  = (string) ($payment['status_detail'] ?? '');
    $payId   = isset($payment['id']) ? (string) $payment['id'] : ($order['mp_payment_id'] ?? null);
    $prevStatus = (string) $order['payment_status'];

    $paidAt = null;
    if ($status === 'approved') {
        $paidAt = !empty($payment['date_approved']) ? date('Y-m-d H:i:s', strtotime($payment['date_approved'])) : date('Y-m-d H:i:s');
    }

    // pedido avança para "a separar" na primeira aprovação
    $newFulfillment = $order['fulfillment_status'];
    if ($status === 'approved' && $order['fulfillment_status'] === 'aguardando_pagamento') {
        $newFulfillment = 'a_separar';
    }
    if (in_array($status, ['rejected', 'cancelled', 'refunded', 'charged_back'], true)
        && $order['fulfillment_status'] === 'aguardando_pagamento') {
        $newFulfillment = 'aguardando_pagamento';
    }

    $pdo->prepare('
        UPDATE orders
           SET payment_status = ?, payment_status_detail = ?, mp_payment_id = ?, payment_raw = ?,
               paid_at = COALESCE(?, paid_at), fulfillment_status = ?,
               status = ?, payment_provider = COALESCE(payment_provider, ?)
         WHERE id = ?
    ')->execute([
        $status, $detail ?: null, $payId, json_encode($payment, JSON_UNESCAPED_UNICODE),
        $paidAt, $newFulfillment,
        mp_status_label($status)['label'], 'mercado_pago',
        $orderId,
    ]);

    $friendly = mp_friendly($status, $detail);
    $errors = mp_error_messages($payment);

    if ($prevStatus !== $status) {
        order_log($orderId, 'payment', $prevStatus, $status,
            $friendly['message'],
            ['status_detail' => $detail, 'mp_payment_id' => $payId, 'amount' => $payment['transaction_amount'] ?? null]);
        if ($newFulfillment !== $order['fulfillment_status']) {
            order_log($orderId, 'fulfillment', $order['fulfillment_status'], $newFulfillment,
                'Separação liberada após aprovação do pagamento.');
        }
    }

    return ['payment_status' => $status, 'friendly' => $friendly, 'errors' => $errors];
}

/**
 * Marca um pagamento como aprovado de forma simulada (Mercado Pago não configurado).
 */
function simulate_order_paid(array $order): void
{
    $pdo = db();
    $orderId = (int) $order['id'];
    $pdo->prepare("
        UPDATE orders
           SET payment_status = 'approved', payment_status_detail = 'simulado',
               status = 'Pagamento aprovado (simulado)', paid_at = NOW(),
               fulfillment_status = IF(fulfillment_status = 'aguardando_pagamento', 'a_separar', fulfillment_status)
         WHERE id = ?
    ")->execute([$orderId]);
    order_log($orderId, 'payment', $order['payment_status'], 'approved',
        'Pagamento aprovado em modo simulado (Mercado Pago sem credenciais cadastradas).');
    order_log($orderId, 'fulfillment', 'aguardando_pagamento', 'a_separar', 'Separação liberada.');
}
