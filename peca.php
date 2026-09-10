<?php
require_once __DIR__ . '/includes/data.php';

$idOrSlug = $_GET['id'] ?? $_GET['slug'] ?? 1;
$product = get_product_by_id_or_slug($idOrSlug);

if (!$product || empty($product['isActive'])) {
    header("Location: pecas.php");
    exit;
}

$page_title = $product['name'];
require_once __DIR__ . '/includes/header.php';
$relatedProducts = array_slice(array_filter(get_products(), fn($p) => $p['id'] != $product['id']), 0, 2);

// Avaliações (dados de demonstração)
$rating = 4.8;
$reviewCount = 12;
$reviews = [
    [
        'author' => 'Marina S.',
        'date'   => '15 de Novembro, 2025',
        'stars'  => 5,
        'title'  => 'Perfeição em forma de vestido',
        'body'   => 'O caimento é simplesmente impecável. O linho tem uma qualidade maravilhosa, estruturado mas fresco. Usei em um casamento diurno e recebi muitos elogios. Vale cada centavo.',
    ],
    [
        'author' => 'Camila T.',
        'date'   => '02 de Novembro, 2025',
        'stars'  => 4,
        'title'  => 'Muito elegante',
        'body'   => 'O design minimalista é exatamente o que eu procurava. A cor é um off-white muito sofisticado. Só tirei uma estrela porque achei a forma um pouquinho grande, recomendo pegar um tamanho menor se gostar de algo mais justo.',
    ],
];

function render_stars(float $value): string
{
    $full = (int) round($value);
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= $i <= $full ? '★' : '☆';
    }
    return $out;
}
?>

<div class="section" style="padding-top: 30px;">
    <div class="container">
        <div class="pdp-grid">

            <!-- Galeria: miniaturas + imagem principal -->
            <div class="pdp-gallery">
                <div class="pdp-thumbs">
                    <?php foreach ($product['images'] as $i => $img): ?>
                        <button type="button" class="pdp-thumb <?php echo $i === 0 ? 'active' : ''; ?>" data-img="<?php echo htmlspecialchars($img); ?>" aria-label="Ver imagem <?php echo $i + 1; ?>">
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($product['name']); ?> — miniatura <?php echo $i + 1; ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="pdp-main-img">
                    <img id="pdpMainImage" src="<?php echo htmlspecialchars($product['images'][0]); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
            </div>

            <!-- Detalhes -->
            <div class="pdp-details-sticky">
                <nav class="pdp-breadcrumb">
                    <a href="pecas.php">Shop</a>
                    <span>&rsaquo;</span>
                    <a href="pecas.php?categoria=<?php echo urlencode($product['category']); ?>"><?php echo htmlspecialchars($product['category']); ?></a>
                </nav>

                <h1 class="pdp-title"><?php echo htmlspecialchars($product['name']); ?></h1>

                <div class="pdp-price">
                    <?php if ($product['salePrice']): ?>
                        <span class="price-sale">R$ <?php echo number_format($product['salePrice'], 2, ',', '.'); ?></span>
                        <span class="price-original" style="font-size: 1rem; margin-left: 8px;">R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></span>
                    <?php else: ?>
                        R$ <?php echo number_format($product['price'], 2, ',', '.'); ?>
                    <?php endif; ?>
                </div>

                <p class="pdp-description"><?php echo htmlspecialchars($product['description']); ?></p>

                <!-- Seletor de Tamanho -->
                <div>
                    <div class="size-selector-label">
                        <span>Tamanho</span>
                        <a href="#" class="js-open-measures" style="text-decoration: underline; color: var(--color-text-muted); font-size: 0.8rem;">Guia de Medidas</a>
                    </div>
                    <div class="size-options">
                        <?php foreach ($product['sizes'] as $idx => $sz): ?>
                            <button type="button" class="size-btn <?php echo $idx === 0 ? 'active' : ''; ?>" data-size="<?php echo $sz; ?>">
                                <?php echo $sz; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Ação de Compra -->
                <button type="button" class="btn btn-primary btn-full btn-lg js-add-to-cart" data-product-id="<?php echo $product['id']; ?>" data-size="<?php echo htmlspecialchars($product['sizes'][0]); ?>">
                    Adicionar à Sacola
                </button>

                <!-- Compartilhar -->
                <div class="pdp-share">
                    <span>Compartilhar:</span>
                    <a href="<?php echo htmlspecialchars($product['instagramUrl'] ?: 'https://instagram.com'); ?>" target="_blank" rel="noopener"><?php echo $product['instagramUrl'] ? 'Ver no Instagram' : 'Instagram'; ?></a>
                    <a href="https://pinterest.com" target="_blank" rel="noopener">Pinterest</a>
                    <a href="https://wa.me/?text=<?php echo urlencode($product['name']); ?>" target="_blank" rel="noopener">WhatsApp</a>
                    <button type="button" class="pdp-copy-link js-copy-link">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        </svg>
                        Link
                    </button>
                </div>

                <!-- Acordeões -->
                <div class="pdp-accordions">
                    <div class="accordion-item">
                        <button class="accordion-header">
                            <span>Detalhes e Cuidados</span>
                            <svg class="accordion-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="accordion-content">
                            <p><strong>Composição:</strong> <?php echo htmlspecialchars($product['composition']); ?></p>
                            <p style="margin-top: 8px;"><strong>Cuidados:</strong> <?php echo htmlspecialchars($product['careInstructions']); ?></p>
                        </div>
                    </div>

                    <?php
                    $ms = $product['measurements'] ?? null;
                    $msRows = array_filter($ms['rows'] ?? [], fn($r) => array_filter($r));
                    if ($msRows || !empty($ms['note'])):
                    ?>
                    <div class="accordion-item">
                        <button class="accordion-header">
                            <span>Guia de Medidas</span>
                            <svg class="accordion-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="accordion-content">
                            <?php if ($msRows): ?>
                                <table class="pdp-measures">
                                    <thead>
                                        <tr><th>Tam.</th><th>Busto</th><th>Cintura</th><th>Quadril</th><th>Comp.</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($msRows as $sz => $m): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($sz); ?></strong></td>
                                                <td><?php echo htmlspecialchars($m['busto'] ?? '') ?: '&mdash;'; ?></td>
                                                <td><?php echo htmlspecialchars($m['cintura'] ?? '') ?: '&mdash;'; ?></td>
                                                <td><?php echo htmlspecialchars($m['quadril'] ?? '') ?: '&mdash;'; ?></td>
                                                <td><?php echo htmlspecialchars($m['comprimento'] ?? '') ?: '&mdash;'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p style="font-size: 0.78rem; color: var(--color-text-muted); margin-top: 8px;">Medidas aproximadas da peça em centímetros.</p>
                            <?php endif; ?>
                            <?php if (!empty($ms['note'])): ?>
                                <p style="margin-top: <?php echo $msRows ? '10px' : '0'; ?>;"><?php echo htmlspecialchars($ms['note']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="accordion-item active">
                        <button class="accordion-header">
                            <span>Envio e Devoluções</span>
                            <svg class="accordion-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="accordion-content">
                            <p>Enviamos para todo o Brasil via transportadora expressa. Frete grátis em compras acima de R$ 800, com prazo médio de 3 a 5 dias úteis após a postagem. A primeira troca é totalmente grátis em até 30 dias após o recebimento.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Avaliações -->
<div class="container">
    <section class="reviews-section">
        <div class="reviews-summary">
            <h2>Avaliações</h2>
            <div class="reviews-score-row">
                <span class="reviews-score"><?php echo number_format($rating, 1, ',', '.'); ?></span>
                <span class="review-stars" aria-hidden="true"><?php echo render_stars($rating); ?></span>
            </div>
            <p class="reviews-count">Baseado em <?php echo $reviewCount; ?> avaliações</p>
            <a href="#" class="link-underline">Escrever uma Avaliação</a>
        </div>

        <div class="reviews-list">
            <?php foreach ($reviews as $rv): ?>
                <article class="review-item">
                    <div class="review-head">
                        <div>
                            <span class="review-author"><?php echo htmlspecialchars($rv['author']); ?></span>
                            <span class="review-date"><?php echo htmlspecialchars($rv['date']); ?></span>
                        </div>
                        <span class="review-stars" aria-hidden="true"><?php echo render_stars((float) $rv['stars']); ?></span>
                    </div>
                    <h3 class="review-title"><?php echo htmlspecialchars($rv['title']); ?></h3>
                    <p class="review-body"><?php echo htmlspecialchars($rv['body']); ?></p>
                </article>
            <?php endforeach; ?>

            <a href="#" class="link-underline reviews-all">Ver Todas as <?php echo $reviewCount; ?> Avaliações</a>
        </div>
    </section>
</div>

<!-- Complete o Visual -->
<div class="container">
    <section class="styling-section">
        <div class="styling-head">
            <h2>Complete o Visual</h2>
            <a href="pecas.php" class="link-underline">Ver Todos</a>
        </div>
        <div class="styling-grid">
            <?php foreach ($relatedProducts as $rel): ?>
                <a href="peca.php?id=<?php echo $rel['id']; ?>" class="styling-card">
                    <div class="styling-card-media">
                        <img src="<?php echo htmlspecialchars($rel['images'][0]); ?>" alt="<?php echo htmlspecialchars($rel['name']); ?>">
                    </div>
                    <h3><?php echo htmlspecialchars($rel['name']); ?></h3>
                    <span class="styling-card-price">R$ <?php echo number_format($rel['salePrice'] ?? $rel['price'], 2, ',', '.'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
