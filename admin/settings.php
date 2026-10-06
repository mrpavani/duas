<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/data.php';
$admin = require_admin();
$pdo = db();

// --------------------------------------------------------------------------
// Processamento POST
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $op = post('op', 'save_announcement');

    // 1. Salvar barra de aviso
    if ($op === 'save_announcement') {
        $text = trim((string) post('announcement_text', ''));
        if (mb_strlen($text) > 240) $text = mb_substr($text, 0, 240);
        $active = post('announcement_active') ? '1' : '0';

        set_setting('announcement_text', $text);
        set_setting('announcement_active', $active);

        flash_set('success', 'Barra de aviso atualizada com sucesso.');
        admin_redirect('settings.php#announcement');
    }

    // 2. Salvar grade de tamanhos (em lote)
    if ($op === 'save_sizes') {
        $activeSizes = (array) post('active_sizes', []);
        $sizesData = (array) post('sizes', []);

        $updStmt = $pdo->prepare("
            UPDATE `sizes`
            SET `is_active` = :is_active,
                `name` = :name,
                `busto_hint` = :busto,
                `cintura_hint` = :cintura,
                `quadril_hint` = :quadril,
                `comprimento_hint` = :comp,
                `sort_order` = :sort
            WHERE `id` = :id
        ");

        foreach ($sizesData as $id => $data) {
            $id = (int) $id;
            $isActive = in_array((string) $id, $activeSizes, true) ? 1 : 0;
            $name = trim((string) ($data['name'] ?? ''));
            $busto = trim((string) ($data['busto'] ?? ''));
            $cintura = trim((string) ($data['cintura'] ?? ''));
            $quadril = trim((string) ($data['quadril'] ?? ''));
            $comp = trim((string) ($data['comprimento'] ?? ''));
            $sort = (int) ($data['sort'] ?? 0);

            $updStmt->execute([
                ':is_active' => $isActive,
                ':name' => $name,
                ':busto' => $busto,
                ':cintura' => $cintura,
                ':quadril' => $quadril,
                ':comp' => $comp,
                ':sort' => $sort,
                ':id' => $id,
            ]);
        }

        flash_set('success', 'Grade de tamanhos e quadro de medidas atualizados com sucesso.');
        admin_redirect('settings.php#tamanhos');
    }

    // 3. Alternar status rápido (toggle)
    if ($op === 'toggle_size') {
        $sizeId = (int) post('size_id', 0);
        $stmt = $pdo->prepare("SELECT is_active, code FROM `sizes` WHERE id = ?");
        $stmt->execute([$sizeId]);
        $row = $stmt->fetch();
        if ($row) {
            $newStatus = (int) $row['is_active'] === 1 ? 0 : 1;
            $pdo->prepare("UPDATE `sizes` SET is_active = ? WHERE id = ?")->execute([$newStatus, $sizeId]);
            flash_set('success', 'Tamanho ' . e($row['code']) . ' ' . ($newStatus ? 'ativado' : 'desativado') . '.');
        }
        admin_redirect('settings.php#tamanhos');
    }

    // 4. Cadastrar novo tamanho
    if ($op === 'add_size') {
        $code = trim((string) post('new_code', ''));
        $name = trim((string) post('new_name', ''));
        $cat = trim((string) post('new_category', 'letra'));
        $busto = trim((string) post('new_busto', ''));
        $cintura = trim((string) post('new_cintura', ''));
        $quadril = trim((string) post('new_quadril', ''));
        $comp = trim((string) post('new_comprimento', ''));
        $isActive = post('new_active') ? 1 : 0;
        $sort = (int) post('new_sort', 100);

        if ($code === '') {
            flash_set('error', 'Informe o código/sigla do tamanho (ex: GG, XG, 38, etc).');
        } else {
            // Verificar duplicidade
            $stmt = $pdo->prepare("SELECT id FROM `sizes` WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                flash_set('error', "O tamanho '{$code}' já existe na lista.");
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO `sizes` (`code`, `name`, `category`, `busto_hint`, `cintura_hint`, `quadril_hint`, `comprimento_hint`, `is_active`, `sort_order`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$code, $name ?: $code, $cat, $busto, $cintura, $quadril, $comp, $isActive, $sort]);
                flash_set('success', "Tamanho '{$code}' cadastrado com sucesso.");
            }
        }
        admin_redirect('settings.php#tamanhos');
    }

    // 5. Excluir tamanho
    if ($op === 'delete_size') {
        $delId = (int) post('size_id', 0);
        $stmt = $pdo->prepare("SELECT code FROM `sizes` WHERE id = ?");
        $stmt->execute([$delId]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("DELETE FROM `sizes` WHERE id = ?")->execute([$delId]);
            flash_set('success', "Tamanho '{$row['code']}' removido.");
        }
        admin_redirect('settings.php#tamanhos');
    }

    // 6. Categorias de produto: cadastrar
    if ($op === 'add_category') {
        ensure_categories_table();
        $name = trim((string) post('category_name', ''));
        $sort = (int) post('category_sort', 0);
        if ($name === '') {
            flash_set('error', 'Informe o nome da categoria.');
        } elseif (mb_strlen($name) > 120) {
            flash_set('error', 'O nome da categoria deve ter no máximo 120 caracteres.');
        } else {
            $dup = $pdo->prepare('SELECT id FROM categories WHERE name = ?');
            $dup->execute([$name]);
            if ($dup->fetch()) {
                flash_set('error', "A categoria '{$name}' já existe.");
            } else {
                $pdo->prepare('INSERT INTO categories (name, sort_order) VALUES (?, ?)')->execute([$name, $sort]);
                flash_set('success', "Categoria '{$name}' cadastrada.");
            }
        }
        admin_redirect('settings.php#categorias');
    }

    // 7. Categorias de produto: editar (renomeia também nos produtos vinculados)
    if ($op === 'edit_category') {
        ensure_categories_table();
        $catId = (int) post('category_id', 0);
        $name = trim((string) post('category_name', ''));
        $sort = (int) post('category_sort', 0);
        $cur = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
        $cur->execute([$catId]);
        $oldName = $cur->fetchColumn();
        if ($oldName === false) {
            flash_set('error', 'Categoria não encontrada.');
        } elseif ($name === '' || mb_strlen($name) > 120) {
            flash_set('error', 'Informe um nome de categoria válido (até 120 caracteres).');
        } else {
            $dup = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND id <> ?');
            $dup->execute([$name, $catId]);
            if ($dup->fetch()) {
                flash_set('error', "Já existe outra categoria chamada '{$name}'.");
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE categories SET name = ?, sort_order = ? WHERE id = ?')->execute([$name, $sort, $catId]);
                    if ($name !== $oldName) {
                        $pdo->prepare('UPDATE products SET category = ? WHERE category COLLATE utf8mb4_unicode_ci = ?')
                            ->execute([$name, $oldName]);
                    }
                    $pdo->commit();
                    flash_set('success', "Categoria '{$name}' atualizada.");
                } catch (Throwable $ex) {
                    $pdo->rollBack();
                    flash_set('error', 'Não foi possível atualizar a categoria.');
                }
            }
        }
        admin_redirect('settings.php#categorias');
    }

    // 8. Categorias de produto: excluir (somente sem produto vinculado)
    if ($op === 'delete_category') {
        ensure_categories_table();
        $catId = (int) post('category_id', 0);
        $cur = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
        $cur->execute([$catId]);
        $catName = $cur->fetchColumn();
        if ($catName === false) {
            flash_set('error', 'Categoria não encontrada.');
        } else {
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category COLLATE utf8mb4_unicode_ci = ?');
            $cnt->execute([$catName]);
            $linked = (int) $cnt->fetchColumn();
            if ($linked > 0) {
                flash_set('error', "A categoria '{$catName}' não pode ser excluída: há {$linked} produto(s) vinculado(s). Mova ou exclua os produtos primeiro.");
            } else {
                $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$catId]);
                flash_set('success', "Categoria '{$catName}' excluída.");
            }
        }
        admin_redirect('settings.php#categorias');
    }
}

// --------------------------------------------------------------------------
// Dados para exibição
// --------------------------------------------------------------------------
$text = (string) get_setting('announcement_text', '');
$active = get_setting('announcement_active', '1') === '1';

// Carregar todos os tamanhos cadastrados
$allSizes = $pdo->query("SELECT * FROM `sizes` ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$adminPageTitle = 'Configurações da Loja';
$adminActive = 'settings';
require __DIR__ . '/_header.php';
?>

<div class="list-head" style="margin-bottom: 24px;">
    <div>
        <p class="lead" style="margin-bottom: 0;">Gerencie avisos de topo, a grade de tamanhos do mercado e o quadro de medidas exibido para os clientes.</p>
    </div>
</div>

<!-- ======================================================================== -->
<!-- SEÇÃO 1: GRADE DE TAMANHOS & QUADRO DE MEDIDAS                           -->
<!-- ======================================================================== -->
<div id="tamanhos" style="margin-bottom: 40px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight:600; display:flex; align-items:center; gap:8px;">
                <?php echo ic('settings'); ?> Grade de Tamanhos & Quadro de Medidas
            </h2>
            <p style="color:var(--a-muted); font-size:0.86rem; margin-top:4px;">
                Ative ou desative os tamanhos existentes no mercado (PP, P, M, G, GG, Extra G, Plus Size, Numéricos).
                Os tamanhos marcados como <strong>Ativo</strong> ficam disponíveis na edição de produtos e montam automaticamente o <strong>Quadro Geral de Medidas</strong> da loja.
            </p>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addSizeBox').toggleAttribute('hidden');">
            <?php echo ic('plus'); ?> Novo Tamanho
        </button>
    </div>

    <!-- Box: Cadastrar Novo Tamanho (oculto por padrão) -->
    <div id="addSizeBox" hidden class="panel" style="background:#faf9f7; border: 1px dashed var(--a-border); margin-bottom:20px; padding:20px;">
        <h3 style="font-size:1rem; margin-bottom:12px;">Cadastrar Novo Tamanho</h3>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="op" value="add_size">

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:12px; margin-bottom:14px;">
                <label class="field" style="margin:0;">
                    <span>Sigla / Código *</span>
                    <input type="text" name="new_code" placeholder="Ex: G4, 54, Sob Medida" required>
                </label>
                <label class="field" style="margin:0;">
                    <span>Nome / Descrição</span>
                    <input type="text" name="new_name" placeholder="Ex: Plus Size 54">
                </label>
                <label class="field" style="margin:0;">
                    <span>Tipo</span>
                    <select name="new_category">
                        <option value="letra">Letra / Alfafanumérico</option>
                        <option value="numero">Numérico</option>
                    </select>
                </label>
                <label class="field" style="margin:0;">
                    <span>Busto ref. (cm)</span>
                    <input type="text" name="new_busto" placeholder="Ex: 132-136">
                </label>
                <label class="field" style="margin:0;">
                    <span>Cintura ref. (cm)</span>
                    <input type="text" name="new_cintura" placeholder="Ex: 114-118">
                </label>
                <label class="field" style="margin:0;">
                    <span>Quadril ref. (cm)</span>
                    <input type="text" name="new_quadril" placeholder="Ex: 142-146">
                </label>
                <label class="field" style="margin:0;">
                    <span>Comp. ref. (cm)</span>
                    <input type="text" name="new_comprimento" placeholder="Ex: 120">
                </label>
                <label class="field" style="margin:0;">
                    <span>Ordem</span>
                    <input type="number" name="new_sort" value="100">
                </label>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center;">
                <label class="check" style="margin:0;">
                    <input type="checkbox" name="new_active" value="1" checked>
                    <span>Já iniciar como ativo na loja</span>
                </label>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('addSizeBox').hidden = true;">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><?php echo ic('check'); ?> Salvar Tamanho</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Formulário em lote para Salvar a Grade e Medidas de Referência -->
    <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op" value="save_sizes">

        <div class="table-wrap" style="margin-bottom: 16px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 70px; text-align:center;">Ativo</th>
                        <th style="width: 90px;">Código</th>
                        <th>Nome / Descrição</th>
                        <th style="width: 90px;">Tipo</th>
                        <th style="width: 100px;">Busto (cm)</th>
                        <th style="width: 100px;">Cintura (cm)</th>
                        <th style="width: 100px;">Quadril (cm)</th>
                        <th style="width: 100px;">Comp. (cm)</th>
                        <th style="width: 70px;">Ordem</th>
                        <th style="width: 80px; text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allSizes)): ?>
                        <tr><td colspan="10" class="empty">Nenhum tamanho cadastrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($allSizes as $sz): ?>
                            <tr style="<?php echo (int)$sz['is_active'] === 1 ? '' : 'opacity: 0.65; background:#fafafa;'; ?>">
                                <!-- Checkbox Ativo -->
                                <td style="text-align:center;">
                                    <input type="checkbox" name="active_sizes[]" value="<?php echo (int) $sz['id']; ?>"
                                           <?php echo (int) $sz['is_active'] === 1 ? 'checked' : ''; ?>
                                           style="width:17px; height:17px; cursor:pointer;"
                                           title="Marque para ativar este tamanho na loja">
                                </td>

                                <!-- Código em destaque -->
                                <td>
                                    <strong style="font-size:0.95rem; letter-spacing:0.02em;"><?php echo e($sz['code']); ?></strong>
                                    <?php if ((int)$sz['is_active'] === 1): ?>
                                        <span class="badge badge-on" style="display:block; font-size:0.65rem; margin-top:2px;">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-off" style="display:block; font-size:0.65rem; margin-top:2px;">Inativo</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Nome Descritivo editável -->
                                <td>
                                    <input type="text" name="sizes[<?php echo (int)$sz['id']; ?>][name]" value="<?php echo e($sz['name']); ?>"
                                           style="width:100%; font-size:0.85rem; padding:5px 8px; border:1px solid var(--a-border); border-radius:4px;">
                                </td>

                                <!-- Tipo -->
                                <td>
                                    <span style="font-size:0.78rem; text-transform:capitalize; color:var(--a-muted);">
                                        <?php echo $sz['category'] === 'numero' ? 'Numérico' : 'Letra'; ?>
                                    </span>
                                </td>

                                <!-- Busto -->
                                <td>
                                    <input type="text" name="sizes[<?php echo (int)$sz['id']; ?>][busto]" value="<?php echo e($sz['busto_hint']); ?>"
                                           placeholder="Ex: 84-88" style="width:100%; font-size:0.82rem; padding:4px 6px; border:1px solid var(--a-border); border-radius:4px;">
                                </td>

                                <!-- Cintura -->
                                <td>
                                    <input type="text" name="sizes[<?php echo (int)$sz['id']; ?>][cintura]" value="<?php echo e($sz['cintura_hint']); ?>"
                                           placeholder="Ex: 66-70" style="width:100%; font-size:0.82rem; padding:4px 6px; border:1px solid var(--a-border); border-radius:4px;">
                                </td>

                                <!-- Quadril -->
                                <td>
                                    <input type="text" name="sizes[<?php echo (int)$sz['id']; ?>][quadril]" value="<?php echo e($sz['quadril_hint']); ?>"
                                           placeholder="Ex: 94-98" style="width:100%; font-size:0.82rem; padding:4px 6px; border:1px solid var(--a-border); border-radius:4px;">
                                </td>

                                <!-- Comprimento -->
                                <td>
                                    <input type="text" name="sizes[<?php echo (int)$sz['id']; ?>][comprimento]" value="<?php echo e($sz['comprimento_hint']); ?>"
                                           placeholder="Ex: 112" style="width:100%; font-size:0.82rem; padding:4px 6px; border:1px solid var(--a-border); border-radius:4px;">
                                </td>

                                <!-- Sort Order -->
                                <td>
                                    <input type="number" name="sizes[<?php echo (int)$sz['id']; ?>][sort]" value="<?php echo (int)$sz['sort_order']; ?>"
                                           style="width:55px; font-size:0.82rem; padding:4px 6px; border:1px solid var(--a-border); border-radius:4px; text-align:center;">
                                </td>

                                <!-- Ações -->
                                <td class="row-actions">
                                    <button type="submit" form="delSizeForm<?php echo (int)$sz['id']; ?>" class="btn-icon is-danger"
                                            title="Excluir tamanho" onclick="return confirm('Deseja realmente remover o tamanho <?php echo e($sz['code']); ?>?');">
                                        <?php echo ic('trash', 14); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
            <div style="font-size:0.85rem; color:var(--a-muted);">
                💡 Dica: Marque ou desmarque a caixa <strong>Ativo</strong> e clique no botão ao lado para salvar todas as alterações da grade.
            </div>
            <button type="submit" class="btn btn-primary">
                <?php echo ic('check'); ?> Salvar Alterações da Grade de Tamanhos
            </button>
        </div>
    </form>

    <!-- Formulários ocultos para exclusão de cada linha -->
    <?php foreach ($allSizes as $sz): ?>
        <form id="delSizeForm<?php echo (int)$sz['id']; ?>" method="post" style="display:none;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="op" value="delete_size">
            <input type="hidden" name="size_id" value="<?php echo (int)$sz['id']; ?>">
        </form>
    <?php endforeach; ?>

    <!-- Prévia do Quadro Geral de Medidas (Como reflete na loja) -->
    <div class="panel" style="margin-top: 24px; background: #ffffff;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <div>
                <h3 style="font-size: 1.05rem; font-weight:600;">👁️ Prévia do Quadro de Medidas na Loja (Guia de Medidas)</h3>
                <p style="font-size:0.82rem; color:var(--a-muted); margin-top:2px;">
                    Este é o quadro exato exibido para os clientes ao clicarem em "Guia de Medidas" na loja e no rodapé. Ele atualiza automaticamente com os tamanhos ativos acima.
                </p>
            </div>
            <span class="badge badge-info">Reflete em tempo real</span>
        </div>

        <?php
        $activePreview = array_filter($allSizes, fn($s) => (int)$s['is_active'] === 1);
        ?>
        <?php if (empty($activePreview)): ?>
            <div style="padding:20px; text-align:center; color:var(--a-muted); background:#faf9f7; border-radius:4px;">
                Nenhum tamanho está ativo no momento. Ative ao menos um tamanho na tabela acima.
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table" style="font-size:0.84rem;">
                    <thead>
                        <tr style="background:#f7f6f4;">
                            <th>Tamanho</th>
                            <th>Busto (cm)</th>
                            <th>Cintura (cm)</th>
                            <th>Quadril (cm)</th>
                            <th>Comprimento (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activePreview as $ps): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($ps['code']); ?></strong>
                                    <span style="color:var(--a-muted); font-size:0.8rem; margin-left:4px;">(<?php echo e($ps['name']); ?>)</span>
                                </td>
                                <td><?php echo e($ps['busto_hint']) ?: '&mdash;'; ?></td>
                                <td><?php echo e($ps['cintura_hint']) ?: '&mdash;'; ?></td>
                                <td><?php echo e($ps['quadril_hint']) ?: '&mdash;'; ?></td>
                                <td><?php echo e($ps['comprimento_hint']) ?: '&mdash;'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<hr style="border:0; border-top:1px solid var(--a-border); margin: 36px 0;">

<!-- ======================================================================== -->
<!-- SEÇÃO: CATEGORIAS DE PRODUTOS                                             -->
<!-- ======================================================================== -->
<?php $allCategories = get_categories(); ?>
<div id="categorias" style="margin-bottom: 40px;">
    <h2 style="font-size: 1.25rem; font-weight:600; margin-bottom:6px;">Categorias de Produtos</h2>
    <p style="color:var(--a-muted); font-size:0.86rem; margin-bottom:16px;">
        Cadastre, edite e exclua as categorias usadas nos produtos e no menu da loja.
        Uma categoria só pode ser excluída quando <strong>não há nenhum produto vinculado</strong> a ela.
    </p>

    <form method="post" class="panel" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin-bottom:16px; padding:16px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op" value="add_category">
        <label class="field" style="margin:0; flex:1; min-width:220px;">
            <span>Nova categoria</span>
            <input type="text" name="category_name" maxlength="120" placeholder="Ex: Saias" required>
        </label>
        <label class="field" style="margin:0; width:90px;">
            <span>Ordem</span>
            <input type="number" name="category_sort" value="0">
        </label>
        <button type="submit" class="btn btn-primary btn-sm"><?php echo ic('plus'); ?> Cadastrar</button>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th style="width:90px;">Ordem</th>
                    <th style="width:150px;">Produtos</th>
                    <th style="width:160px; text-align:right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allCategories as $cat): ?>
                    <tr>
                        <td>
                            <input type="text" name="category_name" form="catEdit<?php echo $cat['id']; ?>" value="<?php echo e($cat['name']); ?>" maxlength="120" required
                                   style="width:100%; font-size:0.88rem; padding:5px 8px; border:1px solid var(--a-border); border-radius:4px;">
                        </td>
                        <td>
                            <input type="number" name="category_sort" form="catEdit<?php echo $cat['id']; ?>" value="<?php echo (int) $cat['sort_order']; ?>"
                                   style="width:65px; font-size:0.85rem; padding:4px 6px; border:1px solid var(--a-border); border-radius:4px; text-align:center;">
                        </td>
                        <td>
                            <?php echo (int) $cat['products']; ?> vinculado(s)
                            <?php if ($cat['products'] > 0): ?>
                                <span class="hint" style="display:block;"><?php echo (int) $cat['in_stock']; ?> em estoque</span>
                            <?php endif; ?>
                        </td>
                        <td class="row-actions">
                            <button type="submit" form="catEdit<?php echo $cat['id']; ?>" class="btn btn-secondary btn-sm"><?php echo ic('check', 14); ?> Salvar</button>
                            <?php if ($cat['products'] === 0): ?>
                                <button type="submit" form="catDel<?php echo $cat['id']; ?>" class="btn-icon is-danger" title="Excluir categoria"
                                        onclick="return confirm('Excluir a categoria <?php echo e(addslashes($cat['name'])); ?>?');"><?php echo ic('trash', 14); ?></button>
                            <?php else: ?>
                                <span class="btn-icon" title="Exclusão bloqueada: há produtos vinculados" style="opacity:.35; cursor:not-allowed;"><?php echo ic('trash', 14); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$allCategories): ?>
                    <tr><td colspan="4" class="empty">Nenhuma categoria cadastrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php foreach ($allCategories as $cat): ?>
        <form id="catEdit<?php echo $cat['id']; ?>" method="post" style="display:none;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="op" value="edit_category">
            <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
        </form>
        <form id="catDel<?php echo $cat['id']; ?>" method="post" style="display:none;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="op" value="delete_category">
            <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
        </form>
    <?php endforeach; ?>
</div>

<hr style="border:0; border-top:1px solid var(--a-border); margin: 36px 0;">

<!-- ======================================================================== -->
<!-- SEÇÃO 2: BARRA DE AVISO (TOPO DO SITE)                                    -->
<!-- ======================================================================== -->
<div id="announcement">
    <h2 style="font-size: 1.25rem; font-weight:600; margin-bottom:6px;">Barra de Aviso (topo do site)</h2>
    <p style="color:var(--a-muted); font-size:0.86rem; margin-bottom:16px;">Texto em destaque exibido no topo de todas as páginas da loja.</p>

    <form method="post" class="panel form-narrow">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op" value="save_announcement">

        <label class="field">
            <span>Mensagem</span>
            <textarea name="announcement_text" id="annText" rows="2" maxlength="240"
                      placeholder="Ex: FRETE GRÁTIS ACIMA DE R$ 800 • ATÉ 6X SEM JUROS"><?php echo e($text); ?></textarea>
            <small>Use <code>&bull;</code> para separar avisos. Máximo de 240 caracteres.</small>
        </label>

        <label class="check">
            <input type="checkbox" name="announcement_active" value="1" <?php echo $active ? 'checked' : ''; ?>>
            <span>Exibir a barra de aviso na loja</span>
        </label>

        <div style="margin: 4px 0 18px;">
            <span class="hint" style="display:block; margin-bottom:6px;">Prévia da barra:</span>
            <div class="preview-bar"><span id="annPreview"><?php echo e($text) ?: 'A barra ficará vazia'; ?></span></div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo ic('check'); ?> Salvar Barra de Aviso</button>
            <a href="../index.php" target="_blank" rel="noopener" class="btn btn-ghost"><?php echo ic('external'); ?> Ver na loja</a>
        </div>
    </form>
</div>

<script>
(function () {
    var t = document.getElementById('annText'), p = document.getElementById('annPreview');
    if (t && p) t.addEventListener('input', function () { p.textContent = t.value || 'A barra ficará vazia'; });
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
