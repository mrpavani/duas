<?php
// ==========================================================================
// DUÁS - API / AVISE-ME QUANDO CHEGAR (RESTOCK NOTIFICATION)
// ==========================================================================

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$productId = isset($_POST['productId']) ? (int) $_POST['productId'] : 0;
$size      = trim((string) ($_POST['size'] ?? ''));
$name      = trim((string) ($_POST['name'] ?? ''));
$email     = trim((string) ($_POST['email'] ?? ''));

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Produto inválido.']);
    exit;
}

if ($size === '') {
    echo json_encode(['success' => false, 'message' => 'Selecione um tamanho.']);
    exit;
}

if ($name === '' || mb_strlen($name) < 2) {
    echo json_encode(['success' => false, 'message' => 'Por favor, informe seu nome completo.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Por favor, informe um endereço de e-mail válido.']);
    exit;
}

try {
    $pdo = db();
    $product = get_product_by_id_or_slug($productId);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado.']);
        exit;
    }

    $productName = $product['name'];

    // Verifica se já existe um pedido pendente para o mesmo email, produto e tamanho
    $stmt = $pdo->prepare("
        SELECT id, status FROM stock_notifications
        WHERE product_id = ? AND size = ? AND customer_email = ?
        LIMIT 1
    ");
    $stmt->execute([$productId, $size, $email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['status'] === 'pending') {
            echo json_encode([
                'success' => true,
                'message' => 'Você já está na lista de espera deste tamanho! Avisaremos você por e-mail assim que houver reposição.',
                'already_registered' => true
            ]);
            exit;
        } else {
            // Se já foi notificado no passado, renova para pendente
            $pdo->prepare("UPDATE stock_notifications SET status = 'pending', customer_name = ?, created_at = NOW(), notified_at = NULL WHERE id = ?")
                ->execute([$name, $existing['id']]);
        }
    } else {
        $ins = $pdo->prepare("
            INSERT INTO stock_notifications (product_id, product_name, size, customer_name, customer_email, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $ins->execute([$productId, $productName, $size, $name, $email]);
    }

    echo json_encode([
        'success' => true,
        'message' => "Perfeito, {$name}! Avisaremos você em {$email} assim que o tamanho {$size} voltar ao estoque.",
        'size' => $size
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro ao registrar sua solicitação. Tente novamente.']);
}
