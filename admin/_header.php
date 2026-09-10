<?php
/** @var array $admin  vindo de require_admin() */
$adminPageTitle = $adminPageTitle ?? 'Painel';
$adminActive = $adminActive ?? '';
$base = admin_base();
$nav = [
    ''                => ['Visão geral', 'index.php'],
    'orders'          => ['Pedidos', 'orders.php'],
    'products'        => ['Produtos', 'products.php'],
    'blog'            => ['Blog', 'blog.php'],
    'promotions'      => ['Promoções', 'promotions.php'],
    'freeship'        => ['Frete grátis', 'free-shipping.php'],
    'payments'        => ['Meios de pagamento', 'payment-methods.php'],
    'shipping'        => ['Formas de entrega', 'shipping-methods.php'],
    'users'           => ['Usuários', 'users.php'],
    'settings'        => ['Configurações', 'settings.php'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo e($adminPageTitle); ?> &middot; Duás Admin</title>
    <link rel="stylesheet" href="<?php echo e($base); ?>/assets/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a href="<?php echo e($base); ?>/index.php" class="admin-brand">DUÁS<span>Admin</span></a>
        <nav class="admin-nav">
            <?php foreach ($nav as $key => [$label, $file]): ?>
                <a href="<?php echo e($base . '/' . $file); ?>" class="<?php echo $adminActive === $key ? 'is-active' : ''; ?>">
                    <?php echo e($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar-foot">
            <a href="<?php echo e($base); ?>/../index.php" target="_blank" rel="noopener"><?php echo ic('external', 14); ?> Ver loja</a>
        </div>
    </aside>

    <div class="admin-content">
        <header class="admin-topbar">
            <h1><?php echo e($adminPageTitle); ?></h1>
            <div class="admin-user">
                <span><?php echo e($admin['name'] ?? ''); ?></span>
                <a href="<?php echo e($base); ?>/logout.php" class="btn btn-secondary btn-sm"><?php echo ic('logout'); ?> Sair</a>
            </div>
        </header>

        <main class="admin-main">
            <?php foreach (flash_all() as $f): ?>
                <div class="alert alert-<?php echo e($f['type']); ?>"><?php echo e($f['message']); ?></div>
            <?php endforeach; ?>
