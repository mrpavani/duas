<?php
// ==========================================================================
// DUÁS - DATA REPOSITORY & SESSION MANAGEMENT
// Entities: Product, BlogPost, CartItem, Order
// Produtos, promoções e frete grátis vêm do banco (área /admin).
// ==========================================================================

require_once __DIR__ . '/db.php';

/**
 * O carrinho é sempre temporário: vive só enquanto o navegador estiver aberto.
 * - cookie de sessão sem lifetime -> o navegador descarta ao fechar
 * - TTL de inatividade no servidor -> cobre navegadores que restauram a sessão
 * Nenhum dado do cliente fica guardado entre visitas (LGPD: minimização).
 */
const CARRINHO_TTL_SEGUNDOS = 7200; // 2 horas de inatividade

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('DUAS_LOJA');
    session_start();
}

// Initialize Cart Session if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Expira o carrinho por inatividade
if (isset($_SESSION['cart_touched']) && (time() - (int) $_SESSION['cart_touched']) > CARRINHO_TTL_SEGUNDOS) {
    $_SESSION['cart'] = [];
    unset($_SESSION['coupon'], $_SESSION['checkout']);
}
$_SESSION['cart_touched'] = time();

/**
 * Normalizes string for accent-insensitive and case-insensitive search
 */
function normalize_search_str($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $unwanted_array = [
        'á'=>'a', 'à'=>'a', 'ã'=>'a', 'â'=>'a', 'ä'=>'a',
        'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e',
        'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i',
        'ó'=>'o', 'ò'=>'o', 'õ'=>'o', 'ô'=>'o', 'ö'=>'o',
        'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'u',
        'ç'=>'c', 'ñ'=>'n'
    ];
    return strtr($str, $unwanted_array);
}

/**
 * Preço promocional efetivo respeitando a janela sale_starts_at / sale_ends_at.
 */
function effective_sale_price(array $row): ?float {
    if ($row['sale_price'] === null || $row['sale_price'] === '') {
        return null;
    }
    $now = date('Y-m-d H:i:s');
    if (!empty($row['sale_starts_at']) && $now < $row['sale_starts_at']) return null;
    if (!empty($row['sale_ends_at'])   && $now > $row['sale_ends_at'])   return null;
    return (float) $row['sale_price'];
}

/**
 * Converte um registro do banco no formato usado pelo storefront.
 */
function product_row_to_shape(array $r, array $images, array $sizes): array {
    return [
        'id'             => (int) $r['id'],
        'name'           => $r['name'],
        'slug'           => $r['slug'],
        'category'       => $r['category'],
        'price'          => (float) $r['price'],
        'salePrice'      => effective_sale_price($r),
        'listSalePrice'  => $r['sale_price'] !== null ? (float) $r['sale_price'] : null,
        'saleStartsAt'   => $r['sale_starts_at'] ?? null,
        'saleEndsAt'     => $r['sale_ends_at'] ?? null,
        'isNewRelease'   => (bool) $r['is_new_release'],
        'isActive'       => (bool) ($r['is_active'] ?? 1),
        'images'         => $images ?: ['https://placehold.co/1000x1333/f7f6f4/121212?text=Du%C3%A1s'],
        'sizes'          => $sizes ?: ['P', 'M', 'G'],
        'description'    => (string) $r['description'],
        'composition'    => $r['composition'],
        'careInstructions' => $r['care_instructions'],
        'instagramUrl'   => $r['instagram_url'] ?? null,
        'measurements'   => !empty($r['measurements']) ? (json_decode((string) $r['measurements'], true) ?: null) : null,
    ];
}

/**
 * Returns the full Product Catalog (do banco).
 * @param bool $activeOnly true = só produtos ativos (storefront); false = todos (admin)
 */
function get_products($activeOnly = true) {
    $pdo = db();
    $sql = 'SELECT * FROM products' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY id';
    $rows = $pdo->query($sql)->fetchAll();
    if (!$rows) return [];

    $ids = array_column($rows, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $imgStmt = $pdo->prepare("SELECT product_id, image_url FROM product_images WHERE product_id IN ($in) ORDER BY product_id, position, id");
    $imgStmt->execute($ids);
    $images = [];
    foreach ($imgStmt->fetchAll() as $row) $images[$row['product_id']][] = $row['image_url'];

    $szStmt = $pdo->prepare("SELECT product_id, size FROM product_sizes WHERE product_id IN ($in) ORDER BY product_id, position, id");
    $szStmt->execute($ids);
    $sizes = [];
    foreach ($szStmt->fetchAll() as $row) $sizes[$row['product_id']][] = $row['size'];

    $out = [];
    foreach ($rows as $r) {
        $out[] = product_row_to_shape($r, $images[$r['id']] ?? [], $sizes[$r['id']] ?? []);
    }
    return $out;
}

/**
 * Finds a single product by ID or Slug (retorna também inativos; o storefront valida isActive).
 */
function get_product_by_id_or_slug($val) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id OR slug = :slug LIMIT 1');
    $stmt->execute([':id' => is_numeric($val) ? (int) $val : 0, ':slug' => (string) $val]);
    $r = $stmt->fetch();
    if (!$r) return null;

    $imgStmt = $pdo->prepare('SELECT image_url FROM product_images WHERE product_id = ? ORDER BY position, id');
    $imgStmt->execute([$r['id']]);
    $images = array_column($imgStmt->fetchAll(), 'image_url');

    $szStmt = $pdo->prepare('SELECT size FROM product_sizes WHERE product_id = ? ORDER BY position, id');
    $szStmt->execute([$r['id']]);
    $sizes = array_column($szStmt->fetchAll(), 'size');

    return product_row_to_shape($r, $images, $sizes);
}

// --------------------------------------------------------------------------
// Promoções / cupons (tabela promotions, gerida em /admin)
// --------------------------------------------------------------------------
function get_promotion_by_code(string $code): ?array {
    $stmt = db()->prepare('SELECT * FROM promotions WHERE code = ? LIMIT 1');
    $stmt->execute([strtoupper(trim($code))]);
    return $stmt->fetch() ?: null;
}

/**
 * @return array{ok:bool,message:string}
 */
function promotion_check(?array $promo, float $subtotal): array {
    if (!$promo)                                return ['ok' => false, 'message' => 'Cupom inválido.'];
    if ((int) $promo['is_active'] !== 1)        return ['ok' => false, 'message' => 'Cupom desativado.'];
    $now = date('Y-m-d H:i:s');
    if (!empty($promo['starts_at']) && $now < $promo['starts_at']) return ['ok' => false, 'message' => 'Cupom ainda não está válido.'];
    if (!empty($promo['ends_at'])   && $now > $promo['ends_at'])   return ['ok' => false, 'message' => 'Cupom expirado.'];
    if ($promo['usage_limit'] !== null && (int) $promo['used_count'] >= (int) $promo['usage_limit']) {
        return ['ok' => false, 'message' => 'Cupom esgotado.'];
    }
    if ($subtotal + 0.001 < (float) $promo['min_subtotal']) {
        return ['ok' => false, 'message' => 'Valor mínimo de R$ ' . number_format((float) $promo['min_subtotal'], 2, ',', '.') . ' não atingido.'];
    }
    return ['ok' => true, 'message' => 'Cupom aplicado.'];
}

/**
 * Promoções vigentes agora (ativas, dentro do período e com uso disponível).
 * @return array<int,array<string,mixed>>
 */
function get_active_promotions(): array {
    $now = date('Y-m-d H:i:s');
    $rows = db()->query('SELECT * FROM promotions WHERE is_active = 1 ORDER BY discount_value DESC')->fetchAll();
    return array_values(array_filter($rows, function ($r) use ($now) {
        if (!empty($r['starts_at']) && $now < $r['starts_at']) return false;
        if (!empty($r['ends_at'])   && $now > $r['ends_at'])   return false;
        if ($r['usage_limit'] !== null && (int) $r['used_count'] >= (int) $r['usage_limit']) return false;
        return true;
    }));
}

function promotion_discount(array $promo, float $subtotal): float {
    $value = (float) $promo['discount_value'];
    $discount = $promo['discount_type'] === 'percent' ? $subtotal * ($value / 100) : $value;
    return round(min($discount, $subtotal), 2);
}

// --------------------------------------------------------------------------
// Frete grátis por período (tabela free_shipping_rules, gerida em /admin)
// --------------------------------------------------------------------------
function active_free_shipping_threshold(): ?float {
    $rows = db()->query('SELECT min_subtotal, starts_at, ends_at FROM free_shipping_rules WHERE is_active = 1')->fetchAll();
    $now  = date('Y-m-d H:i:s');
    $best = null;
    foreach ($rows as $r) {
        if (!empty($r['starts_at']) && $now < $r['starts_at']) continue;
        if (!empty($r['ends_at'])   && $now > $r['ends_at'])   continue;
        $v = (float) $r['min_subtotal'];
        if ($best === null || $v < $best) $best = $v;
    }
    return $best;
}

/**
 * Filter products by category or category/price range with normalized accent support
 */
function get_filtered_products($category = null, $sort = null, $query = null) {
    $products = get_products();

    if ($category && strtolower($category) !== 'todos') {
        $catNorm = normalize_search_str($category);
        $products = array_filter($products, function($p) use ($catNorm) {
            return normalize_search_str($p['category']) === $catNorm;
        });
    }

    if ($query) {
        $qNorm = normalize_search_str(trim($query));
        $products = array_filter($products, function($p) use ($qNorm) {
            $nameNorm = normalize_search_str($p['name']);
            $catNorm = normalize_search_str($p['category']);
            $descNorm = normalize_search_str($p['description']);
            return strpos($nameNorm, $qNorm) !== false || 
                   strpos($catNorm, $qNorm) !== false ||
                   strpos($descNorm, $qNorm) !== false;
        });
    }

    if ($sort) {
        if ($sort === 'price-asc') {
            usort($products, fn($a, $b) => ($a['salePrice'] ?? $a['price']) <=> ($b['salePrice'] ?? $b['price']));
        } elseif ($sort === 'price-desc') {
            usort($products, fn($a, $b) => ($b['salePrice'] ?? $b['price']) <=> ($a['salePrice'] ?? $a['price']));
        } elseif ($sort === 'newest') {
            usort($products, fn($a, $b) => ($b['isNewRelease'] ? 1 : 0) <=> ($a['isNewRelease'] ? 1 : 0));
        }
    }

    return array_values($products);
}

/**
 * Formata uma data (Y-m-d) em pt-BR: "05 de Setembro, 2026".
 */
function format_date_ptbr($date): string {
    if (!$date) return '';
    $ts = strtotime((string) $date);
    if (!$ts) return (string) $date;
    $months = [1=>'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    return sprintf('%02d de %s, %d', (int) date('d', $ts), $months[(int) date('n', $ts)], (int) date('Y', $ts));
}

/**
 * Formatação inline segura para o conteúdo do blog (texto simples -> HTML).
 * Escapa tudo primeiro e só depois aplica **negrito**, *itálico* e [texto](url).
 */
function blog_inline(string $s): string {
    $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*([^*\n]+)\*(?![\w*])/s', '<em>$1</em>', $s);
    $s = preg_replace('/(?<![\w_])_([^_\n]+)_(?![\w_])/s', '<em>$1</em>', $s);
    $s = preg_replace_callback('/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', function ($m) {
        return '<a href="' . $m[2] . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
    }, $s);
    return $s;
}

/**
 * Converte o conteúdo escrito em texto normal para HTML pronto para a página.
 * Regras: linha em branco = novo parágrafo; "## " = subtítulo; "> " = citação;
 * "- " = lista; "1. " = lista numerada.
 */
function blog_text_to_html(string $text): string {
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') return '';

    $out = [];
    foreach (preg_split('/\n{2,}/', $text) as $block) {
        $block = trim($block, "\n");
        if ($block === '') continue;
        $lines = explode("\n", $block);

        if (count($lines) === 1 && preg_match('/^#{1,6}\s+(.+)$/u', $lines[0], $m)) {
            $out[] = '<h3>' . blog_inline(trim($m[1])) . '</h3>';
            continue;
        }
        $allMatch = fn($re) => count(array_filter($lines, fn($l) => !preg_match($re, $l))) === 0;

        if ($allMatch('/^>\s?/')) {
            $q = array_map(fn($l) => blog_inline(preg_replace('/^>\s?/', '', $l)), $lines);
            $out[] = '<blockquote>' . implode('<br>', $q) . '</blockquote>';
        } elseif ($allMatch('/^[-*]\s+/')) {
            $items = array_map(fn($l) => '<li>' . blog_inline(preg_replace('/^[-*]\s+/', '', $l)) . '</li>', $lines);
            $out[] = '<ul>' . implode('', $items) . '</ul>';
        } elseif ($allMatch('/^\d+[.)]\s+/')) {
            $items = array_map(fn($l) => '<li>' . blog_inline(preg_replace('/^\d+[.)]\s+/', '', $l)) . '</li>', $lines);
            $out[] = '<ol>' . implode('', $items) . '</ol>';
        } else {
            $out[] = '<p>' . implode('<br>', array_map('blog_inline', $lines)) . '</p>';
        }
    }
    return implode("\n", $out);
}

/**
 * Caminho inverso: HTML antigo -> texto normal, para carregar no editor.
 */
function blog_html_to_text(string $html): string {
    $t = $html;
    $t = preg_replace('#<h[1-6][^>]*>(.*?)</h[1-6]>#is', "\n\n## $1\n\n", $t);
    $t = preg_replace('#<blockquote[^>]*>(.*?)</blockquote>#is', "\n\n> $1\n\n", $t);
    $t = preg_replace('#<li[^>]*>(.*?)</li>#is', "\n- $1", $t);
    $t = preg_replace('#</?(ul|ol)[^>]*>#i', "\n\n", $t);
    $t = preg_replace('#<br\s*/?>#i', "\n", $t);
    $t = preg_replace('#</p>#i', "\n\n", $t);
    $t = preg_replace('#<p[^>]*>#i', '', $t);
    $t = preg_replace('#<(strong|b)[^>]*>(.*?)</(strong|b)>#is', '**$2**', $t);
    $t = preg_replace('#<(em|i)[^>]*>(.*?)</(em|i)>#is', '*$2*', $t);
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/[ \t]+\n/', "\n", $t);
    $t = preg_replace('/\n{3,}/', "\n\n", $t);
    return trim($t);
}

/**
 * Converte um registro de blog_posts no formato usado pelo storefront.
 */
function blog_row_to_shape(array $r): array {
    $format = $r['content_format'] ?? 'text';
    $html = $format === 'html' ? (string) $r['content'] : blog_text_to_html((string) $r['content']);

    $excerpt = trim((string) ($r['excerpt'] ?? ''));
    if ($excerpt === '') {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
        $excerpt = mb_substr($plain, 0, 180);
        if (mb_strlen($plain) > 180) $excerpt = rtrim($excerpt) . '…';
    }

    return [
        'id'          => (int) $r['id'],
        'title'       => $r['title'],
        'slug'        => $r['slug'],
        'category'    => $r['category'],
        'readTime'    => $r['read_time'],
        'publishedAt' => !empty($r['published_label']) ? $r['published_label'] : format_date_ptbr($r['published_at']),
        'coverImage'  => $r['cover_image'],
        'excerpt'     => $excerpt,
        'content'     => $html,
        'contentRaw'  => (string) $r['content'],
        'contentFormat' => $format,
        'isActive'    => (bool) ($r['is_active'] ?? 1),
    ];
}

/**
 * Returns the Blog Posts list (do banco).
 * @param bool $activeOnly true = só posts ativos (storefront); false = todos (admin)
 */
function get_blog_posts($activeOnly = true) {
    $sql = 'SELECT * FROM blog_posts' . ($activeOnly ? ' WHERE is_active = 1' : '')
         . ' ORDER BY published_at DESC, id DESC';
    return array_map('blog_row_to_shape', db()->query($sql)->fetchAll());
}

/**
 * Finds a blog post by slug (retorna também inativos; blog-post.php valida isActive).
 */
function get_blog_post_by_slug($slug) {
    $stmt = db()->prepare('SELECT * FROM blog_posts WHERE slug = ? LIMIT 1');
    $stmt->execute([(string) $slug]);
    $r = $stmt->fetch();
    return $r ? blog_row_to_shape($r) : null;
}

/**
 * Calculates cart total and count
 */
function get_cart_summary() {
    $cart = $_SESSION['cart'] ?? [];
    $total = 0;
    $count = 0;

    foreach ($cart as $item) {
        $product = get_product_by_id_or_slug($item['productId']);
        if ($product) {
            $unitPrice = $product['salePrice'] ?? $product['price'];
            $total += $unitPrice * $item['quantity'];
            $count += $item['quantity'];
        }
    }

    $goal = active_free_shipping_threshold();       // null = sem campanha de frete grátis
    $hasFree = $goal !== null && $total >= $goal;

    return [
        'total' => $total,
        'count' => $count,
        'freeShippingGoal' => $goal ?? 0.0,
        'freeShippingActive' => $goal !== null,
        'freeShippingRemaining' => $goal !== null ? max(0, $goal - $total) : 0.0,
        'hasFreeShipping' => $hasFree
    ];
}
?>
