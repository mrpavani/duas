<?php
require_once __DIR__ . '/_bootstrap.php';
$admin = require_admin();

$pdo = db();
$count = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();

$stats = [
    ['Pagamentos pendentes', $count("SELECT COUNT(*) FROM orders WHERE payment_status IN ('pending','in_process')"), 'orders.php?pay=pending'],
    ['Pedidos a separar', $count("SELECT COUNT(*) FROM orders WHERE fulfillment_status IN ('a_separar','em_separacao')"), 'orders.php?ful=a_separar'],
    ['Pedidos a enviar', $count("SELECT COUNT(*) FROM orders WHERE fulfillment_status = 'separado'"), 'orders.php?ful=separado'],
    ['Produtos ativos', $count('SELECT COUNT(*) FROM products WHERE is_active = 1'), 'products.php'],
    ['Posts publicados', $count('SELECT COUNT(*) FROM blog_posts WHERE is_active = 1'), 'blog.php'],
    ['Promoções ativas', $count('SELECT COUNT(*) FROM promotions WHERE is_active = 1'), 'promotions.php'],
    ['Regras de frete grátis', $count('SELECT COUNT(*) FROM free_shipping_rules WHERE is_active = 1'), 'free-shipping.php'],
    ['Meios de pagamento ativos', $count('SELECT COUNT(*) FROM payment_methods WHERE is_active = 1'), 'payment-methods.php'],
    ['Formas de entrega ativas', $count('SELECT COUNT(*) FROM shipping_methods WHERE is_active = 1'), 'shipping-methods.php'],
    ['Usuários do painel', $count('SELECT COUNT(*) FROM admin_users'), 'users.php'],
];

$adminPageTitle = 'Visão geral';
$adminActive = '';
require __DIR__ . '/_header.php';
?>

<p class="lead">Bem-vindo(a), <?php echo e($admin['name']); ?>. Gerencie os acessos ao painel, os meios de pagamento e as formas de entrega da loja.</p>

<div class="stat-grid">
    <?php foreach ($stats as [$label, $value, $link]): ?>
        <a class="stat-card" href="<?php echo e($link); ?>">
            <span class="stat-value"><?php echo e($value); ?></span>
            <span class="stat-label"><?php echo e($label); ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <h2>Atalhos</h2>
    <div class="shortcut-row">
        <a class="btn btn-primary" href="orders.php"><?php echo ic('eye'); ?> Ver pedidos</a>
        <a class="btn btn-secondary" href="products.php?action=new"><?php echo ic('plus'); ?> Novo produto</a>
        <a class="btn btn-secondary" href="blog.php?action=new"><?php echo ic('plus'); ?> Novo post</a>
        <a class="btn btn-secondary" href="promotions.php?action=new"><?php echo ic('plus'); ?> Nova promoção</a>
        <a class="btn btn-secondary" href="free-shipping.php?action=new"><?php echo ic('plus'); ?> Nova regra de frete grátis</a>
        <a class="btn btn-secondary" href="payment-methods.php?action=new"><?php echo ic('plus'); ?> Novo meio de pagamento</a>
        <a class="btn btn-secondary" href="shipping-methods.php?action=new"><?php echo ic('plus'); ?> Nova forma de entrega</a>
        <a class="btn btn-secondary" href="users.php?action=new"><?php echo ic('plus'); ?> Novo usuário</a>
        <a class="btn btn-secondary" href="settings.php"><?php echo ic('pencil'); ?> Barra de aviso</a>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
