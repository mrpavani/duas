<?php
$page_title = "Coleção de Peças";
require_once __DIR__ . '/includes/header.php';

$categoryFilter = $_GET['categoria'] ?? null;
$sortFilter = $_GET['ordem'] ?? null;
$searchQuery = $_GET['busca'] ?? null;

$filteredProducts = get_filtered_products($categoryFilter, $sortFilter, $searchQuery);
?>

<div class="section" style="padding-top: 40px;">
    <div class="container">
        <!-- Header Banner & Title -->
        <div style="text-align: center; margin-bottom: 40px;">
            <span class="subtitle">Catálogo Completo</span>
            <h1>
                <?php 
                if ($searchQuery) {
                    echo 'Resultados para: "' . htmlspecialchars($searchQuery) . '"';
                } elseif ($categoryFilter && strtolower($categoryFilter) !== 'todos') {
                    echo htmlspecialchars(ucfirst($categoryFilter));
                } else {
                    echo 'Todas as Peças';
                }
                ?>
            </h1>
            <p class="lead" style="margin-top: 8px;">Alfaiataria contemporânea e tecidos nobres pensados para transitar em todas as ocasiões.</p>
        </div>

        <!-- Catalog Toolbar / Filters -->
        <div class="catalog-toolbar">
<?php
$catalogCategories = array_values(array_unique(array_filter(array_map(fn($p) => $p['category'], get_products(true)))));
sort($catalogCategories);
?>
            <div class="filter-categories">
                <a href="pecas.php" class="filter-link <?php echo (!$categoryFilter || strtolower($categoryFilter) === 'todos') ? 'active' : ''; ?>">Todos</a>
                <?php foreach ($catalogCategories as $cat): ?>
                    <a href="pecas.php?categoria=<?php echo urlencode($cat); ?>" class="filter-link <?php echo $categoryFilter === $cat ? 'active' : ''; ?>"><?php echo htmlspecialchars($cat); ?></a>
                <?php endforeach; ?>
            </div>

            <form action="pecas.php" method="GET" style="display: flex; align-items: center; gap: 12px;">
                <?php if ($categoryFilter): ?>
                    <input type="hidden" name="categoria" value="<?php echo htmlspecialchars($categoryFilter); ?>">
                <?php endif; ?>
                <?php if ($searchQuery): ?>
                    <input type="hidden" name="busca" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <?php endif; ?>
                <label for="sortSelect" style="font-size: 0.85rem; color: var(--color-text-muted);">Ordenar por:</label>
                <select name="ordem" id="sortSelect" class="sort-select" onchange="this.form.submit()">
                    <option value="">Destaques</option>
                    <option value="newest" <?php echo $sortFilter === 'newest' ? 'selected' : ''; ?>>Mais Recentes</option>
                    <option value="price-asc" <?php echo $sortFilter === 'price-asc' ? 'selected' : ''; ?>>Menor Preço</option>
                    <option value="price-desc" <?php echo $sortFilter === 'price-desc' ? 'selected' : ''; ?>>Maior Preço</option>
                </select>
            </form>
        </div>

        <!-- Product Grid (4 colunas desktop / 2 mobile) -->
        <?php if (empty($filteredProducts)): ?>
            <div style="text-align: center; padding: 60px 0; color: var(--color-text-muted);">
                <p style="font-family: var(--font-heading); font-size: 1.4rem; margin-bottom: 12px;">Nenhuma peça encontrada</p>
                <p>Tente ajustar seus filtros de busca ou navegar por todas as categorias.</p>
                <a href="pecas.php" class="btn btn-outline btn-sm" style="margin-top: 20px;">Limpar Filtros</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($filteredProducts as $product): ?>
                    <?php
                    $isOut = !empty($product['isOutOfStock']);
                    $sizesStockMap = $product['sizesStock'] ?? [];
                    ?>
                    <div class="product-card">
                        <div class="product-media">
                            <?php if ($isOut): ?>
                                <span class="product-badge" style="background:#b71c1c; color:#ffffff;">Esgotado</span>
                            <?php elseif ($product['isNewRelease']): ?>
                                <span class="product-badge">Novo</span>
                            <?php endif; ?>

                            <?php if ($product['salePrice'] && !$isOut): ?>
                                <span class="product-badge product-badge-sale"><?php echo (int) round((1 - $product['salePrice'] / $product['price']) * 100); ?>% OFF</span>
                            <?php endif; ?>

                            <a href="peca.php?id=<?php echo $product['id']; ?>">
                                <img src="<?php echo htmlspecialchars($product['images'][0]); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-img">
                                <?php if (isset($product['images'][1])): ?>
                                    <img src="<?php echo htmlspecialchars($product['images'][1]); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-img-hover">
                                <?php endif; ?>
                            </a>

                            <?php if ($isOut): ?>
                                <a href="peca.php?id=<?php echo $product['id']; ?>" class="quick-add-btn" style="background:#121212; color:#ffffff; text-align:center; display:flex; align-items:center; justify-content:center; gap:6px;">
                                    🔔 Avise-me quando chegar
                                </a>
                            <?php else: ?>
                                <button class="quick-add-btn js-add-to-cart" data-product-id="<?php echo $product['id']; ?>" data-size="<?php echo htmlspecialchars($product['sizes'][0] ?? 'M'); ?>">
                                    + Comprar Rápido
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="product-info">
                            <span class="product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                            <a href="peca.php?id=<?php echo $product['id']; ?>">
                                <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            </a>
                            <div class="product-price">
                                <?php if ($product['salePrice']): ?>
                                    <span class="price-sale">R$ <?php echo number_format($product['salePrice'], 2, ',', '.'); ?></span>
                                    <span class="price-original">R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></span>
                                <?php else: ?>
                                    <span>R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="product-sizes-preview">
                                <?php foreach ($product['sizes'] as $sz): ?>
                                    <?php $szOut = (int)($sizesStockMap[$sz] ?? 0) <= 0; ?>
                                    <span class="size-pill <?php echo $szOut ? 'size-pill-out' : ''; ?>" style="<?php echo $szOut ? 'opacity:0.45; text-decoration:line-through;' : ''; ?>" title="<?php echo $szOut ? 'Tamanho esgotado' : 'Disponível'; ?>"><?php echo htmlspecialchars($sz); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
