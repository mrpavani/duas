<?php
require_once __DIR__ . '/_bootstrap.php';
$admin = require_admin();
$pdo = db();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

const SIZES = ['P', 'M', 'G'];
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
    $chosenSizes = array_values(array_intersect(SIZES, (array) post('sizes', [])));
    $instagram = trim((string) post('instagram_url', ''));

    // Medidas: 1 linha por tamanho (P/M/G) + observação
    $mIn = (array) post('m', []);
    $mRows = [];
    foreach (SIZES as $sz) {
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
    if ($price === null || $price <= 0) $errors[] = 'Informe um preço válido.';
    if ($salePrice !== null && $price !== null && $salePrice >= $price) $errors[] = 'O preço promocional deve ser menor que o preço.';
    if ($saleStart && $saleEnd && $saleStart > $saleEnd) $errors[] = 'A data inicial da promoção é posterior à final.';
    if (!$chosenSizes) $errors[] = 'Selecione ao menos um tamanho.';
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

    // sincroniza tamanhos
    $pdo->prepare('DELETE FROM product_sizes WHERE product_id = ?')->execute([$pid]);
    $insSize = $pdo->prepare('INSERT INTO product_sizes (product_id, size, position) VALUES (?, ?, ?)');
    foreach ($chosenSizes as $p => $sz) $insSize->execute([$pid, $sz, $p]);

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
    $curSizes = ['P','M','G'];
    $images = [];

    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { flash_set('error', 'Produto não encontrado.'); admin_redirect('products.php'); }
        $s = $pdo->prepare('SELECT size FROM product_sizes WHERE product_id = ? ORDER BY position, id');
        $s->execute([$id]);
        $curSizes = array_column($s->fetchAll(), 'size');
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
            <input type="text" name="category" list="cats" value="<?php echo e($row['category']); ?>" required>
            <datalist id="cats">
                <option value="Vestidos"><option value="Blazers"><option value="Conjuntos"><option value="Blusas"><option value="Calças">
            </datalist>
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
            <span>Tamanhos</span>
            <span class="inline-checks">
                <?php foreach (SIZES as $sz): ?>
                    <label class="check check-sm">
                        <input type="checkbox" name="sizes[]" value="<?php echo $sz; ?>" <?php echo in_array($sz, $curSizes, true) ? 'checked' : ''; ?>>
                        <span><?php echo $sz; ?></span>
                    </label>
                <?php endforeach; ?>
            </span>
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
                        <?php
                        $mHints = [
                            'P' => ['84-88', '66-70', '94-98', '112'],
                            'M' => ['90-94', '72-76', '100-104', '113'],
                            'G' => ['96-100', '78-82', '106-110', '114'],
                        ];
                        foreach (SIZES as $sz):
                            $mr = $meas['rows'][$sz] ?? [];
                            $h = $mHints[$sz] ?? ['', '', '', ''];
                        ?>
                            <tr>
                                <td><strong><?php echo $sz; ?></strong></td>
                                <td><input type="text" name="m[<?php echo $sz; ?>][busto]" value="<?php echo e($mr['busto'] ?? ''); ?>" placeholder="<?php echo $h[0]; ?>"></td>
                                <td><input type="text" name="m[<?php echo $sz; ?>][cintura]" value="<?php echo e($mr['cintura'] ?? ''); ?>" placeholder="<?php echo $h[1]; ?>"></td>
                                <td><input type="text" name="m[<?php echo $sz; ?>][quadril]" value="<?php echo e($mr['quadril'] ?? ''); ?>" placeholder="<?php echo $h[2]; ?>"></td>
                                <td><input type="text" name="m[<?php echo $sz; ?>][comprimento]" value="<?php echo e($mr['comprimento'] ?? ''); ?>" placeholder="<?php echo $h[3]; ?>"></td>
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
           (SELECT COUNT(*) FROM product_images WHERE product_id = p.id) AS img_count
    FROM products p ORDER BY p.id DESC
')->fetchAll();

$adminPageTitle = 'Produtos';
$adminActive = 'products';
require __DIR__ . '/_header.php';
?>
<div class="list-head">
    <p class="lead">Cadastro, edição, ativação, preços, promoção por período e imagens dos produtos.</p>
    <a href="products.php?action=new" class="btn btn-primary"><?php echo ic('plus'); ?> Novo produto</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th></th><th>Produto</th><th>Categoria</th><th>Preço</th><th>Promoção</th><th>Imgs</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $now = date('Y-m-d H:i:s');
            $promoLive = $r['sale_price'] !== null
                && (empty($r['sale_starts_at']) || $now >= $r['sale_starts_at'])
                && (empty($r['sale_ends_at']) || $now <= $r['sale_ends_at']);
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
            <tr><td colspan="8" class="empty">Nenhum produto cadastrado.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
