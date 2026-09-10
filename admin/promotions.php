<?php
require_once __DIR__ . '/_bootstrap.php';
$admin = require_admin();
$pdo = db();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

function pdt(?string $v): ?string { $v = trim((string) $v); return $v === '' ? null : str_replace('T', ' ', $v) . (strlen($v) === 16 ? ':00' : ''); }
function pfrom(?string $v): string { return $v ? str_replace(' ', 'T', substr($v, 0, 16)) : ''; }
function pdec(?string $v): float { $v = str_replace(['.', ' '], '', trim((string) $v)); return (float) str_replace(',', '.', $v); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $op = post('op');
    $pid = (int) post('id');

    if ($op === 'delete') {
        $pdo->prepare('DELETE FROM promotions WHERE id = ?')->execute([$pid]);
        flash_set('success', 'Promoção removida.');
        admin_redirect('promotions.php');
    }
    if ($op === 'toggle') {
        $pdo->prepare('UPDATE promotions SET is_active = 1 - is_active WHERE id = ?')->execute([$pid]);
        admin_redirect('promotions.php');
    }

    $name = trim((string) post('name', ''));
    $code = strtoupper(trim((string) post('code', '')));
    $type = post('discount_type') === 'fixed' ? 'fixed' : 'percent';
    $value = pdec(post('discount_value'));
    $minSub = pdec(post('min_subtotal'));
    $startsAt = pdt(post('starts_at'));
    $endsAt = pdt(post('ends_at'));
    $limit = trim((string) post('usage_limit', ''));
    $limit = $limit === '' ? null : (int) $limit;
    $isActive = post('is_active') ? 1 : 0;

    $errors = [];
    if ($name === '') $errors[] = 'Informe o nome da promoção.';
    if ($value <= 0) $errors[] = 'Informe um valor de desconto maior que zero.';
    if ($type === 'percent' && $value > 100) $errors[] = 'Percentual não pode passar de 100%.';
    if ($startsAt && $endsAt && $startsAt > $endsAt) $errors[] = 'A data inicial é posterior à final.';
    if ($code !== '') {
        $dup = $pdo->prepare('SELECT id FROM promotions WHERE code = ? AND id <> ? LIMIT 1');
        $dup->execute([$code, $pid]);
        if ($dup->fetch()) $errors[] = 'Já existe uma promoção com esse cupom.';
    }

    if ($errors) {
        foreach ($errors as $err) flash_set('error', $err);
        admin_redirect('promotions.php?action=' . ($pid ? 'edit&id=' . $pid : 'new'));
    }

    $params = [$name, $code ?: null, $type, $value, $minSub, $startsAt, $endsAt, $limit, $isActive];
    if ($pid > 0) {
        $params[] = $pid;
        $pdo->prepare('UPDATE promotions SET name=?, code=?, discount_type=?, discount_value=?, min_subtotal=?, starts_at=?, ends_at=?, usage_limit=?, is_active=? WHERE id=?')->execute($params);
        flash_set('success', 'Promoção atualizada.');
    } else {
        $pdo->prepare('INSERT INTO promotions (name, code, discount_type, discount_value, min_subtotal, starts_at, ends_at, usage_limit, is_active) VALUES (?,?,?,?,?,?,?,?,?)')->execute($params);
        flash_set('success', 'Promoção cadastrada.');
    }
    admin_redirect('promotions.php');
}

if ($action === 'new' || $action === 'edit') {
    $row = ['id'=>0,'name'=>'','code'=>'','discount_type'=>'percent','discount_value'=>'','min_subtotal'=>'0',
            'starts_at'=>null,'ends_at'=>null,'usage_limit'=>'','used_count'=>0,'is_active'=>1];
    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM promotions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { flash_set('error', 'Promoção não encontrada.'); admin_redirect('promotions.php'); }
    }

    $adminPageTitle = $action === 'edit' ? 'Editar promoção' : 'Nova promoção';
    $adminActive = 'promotions';
    require __DIR__ . '/_header.php';
    ?>
    <p><a href="promotions.php" class="back-link"><?php echo ic('arrow-left', 14); ?> Voltar</a></p>

    <form method="post" class="panel form-narrow">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op" value="save">
        <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">

        <label class="field">
            <span>Nome interno</span>
            <input type="text" name="name" value="<?php echo e($row['name']); ?>" placeholder="Ex: Black Friday 2026" required>
        </label>

        <label class="field field-sm">
            <span>Cupom (opcional)</span>
            <input type="text" name="code" value="<?php echo e($row['code']); ?>" placeholder="DUAS10" style="text-transform:uppercase">
            <small>Em branco = desconto sem cupom (uso interno / futuro automático).</small>
        </label>

        <div class="grid-2">
            <label class="field field-sm">
                <span>Tipo de desconto</span>
                <select name="discount_type">
                    <option value="percent" <?php echo $row['discount_type'] === 'percent' ? 'selected' : ''; ?>>Percentual (%)</option>
                    <option value="fixed" <?php echo $row['discount_type'] === 'fixed' ? 'selected' : ''; ?>>Valor fixo (R$)</option>
                </select>
            </label>
            <label class="field field-sm">
                <span>Valor do desconto</span>
                <input type="text" name="discount_value" value="<?php echo e($row['discount_value']); ?>" placeholder="10" required>
            </label>
        </div>

        <label class="field field-sm">
            <span>Valor mínimo do pedido (R$)</span>
            <input type="text" name="min_subtotal" value="<?php echo e($row['min_subtotal']); ?>" placeholder="0">
        </label>

        <div class="grid-2">
            <label class="field field-sm">
                <span>Válida a partir de</span>
                <input type="datetime-local" name="starts_at" value="<?php echo e(pfrom($row['starts_at'])); ?>">
            </label>
            <label class="field field-sm">
                <span>Válida até</span>
                <input type="datetime-local" name="ends_at" value="<?php echo e(pfrom($row['ends_at'])); ?>">
            </label>
        </div>

        <label class="field field-sm">
            <span>Limite de usos (opcional)</span>
            <input type="number" name="usage_limit" value="<?php echo e($row['usage_limit']); ?>" min="1">
            <?php if ($action === 'edit'): ?><small>Usos até agora: <?php echo (int) $row['used_count']; ?></small><?php endif; ?>
        </label>

        <label class="check">
            <input type="checkbox" name="is_active" value="1" <?php echo (int) $row['is_active'] === 1 ? 'checked' : ''; ?>>
            <span>Ativa</span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo ic('check'); ?> Salvar</button>
            <a href="promotions.php" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

$rows = $pdo->query('SELECT * FROM promotions ORDER BY is_active DESC, id DESC')->fetchAll();
$now = date('Y-m-d H:i:s');

$adminPageTitle = 'Promoções';
$adminActive = 'promotions';
require __DIR__ . '/_header.php';
?>
<div class="list-head">
    <p class="lead">Cupons e descontos com valor mínimo, período de validade e limite de usos. O cupom <code>DUAS10</code> já está ativo.</p>
    <a href="promotions.php?action=new" class="btn btn-primary"><?php echo ic('plus'); ?> Nova promoção</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Nome</th><th>Cupom</th><th>Desconto</th><th>Mín.</th><th>Período</th><th>Usos</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $live = (int) $r['is_active'] === 1
                && (empty($r['starts_at']) || $now >= $r['starts_at'])
                && (empty($r['ends_at']) || $now <= $r['ends_at']);
            ?>
            <tr>
                <td><?php echo e($r['name']); ?></td>
                <td><?php echo $r['code'] ? '<code>' . e($r['code']) . '</code>' : '<span class="hint">automático</span>'; ?></td>
                <td><?php echo $r['discount_type'] === 'percent' ? rtrim(rtrim(number_format((float) $r['discount_value'], 2, ',', '.'), '0'), ',') . '%' : 'R$ ' . number_format((float) $r['discount_value'], 2, ',', '.'); ?></td>
                <td><?php echo (float) $r['min_subtotal'] > 0 ? 'R$ ' . number_format((float) $r['min_subtotal'], 2, ',', '.') : '&mdash;'; ?></td>
                <td class="hint">
                    <?php echo $r['starts_at'] ? e(date('d/m/y H:i', strtotime($r['starts_at']))) : 'sempre'; ?>
                    &rarr;
                    <?php echo $r['ends_at'] ? e(date('d/m/y H:i', strtotime($r['ends_at']))) : '&infin;'; ?>
                </td>
                <td><?php echo (int) $r['used_count']; ?><?php echo $r['usage_limit'] !== null ? ' / ' . (int) $r['usage_limit'] : ''; ?></td>
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
                    <?php echo edit_link('promotions.php?action=edit&id=' . (int) $r['id']); ?>
                    <form method="post" onsubmit="return confirm('Excluir esta promoção?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="op" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                        <button type="submit" class="btn-icon is-danger" title="Excluir"><?php echo ic('trash'); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="empty">Nenhuma promoção cadastrada.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
