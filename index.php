<?php
$page_title = "Home | Elegância & Alfaiataria Autoral";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/settings.php';

$products    = get_products();
$newReleases = array_values(array_filter($products, fn($p) => $p['isNewRelease']));
$featured    = array_slice(array_merge($newReleases, $products), 0, 8);
$onSale      = array_values(array_filter($products, fn($p) => $p['salePrice']));
$blogPosts   = array_slice(get_blog_posts(), 0, 3);

// Categorias reais com contagem
$categories = [];
foreach ($products as $p) {
    $categories[$p['category']] = ($categories[$p['category']] ?? 0) + 1;
}

// Desconto (%) de um produto em promoção
$discountPct = fn($p) => $p['salePrice'] ? (int) round((1 - $p['salePrice'] / $p['price']) * 100) : 0;
usort($onSale, fn($a, $b) => $discountPct($b) <=> $discountPct($a));

$freeShipping = active_free_shipping_threshold();
$promos       = get_active_promotions();
$topPromo     = $promos[0] ?? null;

// Vitrine para a faixa do Instagram
$igShots = [];
foreach ($products as $p) {
    if (count($igShots) >= 6) break;
    $igShots[] = ['img' => $p['images'][0], 'id' => $p['id'], 'name' => $p['name']];
}

/** Card de produto reutilizado na home */
function home_product_card(array $product): void
{
    ?>
    <div class="product-card">
        <div class="product-media">
            <?php if ($product['isNewRelease']): ?><span class="product-badge">Novo</span><?php endif; ?>
            <?php if ($product['salePrice']): ?>
                <span class="product-badge product-badge-sale"><?php echo (int) round((1 - $product['salePrice'] / $product['price']) * 100); ?>% OFF</span>
            <?php endif; ?>

            <a href="peca.php?id=<?php echo $product['id']; ?>">
                <img src="<?php echo htmlspecialchars($product['images'][0]); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-img" loading="lazy">
                <?php if (isset($product['images'][1])): ?>
                    <img src="<?php echo htmlspecialchars($product['images'][1]); ?>" alt="" class="product-img-hover" loading="lazy">
                <?php endif; ?>
            </a>

            <button class="quick-add-btn js-add-to-cart" data-product-id="<?php echo $product['id']; ?>" data-size="M">
                + Comprar Rápido (Tamanho M)
            </button>
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
                    <span class="size-pill"><?php echo $sz; ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}
?>

<!-- ====================== Barra de Departamentos + Busca ====================== -->
<div class="dept-bar">
    <div class="container dept-bar-inner">
        <div class="dept-menu">
            <button type="button" class="dept-toggle" id="deptToggle" aria-expanded="false">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
                Todos os Departamentos
                <svg class="dept-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>
            <div class="dept-list" id="deptList" hidden>
                <a href="pecas.php">Todas as peças</a>
                <?php foreach ($categories as $cat => $qty): ?>
                    <a href="pecas.php?categoria=<?php echo urlencode($cat); ?>">
                        <?php echo htmlspecialchars($cat); ?> <span class="dept-qty"><?php echo $qty; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <form action="pecas.php" method="GET" class="dept-search">
            <input type="text" name="busca" placeholder="Buscar peças, tecidos ou categorias&hellip;" aria-label="Buscar">
            <select name="categoria" aria-label="Categoria">
                <option value="">Todas as categorias</option>
                <?php foreach ($categories as $cat => $qty): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" aria-label="Buscar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
        </form>
    </div>
</div>

<!-- ====================== Hero ====================== -->
<section class="hero-editorial">
    <img src="https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=1800&q=80" alt="Coleção Primavera/Verão Duás" class="hero-bg">
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <span class="subtitle">Coleção Primavera / Verão 2026</span>
        <h1>A Poética da Alfaiataria Fluida</h1>
        <p>Peças autorais criadas por Larissa e Letícia. Linho puro, seda e recortes precisos para uma elegância sem esforço.</p>
        <div class="hero-actions">
            <a href="pecas.php" class="btn btn-primary btn-lg">Descobrir a Coleção</a>
            <a href="pecas.php?ordem=newest" class="btn btn-outline btn-lg hero-btn-ghost">Comprar Agora</a>
        </div>
    </div>
</section>

<!-- ====================== Dois cards promocionais ====================== -->
<?php if (count($onSale) >= 1): ?>
<section class="section section-tight">
    <div class="container">
        <div class="promo-duo">
            <?php foreach (array_slice($onSale, 0, 2) as $p): ?>
                <article class="promo-card">
                    <img src="<?php echo htmlspecialchars($p['images'][0]); ?>" alt="" class="promo-card-bg" loading="lazy">
                    <div class="promo-card-body">
                        <span class="promo-kicker">Até <?php echo $discountPct($p); ?>% OFF</span>
                        <h3><?php echo htmlspecialchars($p['name']); ?></h3>
                        <p>A partir de <strong>R$ <?php echo number_format($p['salePrice'], 2, ',', '.'); ?></strong></p>
                        <a href="peca.php?id=<?php echo $p['id']; ?>" class="btn btn-primary btn-sm">Compre Agora</a>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (count($onSale) === 1): ?>
                <article class="promo-card">
                    <img src="https://images.unsplash.com/photo-1509631179647-0177331693ae?auto=format&fit=crop&w=1000&q=80" alt="" class="promo-card-bg" loading="lazy">
                    <div class="promo-card-body">
                        <span class="promo-kicker">Novidades</span>
                        <h3>Faça Uma Declaração<br>Nesta Temporada</h3>
                        <p>Peças em tiragem limitada.</p>
                        <a href="pecas.php?ordem=newest" class="btn btn-primary btn-sm">Compre Agora</a>
                    </div>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ====================== Benefícios ====================== -->
<section class="section section-tight">
    <div class="container">
        <div class="benefits">
            <div class="benefit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                <h4>Frete grátis</h4>
                <p><?php echo $freeShipping !== null
                        ? 'Para compras acima de R$ ' . number_format($freeShipping, 2, ',', '.')
                        : 'Consulte as condições no checkout'; ?></p>
            </div>
            <div class="benefit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                <h4>Pagamento seguro</h4>
                <p>Cartão, Pix e boleto pelo Mercado Pago</p>
            </div>
            <div class="benefit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                <h4>30 dias para trocar</h4>
                <p>Primeira troca por nossa conta</p>
            </div>
            <div class="benefit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path></svg>
                <h4>Atendimento próximo</h4>
                <p>Consultoria de estilo por WhatsApp</p>
            </div>
        </div>
    </div>
</section>

<!-- ====================== Sobre nós ====================== -->
<section class="section section-neutral">
    <div class="container">
        <div class="story-block">
            <div class="story-content">
                <span class="subtitle">Por Larissa e Leticia</span>
                <h2 style="margin-bottom: 20px;">Projetado para durar, criado com propósito</h2>
                <div class="story-quote">
                    "Acreditamos que a roupa deve vestir a essência feminina com autenticidade, unindo o rigor técnico da alfaiataria ao conforto do linho natural."
                </div>
                <p style="color: var(--color-text-muted); margin-bottom: 28px; line-height: 1.8;">
                    Fundada no intuito de desacelerar o consumo e criar vestuário com significado, a Duás desenvolve coleções autorais focadas na sofisticação atemporal.
                </p>
                <a href="quem-somos.php" class="btn btn-outline">Explore Mais</a>
            </div>
            <div class="story-media">
                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=1000&q=80" alt="Larissa e Leticia - Estilistas Duás" loading="lazy">
            </div>
        </div>
    </div>
</section>

<!-- ====================== Faixa de categorias ====================== -->
<section class="section section-tight">
    <div class="container">
        <div class="cat-strip">
            <?php foreach ($categories as $cat => $qty): ?>
                <a href="pecas.php?categoria=<?php echo urlencode($cat); ?>" class="cat-chip">
                    <span class="cat-chip-name"><?php echo htmlspecialchars($cat); ?></span>
                    <span class="cat-chip-qty"><?php echo $qty; ?> peça<?php echo $qty > 1 ? 's' : ''; ?></span>
                </a>
            <?php endforeach; ?>
            <a href="pecas.php" class="cat-chip cat-chip-all">
                <span class="cat-chip-name">Ver tudo</span>
                <span class="cat-chip-qty"><?php echo count($products); ?> peças</span>
            </a>
        </div>
    </div>
</section>

<!-- ====================== Em destaque (carrossel) ====================== -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="subtitle">Seleção Editorial</span>
            <h2 class="section-title">Em Destaque</h2>
            <p class="lead">Modelagens contemporâneas desenvolvidas em tiragem limitada.</p>
        </div>

        <div class="carousel" data-carousel>
            <button class="carousel-nav carousel-prev" type="button" aria-label="Anterior">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>

            <div class="carousel-track" data-carousel-track>
                <?php foreach ($featured as $product): ?>
                    <div class="carousel-item"><?php home_product_card($product); ?></div>
                <?php endforeach; ?>
            </div>

            <button class="carousel-nav carousel-next" type="button" aria-label="Próximo">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>

            <div class="carousel-dots" data-carousel-dots></div>
        </div>

        <div style="text-align: center; margin-top: 40px;">
            <a href="pecas.php" class="link-underline">Ver Todas as Peças (<?php echo count($products); ?>) &rarr;</a>
        </div>
    </div>
</section>

<!-- ====================== Oferta do Dia ====================== -->
<?php if ($onSale || $topPromo): ?>
<section class="section section-neutral">
    <div class="container">
        <div class="section-header">
            <span class="subtitle">Oferta de hoje</span>
            <h2 class="section-title">Oferta do Dia</h2>
            <p class="lead">Estilos por tempo limitado a preços imbatíveis. Aproveite antes que acabem.</p>
        </div>

        <div class="deal-bento">
            <?php $big = $onSale[0] ?? null; ?>
            <?php if ($big): ?>
                <article class="deal-card deal-big">
                    <img src="<?php echo htmlspecialchars($big['images'][0]); ?>" alt="" loading="lazy">
                    <div class="deal-body">
                        <span class="deal-kicker">Desconto de <?php echo $discountPct($big); ?>%</span>
                        <h3><?php echo htmlspecialchars($big['name']); ?></h3>
                        <p class="deal-price">
                            <strong>R$ <?php echo number_format($big['salePrice'], 2, ',', '.'); ?></strong>
                            <s>R$ <?php echo number_format($big['price'], 2, ',', '.'); ?></s>
                        </p>
                        <a href="peca.php?id=<?php echo $big['id']; ?>" class="btn btn-primary btn-sm">Compre Agora</a>
                    </div>
                </article>
            <?php endif; ?>

            <div class="deal-side">
                <?php $second = $onSale[1] ?? null; ?>
                <?php if ($second): ?>
                    <article class="deal-card">
                        <img src="<?php echo htmlspecialchars($second['images'][0]); ?>" alt="" loading="lazy">
                        <div class="deal-body">
                            <span class="deal-kicker">Desconto de <?php echo $discountPct($second); ?>%</span>
                            <h3><?php echo htmlspecialchars($second['name']); ?></h3>
                            <a href="peca.php?id=<?php echo $second['id']; ?>" class="btn btn-primary btn-sm">Compre Agora</a>
                        </div>
                    </article>
                <?php endif; ?>

                <?php if ($topPromo): ?>
                    <article class="deal-card deal-card-flat">
                        <div class="deal-body">
                            <span class="deal-kicker">
                                <?php echo $topPromo['discount_type'] === 'percent'
                                    ? rtrim(rtrim(number_format((float) $topPromo['discount_value'], 2, ',', '.'), '0'), ',') . '% OFF'
                                    : 'R$ ' . number_format((float) $topPromo['discount_value'], 2, ',', '.') . ' OFF'; ?>
                            </span>
                            <h3><?php echo htmlspecialchars($topPromo['name']); ?></h3>
                            <?php if ($topPromo['code']): ?>
                                <p>Use o cupom <strong class="deal-coupon"><?php echo htmlspecialchars($topPromo['code']); ?></strong> no checkout.</p>
                            <?php endif; ?>
                            <a href="pecas.php" class="btn btn-primary btn-sm">Compre Agora</a>
                        </div>
                    </article>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ====================== Faixa de vantagem ====================== -->
<section class="member-strip">
    <div class="container member-strip-inner">
        <p>
            <strong>Cliente Duás</strong> &mdash;
            <?php if ($topPromo && $topPromo['code']): ?>
                <?php echo htmlspecialchars($topPromo['code']); ?> com desconto na primeira compra
            <?php else: ?>
                vantagens exclusivas na primeira compra
            <?php endif; ?>
            <?php if ($freeShipping !== null): ?>
                e frete grátis acima de R$ <?php echo number_format($freeShipping, 2, ',', '.'); ?>
            <?php endif; ?>
        </p>
        <a href="pecas.php" class="btn btn-secondary btn-sm">Compre Agora</a>
    </div>
</section>

<!-- ====================== Banner secundário ====================== -->
<section class="cta-banner">
    <img src="https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=1800&q=80" alt="" class="cta-banner-bg" loading="lazy">
    <div class="cta-banner-overlay"></div>
    <div class="container cta-banner-content">
        <h2>Looks Frescos Para Dias Ensolarados</h2>
        <p>De tecidos arejados a conjuntos versáteis, esta seleção foi feita para você se sentir bem e parecer melhor. Linho puro, seda e algodão egípcio com caimento impecável.</p>
        <a href="pecas.php" class="btn btn-primary btn-lg">Compre Agora</a>
    </div>
</section>

<!-- ====================== Blog ====================== -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="subtitle">Jornal Editorial</span>
            <h2 class="section-title">Matérias & Inspirações</h2>
        </div>

        <div class="blog-grid">
            <?php foreach ($blogPosts as $post): ?>
                <article class="blog-card">
                    <a href="blog-post.php?slug=<?php echo $post['slug']; ?>" class="blog-media">
                        <img src="<?php echo htmlspecialchars($post['coverImage']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" loading="lazy">
                    </a>
                    <div class="blog-meta"><?php echo htmlspecialchars($post['category']); ?> &bull; <?php echo htmlspecialchars($post['publishedAt']); ?></div>
                    <a href="blog-post.php?slug=<?php echo $post['slug']; ?>">
                        <h3 class="blog-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                    </a>
                    <p class="blog-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                    <a href="blog-post.php?slug=<?php echo $post['slug']; ?>" class="link-underline">Ler Matéria Completa</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ====================== Faixa Instagram ====================== -->
<section class="ig-strip">
    <a href="https://instagram.com" target="_blank" rel="noopener" class="ig-badge" aria-label="Instagram Duás">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
        </svg>
        <span>@duas</span>
    </a>
    <?php foreach ($igShots as $shot): ?>
        <a href="peca.php?id=<?php echo $shot['id']; ?>" class="ig-cell">
            <img src="<?php echo htmlspecialchars($shot['img']); ?>" alt="<?php echo htmlspecialchars($shot['name']); ?>" loading="lazy">
        </a>
    <?php endforeach; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
