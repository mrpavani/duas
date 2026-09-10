<?php
$page_title = "Blog & Editorial";
require_once __DIR__ . '/includes/header.php';
$posts = get_blog_posts();
?>

<div class="section" style="padding-top: 40px;">
    <div class="container">
        <div class="section-header">
            <span class="subtitle">Jornal Editorial</span>
            <h1>Universo Duás</h1>
            <p class="lead">Artigos sobre estilo de vida, ensaios de moda e arquitetura por Larissa & Letícia.</p>
        </div>

        <div class="blog-grid" style="margin-top: 50px;">
            <?php foreach ($posts as $post): ?>
                <article class="blog-card">
                    <a href="blog-post.php?slug=<?php echo $post['slug']; ?>" class="blog-media">
                        <img src="<?php echo htmlspecialchars($post['coverImage']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                    </a>
                    <div class="blog-meta">
                        <span><?php echo htmlspecialchars($post['category']); ?></span> • 
                        <span><?php echo htmlspecialchars($post['publishedAt']); ?></span>
                    </div>
                    <a href="blog-post.php?slug=<?php echo $post['slug']; ?>">
                        <h3 class="blog-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                    </a>
                    <p class="blog-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                    <a href="blog-post.php?slug=<?php echo $post['slug']; ?>" class="link-underline">Ler Matéria Completa &rarr;</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
