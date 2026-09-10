<?php
require_once __DIR__ . '/_bootstrap.php';
$admin = require_admin();
$pdo = db();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// --------------------------------------------------------------------------
// POST: salvar / excluir
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $op = post('op');

    if ($op === 'delete') {
        $delId = (int) post('id');
        if ($delId === (int) $admin['id']) {
            flash_set('error', 'Você não pode excluir o próprio usuário.');
        } else {
            $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$delId]);
            flash_set('success', 'Usuário excluído.');
        }
        admin_redirect('users.php');
    }

    // salvar (novo ou edição)
    $editId = (int) post('id');
    $name = trim((string) post('name', ''));
    $email = trim((string) post('email', ''));
    $password = (string) post('password', '');
    $isActive = post('is_active') ? 1 : 0;

    $errors = [];
    if ($name === '')                              $errors[] = 'Informe o nome.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
    if ($editId === 0 && strlen($password) < 8)    $errors[] = 'A senha deve ter ao menos 8 caracteres.';
    if ($editId > 0 && $password !== '' && strlen($password) < 8) $errors[] = 'A nova senha deve ter ao menos 8 caracteres.';

    // e-mail único
    $dup = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? AND id <> ? LIMIT 1');
    $dup->execute([$email, $editId]);
    if ($dup->fetch()) $errors[] = 'Já existe um usuário com esse e-mail.';

    if ($errors) {
        foreach ($errors as $err) flash_set('error', $err);
        admin_redirect('users.php?action=' . ($editId ? 'edit&id=' . $editId : 'new'));
    }

    if ($editId > 0) {
        if ($password !== '') {
            $pdo->prepare('UPDATE admin_users SET name = ?, email = ?, is_active = ?, password_hash = ? WHERE id = ?')
                ->execute([$name, $email, $isActive, password_hash($password, PASSWORD_DEFAULT), $editId]);
        } else {
            $pdo->prepare('UPDATE admin_users SET name = ?, email = ?, is_active = ? WHERE id = ?')
                ->execute([$name, $email, $isActive, $editId]);
        }
        flash_set('success', 'Usuário atualizado.');
    } else {
        $pdo->prepare('INSERT INTO admin_users (name, email, password_hash, is_active) VALUES (?, ?, ?, ?)')
            ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $isActive]);
        flash_set('success', 'Usuário cadastrado.');
    }
    admin_redirect('users.php');
}

// --------------------------------------------------------------------------
// Formulário (novo / editar)
// --------------------------------------------------------------------------
if ($action === 'new' || $action === 'edit') {
    $row = ['id' => 0, 'name' => '', 'email' => '', 'is_active' => 1];
    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT id, name, email, is_active FROM admin_users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { flash_set('error', 'Usuário não encontrado.'); admin_redirect('users.php'); }
    }

    $adminPageTitle = $action === 'edit' ? 'Editar usuário' : 'Novo usuário';
    $adminActive = 'users';
    require __DIR__ . '/_header.php';
    ?>
    <p><a href="users.php" class="back-link"><?php echo ic('arrow-left', 14); ?> Voltar para usuários</a></p>

    <form method="post" class="panel form-narrow">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op" value="save">
        <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">

        <label class="field">
            <span>Nome</span>
            <input type="text" name="name" value="<?php echo e($row['name']); ?>" required>
        </label>

        <label class="field">
            <span>E-mail</span>
            <input type="email" name="email" value="<?php echo e($row['email']); ?>" required>
        </label>

        <label class="field">
            <span>Senha <?php echo $action === 'edit' ? '(deixe em branco para manter)' : ''; ?></span>
            <input type="password" name="password" <?php echo $action === 'edit' ? '' : 'required'; ?> autocomplete="new-password">
        </label>

        <label class="check">
            <input type="checkbox" name="is_active" value="1" <?php echo (int) $row['is_active'] === 1 ? 'checked' : ''; ?>>
            <span>Usuário ativo</span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo ic('check'); ?> Salvar</button>
            <a href="users.php" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

// --------------------------------------------------------------------------
// Listagem
// --------------------------------------------------------------------------
$users = $pdo->query('SELECT id, name, email, is_active, last_login_at, created_at FROM admin_users ORDER BY name')->fetchAll();

$adminPageTitle = 'Usuários';
$adminActive = 'users';
require __DIR__ . '/_header.php';
?>
<div class="list-head">
    <p class="lead">Usuários com acesso ao painel. Sem perfis ou permissões &mdash; todos têm o mesmo acesso.</p>
    <a href="users.php?action=new" class="btn btn-primary"><?php echo ic('plus'); ?> Novo usuário</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nome</th><th>E-mail</th><th>Status</th><th>Último acesso</th><th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo e($u['name']); ?></td>
                    <td><?php echo e($u['email']); ?></td>
                    <td>
                        <span class="badge <?php echo (int) $u['is_active'] === 1 ? 'badge-on' : 'badge-off'; ?>">
                            <?php echo (int) $u['is_active'] === 1 ? 'Ativo' : 'Inativo'; ?>
                        </span>
                    </td>
                    <td><?php echo $u['last_login_at'] ? e(date('d/m/Y H:i', strtotime($u['last_login_at']))) : '&mdash;'; ?></td>
                    <td class="row-actions">
                        <?php echo edit_link('users.php?action=edit&id=' . (int) $u['id']); ?>
                        <?php if ((int) $u['id'] !== (int) $admin['id']): ?>
                            <form method="post" onsubmit="return confirm('Excluir este usuário?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="op" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                <button type="submit" class="btn-icon is-danger" title="Excluir"><?php echo ic('trash'); ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
