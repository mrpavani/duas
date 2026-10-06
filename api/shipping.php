<?php
// Cotação de frete para o carrinho atual: GET api/shipping.php?cep=00000000
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/shipping.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$cep = shipping_digits((string) ($_GET['cep'] ?? ''));
if (strlen($cep) !== 8) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Informe um CEP válido com 8 dígitos.']);
    exit;
}

$summary = get_cart_summary();
$qty = max(1, (int) $summary['count']);
$options = shipping_quotes($cep, (float) $summary['total'], $qty, (bool) $summary['hasFreeShipping']);

if (!$options) {
    echo json_encode(['ok' => false, 'error' => 'Nenhuma forma de entrega disponível para este CEP.']);
    exit;
}
echo json_encode(['ok' => true, 'options' => $options], JSON_UNESCAPED_UNICODE);
