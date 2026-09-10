<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/orders.php';
$admin = require_admin();
$pdo = db();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

function pay_badge_class(string $s): string
{
    return match ($s) {
        'approved', 'authorized' => 'badge-on',
        'rejected', 'cancelled', 'refunded', 'charged_back' => 'badge-err',
        default => 'badge-warn',
    };
}
function ful_badge_class(string $s): string
{
    return match ($s) {
        'entregue' => 'badge-on',
        'cancelado' => 'badge-err',
        'aguardando_pagamento' => 'badge-warn',
        default => 'badge-info',
    };
}
function dt(?string $v): string
{
    return $v ? date('d/m/Y H:i', strtotime($v)) : '—';
}

// --------------------------------------------------------------------------
// POST (ações na tela de detalhe)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $op = post('op');
    $oid = (int) post('id');
    $order = get_order($oid);
    if (!$order) { flash_set('error', 'Pedido não encontrado.'); admin_redirect('orders.php'); }
    $back = 'orders.php?id=' . $oid;

    if ($op === 'sync_payment') {
        if (empty($order['mp_payment_id'])) {
            flash_set('error', 'Este pedido ainda não tem um pagamento do Mercado Pago para sincronizar.');
        } else {
            $res = mp_get_payment((string) $order['mp_payment_id']);
            if ($res['ok']) {
                $applied = apply_mp_payment_to_order($order, $res['data']);
                flash_set($applied['friendly']['tone'] === 'error' ? 'error' : 'success',
                    'Mercado Pago: ' . $applied['friendly']['message']);
            } else {
                flash_set('error', $res['error'] ?: 'Falha ao consultar o Mercado Pago.');
            }
        }
        admin_redirect($back);
    }

    if ($op === 'set_payment_manual') {
        $new = (string) post('payment_status', '');
        $allowed = ['approved', 'pending', 'in_process', 'rejected', 'cancelled', 'refunded'];
        if (!in_array($new, $allowed, true)) { flash_set('error', 'Status inválido.'); admin_redirect($back); }
        $note = trim((string) post('note', ''));
        $ful = $order['fulfillment_status'];
        $paidAt = $order['paid_at'];
        if ($new === 'approved') {
            if ($ful === 'aguardando_pagamento') $ful = 'a_separar';
            $paidAt = $paidAt ?: date('Y-m-d H:i:s');
        }
        $pdo->prepare('UPDATE orders SET payment_status=?, payment_status_detail=?, status=?, fulfillment_status=?, paid_at=? WHERE id=?')
            ->execute([$new, 'ajuste_manual', mp_status_label($new)['label'], $ful, $paidAt, $oid]);
        order_log($oid, 'payment', $order['payment_status'], $new,
            'Status de pagamento ajustado manualmente por ' . $admin['name'] . ($note ? ' — ' . $note : ''), [], (int) $admin['id']);
        if ($ful !== $order['fulfillment_status']) {
            order_log($oid, 'fulfillment', $order['fulfillment_status'], $ful, 'Separação liberada após ajuste de pagamento.', [], (int) $admin['id']);
        }
        flash_set('success', 'Pagamento atualizado.');
        admin_redirect($back);
    }

    if ($op === 'advance_fulfillment') {
        $target = (string) post('to', '');
        $valid = array_merge(FULFILLMENT_FLOW, ['cancelado']);
        if (!in_array($target, $valid, true)) { flash_set('error', 'Etapa inválida.'); admin_redirect($back); }

        // não deixa separar/enviar sem pagamento aprovado
        if (!in_array($target, ['cancelado'], true)
            && $order['payment_status'] !== 'approved'
            && $target !== 'aguardando_pagamento') {
            flash_set('error', 'O pagamento precisa estar aprovado para avançar a separação.');
            admin_redirect($back);
        }

        $stamp = [];
        if ($target === 'separado')  $stamp['separated_at'] = date('Y-m-d H:i:s');
        if ($target === 'enviado')   $stamp['shipped_at'] = date('Y-m-d H:i:s');
        if ($target === 'entregue')  $stamp['delivered_at'] = date('Y-m-d H:i:s');
        if ($target === 'cancelado') $stamp['cancelled_at'] = date('Y-m-d H:i:s');

        $set = 'fulfillment_status = ?, status = ?';
        $params = [$target, fulfillment_label($target)];
        foreach ($stamp as $col => $val) { $set .= ", $col = ?"; $params[] = $val; }
        $params[] = $oid;
        $pdo->prepare("UPDATE orders SET $set WHERE id = ?")->execute($params);

        order_log($oid, 'fulfillment', $order['fulfillment_status'], $target,
            'Etapa alterada para "' . fulfillment_label($target) . '" por ' . $admin['name'], [], (int) $admin['id']);
        flash_set('success', 'Etapa atualizada para ' . fulfillment_label($target) . '.');
        admin_redirect($back);
    }

    if ($op === 'save_shipping') {
        $track = trim((string) post('tracking_code', ''));
        $label = trim((string) post('shipping_label', ''));
        $pdo->prepare('UPDATE orders SET tracking_code=?, shipping_label=? WHERE id=?')
            ->execute([$track ?: null, $label ?: $order['shipping_label'], $oid]);
        order_log($oid, 'note', null, null,
            'Dados de envio atualizados' . ($track ? ' — rastreio ' . $track : '') . ($label ? ' — ' . $label : ''), [], (int) $admin['id']);
        flash_set('success', 'Dados de envio salvos.');
        admin_redirect($back);
    }

    if ($op === 'add_note') {
        $note = trim((string) post('note', ''));
        if ($note !== '') {
            order_log($oid, 'note', null, null, $note, [], (int) $admin['id']);
            flash_set('success', 'Anotação adicionada.');
        }
        admin_redirect($back);
    }

    admin_redirect($back);
}

// --------------------------------------------------------------------------
// DETALHE
// --------------------------------------------------------------------------
if ($id) {
    $order = get_order($id);
    if (!$order) { flash_set('error', 'Pedido não encontrado.'); admin_redirect('orders.php'); }
    $items = get_order_items($id);
    $events = get_order_events($id);
    $raw = $order['payment_raw'] ? json_decode((string) $order['payment_raw'], true) : null;
    $friendly = mp_friendly($order['payment_status'], $order['payment_status_detail']);
    $payErrors = $raw ? mp_error_messages($raw) : ($friendly['tone'] === 'error' ? [$friendly['message']] : []);

    // próxima etapa possível
    $curIdx = array_search($order['fulfillment_status'], FULFILLMENT_FLOW, true);
    $nextStep = ($curIdx !== false && $curIdx < count(FULFILLMENT_FLOW) - 1) ? FULFILLMENT_FLOW[$curIdx + 1] : null;

    $adminPageTitle = 'Pedido #' . $order['order_code'];
    $adminActive = 'orders';
    require __DIR__ . '/_header.php';
    ?>
    <p><a href="orders.php" class="back-link"><?php echo ic('arrow-left', 14); ?> Todos os pedidos</a></p>

    <div class="order-grid">
        <div>
            <!-- Itens -->
            <div class="panel">
                <h2>Itens</h2>
                <table class="data-table">
                    <thead><tr><th>Produto</th><th>Tam.</th><th>Qtd</th><th>Unit.</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?php echo e($it['product_name']); ?></td>
                            <td><?php echo e($it['size']); ?></td>
                            <td><?php echo (int) $it['quantity']; ?></td>
                            <td>R$ <?php echo number_format((float) $it['unit_price'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $it['unit_price'] * (int) $it['quantity'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="totals">
                    <div><span>Subtotal</span><span>R$ <?php echo number_format((float) $order['subtotal'], 2, ',', '.'); ?></span></div>
                    <?php if ((float) $order['discount'] > 0): ?>
                        <div><span>Desconto<?php echo $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : ''; ?></span><span>- R$ <?php echo number_format((float) $order['discount'], 2, ',', '.'); ?></span></div>
                    <?php endif; ?>
                    <div><span>Frete<?php echo $order['shipping_label'] ? ' (' . e($order['shipping_label']) . ')' : ''; ?></span><span>R$ <?php echo number_format((float) $order['shipping'], 2, ',', '.'); ?></span></div>
                    <div class="grand"><span>Total</span><span>R$ <?php echo number_format((float) $order['total'], 2, ',', '.'); ?></span></div>
                </div>
            </div>

            <!-- Pagamento -->
            <div class="panel">
                <h2>Pagamento &mdash; autorização</h2>
                <div class="pay-status pay-<?php echo e($friendly['tone']); ?>">
                    <strong><?php echo e($friendly['title']); ?></strong>
                    <p><?php echo e($friendly['message']); ?></p>
                </div>

                <?php if ($payErrors): ?>
                    <div class="pay-errors">
                        <strong>Erros retornados:</strong>
                        <ul>
                            <?php foreach ($payErrors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="kv">
                    <div><span>Provedor</span><b><?php echo e($order['payment_provider'] ?: '—'); ?><?php echo $order['payment_label'] ? ' · ' . e($order['payment_label']) : ''; ?></b></div>
                    <div><span>Status</span><b><?php echo e(payment_label_ptbr($order['payment_status'])); ?> <span class="hint">(<?php echo e($order['payment_status']); ?>)</span></b></div>
                    <div><span>status_detail</span><b><?php echo e($order['payment_status_detail'] ?: '—'); ?></b></div>
                    <div><span>ID do pagamento (MP)</span><b><?php echo e($order['mp_payment_id'] ?: '—'); ?></b></div>
                    <div><span>Preferência</span><b><?php echo e($order['mp_preference_id'] ?: '—'); ?></b></div>
                    <div><span>Pago em</span><b><?php echo dt($order['paid_at']); ?></b></div>
                    <?php if ($raw): ?>
                        <div><span>Valor processado</span><b>R$ <?php echo number_format((float) ($raw['transaction_amount'] ?? 0), 2, ',', '.'); ?></b></div>
                        <div><span>Meio (MP)</span><b><?php echo e(($raw['payment_type_id'] ?? '') . ' ' . ($raw['payment_method_id'] ?? '')); ?></b></div>
                        <div><span>Parcelas</span><b><?php echo e($raw['installments'] ?? '—'); ?></b></div>
                        <div><span>E-mail do pagador</span><b><?php echo e($raw['payer']['email'] ?? '—'); ?></b></div>
                    <?php endif; ?>
                </div>

                <div class="form-actions" style="margin-top:16px;flex-wrap:wrap;">
                    <?php if (!empty($order['mp_init_point']) && $order['payment_status'] !== 'approved'): ?>
                        <a href="<?php echo e($order['mp_init_point']); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><?php echo ic('external'); ?> Abrir Checkout Pro</a>
                    <?php endif; ?>
                    <form method="post" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="op" value="sync_payment">
                        <input type="hidden" name="id" value="<?php echo (int) $order['id']; ?>">
                        <button class="btn btn-primary btn-sm" type="submit"><?php echo ic('refresh'); ?> Sincronizar com Mercado Pago</button>
                    </form>
                </div>

                <?php if ($raw): ?>
                    <details style="margin-top:14px;">
                        <summary class="hint" style="cursor:pointer;">Ver resposta completa do Mercado Pago (JSON)</summary>
                        <pre class="raw-json"><?php echo e(json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                    </details>
                <?php endif; ?>

                <details style="margin-top:12px;">
                    <summary class="hint" style="cursor:pointer;">Ajuste manual de pagamento (sem Mercado Pago)</summary>
                    <form method="post" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="op" value="set_payment_manual">
                        <input type="hidden" name="id" value="<?php echo (int) $order['id']; ?>">
                        <label class="field field-sm" style="margin:0;">
                            <span>Novo status</span>
                            <select name="payment_status">
                                <option value="approved">Aprovado</option>
                                <option value="pending">Pendente</option>
                                <option value="in_process">Em análise</option>
                                <option value="rejected">Recusado</option>
                                <option value="cancelled">Cancelado</option>
                                <option value="refunded">Estornado</option>
                            </select>
                        </label>
                        <label class="field" style="margin:0;flex:1;min-width:180px;">
                            <span>Observação</span>
                            <input type="text" name="note" placeholder="motivo do ajuste">
                        </label>
                        <button class="btn btn-secondary btn-sm" type="submit"><?php echo ic('check'); ?> Aplicar</button>
                    </form>
                </details>
            </div>

            <!-- Separação & Envio -->
            <div class="panel">
                <h2>Separação &amp; Envio</h2>
                <p>Etapa atual: <span class="badge <?php echo ful_badge_class($order['fulfillment_status']); ?>"><?php echo e(fulfillment_label($order['fulfillment_status'])); ?></span></p>

                <div class="steps">
                    <?php foreach (FULFILLMENT_FLOW as $step): ?>
                        <?php $done = ($curIdx !== false && array_search($step, FULFILLMENT_FLOW, true) <= $curIdx) && $order['fulfillment_status'] !== 'cancelado'; ?>
                        <span class="step <?php echo $done ? 'is-done' : ''; ?>"><?php echo e(fulfillment_label($step)); ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions" style="flex-wrap:wrap;">
                    <?php if ($nextStep && $order['fulfillment_status'] !== 'cancelado'): ?>
                        <form method="post" class="inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="op" value="advance_fulfillment">
                            <input type="hidden" name="id" value="<?php echo (int) $order['id']; ?>">
                            <input type="hidden" name="to" value="<?php echo e($nextStep); ?>">
                            <button class="btn btn-primary btn-sm" type="submit"><?php echo ic('arrow-right'); ?> Avançar para "<?php echo e(fulfillment_label($nextStep)); ?>"</button>
                        </form>
                    <?php endif; ?>
                    <?php if (!in_array($order['fulfillment_status'], ['entregue', 'cancelado'], true)): ?>
                        <form method="post" class="inline" onsubmit="return confirm('Cancelar este pedido?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="op" value="advance_fulfillment">
                            <input type="hidden" name="id" value="<?php echo (int) $order['id']; ?>">
                            <input type="hidden" name="to" value="cancelado">
                            <button class="btn btn-ghost btn-sm link-danger" type="submit"><?php echo ic('x'); ?> Cancelar pedido</button>
                        </form>
                    <?php endif; ?>
                </div>

                <form method="post" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="op" value="save_shipping">
                    <input type="hidden" name="id" value="<?php echo (int) $order['id']; ?>">
                    <label class="field field-sm" style="margin:0;">
                        <span>Transportadora</span>
                        <input type="text" name="shipping_label" value="<?php echo e($order['shipping_label']); ?>">
                    </label>
                    <label class="field field-sm" style="margin:0;">
                        <span>Código de rastreio</span>
                        <input type="text" name="tracking_code" value="<?php echo e($order['tracking_code']); ?>" placeholder="BR123456789BR">
                    </label>
                    <button class="btn btn-secondary btn-sm" type="submit"><?php echo ic('truck'); ?> Salvar envio</button>
                </form>

                <div class="kv" style="margin-top:16px;">
                    <div><span>Pago em</span><b><?php echo dt($order['paid_at']); ?></b></div>
                    <div><span>Separado em</span><b><?php echo dt($order['separated_at']); ?></b></div>
                    <div><span>Enviado em</span><b><?php echo dt($order['shipped_at']); ?></b></div>
                    <div><span>Entregue em</span><b><?php echo dt($order['delivered_at']); ?></b></div>
                </div>
            </div>

            <!-- Histórico -->
            <div class="panel">
                <h2>Histórico</h2>
                <ol class="tl">
                    <?php foreach ($events as $ev): ?>
                        <li>
                            <span class="tl-when"><?php echo dt($ev['created_at']); ?></span>
                            <span class="tl-type tl-<?php echo e($ev['event_type']); ?>"><?php echo e($ev['event_type']); ?></span>
                            <span class="tl-msg">
                                <?php echo e($ev['message']); ?>
                                <?php if ($ev['from_status'] || $ev['to_status']): ?>
                                    <span class="hint"><?php echo e($ev['from_status'] ?: '—'); ?> &rarr; <?php echo e($ev['to_status'] ?: '—'); ?></span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$events): ?><li class="hint">Sem eventos.</li><?php endif; ?>
                </ol>
            </div>
        </div>

        <!-- Aside -->
        <aside>
            <div class="panel">
                <h2>Resumo</h2>
                <div class="kv">
                    <div><span>Pedido</span><b>#<?php echo e($order['order_code']); ?></b></div>
                    <div><span>Data</span><b><?php echo dt($order['created_at']); ?></b></div>
                    <div><span>Pagamento</span><b><span class="badge <?php echo pay_badge_class($order['payment_status']); ?>"><?php echo e(payment_label_ptbr($order['payment_status'])); ?></span></b></div>
                    <div><span>Separação</span><b><span class="badge <?php echo ful_badge_class($order['fulfillment_status']); ?>"><?php echo e(fulfillment_label($order['fulfillment_status'])); ?></span></b></div>
                    <div><span>Total</span><b>R$ <?php echo number_format((float) $order['total'], 2, ',', '.'); ?></b></div>
                </div>
            </div>
            <div class="panel">
                <h2>Cliente</h2>
                <div class="kv">
                    <div><span>Nome</span><b><?php echo e($order['customer_name'] ?: '—'); ?></b></div>
                    <div><span>E-mail</span><b><?php echo e($order['customer_email'] ?: '—'); ?></b></div>
                    <div><span>CPF</span><b><?php echo e($order['customer_doc'] ?: '—'); ?></b></div>
                    <div><span>Entrega</span><b><?php echo e($order['shipping_address'] ?: '—'); ?></b></div>
                </div>
            </div>
            <div class="panel">
                <h2>Anotação interna</h2>
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="op" value="add_note">
                    <input type="hidden" name="id" value="<?php echo (int) $order['id']; ?>">
                    <label class="field" style="margin-bottom:10px;">
                        <textarea name="note" rows="3" placeholder="Registrar um comentário no histórico"></textarea>
                    </label>
                    <button class="btn btn-secondary btn-sm" type="submit"><?php echo ic('plus'); ?> Adicionar</button>
                </form>
            </div>
        </aside>
    </div>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

// --------------------------------------------------------------------------
// LISTA
// --------------------------------------------------------------------------
$fPay = $_GET['pay'] ?? '';
$fFul = $_GET['ful'] ?? '';
$where = [];
$params = [];
if ($fPay !== '') { $where[] = 'payment_status = ?'; $params[] = $fPay; }
if ($fFul !== '') { $where[] = 'fulfillment_status = ?'; $params[] = $fFul; }
$sql = 'SELECT * FROM orders' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$adminPageTitle = 'Pedidos';
$adminActive = 'orders';
require __DIR__ . '/_header.php';
?>
<p class="lead">Todo o ciclo do pedido: autorização do pagamento (Mercado Pago), separação do produto e envio ao cliente.</p>

<form method="get" class="filters">
    <select name="pay" onchange="this.form.submit()">
        <option value="">Pagamento: todos</option>
        <?php foreach (['pending'=>'Pendente','approved'=>'Aprovado','in_process'=>'Em análise','rejected'=>'Recusado','cancelled'=>'Cancelado','refunded'=>'Estornado'] as $k=>$v): ?>
            <option value="<?php echo $k; ?>" <?php echo $fPay === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
        <?php endforeach; ?>
    </select>
    <select name="ful" onchange="this.form.submit()">
        <option value="">Separação: todas</option>
        <?php foreach (array_merge(FULFILLMENT_FLOW, ['cancelado']) as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo $fFul === $s ? 'selected' : ''; ?>><?php echo e(fulfillment_label($s)); ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($fPay || $fFul): ?><a href="orders.php" class="btn btn-ghost btn-sm">Limpar</a><?php endif; ?>
</form>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Pedido</th><th>Cliente</th><th>Data</th><th>Total</th><th>Pagamento</th><th>Separação</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong>#<?php echo e($r['order_code']); ?></strong></td>
                <td><?php echo e($r['customer_name'] ?: '—'); ?><br><span class="hint"><?php echo e($r['customer_email']); ?></span></td>
                <td><?php echo dt($r['created_at']); ?></td>
                <td>R$ <?php echo number_format((float) $r['total'], 2, ',', '.'); ?></td>
                <td><span class="badge <?php echo pay_badge_class($r['payment_status']); ?>"><?php echo e(payment_label_ptbr($r['payment_status'])); ?></span></td>
                <td><span class="badge <?php echo ful_badge_class($r['fulfillment_status']); ?>"><?php echo e(fulfillment_label($r['fulfillment_status'])); ?></span></td>
                <td class="row-actions"><?php echo open_link('orders.php?id=' . (int) $r['id']); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">Nenhum pedido.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
