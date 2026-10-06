<?php
require_once __DIR__ . '/_bootstrap.php';
$admin = require_admin();
$pdo = db();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

require_once __DIR__ . '/../includes/data.php';

function get_product_active_sizes(): array
{
    $list = function_exists('get_active_sizes') ? get_active_sizes() : [];
    if (!$list) {
        $list = [
            ['code' => 'PP', 'name' => 'Extra Pequeno (34)', 'busto_hint' => '80-84', 'cintura_hint' => '62-66', 'quadril_hint' => '90-94', 'comprimento_hint' => '110'],
            ['code' => 'P', 'name' => 'Pequeno (36/38)', 'busto_hint' => '84-88', 'cintura_hint' => '66-70', 'quadril_hint' => '94-98', 'comprimento_hint' => '112'],
            ['code' => 'M', 'name' => 'Médio (40)', 'busto_hint' => '90-94', 'cintura_hint' => '72-76', 'quadril_hint' => '100-104', 'comprimento_hint' => '113'],
            ['code' => 'G', 'name' => 'Grande (42)', 'busto_hint' => '96-100', 'cintura_hint' => '78-82', 'quadril_hint' => '106-110', 'comprimento_hint' => '114'],
            ['code' => 'GG', 'name' => 'Extra Grande (44)', 'busto_hint' => '102-106', 'cintura_hint' => '84-88', 'quadril_hint' => '112-116', 'comprimento_hint' => '115'],
            ['code' => 'Extra G', 'name' => 'Extra Grande Especial (46)', 'busto_hint' => '108-112', 'cintura_hint' => '90-94', 'quadril_hint' => '118-122', 'comprimento_hint' => '116'],
        ];
    }
    return $list;
}

const UPLOAD_DIR = __DIR__ . '/../uploads/products';
const UPLOAD_WEB = 'uploads/products/';
const MAX_IMG_BYTES = 3145728; // 3 MB

function admin_slugify(string $s): string
{
    $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n','&'=>'e'];
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string) $s, '-') ?: 'produto';
}

function unique_slug(PDO $pdo, string $slug, int $ignoreId): string
{
    $base = $slug; $n = 1;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetch()) return $slug;
        $slug = $base . '-' . (++$n);
    }
}

function to_datetime(?string $v): ?string
{
    $v = trim((string) $v);
    return $v === '' ? null : str_replace('T', ' ', $v) . (strlen($v) === 16 ? ':00' : '');
}
function from_datetime(?string $v): string
{
    return $v ? str_replace(' ', 'T', substr($v, 0, 16)) : '';
}
function to_decimal(?string $v): ?float
{
    $v = str_replace(['.', ' '], '', trim((string) $v));
    $v = str_replace(',', '.', $v);
    return $v === '' ? null : round((float) $v, 2);
}
function admin_img_src(string $url): string
{
    return preg_match('#^https?://#', $url) ? $url : '../' . ltrim($url, '/');
}

function handle_uploads(PDO $pdo, int $productId): int
{
    if (empty($_FILES['images']['name'][0])) return 0;
    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $pos = (int) $pdo->query('SELECT COALESCE(MAX(position), -1) + 1 FROM product_images WHERE product_id = ' . $productId)->fetchColumn();
    $done = 0;

    foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
        $err = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $orig = $_FILES['images']['name'][$i];
        if ($err !== UPLOAD_ERR_OK)                { flash_set('error', "Falha no upload de {$orig}."); continue; }
        if ($_FILES['images']['size'][$i] > MAX_IMG_BYTES) { flash_set('error', "{$orig}: acima de 3 MB."); continue; }
        $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : ($_FILES['images']['type'][$i] ?? '');
        if (!isset($allowed[$mime]))              { flash_set('error', "{$orig}: use JPG, PNG ou WEBP."); continue; }

        $fname = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, UPLOAD_DIR . '/' . $fname)) { flash_set('error', "Não foi possível salvar {$orig}."); continue; }

        $pdo->prepare('INSERT INTO product_images (product_id, image_url, position) VALUES (?, ?, ?)')
            ->execute([$productId, UPLOAD_WEB . $fname, $pos++]);
        $done++;
    }
    return $done;
}

// --------------------------------------------------------------------------
// POST
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $op = post('op');
    $pid = (int) post('id');

    if ($op === 'delete') {
        foreach ($pdo->query('SELECT image_url FROM product_images WHERE product_id = ' . $pid)->fetchAll() as $img) {
            if (!preg_match('#^https?://#', $img['image_url'])) @unlink(__DIR__ . '/../' . $img['image_url']);
        }
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$pid]);
        flash_set('success', 'Produto excluído.');
        admin_redirect('products.php');
    }

    if ($op === 'toggle') {
        $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?')->execute([$pid]);
        admin_redirect('products.php');
    }

    if ($op === 'img_delete') {
        $imgId = (int) post('image_id');
        $row = $pdo->prepare('SELECT image_url FROM product_images WHERE id = ? AND product_id = ?');
        $row->execute([$imgId, $pid]);
        if ($url = $row->fetchColumn()) {
            if (!preg_match('#^https?://#', $url)) @unlink(__DIR__ . '/../' . $url);
            $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imgId]);
            flash_set('success', 'Imagem removida.');
        }
        admin_redirect('products.php?action=edit&id=' . $pid);
    }

    if ($op === 'img_primary') {
        $imgId = (int) post('image_id');
        $pdo->prepare('UPDATE product_images SET position = position + 1 WHERE product_id = ?')->execute([$pid]);
        $pdo->prepare('UPDATE product_images SET position = 0 WHERE id = ? AND product_id = ?')->execute([$imgId, $pid]);
        // renumera
        $imgs = $pdo->prepare('SELECT id FROM product_images WHERE product_id = ? ORDER BY position, id');
        $imgs->execute([$pid]);
        $p = 0;
        foreach ($imgs->fetchAll() as $r) {
            $pdo->prepare('UPDATE product_images SET position = ? WHERE id = ?')->execute([$p++, $r['id']]);
        }
        flash_set('success', 'Imagem principal definida.');
        admin_redirect('products.php?action=edit&id=' . $pid);
    }

    // op = save
    $name = trim((string) post('name', ''));
    $slug = admin_slugify(post('slug') !== '' ? (string) post('slug') : $name);
    $category = trim((string) post('category', ''));
    $price = to_decimal(post('price'));
    $salePrice = to_decimal(post('sale_price'));
    $saleStart = to_datetime(post('sale_starts_at'));
    $saleEnd = to_datetime(post('sale_ends_at'));
    $description = trim((string) post('description', ''));
    $composition = trim((string) post('composition', ''));
    $care = trim((string) post('care_instructions', ''));
    $isNew = post('is_new_release') ? 1 : 0;
    $isActive = post('is_active') ? 1 : 0;

    $activeSizesList = get_product_active_sizes();
    $activeCodes = array_column($activeSizesList, 'code');
    $chosenSizes = array_values(array_intersect($activeCodes, (array) post('sizes', [])));
    $stockInput = (array) post('size_stock', []);
    $instagram = trim((string) post('instagram_url', ''));

    // Medidas: 1 linha por tamanho ativo + observação
    $mIn = (array) post('m', []);
    $mRows = [];
    foreach ($activeCodes as $sz) {
        $r = (array) ($mIn[$sz] ?? []);
        $line = [
            'busto'       => trim((string) ($r['busto'] ?? '')),
            'cintura'     => trim((string) ($r['cintura'] ?? '')),
            'quadril'     => trim((string) ($r['quadril'] ?? '')),
            'comprimento' => trim((string) ($r['comprimento'] ?? '')),
        ];
        if (array_filter($line)) $mRows[$sz] = $line;
    }
    $mNote = trim((string) post('measure_note', ''));
    $measurements = ($mRows || $mNote !== '')
        ? json_encode(['note' => $mNote, 'rows' => $mRows], JSON_UNESCAPED_UNICODE)
        : null;

    $errors = [];
    if ($name === '')            $errors[] = 'Informe o nome do produto.';
    if ($category === '')        $errors[] = 'Informe a categoria.';
    elseif (!in_array(mb_strtolower($category), array_map('mb_strtolower', array_column(get_categories(), 'name')), true)) {
        $errors[] = 'Categoria inválida. Cadastre-a em Configurações > Categorias de Produtos.';
    }
    if ($price === null || $price <= 0) $errors[] = 'Informe um preço válido.';
    if ($salePrice !== null && $price !== null && $salePrice >= $price) $errors[] = 'O preço promocional deve ser menor que o preço.';
    if ($saleStart && $saleEnd && $saleStart > $saleEnd) $errors[] = 'A data inicial da promoção é posterior à final.';
    if (!$chosenSizes) $errors[] = 'Selecione ao menos um tamanho e informe a quantidade disponível em estoque.';
    if ($instagram !== '' && !filter_var($instagram, FILTER_VALIDATE_URL)) $errors[] = 'O link do Instagram não é uma URL válida.';

    if ($errors) {
        foreach ($errors as $err) flash_set('error', $err);
        admin_redirect('products.php?action=' . ($pid ? 'edit&id=' . $pid : 'new'));
    }

    $slug = unique_slug($pdo, $slug, $pid);

    if ($pid > 0) {
        $pdo->prepare('UPDATE products SET name=?, slug=?, category=?, price=?, sale_price=?, sale_starts_at=?, sale_ends_at=?, description=?, composition=?, care_instructions=?, instagram_url=?, measurements=?, is_new_release=?, is_active=? WHERE id=?')
            ->execute([$name, $slug, $category, $price, $salePrice, $saleStart, $saleEnd, $description, $composition ?: null, $care ?: null, $instagram ?: null, $measurements, $isNew, $isActive, $pid]);
    } else {
        $pdo->prepare('INSERT INTO products (name, slug, category, price, sale_price, sale_starts_at, sale_ends_at, description, composition, care_instructions, instagram_url, measurements, is_new_release, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$name, $slug, $category, $price, $salePrice, $saleStart, $saleEnd, $description, $composition ?: null, $care ?: null, $instagram ?: null, $measurements, $isNew, $isActive]);
        $pid = (int) $pdo->lastInsertId();
    }

    // sincroniza tamanhos e estoque por tamanho
    $pdo->prepare('DELETE FROM product_sizes WHERE product_id = ?')->execute([$pid]);
    $insSize = $pdo->prepare('INSERT INTO product_sizes (product_id, size, stock, position) VALUES (?, ?, ?, ?)');
    foreach ($chosenSizes as $p => $sz) {
        $stk = max(0, (int) ($stockInput[$sz] ?? 0));
        $insSize->execute([$pid, $sz, $stk, $p]);
    }

    $uploaded = handle_uploads($pdo, $pid);

    flash_set('success', 'Produto salvo.' . ($uploaded ? " {$uploaded} imagem(ns) enviada(s)." : ''));
    admin_redirect('products.php?action=edit&id=' . $pid);
}

// --------------------------------------------------------------------------
// Formulário
// --------------------------------------------------------------------------
if ($action === 'new' || $action === 'edit') {
    $row = ['id'=>0,'name'=>'','slug'=>'','category'=>'','price'=>'','sale_price'=>'','sale_starts_at'=>null,
            'sale_ends_at'=>null,'description'=>'','composition'=>'','care_instructions'=>'','instagram_url'=>'',
            'measurements'=>null,'is_new_release'=>0,'is_active'=>1];
    $activeSizesList = get_product_active_sizes();
    $curSizes = ['P','M','G'];
    $curStock = ['P' => 5, 'M' => 5, 'G' => 5];
    $images = [];

    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { flash_set('error', 'Produto não encontrado.'); admin_redirect('products.php'); }
        $s = $pdo->prepare('SELECT size, stock FROM product_sizes WHERE product_id = ? ORDER BY position, id');
        $s->execute([$id]);
        $sRows = $s->fetchAll(PDO::FETCH_ASSOC);
        $curSizes = array_column($sRows, 'size');
        $curStock = [];
        foreach ($sRows as $sr) {
            $curStock[$sr['size']] = (int) $sr['stock'];
        }
        $im = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY position, id');
        $im->execute([$id]);
        $images = $im->fetchAll();
    }
    $meas = (!empty($row['measurements']) ? json_decode((string) $row['measurements'], true) : null) ?: ['note' => '', 'rows' => []];

    $adminPageTitle = $action === 'edit' ? 'Editar produto' : 'Novo produto';
    $adminActive = 'products';
    require __DIR__ . '/_header.php';
    ?>
    <p><a href="products.php" class="back-link"><?php echo ic('arrow-left', 14); ?> Voltar para produtos</a></p>

    <form method="post" enctype="multipart/form-data" class="panel form-narrow">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op" value="save">
        <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">

        <label class="field">
            <span>Nome</span>
            <input type="text" name="name" value="<?php echo e($row['name']); ?>" required>
        </label>

        <label class="field">
            <span>Slug (URL)</span>
            <input type="text" name="slug" value="<?php echo e($row['slug']); ?>" placeholder="gerado a partir do nome se vazio">
        </label>

        <label class="field">
            <span>Categoria</span>
            <?php
            $catNames = array_column(get_categories(), 'name');
            if ($row['category'] !== '' && !in_array($row['category'], $catNames, true)) $catNames[] = $row['category'];
            ?>
            <select name="category" required>
                <option value="">Selecione uma categoria</option>
                <?php foreach ($catNames as $cn): ?>
                    <option value="<?php echo e($cn); ?>" <?php echo $cn === $row['category'] ? 'selected' : ''; ?>><?php echo e($cn); ?></option>
                <?php endforeach; ?>
            </select>
            <small>Gerencie as categorias em <a href="settings.php#categorias">Configurações</a>.</small>
        </label>

        <fieldset class="subfield">
            <legend>Valores</legend>
            <div class="grid-2">
                <label class="field field-sm">
                    <span>Preço (R$)</span>
                    <input type="text" name="price" value="<?php echo e($row['price']); ?>" placeholder="790,00" required>
                </label>
                <label class="field field-sm">
                    <span>Preço promocional (R$)</span>
                    <input type="text" name="sale_price" value="<?php echo e($row['sale_price']); ?>" placeholder="opcional">
                </label>
            </div>
            <div class="grid-2">
                <label class="field field-sm">
                    <span>Promoção começa em</span>
                    <input type="datetime-local" name="sale_starts_at" value="<?php echo e(from_datetime($row['sale_starts_at'])); ?>">
                </label>
                <label class="field field-sm">
                    <span>Promoção termina em</span>
                    <input type="datetime-local" name="sale_ends_at" value="<?php echo e(from_datetime($row['sale_ends_at'])); ?>">
                </label>
            </div>
            <small>Sem datas, o preço promocional vale enquanto estiver preenchido. Com datas, só vale dentro do período.</small>
        </fieldset>

        <label class="field">
            <span>Tamanhos e Quantidade em Estoque *</span>
            <small style="margin-bottom: 8px;">Marque os tamanhos fabricados e informe a quantidade disponível em estoque de cada tamanho. Se um tamanho tiver 0 unidades, o anúncio na loja mudará automaticamente para o botão <strong>"Avise-me quando chegar"</strong>.</small>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 6px;">
                <?php foreach ($activeSizesList as $sObj): ?>
                    <?php
                    $sz = $sObj['code'];
                    $isChecked = in_array($sz, $curSizes, true);
                    $stk = $curStock[$sz] ?? ($isChecked ? 5 : 0);
                    ?>
                    <div style="background:#fcfbf9; border:1px solid <?php echo $isChecked ? '#121212' : 'var(--a-border)'; ?>; border-radius:6px; padding:10px; display:flex; flex-direction:column; gap:6px;">
                        <label class="check" style="margin:0; font-weight:600;">
                            <input type="checkbox" name="sizes[]" value="<?php echo e($sz); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                            <span><?php echo e($sz); ?> <small style="display:inline; color:var(--a-muted); font-weight:normal;">(<?php echo e($sObj['name']); ?>)</small></span>
                        </label>
                        <div style="display:flex; align-items:center; gap:6px; padding-left:22px;">
                            <span style="font-size:0.78rem; color:var(--a-muted);">Estoque:</span>
                            <input type="number" min="0" name="size_stock[<?php echo e($sz); ?>]" value="<?php echo (int) $stk; ?>" style="width:70px; padding:3px 6px; font-size:0.85rem; border:1px solid var(--a-border); border-radius:4px; text-align:center;">
                            <span style="font-size:0.78rem; color:var(--a-muted);">un</span>
                            <?php if ($isChecked && (int)$stk === 0): ?>
                                <span class="badge badge-err" style="font-size:0.65rem;">Sem estoque</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:6px; font-size:0.8rem; color:var(--a-muted);">
                Para gerenciar os tamanhos de mercado (PP, P, M, G, GG, Extra G, etc.), acesse <a href="settings.php#tamanhos" target="_blank" style="text-decoration:underline;">Configurações &rarr; Grade de Tamanhos</a>.
            </div>
        </label>

        <label class="field">
            <span>Descrição</span>
            <textarea name="description" rows="4"><?php echo e($row['description']); ?></textarea>
        </label>

        <label class="field">
            <span>Composição</span>
            <input type="text" name="composition" value="<?php echo e($row['composition']); ?>">
        </label>

        <label class="field">
            <span>Cuidados com a peça</span>
            <input type="text" name="care_instructions" value="<?php echo e($row['care_instructions']); ?>">
        </label>

        <label class="field">
            <span>Link do Instagram da peça</span>
            <input type="url" name="instagram_url" value="<?php echo e($row['instagram_url']); ?>" placeholder="https://www.instagram.com/p/...">
            <small>Post ou reel do produto. Aparece como "Ver no Instagram" na página da peça.</small>
        </label>

        <fieldset class="subfield">
            <legend>Informações das medidas</legend>
            <div class="table-wrap" style="border:0;">
                <table class="measure-form">
                    <thead>
                        <tr><th>Tam.</th><th>Busto (cm)</th><th>Cintura (cm)</th><th>Quadril (cm)</th><th>Comprimento (cm)</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeSizesList as $sObj): ?>
                            <?php
                            $sz = $sObj['code'];
                            $mr = $meas['rows'][$sz] ?? [];
                            ?>
                            <tr>
                                <td><strong><?php echo e($sz); ?></strong></td>
                                <td><input type="text" name="m[<?php echo e($sz); ?>][busto]" value="<?php echo e($mr['busto'] ?? ''); ?>" placeholder="<?php echo e($sObj['busto_hint'] ?? ''); ?>"></td>
                                <td><input type="text" name="m[<?php echo e($sz); ?>][cintura]" value="<?php echo e($mr['cintura'] ?? ''); ?>" placeholder="<?php echo e($sObj['cintura_hint'] ?? ''); ?>"></td>
                                <td><input type="text" name="m[<?php echo e($sz); ?>][quadril]" value="<?php echo e($mr['quadril'] ?? ''); ?>" placeholder="<?php echo e($sObj['quadril_hint'] ?? ''); ?>"></td>
                                <td><input type="text" name="m[<?php echo e($sz); ?>][comprimento]" value="<?php echo e($mr['comprimento'] ?? ''); ?>" placeholder="<?php echo e($sObj['comprimento_hint'] ?? ''); ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <label class="field" style="margin-top:12px;">
                <span>Observação de vestibilidade (opcional)</span>
                <textarea name="measure_note" rows="2" placeholder="Ex: A modelo veste P e tem 1,75m. Modelagem ampla."><?php echo e($meas['note'] ?? ''); ?></textarea>
            </label>
            <small>Deixe as linhas em branco se não quiser exibir tabela de medidas.</small>
        </fieldset>

        <div class="grid-2">
            <label class="check"><input type="checkbox" name="is_active" value="1" <?php echo (int) $row['is_active'] === 1 ? 'checked' : ''; ?>><span>Produto ativo (visível na loja)</span></label>
            <label class="check"><input type="checkbox" name="is_new_release" value="1" <?php echo (int) $row['is_new_release'] === 1 ? 'checked' : ''; ?>><span>Marcar como lançamento</span></label>
        </div>

        <fieldset class="subfield">
            <legend>Imagens</legend>
            <?php if ($images): ?>
                <div class="img-grid">
                    <?php foreach ($images as $img): ?>
                        <div class="img-cell">
                            <img src="<?php echo e(admin_img_src($img['image_url'])); ?>" alt="">
                            <?php if ((int) $img['position'] === 0): ?><span class="img-tag">Principal</span><?php endif; ?>
                            <div class="img-cell-actions">
                                <?php if ((int) $img['position'] !== 0): ?>
                                <form method="post" class="inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="op" value="img_primary">
                                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                    <input type="hidden" name="image_id" value="<?php echo (int) $img['id']; ?>">
                                    <button type="submit">Tornar principal</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" class="inline" onsubmit="return confirm('Remover imagem?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="op" value="img_delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                    <input type="hidden" name="image_id" value="<?php echo (int) $img['id']; ?>">
                                    <button type="submit" class="link-danger" title="Remover imagem"><?php echo ic('trash', 13); ?></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($action === 'edit'): ?>
                <p class="hint">Nenhuma imagem ainda.</p>
            <?php else: ?>
                <p class="hint">Salve o produto para habilitar o envio de imagens, ou envie já abaixo.</p>
            <?php endif; ?>

            <label class="field" style="margin-top:12px;">
                <span>Enviar imagens (JPG, PNG ou WEBP &mdash; até 3 MB cada)</span>
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
            </label>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo ic('check'); ?> Salvar produto</button>
            <a href="products.php" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>

    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

// --------------------------------------------------------------------------
// Listagem
// --------------------------------------------------------------------------
$rows = $pdo->query('
    SELECT p.*,
           (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY position, id LIMIT 1) AS thumb,
           (SELECT COUNT(*) FROM product_images WHERE product_id = p.id) AS img_count,
           (SELECT COALESCE(SUM(stock), 0) FROM product_sizes WHERE product_id = p.id) AS total_stock
    FROM products p ORDER BY p.id DESC
')->fetchAll();

// Mapa de tamanhos e estoque por produto
$psMap = [];
$psStmt = $pdo->query('SELECT product_id, size, stock FROM product_sizes ORDER BY product_id, position, id');
foreach ($psStmt->fetchAll() as $ps) {
    $psMap[$ps['product_id']][] = $ps;
}

$adminPageTitle = 'Produtos';
$adminActive = 'products';
require __DIR__ . '/_header.php';
?>
<div class="list-head">
    <p class="lead">Cadastro, edição, ativação, preços, promoção por período, estoque por tamanho e imagens dos produtos.</p>
    <a href="products.php?action=new" class="btn btn-primary"><?php echo ic('plus'); ?> Novo produto</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th></th><th>Produto</th><th>Categoria</th><th>Preço</th><th>Promoção</th><th>Estoque</th><th>Imgs</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $now = date('Y-m-d H:i:s');
            $promoLive = $r['sale_price'] !== null
                && (empty($r['sale_starts_at']) || $now >= $r['sale_starts_at'])
                && (empty($r['sale_ends_at']) || $now <= $r['sale_ends_at']);
            $totStk = (int) ($r['total_stock'] ?? 0);
            $sizesList = $psMap[$r['id']] ?? [];
            ?>
            <tr>
                <td>
                    <?php if ($r['thumb']): ?>
                        <img class="row-thumb" src="<?php echo e(admin_img_src($r['thumb'])); ?>" alt="">
                    <?php else: ?>
                        <span class="row-thumb row-thumb-empty">—</span>
                    <?php endif; ?>
                </td>
                <td><strong><?php echo e($r['name']); ?></strong><br><span class="hint"><?php echo e($r['slug']); ?></span></td>
                <td><?php echo e($r['category']); ?></td>
                <td>R$ <?php echo number_format((float) $r['price'], 2, ',', '.'); ?></td>
                <td>
                    <?php if ($r['sale_price'] !== null): ?>
                        R$ <?php echo number_format((float) $r['sale_price'], 2, ',', '.'); ?>
                        <span class="badge <?php echo $promoLive ? 'badge-on' : 'badge-off'; ?>"><?php echo $promoLive ? 'no ar' : 'agendada/off'; ?></span>
                    <?php else: ?>
                        <span class="hint">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($totStk <= 0): ?>
                        <span class="badge badge-err">Esgotado (0 un)</span>
                    <?php else: ?>
                        <span class="badge badge-on">Estoque: <?php echo $totStk; ?> un</span>
                    <?php endif; ?>
                    <?php if ($sizesList): ?>
                        <div style="font-size:0.75rem; color:var(--a-muted); margin-top:3px; line-height:1.2;">
                            <?php
                            $chips = [];
                            foreach ($sizesList as $szItem) {
                                $chips[] = '<strong>' . e($szItem['size']) . '</strong>: ' . (int)$szItem['stock'];
                            }
                            echo implode(' &bull; ', $chips);
                            ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td><?php echo (int) $r['img_count']; ?></td>
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
                    <?php echo edit_link('products.php?action=edit&id=' . (int) $r['id']); ?>
                    <form method="post" onsubmit="return confirm('Excluir este produto e suas imagens?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="op" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                        <button type="submit" class="btn-icon is-danger" title="Excluir"><?php echo ic('trash'); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="9" class="empty">Nenhum produto cadastrado.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
