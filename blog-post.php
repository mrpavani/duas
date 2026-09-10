<?php
require_once __DIR__ . '/includes/data.php';

$slug = $_GET['slug'] ?? 'poetica-do-vestir-alfaiataria-larissa-leticia';
$post = get_blog_post_by_slug($slug);

if (!$post || empty($post['isActive'])) {
    header("Location: blog.php");
    exit;
}

$page_title = $post['title'];
require_once __DIR__ . '/includes/header.php';
$otherPosts = array_slice(array_filter(get_blog_posts(), fn($p) => $p['slug'] !== $post['slug']), 0, 2);
?>

<div class="section" style="padding-top: 40px;">
    <div class="container container-narrow">
        <!-- Meta Header -->
        <div style="text-align: center; margin-bottom: 36px;">
            <span class="subtitle"><?php echo htmlspecialchars($post['category']); ?> • <?php echo htmlspecialchars($post['readTime']); ?></span>
            <h1 style="margin-top: 12px; margin-bottom: 16px; font-size: clamp(2rem, 3.5vw, 3rem);"><?php echo htmlspecialchars($post['title']); ?></h1>
            <p style="font-size: 0.9rem; color: var(--color-text-muted);">Publicado em <?php echo htmlspecialchars($post['publishedAt']); ?> por <strong>Larissa & Leticia</strong></p>
        </div>

        <!-- Cover Image -->
        <div style="margin-bottom: 48px;">
            <img src="<?php echo htmlspecialchars($post['coverImage']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" style="width: 100%; aspect-ratio: 16/9; object-fit: cover;">
        </div>

        <!-- Article Content with Clean Editorial Typography -->
        <article style="font-size: 1.1rem; line-height: 1.95; color: #222222;">
            <?php echo $post['content']; ?>
        </article>

        <!-- Share / Back Link -->
        <div style="margin-top: 60px; padding-top: 24px; border-top: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <a href="blog.php" class="link-underline">&larr; Voltar para o Blog</a>
            <span style="font-size: 0.85rem; color: var(--color-text-muted);">Compartilhar: Instagram • Pinterest • WhatsApp</span>
        </div>

        <!-- Related Posts -->
        <?php if (!empty($otherPosts)): ?>
            <div style="margin-top: 80px;">
                <h3 style="font-family: var(--font-heading); margin-bottom: 24px; text-align: center;">Leia Também</h3>
                <div class="blog-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <?php foreach ($otherPosts as $op): ?>
                        <div class="blog-card">
                            <a href="blog-post.php?slug=<?php echo $op['slug']; ?>" class="blog-media">
                                <img src="<?php echo htmlspecialchars($op['coverImage']); ?>" alt="<?php echo htmlspecialchars($op['title']); ?>">
                            </a>
                            <h4 style="font-size: 1.1rem; margin-top: 8px;"><?php echo htmlspecialchars($op['title']); ?></h4>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
