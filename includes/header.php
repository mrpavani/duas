<?php
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/settings.php';
$summary = get_cart_summary();
$current_page = basename($_SERVER['PHP_SELF']);
$announcementText = trim((string) get_setting('announcement_text', ''));
$announcementOn = get_setting('announcement_active', '1') === '1' && $announcementText !== '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | Duás' : 'Duás | Por Larissa e Leticia'; ?></title>
    <meta name="description" content="Moda autoral feminina por Larissa e Letícia. Alfaiataria contemporânea, linho puro, seda e design atemporal.">
    <link rel="stylesheet" href="css/style.css">
    <link rel="shortcut icon" href="img/duas-logo-solo-svg.svg" type="image/svg+xml">
</head>
<body>

<!-- Top Announcement Bar -->
<?php if ($announcementOn): ?>
<div class="top-bar">
    <div class="container">
        <span><?php echo htmlspecialchars($announcementText); ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Header Main Sticky -->
<header class="header-sticky">
    <div class="container header-container">
        
        <!-- Mobile Toggle Button -->
        <button class="mobile-toggle icon-btn" id="mobileNavToggle" aria-label="Abrir menu">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <!-- Brand Logo (Left / Grid Column 1) -->
        <a href="index.php" class="brand-logo" title="Duás | Por Larissa e Leticia">
            <img src="img/duas-logo-solo-svg.svg" alt="Duás Logo">
        </a>

        <!-- Desktop Navigation Links (Grid Column 2) -->
        <nav class="nav-desktop">
            <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">Home</a>
            <a href="pecas.php" class="nav-link <?php echo $current_page == 'pecas.php' || $current_page == 'peca.php' ? 'active' : ''; ?>">Peças</a>
            <a href="quem-somos.php" class="nav-link <?php echo $current_page == 'quem-somos.php' ? 'active' : ''; ?>">Quem Somos</a>
            <a href="blog.php" class="nav-link <?php echo $current_page == 'blog.php' || $current_page == 'blog-post.php' ? 'active' : ''; ?>">Blog</a>
            <a href="contato.php" class="nav-link <?php echo $current_page == 'contato.php' ? 'active' : ''; ?>">Contato</a>
        </nav>

        <!-- Header Actions (Right / Third in Desktop Layout) -->
        <div class="header-actions">
            <!-- Cart Drawer Trigger Button -->
            <button class="icon-btn js-open-cart" aria-label="Sacola de compras">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <span class="cart-badge js-cart-badge"><?php echo $summary['count']; ?></span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile Navigation Drawer -->
<div class="mobile-nav-overlay" id="mobileNavOverlay"></div>
<div class="mobile-nav-menu" id="mobileNavMenu">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 16px;">
        <img src="img/duas-logo-solo-svg.svg" alt="Duás" style="height: 28px; filter: brightness(0);">
        <button class="icon-btn" id="mobileNavClose" aria-label="Fechar menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>
    <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 24px;">
        <a href="index.php" class="nav-link">Home</a>
        <a href="pecas.php" class="nav-link">Peças / Catálogo</a>
        <a href="quem-somos.php" class="nav-link">Quem Somos</a>
        <a href="blog.php" class="nav-link">Blog & Editorial</a>
        <a href="contato.php" class="nav-link">Atendimento & Contato</a>
    </div>
</div>
