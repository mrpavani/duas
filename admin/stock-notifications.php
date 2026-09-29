<?php
require_once __DIR__ . '/_bootstrap.php';
$admin = require_admin();
$pdo = db();

// --------------------------------------------------------------------------
// POST: Ações
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $op = post('op');
    $id = (int) post('id');

    if ($op === 'mark_notified') {
        $pdo->prepare("UPDATE stock_notifications SET status = 'notified', notified_at = NOW() WHERE id = ?")->execute([$id]);
        flash_set('success', 'Solicitação marcada como notificada.');
        admin_redirect('stock-notifications.php');
    }

    if ($op === 'mark_pending') {
        $pdo->prepare("UPDATE stock_notifications SET status = 'pending', notified_at = NULL WHERE id = ?")->execute([$id]);
        flash_set('success', 'Solicitação revertida para pendente.');
        admin_redirect('stock-notifications.php');
    }

    if ($op === 'delete') {
        $pdo->prepare("DELETE FROM stock_notifications WHERE id = ?")->execute([$id]);
        flash_set('success', 'Solicitação excluída.');
        admin_redirect('stock-notifications.php');
    }
}

// --------------------------------------------------------------------------
// Filtros e listagem
// --------------------------------------------------------------------------
$filterStatus = $_GET['status'] ?? 'all';
$sql = "
    SELECT sn.*,
           p.slug AS product_slug,
           p.is_active AS product_active,
           COALESCE(ps.stock, 0) AS current_stock
    FROM stock_notifications sn
    LEFT JOIN products p ON p.id = sn.product_id
    LEFT JOIN product_sizes ps ON ps.product_id = sn.product_id AND ps.size = sn.size
";

$params = [];
if ($filterStatus === 'pending') {
    $sql .= " WHERE sn.status = 'pending'";
} elseif ($filterStatus === 'notified') {
    $sql .= " WHERE sn.status = 'notified'";
}
$sql .= " ORDER BY sn.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Métricas de resumo
$totalCount = (int) $pdo->query("SELECT COUNT(*) FROM stock_notifications")->fetchColumn();
$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM stock_notifications WHERE status = 'pending'")->fetchColumn();
$notifiedCount = (int) $pdo->query("SELECT COUNT(*) FROM stock_notifications WHERE status = 'notified'")->fetchColumn();

$adminPageTitle = 'Avise-me quando chegar';
$adminActive = 'stock-notifications';
require __DIR__ . '/_header.php';
?>

<div class="list-head">
    <div>
        <p class="lead" style="margin-bottom:0;">Clientes que solicitaram aviso de reposição para peças e tamanhos esgotados.</p>
    </div>
</div>

<!-- Métricas -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
        <span class="stat-val"><?php echo $pendingCount; ?></span>
        <span class="stat-lbl">Aguardando reposição</span>
    </div>
    <div class="stat-card">
        <span class="stat-val"><?php echo $notifiedCount; ?></span>
        <span class="stat-lbl">Notificados</span>
    </div>
    <div class="stat-card">
        <span class="stat-val"><?php echo $totalCount; ?></span>
        <span class="stat-lbl">Total de solicitações</span>
    </div>
</div>

<!-- Filtros de status -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <div style="display:flex; gap:8px;">
        <a href="stock-notifications.php?status=all" class="btn btn-sm <?php echo $filterStatus === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">
            Todas (<?php echo $totalCount; ?>)
        </a>
        <a href="stock-notifications.php?status=pending" class="btn btn-sm <?php echo $filterStatus === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">
            Pendentes (<?php echo $pendingCount; ?>)
        </a>
        <a href="stock-notifications.php?status=notified" class="btn btn-sm <?php echo $filterStatus === 'notified' ? 'btn-primary' : 'btn-secondary'; ?>">
            Notificadas (<?php echo $notifiedCount; ?>)
        </a>
    </div>
</div>

<!-- Tabela de solicitações -->
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 130px;">Data</th>
                <th>Cliente</th>
                <th>E-mail</th>
                <th>Peça / Produto</th>
                <th style="width: 90px; text-align:center;">Tam.</th>
                <th style="width: 120px; text-align:center;">Estoque Atual</th>
                <th style="width: 100px; text-align:center;">Status</th>
                <th style="width: 160px; text-align:right;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="empty">Nenhuma solicitação encontrada neste filtro.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $isPending = $r['status'] === 'pending';
                    $curStk = (int) $r['current_stock'];
                    ?>
                    <tr>
                        <td style="font-size:0.82rem; color:var(--a-muted);">
                            <?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?>
                        </td>

                        <td>
                            <strong><?php echo e($r['customer_name']); ?></strong>
                        </td>

                        <td>
                            <a href="mailto:<?php echo e($r['customer_email']); ?>?subject=<?php echo urlencode('Novidade! O tamanho ' . $r['size'] . ' da peça ' . $r['product_name'] . ' voltou ao estoque'); ?>" style="text-decoration:underline;">
                                <?php echo e($r['customer_email']); ?>
                            </a>
                        </td>

                        <td>
                            <strong><?php echo e($r['product_name']); ?></strong>
                            <?php if (!empty($r['product_id'])): ?>
                                <br><a href="products.php?action=edit&id=<?php echo (int) $r['product_id']; ?>" class="hint" style="text-decoration:underline;">Editar produto #<?php echo (int) $r['product_id']; ?></a>
                            <?php endif; ?>
                        </td>

                        <td style="text-align:center;">
                            <span class="badge" style="background:#121212; color:#fff; font-size:0.8rem; padding:2px 8px;">
                                <?php echo e($r['size']); ?>
                            </span>
                        </td>

                        <td style="text-align:center;">
                            <?php if ($curStk > 0): ?>
                                <span class="badge badge-on" title="Já há estoque disponível deste tamanho!">
                                    <?php echo $curStk; ?> un em estoque
                                </span>
                            <?php else: ?>
                                <span class="badge badge-err" title="Tamanho continua esgotado">
                                    0 un (Esgotado)
                                </span>
                            <?php endif; ?>
                        </td>

                        <td style="text-align:center;">
                            <?php if ($isPending): ?>
                                <span class="badge badge-warn">Pendente</span>
                            <?php else: ?>
                                <span class="badge badge-on">Notificado</span>
                                <?php if (!empty($r['notified_at'])): ?>
                                    <small style="font-size:0.7rem; color:var(--a-muted);"><?php echo date('d/m H:i', strtotime($r['notified_at'])); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td class="row-actions">
                            <?php if ($isPending): ?>
                                <form method="post" class="inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="op" value="mark_notified">
                                    <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" title="Marcar como notificado">
                                        <?php echo ic('check', 13); ?> Notificado
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" class="inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="op" value="mark_pending">
                                    <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" title="Reverter para pendente">
                                        Reverter
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form method="post" class="inline" onsubmit="return confirm('Deseja excluir esta solicitação?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="op" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                <button type="submit" class="btn-icon is-danger" title="Excluir">
                                    <?php echo ic('trash', 14); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
