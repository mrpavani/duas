<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
$admin = require_admin();
$pdo = db();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

const BLOG_UPLOAD_DIR = __DIR__ . '/../uploads/blog';
const BLOG_UPLOAD_WEB = 'uploads/blog/';
const BLOG_MAX_BYTES = 4194304; // 4 MB

function b_slugify(string $s): string
{
    $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n','&'=>'e'];
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string) $s, '-') ?: 'post';
}
function b_unique_slug(PDO $pdo, string $slug, int $ignoreId): string
{
    $base = $slug; $n = 1;
    while (true) {
        $st = $pdo->prepare('SELECT id FROM blog_posts WHERE slug = ? AND id <> ? LIMIT 1');
        $st->execute([$slug, $ignoreId]);
        if (!$st->fetch()) return $slug;
        $slug = $base . '-' . (++$n);
    }
}
function b_img_src(?string $url): string
{
    if (!$url) return '';
    return preg_match('#^https?://#', $url) ? $url : '../' . ltrim($url, '/');
}
function b_handle_cover(): ?string
{
    if (empty($_FILES['cover']['name']) || ($_FILES['cover']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($_FILES['cover']['error'] !== UPLOAD_ERR_OK) { flash_set('error', 'Falha no upload da capa.'); return null; }
    if ($_FILES['cover']['size'] > BLOG_MAX_BYTES)   { flash_set('error', 'A capa passa de 4 MB.'); return null; }
    $mime = function_exists('mime_content_type') ? mime_content_type($_FILES['cover']['tmp_name']) : ($_FILES['cover']['type'] ?? '');
    if (!isset($allowed[$mime]))                     { flash_set('error', 'Capa: use JPG, PNG ou WEBP.'); return null; }
    if (!is_dir(BLOG_UPLOAD_DIR)) @mkdir(BLOG_UPLOAD_DIR, 0775, true);
    $fname = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($_FILES['cover']['tmp_name'], BLOG_UPLOAD_DIR . '/' . $fname)) {
        flash_set('error', 'Não foi possível salvar a capa.');
        return null;
    }
    return BLOG_UPLOAD_WEB . $fname;
}

// --------------------------------------------------------------------------
// POST
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $op = post('op');
    $pid = (int) post('id');

    if ($op === 'delete') {
        $st = $pdo->prepare('SELECT cover_image FROM blog_posts WHERE id = ?');
        $st->execute([$pid]);
        $cv = $st->fetchColumn();
        if ($cv && !preg_match('#^https?://#', $cv)) @unlink(__DIR__ . '/../' . $cv);
        $pdo->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$pid]);
        flash_set('success', 'Post excluído.');
        admin_redirect('blog.php');
    }
    if ($op === 'toggle') {
        $pdo->prepare('UPDATE blog_posts SET is_active = 1 - is_active WHERE id = ?')->execute([$pid]);
        admin_redirect('blog.php');
    }

    // op = save
    $title = trim((string) post('title', ''));
    $slugInput = trim((string) post('slug', ''));
    $slug = b_slugify($slugInput !== '' ? $slugInput : $title);
    $category = trim((string) post('category', ''));
    $readTime = trim((string) post('read_time', ''));
    $excerpt = trim((string) post('excerpt', ''));
    $content = trim((string) post('content', ''));
    $publishedAt = trim((string) post('published_at', '')) ?: null;
    $publishedLabel = trim((string) post('published_label', ''));
    $coverUrl = trim((string) post('cover_image_url', ''));
    $isActive = post('is_active') ? 1 : 0;

    $errors = [];
    if ($title === '')   $errors[] = 'Informe o título.';
    if ($content === '') $errors[] = 'Escreva o conteúdo do post.';
    if ($coverUrl !== '' && !filter_var($coverUrl, FILTER_VALIDATE_URL)) $errors[] = 'A URL da capa não é válida.';

    if ($errors) {
        foreach ($errors as $err) flash_set('error', $err);
        admin_redirect('blog.php?action=' . ($pid ? 'edit&id=' . $pid : 'new'));
    }

    $slug = b_unique_slug($pdo, $slug, $pid);

    // capa: upload > url informada > mantém a atual (edição)
    $uploaded = b_handle_cover();
    $cover = $uploaded ?: ($coverUrl ?: null);
    if ($cover === null && $pid > 0) {
        $st = $pdo->prepare('SELECT cover_image FROM blog_posts WHERE id = ?');
        $st->execute([$pid]);
        $cover = $st->fetchColumn() ?: null;
    }

    // Conteúdo é salvo como texto simples; a formatação HTML é gerada na exibição.
    if ($pid > 0) {
        $pdo->prepare("UPDATE blog_posts SET title=?, slug=?, category=?, read_time=?, excerpt=?, cover_image=?, content=?, content_format='text', published_at=?, published_label=?, is_active=? WHERE id=?")
            ->execute([$title, $slug, $category ?: null, $readTime ?: null, $excerpt ?: null, $cover, $content, $publishedAt, $publishedLabel ?: null, $isActive, $pid]);
        flash_set('success', 'Post atualizado.');
    } else {
        $pdo->prepare("INSERT INTO blog_posts (title, slug, category, read_time, excerpt, cover_image, content, content_format, published_at, published_label, is_active) VALUES (?,?,?,?,?,?,?,'text',?,?,?)")
            ->execute([$title, $slug, $category ?: null, $readTime ?: null, $excerpt ?: null, $cover, $content, $publishedAt, $publishedLabel ?: null, $isActive]);
        $pid = (int) $pdo->lastInsertId();
        flash_set('success', 'Post criado.');
    }
    admin_redirect('blog.php?action=edit&id=' . $pid);
}

// --------------------------------------------------------------------------
// Formulário
// --------------------------------------------------------------------------
if ($action === 'new' || $action === 'edit') {
    $row = ['id'=>0,'title'=>'','slug'=>'','category'=>'','read_time'=>'','excerpt'=>'','cover_image'=>'',
            'content'=>'','published_at'=>date('Y-m-d'),'published_label'=>'','is_active'=>1];
    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { flash_set('error', 'Post não encontrado.'); admin_redirect('blog.php'); }
    }
    // Posts antigos guardados em HTML são convertidos para texto ao abrir no editor.
    $contentForEditor = ($row['content_format'] ?? 'text') === 'html'
        ? blog_html_to_text((string) $row['content'])
        : (string) $row['content'];

    $adminPageTitle = $action === 'edit' ? 'Editar post' : 'Novo post';
    $adminActive = 'blog';
    require __DIR__ . '/_header.php';
    ?>
    <p><a href="blog.php" class="back-link"><?php echo ic('arrow-left', 14); ?> Voltar para o blog</a></p>

    <form method="post" enctype="multipart/form-data" class="panel form-narrow">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op" value="save">
        <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">

        <label class="field">
            <span>Título</span>
            <input type="text" name="title" value="<?php echo e($row['title']); ?>" required>
        </label>

        <label class="field">
            <span>Slug (URL)</span>
            <input type="text" name="slug" value="<?php echo e($row['slug']); ?>" placeholder="gerado a partir do título se vazio">
        </label>

        <div class="grid-2">
            <label class="field field-sm">
                <span>Categoria</span>
                <input type="text" name="category" list="blogcats" value="<?php echo e($row['category']); ?>">
                <datalist id="blogcats">
                    <option value="Universo Duás"><option value="Estilo & Dicas"><option value="Inspiração">
                </datalist>
            </label>
            <label class="field field-sm">
                <span>Tempo de leitura</span>
                <input type="text" name="read_time" value="<?php echo e($row['read_time']); ?>" placeholder="4 min de leitura">
            </label>
        </div>

        <div class="grid-2">
            <label class="field field-sm">
                <span>Data de publicação</span>
                <input type="date" name="published_at" value="<?php echo e($row['published_at']); ?>">
            </label>
            <label class="field field-sm">
                <span>Rótulo da data (opcional)</span>
                <input type="text" name="published_label" value="<?php echo e($row['published_label']); ?>" placeholder="05 de Setembro, 2026">
            </label>
        </div>

        <label class="field">
            <span>Resumo (excerpt)</span>
            <textarea name="excerpt" rows="2"><?php echo e($row['excerpt']); ?></textarea>
        </label>

        <label class="field">
            <span>Conteúdo</span>
            <textarea name="content" rows="16" required placeholder="Escreva normalmente.

Deixe uma linha em branco entre os parágrafos.

## Subtítulo
&gt; Uma citação
- Item de lista
- Outro item

Use **negrito** e *itálico* quando quiser."><?php echo e($contentForEditor); ?></textarea>
            <small>Texto normal. Linha em branco separa parágrafos &middot; <code>## </code> subtítulo &middot; <code>&gt; </code> citação &middot; <code>- </code> lista &middot; <code>**negrito**</code> &middot; <code>*itálico*</code>. O sistema formata para a página.</small>
        </label>

        <fieldset class="subfield">
            <legend>Capa</legend>
            <?php if ($row['cover_image']): ?>
                <img src="<?php echo e(b_img_src($row['cover_image'])); ?>" alt="" style="width:100%;max-width:360px;aspect-ratio:16/9;object-fit:cover;border-radius:var(--a-radius);border:1px solid var(--a-border);">
            <?php endif; ?>
            <label class="field" style="margin-top:12px;">
                <span>Enviar imagem (JPG, PNG ou WEBP &mdash; até 4 MB)</span>
                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
            </label>
            <label class="field">
                <span>&hellip; ou usar URL externa</span>
                <input type="url" name="cover_image_url" value="<?php echo e(preg_match('#^https?://#', (string) $row['cover_image']) ? $row['cover_image'] : ''); ?>" placeholder="https://&hellip;">
            </label>
            <small>Enviar arquivo tem prioridade sobre a URL. Em branco na edição, mantém a capa atual.</small>
        </fieldset>

        <label class="check">
            <input type="checkbox" name="is_active" value="1" <?php echo (int) $row['is_active'] === 1 ? 'checked' : ''; ?>>
            <span>Publicado (visível no blog)</span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo ic('check'); ?> Salvar post</button>
            <a href="blog.php" class="btn btn-ghost">Cancelar</a>
            <?php if ($action === 'edit'): ?>
                <a href="../blog-post.php?slug=<?php echo e($row['slug']); ?>" target="_blank" rel="noopener" class="btn btn-ghost"><?php echo ic('external'); ?> Ver no site</a>
            <?php endif; ?>
        </div>
    </form>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

// --------------------------------------------------------------------------
// Listagem
// --------------------------------------------------------------------------
$rows = $pdo->query('SELECT * FROM blog_posts ORDER BY published_at DESC, id DESC')->fetchAll();

$adminPageTitle = 'Blog';
$adminActive = 'blog';
require __DIR__ . '/_header.php';
?>
<div class="list-head">
    <p class="lead">Cadastro, edição, publicação/despublicação e capa dos artigos do blog.</p>
    <a href="blog.php?action=new" class="btn btn-primary"><?php echo ic('plus'); ?> Novo post</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th></th><th>Título</th><th>Categoria</th><th>Publicação</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td>
                    <?php if ($r['cover_image']): ?>
                        <img class="row-thumb" style="width:64px;height:40px;" src="<?php echo e(b_img_src($r['cover_image'])); ?>" alt="">
                    <?php else: ?>
                        <span class="row-thumb row-thumb-empty" style="width:64px;height:40px;">—</span>
                    <?php endif; ?>
                </td>
                <td><strong><?php echo e($r['title']); ?></strong><br><span class="hint"><?php echo e($r['slug']); ?></span></td>
                <td><?php echo e($r['category'] ?: '—'); ?></td>
                <td><?php echo e($r['published_label'] ?: ($r['published_at'] ? date('d/m/Y', strtotime($r['published_at'])) : '—')); ?></td>
                <td>
                    <form method="post" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="op" value="toggle">
                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                        <button type="submit" class="badge <?php echo (int) $r['is_active'] === 1 ? 'badge-on' : 'badge-off'; ?>">
                            <?php echo (int) $r['is_active'] === 1 ? 'Publicado' : 'Rascunho'; ?>
                        </button>
                    </form>
                </td>
                <td class="row-actions">
                    <a href="../blog-post.php?slug=<?php echo e($r['slug']); ?>" target="_blank" rel="noopener" class="btn-icon" title="Ver no site"><?php echo ic('eye'); ?></a>
                    <?php echo edit_link('blog.php?action=edit&id=' . (int) $r['id']); ?>
                    <form method="post" onsubmit="return confirm('Excluir este post?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="op" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                        <button type="submit" class="btn-icon is-danger" title="Excluir"><?php echo ic('trash'); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="empty">Nenhum post cadastrado.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
