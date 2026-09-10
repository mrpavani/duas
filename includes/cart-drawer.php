<?php
// ==========================================================================
// DUÁS - CART DRAWER TEMPLATE (Sacola Lateral Deslizante)
// ==========================================================================
require_once __DIR__ . '/data.php';
$summary = get_cart_summary();
$cartItems = $_SESSION['cart'] ?? [];
?>

<!-- Drawer Overlay & Panel -->
<div class="drawer-overlay" id="drawerOverlay"></div>

<aside class="cart-drawer" id="cartDrawer" aria-label="Sacola de compras">
    <!-- Header -->
    <div class="drawer-header">
        <h3 class="drawer-title">Sua Sacola (<span class="js-cart-badge"><?php echo $summary['count']; ?></span>)</h3>
        <button class="drawer-close" id="drawerClose" aria-label="Fechar sacola">&times;</button>
    </div>

    <!-- Free Shipping Goal Progress Bar -->
    <div class="free-shipping-bar" id="freeShippingBarContent">
        <?php if ($summary['hasFreeShipping']): ?>
            <div>✨ Parabéns! Você ganhou <strong>Frete Grátis</strong> para todo o Brasil.</div>
        <?php elseif (!empty($summary['freeShippingActive'])): ?>
            <?php $pct = min(100, ($summary['total'] / $summary['freeShippingGoal']) * 100); ?>
            <div>Faltam <strong>R$ <?php echo number_format($summary['freeShippingRemaining'], 2, ',', '.'); ?></strong> para você ganhar <strong>Frete Grátis</strong>.</div>
            <div class="progress-track"><div class="progress-fill" style="width: <?php echo $pct; ?>%;"></div></div>
        <?php else: ?>
            <div>Calculamos o frete na etapa final do checkout.</div>
        <?php endif; ?>
    </div>

    <!-- Body Items -->
    <div class="drawer-body" id="drawerBodyContent">
        <?php if (empty($cartItems)): ?>
            <div style="text-align: center; padding: 40px 0; color: #666;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin: 0 auto 16px auto; opacity: 0.5;">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <p style="font-family: var(--font-heading); font-size: 1.1rem; margin-bottom: 8px;">Sua sacola está vazia</p>
                <p style="font-size: 0.85rem; margin-bottom: 20px;">Explore nossos lançamentos e encontre peças exclusivas.</p>
                <a href="pecas.php" class="btn btn-outline btn-sm">Ver Lançamentos</a>
            </div>
        <?php else: ?>
            <?php foreach ($cartItems as $key => $item): ?>
                <?php
                $product = get_product_by_id_or_slug($item['productId']);
                if (!$product) continue;
                $unitPrice = $product['salePrice'] ?? $product['price'];
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
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Footer Subtotal & Action -->
    <div class="drawer-footer" id="drawerFooterContent">
        <div class="drawer-subtotal">
            <span>Subtotal</span>
            <span>R$ <?php echo number_format($summary['total'], 2, ',', '.'); ?></span>
        </div>
        <p style="font-size: 0.75rem; color: #666; text-align: center;">Frete e cupom de desconto calculados na etapa final.</p>
        <a href="carrinho.php" class="btn btn-primary btn-full <?php echo empty($cartItems) ? 'disabled' : ''; ?>" style="<?php echo empty($cartItems) ? 'pointer-events: none; opacity: 0.5;' : ''; ?>">
            Finalizar Pedido
        </a>
        <a href="pecas.php" class="btn btn-secondary btn-full btn-sm">Continuar Comprando</a>
    </div>
</aside>
