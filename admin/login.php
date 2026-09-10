<?php
require_once __DIR__ . '/_bootstrap.php';

if (current_admin()) {
    admin_redirect(admin_base() . '/index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string) post('email', ''));
    $password = (string) post('password', '');

    if ($email === '' || $password === '') {
        $error = 'Informe e-mail e senha.';
    } elseif (admin_login($email, $password)) {
        admin_redirect(admin_base() . '/index.php');
    } else {
        $error = 'Credenciais inválidas ou usuário inativo.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Entrar &middot; Duás Admin</title>
    <link rel="stylesheet" href="<?php echo e(admin_base()); ?>/assets/admin.css">
</head>
<body class="admin-login-body">
    <form method="post" class="admin-login-card">
        <div class="admin-brand admin-brand-lg">DUÁS<span>Admin</span></div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <?php echo csrf_field(); ?>

        <label class="field">
            <span>E-mail</span>
            <input type="email" name="email" value="<?php echo e(post('email', '')); ?>" required autofocus>
        </label>

        <label class="field">
            <span>Senha</span>
            <input type="password" name="password" required>
        </label>

        <button type="submit" class="btn btn-primary btn-block"><?php echo ic('arrow-right'); ?> Entrar no painel</button>
    </form>
</body>
</html>
