<?php
// ==========================================================================
// DUÁS - Gera sql/duas-atualizar.sql a partir de sql/duas-banco.sql.
//
// Rode sempre que mexer no schema (duas-banco.sql):
//     php bin/gerar-atualizacao.php
//
// O script de atualização é derivado do schema canônico, então os dois
// arquivos nunca saem de sincronia.
// ==========================================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado pela linha de comando.\n");
}

$raiz    = dirname(__DIR__);
$origem  = $raiz . '/sql/duas-banco.sql';
$destino = $raiz . '/sql/duas-atualizar.sql';

$sql = file_get_contents($origem);
if ($sql === false) { fwrite(STDERR, "nao consegui ler $origem\n"); exit(1); }

// ---- 1. extrair os blocos CREATE TABLE ----------------------------------
preg_match_all(
    '/CREATE TABLE IF NOT EXISTS `([a-z_]+)` \((.*?)\n\) ENGINE=[^;]*;/s',
    $sql,
    $matches,
    PREG_SET_ORDER
);

if (!$matches) { fwrite(STDERR, "nenhuma tabela encontrada\n"); exit(1); }

$q = fn(string $s): string => str_replace("'", "''", $s);

$out = [];
$totalCols = 0;
$totalIdx  = 0;
$totalFk   = 0;

foreach ($matches as $m) {
    $tabela = $m[1];
    $corpo  = $m[2];

    // o bloco inteiro, para criar a tabela caso ela nao exista.
    // duas_tab vem antes do CREATE porque so ai da para saber se faltava.
    $out[] = '';
    $out[] = "-- ---------- $tabela ----------";
    $out[] = sprintf("CALL duas_tab('%s');", $q($tabela));
    $out[] = trim($m[0]);
    $out[] = '';

    $colunas = [];
    $indices = [];
    $fks     = [];

    foreach (preg_split('/\n/', $corpo) as $linha) {
        $linha = trim(rtrim(trim($linha), ','));
        if ($linha === '') { continue; }

        if (preg_match('/^`([a-z_]+)` (.+)$/', $linha, $c)) {
            $colunas[] = [$c[1], $c[2]];
        } elseif (str_starts_with($linha, 'PRIMARY KEY')) {
            continue; // vem junto com a criacao da tabela
        } elseif (preg_match('/^(UNIQUE )?KEY `([a-z_]+)` (\(.+\))$/', $linha, $k)) {
            $indices[] = [$k[2], trim($k[1]) . ' KEY `' . $k[2] . '` ' . $k[3]];
        } elseif (preg_match('/^CONSTRAINT `([a-z_]+)` (.+)$/', $linha, $f)) {
            $fks[] = [$f[1], 'CONSTRAINT `' . $f[1] . '` ' . $f[2]];
        }
    }

    // colunas, na ordem, cada uma posicionada depois da anterior.
    // AUTO_INCREMENT fica de fora: so existe junto com a chave primaria,
    // que ja vem na criacao da tabela.
    $anterior = '#FIRST';
    foreach ($colunas as [$nome, $def]) {
        if (!str_contains($def, 'AUTO_INCREMENT')) {
            $out[] = sprintf(
                "CALL duas_col('%s', '%s', '%s', '%s');",
                $q($tabela), $q($nome), $q($def), $q($anterior)
            );
            $totalCols++;
        }
        $anterior = $nome;
    }

    foreach ($indices as [$nome, $def]) {
        $out[] = sprintf(
            "CALL duas_idx('%s', '%s', '%s');",
            $q($tabela), $q($nome), $q($def)
        );
        $totalIdx++;
    }

    foreach ($fks as [$nome, $def]) {
        $out[] = sprintf(
            "CALL duas_fk('%s', '%s', '%s');",
            $q($tabela), $q($nome), $q($def)
        );
        $totalFk++;
    }
}

$estrutura = implode("\n", $out);

// ---- 2. montar o arquivo final -----------------------------------------
$cabecalho = <<<'SQL'
-- ==========================================================================
-- DUAS | ATUALIZAR O BANCO - rodar ANTES de cada nova versao em producao
--
-- O git push leva o codigo para a Hostinger, mas nao mexe no banco.
-- Este arquivo coloca o banco existente na estrutura da versao atual.
--
-- E SEGURO: nao apaga nem sobrescreve nada.
--   - cria tabelas que faltam        (CREATE TABLE IF NOT EXISTS)
--   - cria colunas que faltam        (so se ainda nao existirem)
--   - cria indices e chaves que faltam
--   - insere configuracao inicial so quando ela ainda nao existe
--   - NAO altera produtos, pedidos, textos do site nem a senha do painel
--
-- Pode ser executado quantas vezes quiser: rodar duas vezes nao muda nada.
--
-- COMO RODAR NO phpMyAdmin:
--   1. selecione o banco na lista da esquerda
--   2. aba Importar -> escolher este arquivo -> Executar
--   3. no fim aparece a lista do que foi alterado
--      ("Banco ja estava atualizado" = nada a fazer)
--
-- Faca um backup antes (aba Exportar) - leva alguns segundos e evita sustos.
-- ==========================================================================

SET NAMES utf8mb4;

-- ---------- relatorio do que for alterado ----------

DROP TEMPORARY TABLE IF EXISTS `duas_update_log`;
CREATE TEMPORARY TABLE `duas_update_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `alteracao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- ajudantes ----------
-- O MySQL 8 nao tem "ADD COLUMN IF NOT EXISTS", entao estes tres
-- procedimentos consultam o information_schema antes de alterar.
-- Eles sao removidos no fim do arquivo.

DROP PROCEDURE IF EXISTS `duas_tab`;
DROP PROCEDURE IF EXISTS `duas_col`;
DROP PROCEDURE IF EXISTS `duas_idx`;
DROP PROCEDURE IF EXISTS `duas_fk`;

DELIMITER $$

CREATE PROCEDURE `duas_tab`(IN p_tabela VARCHAR(64))
BEGIN
    DECLARE v_tem_tabela INT DEFAULT 0;

    SELECT COUNT(*) INTO v_tem_tabela
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabela;

    IF v_tem_tabela = 0 THEN
        INSERT INTO `duas_update_log` (`alteracao`)
        VALUES (CONCAT('tabela criada: ', p_tabela));
    END IF;
END$$

CREATE PROCEDURE `duas_col`(
    IN p_tabela VARCHAR(64),
    IN p_coluna VARCHAR(64),
    IN p_def    TEXT,
    IN p_depois VARCHAR(64)
)
BEGIN
    DECLARE v_tem_tabela INT DEFAULT 0;
    DECLARE v_tem_coluna INT DEFAULT 0;

    SELECT COUNT(*) INTO v_tem_tabela
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabela;

    SELECT COUNT(*) INTO v_tem_coluna
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabela AND COLUMN_NAME = p_coluna;

    IF v_tem_tabela = 1 AND v_tem_coluna = 0 THEN
        SET @ddl = CONCAT(
            'ALTER TABLE `', p_tabela, '` ADD COLUMN `', p_coluna, '` ', p_def,
            CASE
                WHEN p_depois = '#FIRST' THEN ' FIRST'
                ELSE CONCAT(' AFTER `', p_depois, '`')
            END
        );
        PREPARE st FROM @ddl; EXECUTE st; DEALLOCATE PREPARE st;
        INSERT INTO `duas_update_log` (`alteracao`)
        VALUES (CONCAT('coluna criada: ', p_tabela, '.', p_coluna));
    END IF;
END$$

CREATE PROCEDURE `duas_idx`(
    IN p_tabela VARCHAR(64),
    IN p_nome   VARCHAR(64),
    IN p_def    TEXT
)
BEGIN
    DECLARE v_tem_tabela INT DEFAULT 0;
    DECLARE v_tem_indice INT DEFAULT 0;

    SELECT COUNT(*) INTO v_tem_tabela
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabela;

    SELECT COUNT(*) INTO v_tem_indice
      FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabela AND INDEX_NAME = p_nome;

    IF v_tem_tabela = 1 AND v_tem_indice = 0 THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_tabela, '` ADD ', p_def);
        PREPARE st FROM @ddl; EXECUTE st; DEALLOCATE PREPARE st;
        INSERT INTO `duas_update_log` (`alteracao`)
        VALUES (CONCAT('indice criado: ', p_tabela, '.', p_nome));
    END IF;
END$$

CREATE PROCEDURE `duas_fk`(
    IN p_tabela VARCHAR(64),
    IN p_nome   VARCHAR(64),
    IN p_def    TEXT
)
BEGIN
    DECLARE v_tem_tabela INT DEFAULT 0;
    DECLARE v_tem_fk     INT DEFAULT 0;

    SELECT COUNT(*) INTO v_tem_tabela
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabela;

    SELECT COUNT(*) INTO v_tem_fk
      FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabela
       AND CONSTRAINT_NAME = p_nome AND CONSTRAINT_TYPE = 'FOREIGN KEY';

    IF v_tem_tabela = 1 AND v_tem_fk = 0 THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_tabela, '` ADD ', p_def);
        PREPARE st FROM @ddl; EXECUTE st; DEALLOCATE PREPARE st;
        INSERT INTO `duas_update_log` (`alteracao`)
        VALUES (CONCAT('chave estrangeira criada: ', p_tabela, '.', p_nome));
    END IF;
END$$

DELIMITER ;

-- ==========================================================================
-- ESTRUTURA
-- As tabelas estao na ordem de dependencia, entao o arquivo funciona com a
-- verificacao de chaves estrangeiras ligada ou desligada.
-- ==========================================================================
SQL;

$rodape = <<<'SQL'


-- ==========================================================================
-- CONFIGURACAO INICIAL
-- Tudo aqui so entra quando ainda nao existe. O que o painel ja editou
-- (texto da faixa, valor do frete gratis, credenciais, senha) fica intacto.
-- ==========================================================================

INSERT INTO `payment_methods` (`provider`,`label`,`environment`,`credentials`,`instructions`,`is_active`,`sort_order`)
SELECT 'mercado_pago', 'Mercado Pago', 'sandbox', '{\"public_key\":\"\",\"access_token\":\"\"}', 'Cartão de crédito, Pix e boleto processados via Mercado Pago.', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `payment_methods` WHERE `provider` = 'mercado_pago');

INSERT INTO `shipping_methods` (`carrier`,`label`,`service_code`,`settings`,`flat_rate`,`free_above`,`estimated_days_min`,`estimated_days_max`,`is_active`,`sort_order`)
SELECT 'correios', 'Correios PAC', '03298', '{\"origin_cep\": \"01310100\", \"contract_code\": \"\", \"contract_password\": \"\"}', NULL, 800, 5, 9, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shipping_methods` WHERE `carrier` = 'correios' AND `label` = 'Correios PAC');

INSERT INTO `shipping_methods` (`carrier`,`label`,`service_code`,`settings`,`flat_rate`,`free_above`,`estimated_days_min`,`estimated_days_max`,`is_active`,`sort_order`)
SELECT 'correios', 'Correios SEDEX', '03220', '{\"origin_cep\": \"01310100\", \"contract_code\": \"\", \"contract_password\": \"\"}', 24.9, NULL, 2, 4, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `shipping_methods` WHERE `carrier` = 'correios' AND `label` = 'Correios SEDEX');

INSERT INTO `free_shipping_rules` (`label`,`min_subtotal`,`starts_at`,`ends_at`,`is_active`)
SELECT 'Frete grátis padrão', 800, NULL, NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM `free_shipping_rules` WHERE `label` = 'Frete grátis padrão');

-- INSERT IGNORE: se a faixa do topo ja foi editada no painel, o texto atual e mantido.
INSERT IGNORE INTO `site_settings` (`setting_key`,`setting_value`) VALUES
  ('announcement_active', '1'),
  ('announcement_text', 'COLEÇÃO PRIMAVERA/VERÃO • FRETE GRÁTIS EM COMPRAS ACIMA DE R$ 800,00 • ATÉ 6X SEM JUROS');

INSERT INTO `promotions` (`name`,`code`,`discount_type`,`discount_value`,`min_subtotal`,`starts_at`,`ends_at`,`usage_limit`,`is_active`)
SELECT 'Cupom de boas-vindas', 'DUAS10', 'percent', 10, 0, NULL, NULL, NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM `promotions` WHERE `code` = 'DUAS10');

-- Usuario do painel: criado apenas se nao houver nenhum. A senha nunca e redefinida.
INSERT INTO `admin_users` (`name`,`email`,`password_hash`,`is_active`)
SELECT 'Administrador Duas', 'admin@duasporll.com.br', '$2y$12$q4DXo/6z3XdoH7QDb4UMfO989Y3HBuxS7BzmqQ2r04s9rwpystARm', 1
WHERE NOT EXISTS (SELECT 1 FROM `admin_users` WHERE `email` = 'admin@duasporll.com.br');


-- ==========================================================================
-- LIMPEZA E RELATORIO
-- ==========================================================================

DROP PROCEDURE IF EXISTS `duas_tab`;
DROP PROCEDURE IF EXISTS `duas_col`;
DROP PROCEDURE IF EXISTS `duas_idx`;
DROP PROCEDURE IF EXISTS `duas_fk`;

-- Uma tabela temporaria nao pode aparecer duas vezes na mesma consulta,
-- por isso a contagem vai antes, para uma variavel.
SELECT COUNT(*) INTO @duas_mudancas FROM `duas_update_log`;

INSERT INTO `duas_update_log` (`alteracao`)
SELECT 'Banco ja estava atualizado - nenhuma alteracao de estrutura necessaria.'
  FROM DUAL WHERE @duas_mudancas = 0;

SELECT `alteracao` AS `O que foi alterado` FROM `duas_update_log` ORDER BY `id`;

DROP TEMPORARY TABLE IF EXISTS `duas_update_log`;
SQL;

$conteudo = $cabecalho . "\n" . $estrutura . $rodape . "\n";

file_put_contents($destino, $conteudo);

printf(
    "gerado: %s\n  tabelas: %d\n  colunas: %d\n  indices: %d\n  chaves estrangeiras: %d\n  bytes: %d\n",
    $destino, count($matches), $totalCols, $totalIdx, $totalFk, strlen($conteudo)
);
