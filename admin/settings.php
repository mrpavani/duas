<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/settings.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $text = trim((string) post('announcement_text', ''));
    if (mb_strlen($text) > 240) $text = mb_substr($text, 0, 240);
    $active = post('announcement_active') ? '1' : '0';

    set_setting('announcement_text', $text);
    set_setting('announcement_active', $active);

    flash_set('success', 'Barra de aviso atualizada.');
    admin_redirect('settings.php');
}

$text = (string) get_setting('announcement_text', '');
$active = get_setting('announcement_active', '1') === '1';

$adminPageTitle = 'Configurações';
$adminActive = 'settings';
require __DIR__ . '/_header.php';
?>
<p class="lead">Textos e ajustes exibidos na loja.</p>

<form method="post" class="panel form-narrow">
    <?php echo csrf_field(); ?>

    <h2>Barra de aviso (topo do site)</h2>

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
        <span class="hint" style="display:block; margin-bottom:6px;">Prévia:</span>
        <div class="preview-bar"><span id="annPreview"><?php echo e($text) ?: 'A barra ficará vazia'; ?></span></div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?php echo ic('check'); ?> Salvar</button>
        <a href="../index.php" target="_blank" rel="noopener" class="btn btn-ghost"><?php echo ic('external'); ?> Ver na loja</a>
    </div>
</form>

<script>
(function () {
    var t = document.getElementById('annText'), p = document.getElementById('annPreview');
    if (t && p) t.addEventListener('input', function () { p.textContent = t.value || 'A barra ficará vazia'; });
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
