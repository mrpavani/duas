<?php
// ==========================================================================
// DUÁS - Gera sql/duas-atualizar-producao.sql
// Combina atualização estrutural de schema + migração do catálogo oficial
// ==========================================================================

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

// 1. Primeiro rodar gerar-atualizacao.php para garantir duas-atualizar.sql atualizado
require_once __DIR__ . '/gerar-atualizacao.php';

$schemaSql = file_get_contents(__DIR__ . '/../sql/duas-atualizar.sql');

// 2. Exportar os produtos atuais do banco
$products = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$productImages = $pdo->query('SELECT * FROM product_images ORDER BY product_id, position, id')->fetchAll(PDO::FETCH_ASSOC);
$productSizes = $pdo->query('SELECT * FROM product_sizes ORDER BY product_id, position, id')->fetchAll(PDO::FETCH_ASSOC);
$sizes = $pdo->query('SELECT * FROM sizes ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);

function sql_val($v) {
    if ($v === null) return 'NULL';
    if (is_int($v) || is_float($v)) return (string) $v;
    return "'" . addslashes((string) $v) . "'";
}

$out = [];
$out[] = "-- ==========================================================================";
$out[] = "-- DUÁS | ATUALIZAÇÃO COMPLETA DE PRODUÇÃO";
$out[] = "-- ";
$out[] = "-- Este script realiza em uma única operação segura:";
$out[] = "-- 1. Atualização estrutural do banco (novas tabelas, colunas de estoque e tamanhos);";
$out[] = "-- 2. Carga da grade completa de medidas e tamanhos (tabela sizes);";
$out[] = "-- 3. Limpeza dos dados fictícios / de homologação;";
$out[] = "-- 4. Carga dos 22 produtos oficiais do documento 'Infos site.docx' com fotos e estoques.";
$out[] = "-- ";
$out[] = "-- COMO APLICAR NO PHPMYADMIN / MYSQL PRODUÇÃO:";
$out[] = "--   1. Selecione o banco de dados 'duas_db' na lista à esquerda;";
$out[] = "--   2. Vá na aba 'Importar' (Import);";
$out[] = "--   3. Selecione este arquivo e clique em 'Executar' (Go).";
$out[] = "-- ==========================================================================\n";
$out[] = "SET NAMES utf8mb4;";
$out[] = "SET FOREIGN_KEY_CHECKS = 0;\n";

// Adicionar a atualização estrutural
$out[] = "-- --------------------------------------------------------------------------";
$out[] = "-- PARTE 1: ATUALIZAÇÃO ESTRUTURAL DE SCHEMA";
$out[] = "-- --------------------------------------------------------------------------\n";
$out[] = $schemaSql;
$out[] = "\n";

// Adicionar a população da grade de tamanhos
$out[] = "-- --------------------------------------------------------------------------";
$out[] = "-- PARTE 2: GRADE DE TAMANHOS (tabela sizes)";
$out[] = "-- --------------------------------------------------------------------------\n";

if (!empty($sizes)) {
    $out[] = "INSERT INTO `sizes` (`code`, `name`, `category`, `busto_hint`, `cintura_hint`, `quadril_hint`, `comprimento_hint`, `is_active`, `sort_order`) VALUES";
    $sizeValues = [];
    foreach ($sizes as $s) {
        $sizeValues[] = sprintf(
            "(%s, %s, %s, %s, %s, %s, %s, %d, %d)",
            sql_val($s['code']),
            sql_val($s['name']),
            sql_val($s['category']),
            sql_val($s['busto_hint']),
            sql_val($s['cintura_hint']),
            sql_val($s['quadril_hint']),
            sql_val($s['comprimento_hint']),
            (int) $s['is_active'],
            (int) $s['sort_order']
        );
    }
    $out[] = implode(",\n", $sizeValues);
    $out[] = "ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `category`=VALUES(`category`), `busto_hint`=VALUES(`busto_hint`), `cintura_hint`=VALUES(`cintura_hint`), `quadril_hint`=VALUES(`quadril_hint`), `comprimento_hint`=VALUES(`comprimento_hint`), `sort_order`=VALUES(`sort_order`);\n";
}

// Limpeza de tabelas fictícias
$out[] = "-- --------------------------------------------------------------------------";
$out[] = "-- PARTE 3: LIMPEZA DE DADOS FICTÍCIOS / DEMONSTRAÇÃO";
$out[] = "-- --------------------------------------------------------------------------\n";
$tablesToTruncate = [
    'order_events',
    'order_items',
    'orders',
    'customers',
    'cart_items',
    'stock_notifications',
    'product_images',
    'product_sizes',
    'products',
    'blog_posts',
    'contact_messages',
    'newsletter_subscribers',
];
foreach ($tablesToTruncate as $tbl) {
    $out[] = "TRUNCATE TABLE `$tbl`;";
}
$out[] = "DELETE FROM `promotions` WHERE `code` IN ('DUAS20', 'VERAO26', 'BF2025', 'PRIMEIRA');";
$out[] = "DELETE FROM `free_shipping_rules` WHERE `label` = 'Campanha de aniversário';\n";

// Inserção dos produtos oficiais
$out[] = "-- --------------------------------------------------------------------------";
$out[] = "-- PARTE 4: INSERÇÃO DOS 22 PRODUTOS OFICIAIS (Infos site.docx)";
$out[] = "-- --------------------------------------------------------------------------\n";

if (!empty($products)) {
    $out[] = "INSERT INTO `products` (`id`, `name`, `slug`, `category`, `description`, `composition`, `care_instructions`, `instagram_url`, `measurements`, `price`, `sale_price`, `sale_starts_at`, `sale_ends_at`, `is_new_release`, `is_active`, `created_at`) VALUES";
    $pVals = [];
    foreach ($products as $p) {
        $pVals[] = sprintf(
            "(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d, %s)",
            (int)$p['id'],
            sql_val($p['name']),
            sql_val($p['slug']),
            sql_val($p['category']),
            sql_val($p['description']),
            sql_val($p['composition']),
            sql_val($p['care_instructions']),
            sql_val($p['instagram_url']),
            sql_val($p['measurements']),
            sql_val($p['price']),
            sql_val($p['sale_price']),
            sql_val($p['sale_starts_at']),
            sql_val($p['sale_ends_at']),
            (int)$p['is_new_release'],
            (int)$p['is_active'],
            sql_val($p['created_at'])
        );
    }
    $out[] = implode(",\n", $pVals) . ";\n";
}

// Inserção das imagens dos produtos
if (!empty($productImages)) {
    $out[] = "INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `position`) VALUES";
    $imgVals = [];
    foreach ($productImages as $im) {
        $imgVals[] = sprintf(
            "(%d, %d, %s, %d)",
            (int)$im['id'],
            (int)$im['product_id'],
            sql_val($im['image_url']),
            (int)$im['position']
        );
    }
    $out[] = implode(",\n", $imgVals) . ";\n";
}

// Inserção dos tamanhos e estoques
if (!empty($productSizes)) {
    $out[] = "INSERT INTO `product_sizes` (`id`, `product_id`, `size`, `stock`, `position`) VALUES";
    $szVals = [];
    foreach ($productSizes as $sz) {
        $szVals[] = sprintf(
            "(%d, %d, %s, %d, %d)",
            (int)$sz['id'],
            (int)$sz['product_id'],
            sql_val($sz['size']),
            (int)$sz['stock'],
            (int)$sz['position']
        );
    }
    $out[] = implode(",\n", $szVals) . ";\n";
}

$out[] = "SET FOREIGN_KEY_CHECKS = 1;\n";
$out[] = "-- === FIM DA ATUALIZAÇÃO DE PRODUÇÃO ===\n";

$finalSql = implode("\n", $out);
$targetFile = __DIR__ . '/../sql/duas-atualizar-producao.sql';
file_put_contents($targetFile, $finalSql);

echo "Arquivo gerado com sucesso: $targetFile\n";
echo "Tamanho: " . strlen($finalSql) . " bytes\n";
