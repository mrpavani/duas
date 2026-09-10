<?php
require_once __DIR__ . '/_bootstrap.php';
$admin = require_admin();
$pdo = db();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

function fdt(?string $v): ?string { $v = trim((string) $v); return $v === '' ? null : str_replace('T', ' ', $v) . (strlen($v) === 16 ? ':00' : ''); }
function ffrom(?string $v): string { return $v ? str_replace(' ', 'T', substr($v, 0, 16)) : ''; }
function fdec(?string $v): float { $v = str_replace(['.', ' '], '', trim((string) $v)); return (float) str_replace(',', '.', $v); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $op = post('op');
    $pid = (int) post('id');

    if ($op === 'delete') {
        $pdo->prepare('DELETE FROM free_shipping_rules WHERE id = ?')->execute([$pid]);
        flash_set('success', 'Regra removida.');
        admin_redirect('free-shipping.php');
    }
    if ($op === 'toggle') {
        $pdo->prepare('UPDATE free_shipping_rules SET is_active = 1 - is_active WHERE id = ?')->execute([$pid]);
        admin_redirect('free-shipping.php');
    }

    $label = trim((string) post('label', ''));
    $minSub = fdec(post('min_subtotal'));
    $startsAt = fdt(post('starts_at'));
    $endsAt = fdt(post('ends_at'));
    $isActive = post('is_active') ? 1 : 0;

    $errors = [];
    if ($label === '') $errors[] = 'Informe um nome para a regra.';
    if ($minSub <= 0) $errors[] = 'Informe o valor mínimo de compra (maior que zero).';
    if ($startsAt && $endsAt && $startsAt > $endsAt) $errors[] = 'A data inicial é posterior à final.';

    if ($errors) {
        foreach ($errors as $err) flash_set('error', $err);
        admin_redirect('free-shipping.php?action=' . ($pid ? 'edit&id=' . $pid : 'new'));
    }

    $params = [$label, $minSub, $startsAt, $endsAt, $isActive];
    if ($pid > 0) {
        $params[] = $pid;
        $pdo->prepare('UPDATE free_shipping_rules SET label=?, min_subtotal=?, starts_at=?, ends_at=?, is_active=? WHERE id=?')->execute($params);
        flash_set('success', 'Regra atualizada.');
    } else {
        $pdo->prepare('INSERT INTO free_shipping_rules (label, min_subtotal, starts_at, ends_at, is_active) VALUES (?,?,?,?,?)')->execute($params);
        flash_set('success', 'Regra cadastrada.');
    }
    admin_redirect('free-shipping.php');
}

if ($action === 'new' || $action === 'edit') {
    $row = ['id'=>0,'label'=>'','min_subtotal'=>'','starts_at'=>null,'ends_at'=>null,'is_active'=>1];
    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM free_shipping_rules WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { flash_set('error', 'Regra não encontrada.'); admin_redirect('free-shipping.php'); }
    }

    $adminPageTitle = $action === 'edit' ? 'Editar regra de frete grátis' : 'Nova regra de frete grátis';
    $adminActive = 'freeship';
    require __DIR__ . '/_header.php';
    ?>
    <p><a href="free-shipping.php" class="back-link"><?php echo ic('arrow-left', 14); ?> Voltar</a></p>

    <form method="post" class="panel form-narrow">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op" value="save">
        <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">

        <label class="field">
            <span>Nome da campanha</span>
            <input type="text" name="label" value="<?php echo e($row['label']); ?>" placeholder="Ex: Frete grátis de aniversário" required>
        </label>

        <label class="field field-sm">
            <span>Frete grátis para compras a partir de (R$)</span>
            <input type="text" name="min_subtotal" value="<?php echo e($row['min_subtotal']); ?>" placeholder="ex: 500,00" required>
        </label>

        <div class="grid-2">
            <label class="field field-sm">
                <span>Começa em</span>
                <input type="datetime-local" name="starts_at" value="<?php echo e(ffrom($row['starts_at'])); ?>">
            </label>
            <label class="field field-sm">
                <span>Termina em</span>
                <input type="datetime-local" name="ends_at" value="<?php echo e(ffrom($row['ends_at'])); ?>">
            </label>
        </div>
        <small>Sem datas, a regra vale enquanto estiver ativa. Havendo várias regras ativas no período, vale a de menor valor mínimo.</small>

        <label class="check" style="margin-top:14px;">
            <input type="checkbox" name="is_active" value="1" <?php echo (int) $row['is_active'] === 1 ? 'checked' : ''; ?>>
            <span>Ativa</span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo ic('check'); ?> Salvar</button>
            <a href="free-shipping.php" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

$rows = $pdo->query('SELECT * FROM free_shipping_rules ORDER BY is_active DESC, min_subtotal')->fetchAll();
$now = date('Y-m-d H:i:s');

$adminPageTitle = 'Frete grátis';
$adminActive = 'freeship';
require __DIR__ . '/_header.php';
?>
<div class="list-head">
    <p class="lead">Regras de "frete grátis acima de X", com período opcional. Desative a regra padrão se não quiser frete grátis fora de campanha.</p>
    <a href="free-shipping.php?action=new" class="btn btn-primary"><?php echo ic('plus'); ?> Nova regra</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Campanha</th><th>Frete grátis acima de</th><th>Período</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $live = (int) $r['is_active'] === 1
                && (empty($r['starts_at']) || $now >= $r['starts_at'])
                && (empty($r['ends_at']) || $now <= $r['ends_at']);
            ?>
            <tr>
                <td><?php echo e($r['label']); ?></td>
                <td>R$ <?php echo number_format((float) $r['min_subtotal'], 2, ',', '.'); ?></td>
                <td class="hint">
                    <?php echo $r['starts_at'] ? e(date('d/m/y H:i', strtotime($r['starts_at']))) : 'sempre'; ?>
                    &rarr;
                    <?php echo $r['ends_at'] ? e(date('d/m/y H:i', strtotime($r['ends_at']))) : '&infin;'; ?>
                </td>
                <td>
                    <form method="post" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="op" value="toggle">
                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                        <button type="submit" class="badge <?php echo $live ? 'badge-on' : 'badge-off'; ?>">
                            <?php echo (int) $r['is_active'] === 1 ? ($live ? 'No ar' : 'Fora do período') : 'Inativa'; ?>
                        </button>
                    </form>
                </td>
                <td class="row-actions">
                    <?php echo edit_link('free-shipping.php?action=edit&id=' . (int) $r['id']); ?>
                    <form method="post" onsubmit="return confirm('Excluir esta regra?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="op" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                        <button type="submit" class="btn-icon is-danger" title="Excluir"><?php echo ic('trash'); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">Nenhuma regra cadastrada.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
