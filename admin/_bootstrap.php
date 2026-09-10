<?php
// ==========================================================================
// DUÁS - ÁREA ADMINISTRATIVA / BOOTSTRAP
// Sessão isolada do storefront, conexão PDO, helpers de auth / CSRF / flash.
// ==========================================================================

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_name('DUAS_ADMIN');
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/_icons.php';

// --------------------------------------------------------------------------
// Helpers básicos
// --------------------------------------------------------------------------
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function admin_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function admin_base(): string
{
    // caminho do diretório /admin relativo à raiz do site
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
}

// --------------------------------------------------------------------------
// Flash messages
// --------------------------------------------------------------------------
function flash_set(string $type, string $message): void
{
    $_SESSION['admin_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_all(): array
{
    $items = $_SESSION['admin_flash'] ?? [];
    unset($_SESSION['admin_flash']);
    return $items;
}

// --------------------------------------------------------------------------
// CSRF
// --------------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página e tente novamente.');
    }
}

// --------------------------------------------------------------------------
// Autenticação (sem perfis / sem permissões)
// --------------------------------------------------------------------------
function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $stmt = db()->prepare('SELECT id, name, email, is_active FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin || (int) $admin['is_active'] !== 1) {
        session_destroy();
        return null;
    }

    return $cache = $admin;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        admin_redirect(admin_base() . '/login.php');
    }
    return $admin;
}

function admin_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || (int) $admin['is_active'] !== 1 || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];

    db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);

    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    session_destroy();
}
