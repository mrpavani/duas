<?php
require_once __DIR__ . '/_bootstrap.php';
$admin = require_admin();
$pdo = db();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

const SETTING_FIELDS = [
    'origin_cep'        => 'CEP de origem',
    'contract_code'     => 'Código do contrato',
    'contract_password' => 'Senha do contrato',
    'notes'            => 'Observações internas',
];

function money_or_null($v): ?float
{
    $v = str_replace(['.', ' '], '', trim((string) $v));
    $v = str_replace(',', '.', $v);
    return $v === '' ? null : (float) $v;
}
function int_or_null($v): ?int
{
    $v = trim((string) $v);
    return $v === '' ? null : (int) $v;
}

// --------------------------------------------------------------------------
// POST
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $op = post('op');

    if ($op === 'delete') {
        $pdo->prepare('DELETE FROM shipping_methods WHERE id = ?')->execute([(int) post('id')]);
        flash_set('success', 'Forma de entrega removida.');
        admin_redirect('shipping-methods.php');
    }

    if ($op === 'toggle') {
        $pdo->prepare('UPDATE shipping_methods SET is_active = 1 - is_active WHERE id = ?')->execute([(int) post('id')]);
        admin_redirect('shipping-methods.php');
    }

    $editId = (int) post('id');
    $carrier = trim((string) post('carrier', ''));
    $label = trim((string) post('label', ''));
    $serviceCode = trim((string) post('service_code', ''));
    $flatRate = money_or_null(post('flat_rate', ''));
    $freeAbove = money_or_null(post('free_above', ''));
    $daysMin = int_or_null(post('estimated_days_min', ''));
    $daysMax = int_or_null(post('estimated_days_max', ''));
    $isActive = post('is_active') ? 1 : 0;
    $sortOrder = (int) post('sort_order', 0);

    $settings = [];
    foreach (array_keys(SETTING_FIELDS) as $key) {
        $val = trim((string) post('set_' . $key, ''));
        if ($val !== '') $settings[$key] = $val;
    }

    $errors = [];
    if ($carrier === '') $errors[] = 'Informe a transportadora (ex: correios).';
    if ($label === '')   $errors[] = 'Informe o nome da forma de entrega.';

    if ($errors) {
        foreach ($errors as $err) flash_set('error', $err);
        admin_redirect('shipping-methods.php?action=' . ($editId ? 'edit&id=' . $editId : 'new'));
    }

    $setJson = $settings ? json_encode($settings, JSON_UNESCAPED_UNICODE) : null;
    $params = [$carrier, $label, $serviceCode ?: null, $setJson, $flatRate, $freeAbove, $daysMin, $daysMax, $isActive, $sortOrder];

    if ($editId > 0) {
        $params[] = $editId;
        $pdo->prepare('UPDATE shipping_methods SET carrier=?, label=?, service_code=?, settings=?, flat_rate=?, free_above=?, estimated_days_min=?, estimated_days_max=?, is_active=?, sort_order=? WHERE id=?')
            ->execute($params);
        flash_set('success', 'Forma de entrega atualizada.');
    } else {
        $pdo->prepare('INSERT INTO shipping_methods (carrier, label, service_code, settings, flat_rate, free_above, estimated_days_min, estimated_days_max, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute($params);
        flash_set('success', 'Forma de entrega cadastrada.');
    }
    admin_redirect('shipping-methods.php');
}

// --------------------------------------------------------------------------
// Formulário
// --------------------------------------------------------------------------
if ($action === 'new' || $action === 'edit') {
    $row = ['id' => 0, 'carrier' => 'correios', 'label' => '', 'service_code' => '', 'settings' => null,
            'flat_rate' => '', 'free_above' => '', 'estimated_days_min' => '', 'estimated_days_max' => '',
            'is_active' => 1, 'sort_order' => 0];
    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM shipping_methods WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { flash_set('error', 'Registro não encontrado.'); admin_redirect('shipping-methods.php'); }
    }
    $set = json_decode((string) ($row['settings'] ?? ''), true) ?: [];

    $adminPageTitle = $action === 'edit' ? 'Editar forma de entrega' : 'Nova forma de entrega';
    $adminActive = 'shipping';
    require __DIR__ . '/_header.php';
    ?>
    <p><a href="shipping-methods.php" class="back-link"><?php echo ic('arrow-left', 14); ?> Voltar</a></p>

    <form method="post" class="panel form-narrow">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op" value="save">
        <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">

        <label class="field">
            <span>Transportadora</span>
            <input type="text" name="carrier" list="carriers" value="<?php echo e($row['carrier']); ?>" placeholder="correios" required>
            <datalist id="carriers">
                <option value="correios">Correios</option>
                <option value="jadlog">Jadlog</option>
                <option value="loggi">Loggi</option>
                <option value="azul_cargo">Azul Cargo</option>
                <option value="total_express">Total Express</option>
                <option value="retirada">Retirada na loja</option>
            </datalist>
            <small>Comece com <code>correios</code>; outras transportadoras podem ser adicionadas.</small>
        </label>

        <label class="field">
            <span>Nome exibido</span>
            <input type="text" name="label" value="<?php echo e($row['label']); ?>" placeholder="Correios PAC" required>
        </label>

        <label class="field field-sm">
            <span>Código do serviço</span>
            <input type="text" name="service_code" value="<?php echo e($row['service_code']); ?>" placeholder="03298 (PAC) / 03220 (SEDEX)">
        </label>

        <div class="grid-2">
            <label class="field field-sm">
                <span>Valor fixo (R$)</span>
                <input type="text" name="flat_rate" value="<?php echo e($row['flat_rate']); ?>" placeholder="ex: 24,90">
                <small>Deixe vazio para calcular por API.</small>
            </label>
            <label class="field field-sm">
                <span>Frete grátis acima de (R$)</span>
                <input type="text" name="free_above" value="<?php echo e($row['free_above']); ?>" placeholder="ex: 800,00">
            </label>
        </div>

        <div class="grid-2">
            <label class="field field-sm">
                <span>Prazo mínimo (dias úteis)</span>
                <input type="number" name="estimated_days_min" value="<?php echo e($row['estimated_days_min']); ?>">
            </label>
            <label class="field field-sm">
                <span>Prazo máximo (dias úteis)</span>
                <input type="number" name="estimated_days_max" value="<?php echo e($row['estimated_days_max']); ?>">
            </label>
        </div>

        <fieldset class="subfield">
            <legend>Configurações da transportadora</legend>
            <?php foreach (SETTING_FIELDS as $key => $lbl): ?>
                <label class="field">
                    <span><?php echo e($lbl); ?></span>
                    <input type="text" name="set_<?php echo e($key); ?>" value="<?php echo e($set[$key] ?? ''); ?>" autocomplete="off">
                </label>
            <?php endforeach; ?>
        </fieldset>

        <label class="field field-sm">
            <span>Ordem de exibição</span>
            <input type="number" name="sort_order" value="<?php echo e($row['sort_order']); ?>">
        </label>

        <label class="check">
            <input type="checkbox" name="is_active" value="1" <?php echo (int) $row['is_active'] === 1 ? 'checked' : ''; ?>>
            <span>Ativa no checkout</span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo ic('check'); ?> Salvar</button>
            <a href="shipping-methods.php" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

// --------------------------------------------------------------------------
// Listagem
// --------------------------------------------------------------------------
$rows = $pdo->query('SELECT * FROM shipping_methods ORDER BY sort_order, label')->fetchAll();

$adminPageTitle = 'Formas de entrega';
$adminActive = 'shipping';
require __DIR__ . '/_header.php';
?>
<div class="list-head">
    <p class="lead">Começa com os Correios (PAC e SEDEX). Adicione outras transportadoras quando quiser.</p>
    <a href="shipping-methods.php?action=new" class="btn btn-primary"><?php echo ic('plus'); ?> Nova forma de entrega</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Nome</th><th>Transportadora</th><th>Serviço</th><th>Valor</th><th>Prazo</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo e($r['label']); ?></td>
                    <td><code><?php echo e($r['carrier']); ?></code></td>
                    <td><?php echo $r['service_code'] ? e($r['service_code']) : '&mdash;'; ?></td>
                    <td>
                        <?php
                        if ($r['flat_rate'] !== null) echo 'R$ ' . number_format((float) $r['flat_rate'], 2, ',', '.');
                        else echo '<span class="hint">calculado</span>';
                        if ($r['free_above'] !== null) echo '<br><span class="hint">grátis &gt; R$ ' . number_format((float) $r['free_above'], 2, ',', '.') . '</span>';
                        ?>
                    </td>
                    <td>
                        <?php
                        $mn = $r['estimated_days_min']; $mx = $r['estimated_days_max'];
                        echo $mn !== null || $mx !== null ? e(trim(($mn ?? '') . ($mn !== null && $mx !== null ? '–' : '') . ($mx ?? ''))) . ' dias' : '&mdash;';
                        ?>
                    </td>
                    <td>
                        <form method="post" class="inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="op" value="toggle">
                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                            <button type="submit" class="badge <?php echo (int) $r['is_active'] === 1 ? 'badge-on' : 'badge-off'; ?>">
                                <?php echo (int) $r['is_active'] === 1 ? 'Ativa' : 'Inativa'; ?>
                            </button>
                        </form>
                    </td>
                    <td class="row-actions">
                        <?php echo edit_link('shipping-methods.php?action=edit&id=' . (int) $r['id']); ?>
                        <form method="post" onsubmit="return confirm('Remover esta forma de entrega?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="op" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                            <button type="submit" class="btn-icon is-danger" title="Excluir"><?php echo ic('trash'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="empty">Nenhuma forma de entrega cadastrada.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
