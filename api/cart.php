<?php
// ==========================================================================
// DUÁS - AJAX CART API ENDPOINT
// Handles cart state updates and renders cart HTML snippets dynamically
// ==========================================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/data.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$productId = isset($_POST['productId']) ? (int)$_POST['productId'] : 0;
$size = $_POST['size'] ?? 'M';
$quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

if ($action === 'add') {
    $product = get_product_by_id_or_slug($productId);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado.']);
        exit;
    }

    $key = $productId . '_' . $size;
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$key] = [
            'productId' => $productId,
            'size' => $size,
            'quantity' => $quantity
        ];
    }
} elseif ($action === 'update') {
    $type = $_POST['type'] ?? 'increase';
    $key = $productId . '_' . $size;

    if (isset($_SESSION['cart'][$key])) {
        if ($type === 'increase') {
            $_SESSION['cart'][$key]['quantity']++;
        } elseif ($type === 'decrease') {
            $_SESSION['cart'][$key]['quantity']--;
            if ($_SESSION['cart'][$key]['quantity'] <= 0) {
                unset($_SESSION['cart'][$key]);
            }
        }
    }
} elseif ($action === 'remove') {
    $key = $productId . '_' . $size;
    if (isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
    }
}

// Generate rendered HTML snippets for response
$summary = get_cart_summary();
$cartItems = $_SESSION['cart'] ?? [];

// Items HTML
ob_start();
if (empty($cartItems)) {
    echo '
    <div style="text-align: center; padding: 40px 0; color: #666;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin: 0 auto 16px auto; opacity: 0.5;">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <path d="M16 10a4 4 0 0 1-8 0"></path>
        </svg>
        <p style="font-family: var(--font-heading); font-size: 1.1rem; margin-bottom: 8px;">Sua sacola está vazia</p>
        <p style="font-size: 0.85rem; margin-bottom: 20px;">Explore nossos lançamentos e encontre peças exclusivas.</p>
        <a href="pecas.php" class="btn btn-outline btn-sm">Ver Lançamentos</a>
    </div>';
} else {
    foreach ($cartItems as $key => $item) {
        $product = get_product_by_id_or_slug($item['productId']);
        if (!$product) continue;
        $unitPrice = $product['salePrice'] ?? $product['price'];
        $itemTotal = $unitPrice * $item['quantity'];
        ?>
        <div class="cart-item">
            <img src="<?php echo htmlspecialchars($product['images'][0]); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="cart-item-img">
            <div class="cart-item-info">
                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                <div class="cart-item-meta">Tamanho: <strong><?php echo htmlspecialchars($item['size']); ?></strong></div>
                <div style="font-size: 0.85rem; font-weight: 600;">R$ <?php echo number_format($unitPrice, 2, ',', '.'); ?></div>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                <span class="remove-item-btn js-remove-item" data-product-id="<?php echo $product['id']; ?>" data-size="<?php echo htmlspecialchars($item['size']); ?>">Remover</span>
                <div class="qty-control">
                    <button class="qty-btn js-qty-change" data-action="decrease" data-product-id="<?php echo $product['id']; ?>" data-size="<?php echo htmlspecialchars($item['size']); ?>">-</button>
                    <span class="qty-val"><?php echo $item['quantity']; ?></span>
                    <button class="qty-btn js-qty-change" data-action="increase" data-product-id="<?php echo $product['id']; ?>" data-size="<?php echo htmlspecialchars($item['size']); ?>">+</button>
                </div>
            </div>
        </div>
        <?php
    }
}
$htmlItems = ob_get_clean();

// Footer HTML
ob_start();
?>
<div class="drawer-subtotal">
    <span>Subtotal</span>
    <span>R$ <?php echo number_format($summary['total'], 2, ',', '.'); ?></span>
</div>
<p style="font-size: 0.75rem; color: #666; text-align: center;">Frete e cupom de desconto calculados na etapa final.</p>
<a href="carrinho.php" class="btn btn-primary btn-full <?php echo empty($cartItems) ? 'disabled' : ''; ?>" style="<?php echo empty($cartItems) ? 'pointer-events: none; opacity: 0.5;' : ''; ?>">
    Finalizar Pedido
</a>
<a href="pecas.php" class="btn btn-secondary btn-full btn-sm">Continuar Comprando</a>
<?php
$htmlFooter = ob_get_clean();

// Shipping Bar HTML
ob_start();
if ($summary['hasFreeShipping']) {
    echo '<div>✨ Parabéns! Você ganhou <strong>Frete Grátis</strong> para todo o Brasil.</div>';
} elseif (!empty($summary['freeShippingActive'])) {
    $pct = min(100, ($summary['total'] / $summary['freeShippingGoal']) * 100);
    echo '<div>Faltam <strong>R$ ' . number_format($summary['freeShippingRemaining'], 2, ',', '.') . '</strong> para você ganhar <strong>Frete Grátis</strong>.</div>';
    echo '<div class="progress-track"><div class="progress-fill" style="width: ' . $pct . '%;"></div></div>';
} else {
    echo '<div>Calculamos o frete na etapa final do checkout.</div>';
}
$htmlShippingBar = ob_get_clean();

echo json_encode([
    'success' => true,
    'summary' => $summary,
    'htmlItems' => $htmlItems,
    'htmlFooter' => $htmlFooter,
    'htmlShippingBar' => $htmlShippingBar
]);
