<?php
// ==========================================================================
// DUÁS - WEBHOOK MERCADO PAGO
// Recebe notificações de pagamento, consulta a API e atualiza o pedido.
// Sempre responde 200 para evitar reenvios em loop.
// ==========================================================================

require_once __DIR__ . '/../includes/orders.php';

http_response_code(200);
header('Content-Type: application/json');

// Extrai o id do pagamento das várias formas que o MP usa
$paymentId = $_GET['data.id'] ?? $_GET['id'] ?? null;
$topic = $_GET['type'] ?? $_GET['topic'] ?? null;

$rawBody = file_get_contents('php://input');
if ($rawBody) {
    $body = json_decode($rawBody, true);
    if (is_array($body)) {
        $topic = $topic ?: ($body['type'] ?? $body['topic'] ?? null);
        $paymentId = $paymentId ?: ($body['data']['id'] ?? $body['id'] ?? null);
    }
}

if ($topic && strpos((string) $topic, 'payment') === false) {
    echo json_encode(['ignored' => true, 'topic' => $topic]);
    exit;
}
if (!$paymentId) {
    echo json_encode(['error' => 'sem id de pagamento']);
    exit;
}

$res = mp_get_payment((string) $paymentId);
if (!$res['ok']) {
    echo json_encode(['error' => 'falha ao consultar pagamento', 'detail' => $res['error']]);
    exit;
}

$payment = $res['data'];
$ref = $payment['external_reference'] ?? null;
$order = $ref ? get_order($ref) : null;

if (!$order) {
    echo json_encode(['error' => 'pedido não encontrado', 'external_reference' => $ref]);
    exit;
}

$applied = apply_mp_payment_to_order($order, $payment);
order_log((int) $order['id'], 'system', null, null, 'Webhook Mercado Pago processado.', [
    'mp_payment_id' => $paymentId, 'status' => $applied['payment_status'],
]);

echo json_encode(['ok' => true, 'order' => $order['order_code'], 'payment_status' => $applied['payment_status']]);
