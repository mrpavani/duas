<?php
require_once __DIR__ . '/_bootstrap.php';
$admin = require_admin();
$pdo = db();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Campos de credencial suportados (genéricos, cobrem Mercado Pago e outros)
const CRED_FIELDS = [
    'public_key'     => 'Public Key / Chave pública',
    'access_token'   => 'Access Token',
    'client_id'      => 'Client ID',
    'client_secret'  => 'Client Secret',
    'webhook_secret' => 'Webhook Secret',
];

// --------------------------------------------------------------------------
// POST
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $op = post('op');

    if ($op === 'delete') {
        $pdo->prepare('DELETE FROM payment_methods WHERE id = ?')->execute([(int) post('id')]);
        flash_set('success', 'Meio de pagamento removido.');
        admin_redirect('payment-methods.php');
    }

    if ($op === 'toggle') {
        $pdo->prepare('UPDATE payment_methods SET is_active = 1 - is_active WHERE id = ?')->execute([(int) post('id')]);
        admin_redirect('payment-methods.php');
    }

    $editId = (int) post('id');
    $provider = trim((string) post('provider', ''));
    $label = trim((string) post('label', ''));
    $environment = post('environment') === 'production' ? 'production' : 'sandbox';
    $instructions = trim((string) post('instructions', ''));
    $isActive = post('is_active') ? 1 : 0;
    $sortOrder = (int) post('sort_order', 0);

    // credenciais existentes (para não apagar o que não foi reenviado)
    $existingCred = [];
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT credentials FROM payment_methods WHERE id = ?');
        $stmt->execute([$editId]);
        $existingCred = json_decode((string) $stmt->fetchColumn(), true) ?: [];
    }

    $credentials = $existingCred;
    foreach (array_keys(CRED_FIELDS) as $key) {
        $val = trim((string) post('cred_' . $key, ''));
        if ($val !== '') {
            $credentials[$key] = $val;
        }
    }
    // limpeza explícita
    foreach (array_keys(CRED_FIELDS) as $key) {
        if (post('clear_' . $key)) {
            unset($credentials[$key]);
        }
    }

    $errors = [];
    if ($provider === '') $errors[] = 'Informe o provedor (ex: mercado_pago).';
    if ($label === '')    $errors[] = 'Informe o nome exibido no checkout.';

    if ($errors) {
        foreach ($errors as $err) flash_set('error', $err);
        admin_redirect('payment-methods.php?action=' . ($editId ? 'edit&id=' . $editId : 'new'));
    }

    $credJson = $credentials ? json_encode($credentials, JSON_UNESCAPED_UNICODE) : null;

    if ($editId > 0) {
        $pdo->prepare('UPDATE payment_methods SET provider=?, label=?, environment=?, credentials=?, instructions=?, is_active=?, sort_order=? WHERE id=?')
            ->execute([$provider, $label, $environment, $credJson, $instructions ?: null, $isActive, $sortOrder, $editId]);
        flash_set('success', 'Meio de pagamento atualizado.');
    } else {
        $pdo->prepare('INSERT INTO payment_methods (provider, label, environment, credentials, instructions, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$provider, $label, $environment, $credJson, $instructions ?: null, $isActive, $sortOrder]);
        flash_set('success', 'Meio de pagamento cadastrado.');
    }
    admin_redirect('payment-methods.php');
}

// --------------------------------------------------------------------------
// Formulário
// --------------------------------------------------------------------------
if ($action === 'new' || $action === 'edit') {
    $row = ['id' => 0, 'provider' => '', 'label' => '', 'environment' => 'sandbox',
            'credentials' => null, 'instructions' => '', 'is_active' => 1, 'sort_order' => 0];
    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM payment_methods WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { flash_set('error', 'Registro não encontrado.'); admin_redirect('payment-methods.php'); }
    }
    $cred = json_decode((string) ($row['credentials'] ?? ''), true) ?: [];

    $adminPageTitle = $action === 'edit' ? 'Editar meio de pagamento' : 'Novo meio de pagamento';
    $adminActive = 'payments';
    require __DIR__ . '/_header.php';
    ?>
    <p><a href="payment-methods.php" class="back-link"><?php echo ic('arrow-left', 14); ?> Voltar</a></p>

    <form method="post" class="panel form-narrow">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op" value="save">
        <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">

        <label class="field">
            <span>Provedor</span>
            <input type="text" name="provider" list="providers" value="<?php echo e($row['provider']); ?>" placeholder="mercado_pago" required>
            <datalist id="providers">
                <option value="mercado_pago">Mercado Pago</option>
                <option value="pagseguro">PagSeguro</option>
                <option value="pagarme">Pagar.me</option>
                <option value="stripe">Stripe</option>
                <option value="pix_manual">Pix manual</option>
                <option value="boleto_manual">Boleto manual</option>
            </datalist>
            <small>Identificador do gateway. Comece com <code>mercado_pago</code>; outros podem ser adicionados livremente.</small>
        </label>

        <label class="field">
            <span>Nome exibido no checkout</span>
            <input type="text" name="label" value="<?php echo e($row['label']); ?>" placeholder="Mercado Pago" required>
        </label>

        <label class="field">
            <span>Ambiente</span>
            <select name="environment">
                <option value="sandbox" <?php echo $row['environment'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox (testes)</option>
                <option value="production" <?php echo $row['environment'] === 'production' ? 'selected' : ''; ?>>Produção</option>
            </select>
        </label>

        <fieldset class="subfield">
            <legend>Credenciais</legend>
            <?php foreach (CRED_FIELDS as $key => $lbl): ?>
                <?php $has = isset($cred[$key]) && $cred[$key] !== ''; ?>
                <label class="field">
                    <span><?php echo e($lbl); ?> <?php if ($has): ?><em class="hint">preenchido &bull; deixe em branco para manter</em><?php endif; ?></span>
                    <input type="text" name="cred_<?php echo e($key); ?>" autocomplete="off"
                           placeholder="<?php echo $has ? '•••••••• (mantido)' : ''; ?>">
                    <?php if ($has): ?>
                        <label class="check check-sm"><input type="checkbox" name="clear_<?php echo e($key); ?>" value="1"><span>Limpar este campo</span></label>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
            <small>Para o Mercado Pago basta <code>Public Key</code> e <code>Access Token</code>.</small>
        </fieldset>

        <label class="field">
            <span>Instruções (exibidas ao cliente)</span>
            <textarea name="instructions" rows="3"><?php echo e($row['instructions']); ?></textarea>
        </label>

        <label class="field field-sm">
            <span>Ordem de exibição</span>
            <input type="number" name="sort_order" value="<?php echo e($row['sort_order']); ?>">
        </label>

        <label class="check">
            <input type="checkbox" name="is_active" value="1" <?php echo (int) $row['is_active'] === 1 ? 'checked' : ''; ?>>
            <span>Ativo no checkout</span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo ic('check'); ?> Salvar</button>
            <a href="payment-methods.php" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

// --------------------------------------------------------------------------
// Listagem
// --------------------------------------------------------------------------
$rows = $pdo->query('SELECT * FROM payment_methods ORDER BY sort_order, label')->fetchAll();

$adminPageTitle = 'Meios de pagamento';
$adminActive = 'payments';
require __DIR__ . '/_header.php';
?>
<div class="list-head">
    <p class="lead">Cadastre quantos meios de pagamento desejar. O Mercado Pago já vem pré-cadastrado.</p>
    <a href="payment-methods.php?action=new" class="btn btn-primary"><?php echo ic('plus'); ?> Novo meio de pagamento</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Nome</th><th>Provedor</th><th>Ambiente</th><th>Credenciais</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <?php $cred = json_decode((string) $r['credentials'], true) ?: []; $filled = array_filter($cred, fn($v) => $v !== ''); ?>
                <tr>
                    <td><?php echo e($r['label']); ?></td>
                    <td><code><?php echo e($r['provider']); ?></code></td>
                    <td><?php echo $r['environment'] === 'production' ? 'Produção' : 'Sandbox'; ?></td>
                    <td><?php echo $filled ? e(count($filled)) . ' campo(s)' : '<span class="hint">não configurado</span>'; ?></td>
                    <td>
                        <form method="post" class="inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="op" value="toggle">
                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                            <button type="submit" class="badge <?php echo (int) $r['is_active'] === 1 ? 'badge-on' : 'badge-off'; ?>">
                                <?php echo (int) $r['is_active'] === 1 ? 'Ativo' : 'Inativo'; ?>
                            </button>
                        </form>
                    </td>
                    <td class="row-actions">
                        <?php echo edit_link('payment-methods.php?action=edit&id=' . (int) $r['id']); ?>
                        <form method="post" onsubmit="return confirm('Remover este meio de pagamento?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="op" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                            <button type="submit" class="btn-icon is-danger" title="Excluir"><?php echo ic('trash'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="empty">Nenhum meio de pagamento cadastrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
