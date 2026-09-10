<?php
// ==========================================================================
// DUÁS - CONFIGURAÇÃO E CONEXÃO COM O BANCO DE DADOS (MySQL / PDO)
// Schema: sql/duas-banco.sql
//
// As credenciais vêm de includes/config.php (não versionado) ou das variáveis
// de ambiente DUAS_DB_HOST / DUAS_DB_NAME / DUAS_DB_USER / DUAS_DB_PASS.
// ==========================================================================

// Fuso horário da loja. Sem isto o PHP assume UTC e grava datas 3 horas à
// frente das que o MySQL escreve com NOW() — no mesmo pedido, a data da
// separação sairia diferente da data do evento no histórico.
// Este arquivo é a raiz comum da loja, do painel e dos scripts de bin/.
date_default_timezone_set('America/Sao_Paulo');

/**
 * Configuração do ambiente.
 *
 * Ordem de busca:
 *   1. includes/config.php       -> desenvolvimento local
 *   2. includes/config.prod.php  -> servidor de produção
 *   3. variáveis de ambiente DUAS_DB_*
 *
 * No servidor, envie APENAS o config.prod.php. Se o config.php local subir
 * junto, ele tem prioridade e a loja tentaria conectar no banco errado.
 */
function duas_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = [];
        foreach (['/config.php', '/config.prod.php'] as $arquivo) {
            $caminho = __DIR__ . $arquivo;
            if (is_readable($caminho)) {
                $cfg = (array) require $caminho;
                break;
            }
        }
    }
    return $cfg;
}

/**
 * Lê uma chave de configuração: arquivo > variável de ambiente > padrão.
 */
function duas_setting(string $key, string $envVar, ?string $default = null): ?string
{
    $db = duas_config()['db'] ?? [];
    if (isset($db[$key]) && $db[$key] !== '') {
        return (string) $db[$key];
    }
    $env = getenv($envVar);
    if (is_string($env) && $env !== '') {
        return $env;
    }
    return $default;
}

function duas_debug(): bool
{
    return (bool) (duas_config()['debug'] ?? false);
}

/**
 * Retorna uma instância única (singleton) de PDO conectada ao banco Duás.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = duas_setting('host', 'DUAS_DB_HOST', 'localhost');
    $name    = duas_setting('name', 'DUAS_DB_NAME', 'duas_db');
    $user    = duas_setting('user', 'DUAS_DB_USER');
    $pass    = duas_setting('pass', 'DUAS_DB_PASS', '');
    $charset = duas_setting('charset', 'DUAS_DB_CHARSET', 'utf8mb4');

    if ($user === null || $user === '') {
        throw new RuntimeException(
            'Banco de dados não configurado. Copie includes/config.example.php para '
            . 'includes/config.php e preencha as credenciais (ou defina DUAS_DB_USER / DUAS_DB_PASS).'
        );
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Nunca expor host/usuário/senha para o visitante em produção.
        error_log('Duás: falha ao conectar no banco - ' . $e->getMessage());
        if (duas_debug()) {
            throw $e;
        }
        throw new RuntimeException('Não foi possível conectar ao banco de dados.');
    }

    return $pdo;
}
