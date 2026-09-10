<?php
// ==========================================================================
// DUÁS - Cria (ou atualiza) um usuário do painel administrativo.
//
// Rode no terminal do servidor, uma vez, logo após instalar o banco:
//     php bin/criar-admin.php
//
// A senha nunca fica gravada em arquivo: só o hash vai para o banco.
// ==========================================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado pela linha de comando.\n");
}

require_once __DIR__ . '/../includes/db.php';

function ask(string $label, bool $required = true): string
{
    while (true) {
        echo $label;
        $value = trim((string) fgets(STDIN));
        if ($value !== '' || !$required) {
            return $value;
        }
        echo "  -> Campo obrigatório.\n";
    }
}

echo "\n=== Duás | criar usuário do painel ===\n\n";

try {
    $pdo = db();
} catch (Throwable $e) {
    exit("Erro de conexão: " . $e->getMessage() . "\n");
}

$name = ask('Nome completo: ');

do {
    $email = ask('E-mail (login): ');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "  -> E-mail inválido.\n";
        $email = '';
    }
} while ($email === '');

echo "\nA senha vai aparecer na tela enquanto você digita.\n";
do {
    $pass = ask('Senha (mínimo 10 caracteres): ');
    if (strlen($pass) < 10) {
        echo "  -> Muito curta.\n";
        $pass = '';
        continue;
    }
    $confirm = ask('Repita a senha: ');
    if ($pass !== $confirm) {
        echo "  -> As senhas não conferem.\n";
        $pass = '';
    }
} while ($pass === '');

$hash = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$existing = $stmt->fetchColumn();

if ($existing) {
    $pdo->prepare('UPDATE admin_users SET name = ?, password_hash = ?, is_active = 1 WHERE id = ?')
        ->execute([$name, $hash, $existing]);
    echo "\n[OK] Usuário atualizado (id {$existing}).\n";
} else {
    $pdo->prepare('INSERT INTO admin_users (name, email, password_hash, is_active) VALUES (?, ?, ?, 1)')
        ->execute([$name, $email, $hash]);
    echo "\n[OK] Usuário criado (id " . $pdo->lastInsertId() . ").\n";
}

echo "Acesse o painel em /admin/login.php\n\n";
