-- ==========================================================================
-- DUÁS | ATUALIZAÇÃO COMPLETA DE PRODUÇÃO
-- 
-- Este script realiza em uma única operação segura:
-- 1. Atualização estrutural do banco (novas tabelas, colunas de estoque e tamanhos);
-- 2. Carga da grade completa de medidas e tamanhos (tabela sizes);
-- 3. Limpeza dos dados fictícios / de homologação;
-- 4. Carga dos 22 produtos oficiais do documento 'Infos site.docx' com fotos e estoques.
-- 
-- COMO APLICAR NO PHPMYADMIN / MYSQL PRODUÇÃO:
--   1. Selecione o banco de dados 'duas_db' na lista à esquerda;
--   2. Vá na aba 'Importar' (Import);
--   3. Selecione este arquivo e clique em 'Executar' (Go).
-- ==========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------------------
-- PARTE 1: ATUALIZAÇÃO ESTRUTURAL DE SCHEMA
-- --------------------------------------------------------------------------

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

-- ---------- products ----------
CALL duas_tab('products');
CREATE TABLE IF NOT EXISTS `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `composition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `care_instructions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instagram_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `measurements` json DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `sale_starts_at` datetime DEFAULT NULL,
  `sale_ends_at` datetime DEFAULT NULL,
  `is_new_release` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `idx_products_category` (`category`),
  KEY `idx_products_new_release` (`is_new_release`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('products', 'name', 'varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('products', 'slug', 'varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL', 'name');
CALL duas_col('products', 'category', 'varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL', 'slug');
CALL duas_col('products', 'description', 'text COLLATE utf8mb4_unicode_ci NOT NULL', 'category');
CALL duas_col('products', 'composition', 'varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'description');
CALL duas_col('products', 'care_instructions', 'varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'composition');
CALL duas_col('products', 'instagram_url', 'varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'care_instructions');
CALL duas_col('products', 'measurements', 'json DEFAULT NULL', 'instagram_url');
CALL duas_col('products', 'price', 'decimal(10,2) NOT NULL', 'measurements');
CALL duas_col('products', 'sale_price', 'decimal(10,2) DEFAULT NULL', 'price');
CALL duas_col('products', 'sale_starts_at', 'datetime DEFAULT NULL', 'sale_price');
CALL duas_col('products', 'sale_ends_at', 'datetime DEFAULT NULL', 'sale_starts_at');
CALL duas_col('products', 'is_new_release', 'tinyint(1) NOT NULL DEFAULT ''0''', 'sale_ends_at');
CALL duas_col('products', 'is_active', 'tinyint(1) NOT NULL DEFAULT ''1''', 'is_new_release');
CALL duas_col('products', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'is_active');
CALL duas_idx('products', 'uq_products_slug', 'UNIQUE KEY `uq_products_slug` (`slug`)');
CALL duas_idx('products', 'idx_products_category', ' KEY `idx_products_category` (`category`)');
CALL duas_idx('products', 'idx_products_new_release', ' KEY `idx_products_new_release` (`is_new_release`)');

-- ---------- customers ----------
CALL duas_tab('customers');
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('customers', 'name', 'varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('customers', 'email', 'varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL', 'name');
CALL duas_col('customers', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'email');
CALL duas_idx('customers', 'uq_customers_email', 'UNIQUE KEY `uq_customers_email` (`email`)');

-- ---------- admin_users ----------
CALL duas_tab('admin_users');
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('admin_users', 'name', 'varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('admin_users', 'email', 'varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL', 'name');
CALL duas_col('admin_users', 'password_hash', 'varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'email');
CALL duas_col('admin_users', 'is_active', 'tinyint(1) NOT NULL DEFAULT ''1''', 'password_hash');
CALL duas_col('admin_users', 'last_login_at', 'datetime DEFAULT NULL', 'is_active');
CALL duas_col('admin_users', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'last_login_at');
CALL duas_col('admin_users', 'updated_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'created_at');
CALL duas_idx('admin_users', 'uq_admin_users_email', 'UNIQUE KEY `uq_admin_users_email` (`email`)');

-- ---------- blog_posts ----------
CALL duas_tab('blog_posts');
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_time` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `cover_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_format` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `published_at` date DEFAULT NULL,
  `published_label` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_slug` (`slug`),
  KEY `idx_blog_published` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('blog_posts', 'title', 'varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('blog_posts', 'slug', 'varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'title');
CALL duas_col('blog_posts', 'category', 'varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'slug');
CALL duas_col('blog_posts', 'read_time', 'varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'category');
CALL duas_col('blog_posts', 'excerpt', 'text COLLATE utf8mb4_unicode_ci', 'read_time');
CALL duas_col('blog_posts', 'cover_image', 'varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'excerpt');
CALL duas_col('blog_posts', 'content', 'mediumtext COLLATE utf8mb4_unicode_ci NOT NULL', 'cover_image');
CALL duas_col('blog_posts', 'content_format', 'varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''text''', 'content');
CALL duas_col('blog_posts', 'is_active', 'tinyint(1) NOT NULL DEFAULT ''1''', 'content_format');
CALL duas_col('blog_posts', 'published_at', 'date DEFAULT NULL', 'is_active');
CALL duas_col('blog_posts', 'published_label', 'varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'published_at');
CALL duas_col('blog_posts', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'published_label');
CALL duas_idx('blog_posts', 'uq_blog_slug', 'UNIQUE KEY `uq_blog_slug` (`slug`)');
CALL duas_idx('blog_posts', 'idx_blog_published', ' KEY `idx_blog_published` (`published_at`)');

-- ---------- contact_messages ----------
CALL duas_tab('contact_messages');
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('contact_messages', 'name', 'varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('contact_messages', 'email', 'varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL', 'name');
CALL duas_col('contact_messages', 'subject', 'varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'email');
CALL duas_col('contact_messages', 'message', 'text COLLATE utf8mb4_unicode_ci NOT NULL', 'subject');
CALL duas_col('contact_messages', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'message');

-- ---------- newsletter_subscribers ----------
CALL duas_tab('newsletter_subscribers');
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_news_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('newsletter_subscribers', 'email', 'varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('newsletter_subscribers', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'email');
CALL duas_idx('newsletter_subscribers', 'uq_news_email', 'UNIQUE KEY `uq_news_email` (`email`)');

-- ---------- payment_methods ----------
CALL duas_tab('payment_methods');
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `environment` enum('sandbox','production') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sandbox',
  `credentials` json DEFAULT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payment_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('payment_methods', 'provider', 'varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('payment_methods', 'label', 'varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL', 'provider');
CALL duas_col('payment_methods', 'environment', 'enum(''sandbox'',''production'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''sandbox''', 'label');
CALL duas_col('payment_methods', 'credentials', 'json DEFAULT NULL', 'environment');
CALL duas_col('payment_methods', 'instructions', 'text COLLATE utf8mb4_unicode_ci', 'credentials');
CALL duas_col('payment_methods', 'is_active', 'tinyint(1) NOT NULL DEFAULT ''1''', 'instructions');
CALL duas_col('payment_methods', 'sort_order', 'int NOT NULL DEFAULT ''0''', 'is_active');
CALL duas_col('payment_methods', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'sort_order');
CALL duas_col('payment_methods', 'updated_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'created_at');
CALL duas_idx('payment_methods', 'idx_payment_active', ' KEY `idx_payment_active` (`is_active`,`sort_order`)');

-- ---------- promotions ----------
CALL duas_tab('promotions');
CREATE TABLE IF NOT EXISTS `promotions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_type` enum('percent','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `discount_value` decimal(10,2) NOT NULL,
  `min_subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `usage_limit` int unsigned DEFAULT NULL,
  `used_count` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_promotions_code` (`code`),
  KEY `idx_promotions_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('promotions', 'name', 'varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('promotions', 'code', 'varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'name');
CALL duas_col('promotions', 'discount_type', 'enum(''percent'',''fixed'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''percent''', 'code');
CALL duas_col('promotions', 'discount_value', 'decimal(10,2) NOT NULL', 'discount_type');
CALL duas_col('promotions', 'min_subtotal', 'decimal(10,2) NOT NULL DEFAULT ''0.00''', 'discount_value');
CALL duas_col('promotions', 'starts_at', 'datetime DEFAULT NULL', 'min_subtotal');
CALL duas_col('promotions', 'ends_at', 'datetime DEFAULT NULL', 'starts_at');
CALL duas_col('promotions', 'usage_limit', 'int unsigned DEFAULT NULL', 'ends_at');
CALL duas_col('promotions', 'used_count', 'int unsigned NOT NULL DEFAULT ''0''', 'usage_limit');
CALL duas_col('promotions', 'is_active', 'tinyint(1) NOT NULL DEFAULT ''1''', 'used_count');
CALL duas_col('promotions', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'is_active');
CALL duas_col('promotions', 'updated_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'created_at');
CALL duas_idx('promotions', 'uq_promotions_code', 'UNIQUE KEY `uq_promotions_code` (`code`)');
CALL duas_idx('promotions', 'idx_promotions_active', ' KEY `idx_promotions_active` (`is_active`)');

-- ---------- free_shipping_rules ----------
CALL duas_tab('free_shipping_rules');
CREATE TABLE IF NOT EXISTS `free_shipping_rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_subtotal` decimal(10,2) NOT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_free_shipping_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('free_shipping_rules', 'label', 'varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('free_shipping_rules', 'min_subtotal', 'decimal(10,2) NOT NULL', 'label');
CALL duas_col('free_shipping_rules', 'starts_at', 'datetime DEFAULT NULL', 'min_subtotal');
CALL duas_col('free_shipping_rules', 'ends_at', 'datetime DEFAULT NULL', 'starts_at');
CALL duas_col('free_shipping_rules', 'is_active', 'tinyint(1) NOT NULL DEFAULT ''1''', 'ends_at');
CALL duas_col('free_shipping_rules', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'is_active');
CALL duas_col('free_shipping_rules', 'updated_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'created_at');
CALL duas_idx('free_shipping_rules', 'idx_free_shipping_active', ' KEY `idx_free_shipping_active` (`is_active`)');

-- ---------- shipping_methods ----------
CALL duas_tab('shipping_methods');
CREATE TABLE IF NOT EXISTS `shipping_methods` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `carrier` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `flat_rate` decimal(10,2) DEFAULT NULL,
  `free_above` decimal(10,2) DEFAULT NULL,
  `estimated_days_min` int DEFAULT NULL,
  `estimated_days_max` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shipping_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('shipping_methods', 'carrier', 'varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('shipping_methods', 'label', 'varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL', 'carrier');
CALL duas_col('shipping_methods', 'service_code', 'varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'label');
CALL duas_col('shipping_methods', 'settings', 'json DEFAULT NULL', 'service_code');
CALL duas_col('shipping_methods', 'flat_rate', 'decimal(10,2) DEFAULT NULL', 'settings');
CALL duas_col('shipping_methods', 'free_above', 'decimal(10,2) DEFAULT NULL', 'flat_rate');
CALL duas_col('shipping_methods', 'estimated_days_min', 'int DEFAULT NULL', 'free_above');
CALL duas_col('shipping_methods', 'estimated_days_max', 'int DEFAULT NULL', 'estimated_days_min');
CALL duas_col('shipping_methods', 'is_active', 'tinyint(1) NOT NULL DEFAULT ''1''', 'estimated_days_max');
CALL duas_col('shipping_methods', 'sort_order', 'int NOT NULL DEFAULT ''0''', 'is_active');
CALL duas_col('shipping_methods', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'sort_order');
CALL duas_col('shipping_methods', 'updated_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'created_at');
CALL duas_idx('shipping_methods', 'idx_shipping_active', ' KEY `idx_shipping_active` (`is_active`,`sort_order`)');

-- ---------- site_settings ----------
CALL duas_tab('site_settings');
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('site_settings', 'setting_key', 'varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL', '#FIRST');
CALL duas_col('site_settings', 'setting_value', 'text COLLATE utf8mb4_unicode_ci', 'setting_key');
CALL duas_col('site_settings', 'updated_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'setting_value');

-- ---------- orders ----------
CALL duas_tab('orders');
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int unsigned DEFAULT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Em Separação',
  `payment_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_status_detail` varchar(90) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_provider` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_label` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_ref` int unsigned DEFAULT NULL,
  `mp_preference_id` varchar(90) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mp_payment_id` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mp_init_point` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_raw` json DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `fulfillment_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aguardando_pagamento',
  `shipping_label` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_code` varchar(90) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `separated_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `customer_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_doc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cep` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_street` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_complement` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_district` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_city` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_state` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consent_at` datetime DEFAULT NULL,
  `consent_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `shipping` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `coupon_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `placed_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_code` (`order_code`),
  KEY `idx_orders_customer` (`customer_id`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('orders', 'order_code', 'varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('orders', 'customer_id', 'int unsigned DEFAULT NULL', 'order_code');
CALL duas_col('orders', 'status', 'varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''Em Separação''', 'customer_id');
CALL duas_col('orders', 'payment_status', 'varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''pending''', 'status');
CALL duas_col('orders', 'payment_status_detail', 'varchar(90) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'payment_status');
CALL duas_col('orders', 'payment_provider', 'varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'payment_status_detail');
CALL duas_col('orders', 'payment_label', 'varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'payment_provider');
CALL duas_col('orders', 'payment_method_ref', 'int unsigned DEFAULT NULL', 'payment_label');
CALL duas_col('orders', 'mp_preference_id', 'varchar(90) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'payment_method_ref');
CALL duas_col('orders', 'mp_payment_id', 'varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'mp_preference_id');
CALL duas_col('orders', 'mp_init_point', 'varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'mp_payment_id');
CALL duas_col('orders', 'payment_raw', 'json DEFAULT NULL', 'mp_init_point');
CALL duas_col('orders', 'paid_at', 'datetime DEFAULT NULL', 'payment_raw');
CALL duas_col('orders', 'fulfillment_status', 'varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''aguardando_pagamento''', 'paid_at');
CALL duas_col('orders', 'shipping_label', 'varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'fulfillment_status');
CALL duas_col('orders', 'tracking_code', 'varchar(90) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'shipping_label');
CALL duas_col('orders', 'separated_at', 'datetime DEFAULT NULL', 'tracking_code');
CALL duas_col('orders', 'shipped_at', 'datetime DEFAULT NULL', 'separated_at');
CALL duas_col('orders', 'delivered_at', 'datetime DEFAULT NULL', 'shipped_at');
CALL duas_col('orders', 'cancelled_at', 'datetime DEFAULT NULL', 'delivered_at');
CALL duas_col('orders', 'customer_name', 'varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'cancelled_at');
CALL duas_col('orders', 'customer_email', 'varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'customer_name');
CALL duas_col('orders', 'customer_phone', 'varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'customer_email');
CALL duas_col('orders', 'customer_doc', 'varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'customer_phone');
CALL duas_col('orders', 'shipping_address', 'varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'customer_doc');
CALL duas_col('orders', 'shipping_cep', 'varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'shipping_address');
CALL duas_col('orders', 'shipping_street', 'varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'shipping_cep');
CALL duas_col('orders', 'shipping_number', 'varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'shipping_street');
CALL duas_col('orders', 'shipping_complement', 'varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'shipping_number');
CALL duas_col('orders', 'shipping_district', 'varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'shipping_complement');
CALL duas_col('orders', 'shipping_city', 'varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'shipping_district');
CALL duas_col('orders', 'shipping_state', 'varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'shipping_city');
CALL duas_col('orders', 'consent_at', 'datetime DEFAULT NULL', 'shipping_state');
CALL duas_col('orders', 'consent_ip', 'varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'consent_at');
CALL duas_col('orders', 'subtotal', 'decimal(10,2) NOT NULL DEFAULT ''0.00''', 'consent_ip');
CALL duas_col('orders', 'discount', 'decimal(10,2) NOT NULL DEFAULT ''0.00''', 'subtotal');
CALL duas_col('orders', 'shipping', 'decimal(10,2) NOT NULL DEFAULT ''0.00''', 'discount');
CALL duas_col('orders', 'total', 'decimal(10,2) NOT NULL DEFAULT ''0.00''', 'shipping');
CALL duas_col('orders', 'coupon_code', 'varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'total');
CALL duas_col('orders', 'placed_at', 'date DEFAULT NULL', 'coupon_code');
CALL duas_col('orders', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'placed_at');
CALL duas_idx('orders', 'uq_orders_code', 'UNIQUE KEY `uq_orders_code` (`order_code`)');
CALL duas_idx('orders', 'idx_orders_customer', ' KEY `idx_orders_customer` (`customer_id`)');
CALL duas_fk('orders', 'fk_orders_customer', 'CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL');

-- ---------- sizes ----------
CALL duas_tab('sizes');
CREATE TABLE IF NOT EXISTS `sizes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'letra',
  `busto_hint` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `cintura_hint` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `quadril_hint` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `comprimento_hint` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sizes_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('sizes', 'code', 'varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('sizes', 'name', 'varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL', 'code');
CALL duas_col('sizes', 'category', 'varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''letra''', 'name');
CALL duas_col('sizes', 'busto_hint', 'varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT ''''', 'category');
CALL duas_col('sizes', 'cintura_hint', 'varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT ''''', 'busto_hint');
CALL duas_col('sizes', 'quadril_hint', 'varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT ''''', 'cintura_hint');
CALL duas_col('sizes', 'comprimento_hint', 'varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT ''''', 'quadril_hint');
CALL duas_col('sizes', 'is_active', 'tinyint(1) NOT NULL DEFAULT ''1''', 'comprimento_hint');
CALL duas_col('sizes', 'sort_order', 'int NOT NULL DEFAULT ''0''', 'is_active');
CALL duas_col('sizes', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'sort_order');
CALL duas_idx('sizes', 'uq_sizes_code', 'UNIQUE KEY `uq_sizes_code` (`code`)');

-- ---------- cart_items ----------
CALL duas_tab('cart_items');
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int unsigned NOT NULL,
  `selected_size` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_line` (`session_id`,`product_id`,`selected_size`),
  KEY `idx_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('cart_items', 'session_id', 'varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL', 'id');
CALL duas_col('cart_items', 'product_id', 'int unsigned NOT NULL', 'session_id');
CALL duas_col('cart_items', 'selected_size', 'varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL', 'product_id');
CALL duas_col('cart_items', 'quantity', 'int unsigned NOT NULL DEFAULT ''1''', 'selected_size');
CALL duas_col('cart_items', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'quantity');
CALL duas_col('cart_items', 'updated_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'created_at');
CALL duas_idx('cart_items', 'uq_cart_line', 'UNIQUE KEY `uq_cart_line` (`session_id`,`product_id`,`selected_size`)');
CALL duas_idx('cart_items', 'idx_cart_product', ' KEY `idx_cart_product` (`product_id`)');
CALL duas_fk('cart_items', 'fk_cart_product', 'CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE');

-- ---------- product_images ----------
CALL duas_tab('product_images');
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_pimg_product` (`product_id`),
  CONSTRAINT `fk_pimg_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('product_images', 'product_id', 'int unsigned NOT NULL', 'id');
CALL duas_col('product_images', 'image_url', 'varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL', 'product_id');
CALL duas_col('product_images', 'position', 'tinyint unsigned NOT NULL DEFAULT ''0''', 'image_url');
CALL duas_idx('product_images', 'idx_pimg_product', ' KEY `idx_pimg_product` (`product_id`)');
CALL duas_fk('product_images', 'fk_pimg_product', 'CONSTRAINT `fk_pimg_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE');

-- ---------- product_sizes ----------
CALL duas_tab('product_sizes');
CREATE TABLE IF NOT EXISTS `product_sizes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `size` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stock` int unsigned NOT NULL DEFAULT '5',
  `position` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_size` (`product_id`,`size`),
  CONSTRAINT `fk_psize_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('product_sizes', 'product_id', 'int unsigned NOT NULL', 'id');
CALL duas_col('product_sizes', 'size', 'varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL', 'product_id');
CALL duas_col('product_sizes', 'stock', 'int unsigned NOT NULL DEFAULT ''5''', 'size');
CALL duas_col('product_sizes', 'position', 'tinyint unsigned NOT NULL DEFAULT ''0''', 'stock');
CALL duas_idx('product_sizes', 'uq_product_size', 'UNIQUE KEY `uq_product_size` (`product_id`,`size`)');
CALL duas_fk('product_sizes', 'fk_psize_product', 'CONSTRAINT `fk_psize_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE');

-- ---------- order_items ----------
CALL duas_tab('order_items');
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `product_id` int unsigned DEFAULT NULL,
  `product_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order` (`order_id`),
  KEY `idx_oi_product` (`product_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('order_items', 'order_id', 'int unsigned NOT NULL', 'id');
CALL duas_col('order_items', 'product_id', 'int unsigned DEFAULT NULL', 'order_id');
CALL duas_col('order_items', 'product_name', 'varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL', 'product_id');
CALL duas_col('order_items', 'size', 'varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL', 'product_name');
CALL duas_col('order_items', 'quantity', 'int unsigned NOT NULL DEFAULT ''1''', 'size');
CALL duas_col('order_items', 'unit_price', 'decimal(10,2) NOT NULL', 'quantity');
CALL duas_idx('order_items', 'idx_oi_order', ' KEY `idx_oi_order` (`order_id`)');
CALL duas_idx('order_items', 'idx_oi_product', ' KEY `idx_oi_product` (`product_id`)');
CALL duas_fk('order_items', 'fk_oi_order', 'CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE');
CALL duas_fk('order_items', 'fk_oi_product', 'CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL');

-- ---------- stock_notifications ----------
CALL duas_tab('stock_notifications');
CREATE TABLE IF NOT EXISTS `stock_notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `product_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','notified') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sn_product` (`product_id`,`size`),
  KEY `idx_sn_email` (`customer_email`),
  CONSTRAINT `fk_sn_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('stock_notifications', 'product_id', 'int unsigned NOT NULL', 'id');
CALL duas_col('stock_notifications', 'product_name', 'varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL', 'product_id');
CALL duas_col('stock_notifications', 'size', 'varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL', 'product_name');
CALL duas_col('stock_notifications', 'customer_name', 'varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL', 'size');
CALL duas_col('stock_notifications', 'customer_email', 'varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL', 'customer_name');
CALL duas_col('stock_notifications', 'status', 'enum(''pending'',''notified'') NOT NULL DEFAULT ''pending''', 'customer_email');
CALL duas_col('stock_notifications', 'notes', 'text DEFAULT NULL', 'status');
CALL duas_col('stock_notifications', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'notes');
CALL duas_col('stock_notifications', 'notified_at', 'datetime DEFAULT NULL', 'created_at');
CALL duas_idx('stock_notifications', 'idx_sn_product', ' KEY `idx_sn_product` (`product_id`,`size`)');
CALL duas_idx('stock_notifications', 'idx_sn_email', ' KEY `idx_sn_email` (`customer_email`)');
CALL duas_fk('stock_notifications', 'fk_sn_product', 'CONSTRAINT `fk_sn_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE');

-- ---------- order_events ----------
CALL duas_tab('order_events');
CREATE TABLE IF NOT EXISTS `order_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `event_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` varchar(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `admin_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_events_order` (`order_id`,`id`),
  CONSTRAINT `fk_order_events_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('order_events', 'order_id', 'int unsigned NOT NULL', 'id');
CALL duas_col('order_events', 'event_type', 'varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL', 'order_id');
CALL duas_col('order_events', 'from_status', 'varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'event_type');
CALL duas_col('order_events', 'to_status', 'varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'from_status');
CALL duas_col('order_events', 'message', 'varchar(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL', 'to_status');
CALL duas_col('order_events', 'meta', 'json DEFAULT NULL', 'message');
CALL duas_col('order_events', 'admin_id', 'int unsigned DEFAULT NULL', 'meta');
CALL duas_col('order_events', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', 'admin_id');
CALL duas_idx('order_events', 'idx_order_events_order', ' KEY `idx_order_events_order` (`order_id`,`id`)');
CALL duas_fk('order_events', 'fk_order_events_order', 'CONSTRAINT `fk_order_events_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE');

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

INSERT INTO `sizes` (`code`, `name`, `category`, `busto_hint`, `cintura_hint`, `quadril_hint`, `comprimento_hint`, `is_active`, `sort_order`) VALUES
('PP', 'Extra Pequeno (34)', 'letra', '80-84', '62-66', '90-94', '110', 1, 10),
('P', 'Pequeno (36/38)', 'letra', '84-88', '66-70', '94-98', '112', 1, 20),
('M', 'Médio (40)', 'letra', '90-94', '72-76', '100-104', '113', 1, 30),
('G', 'Grande (42)', 'letra', '96-100', '78-82', '106-110', '114', 1, 40),
('GG', 'Extra Grande (44)', 'letra', '102-106', '84-88', '112-116', '115', 1, 50),
('Extra G', 'Extra Grande Especial (46)', 'letra', '108-112', '90-94', '118-122', '116', 1, 60),
('G1', 'Plus Size 48 (G1)', 'letra', '114-118', '96-100', '124-128', '117', 0, 70),
('G2', 'Plus Size 50 (G2)', 'letra', '120-124', '102-106', '130-134', '118', 0, 80),
('G3', 'Plus Size 52 (G3)', 'letra', '126-130', '108-112', '136-140', '119', 0, 90),
('Único', 'Tamanho Único (U)', 'letra', '86-96', '68-78', '96-106', '113', 0, 100),
('34', 'Tamanho 34 (PP)', 'numero', '80-84', '62-66', '90-94', '110', 0, 110),
('36', 'Tamanho 36 (P)', 'numero', '84-88', '66-70', '94-98', '111', 0, 120),
('38', 'Tamanho 38 (P/M)', 'numero', '88-92', '70-74', '98-102', '112', 0, 130),
('40', 'Tamanho 40 (M)', 'numero', '92-96', '74-78', '102-106', '113', 0, 140),
('42', 'Tamanho 42 (G)', 'numero', '96-100', '78-82', '106-110', '114', 0, 150),
('44', 'Tamanho 44 (GG)', 'numero', '102-106', '84-88', '112-116', '115', 0, 160),
('46', 'Tamanho 46 (Extra G)', 'numero', '108-112', '90-94', '118-122', '116', 0, 170),
('48', 'Tamanho 48 (Plus)', 'numero', '114-118', '96-100', '124-128', '117', 0, 180),
('50', 'Tamanho 50 (Plus)', 'numero', '120-124', '102-106', '130-134', '118', 0, 190),
('52', 'Tamanho 52 (Plus)', 'numero', '126-130', '108-112', '136-140', '119', 0, 200)
ON DUPLICATE KEY UPDATE `code` = `code`;

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



-- --------------------------------------------------------------------------
-- PARTE 2: GRADE DE TAMANHOS (tabela sizes)
-- --------------------------------------------------------------------------

INSERT INTO `sizes` (`code`, `name`, `category`, `busto_hint`, `cintura_hint`, `quadril_hint`, `comprimento_hint`, `is_active`, `sort_order`) VALUES
('PP', 'Extra Pequeno (34)', 'letra', '80-84', '62-66', '90-94', '110', 1, 10),
('P', 'Pequeno (36/38)', 'letra', '84-88', '66-70', '94-98', '112', 1, 20),
('M', 'Médio (40)', 'letra', '90-94', '72-76', '100-104', '113', 1, 30),
('G', 'Grande (42)', 'letra', '96-100', '78-82', '106-110', '114', 1, 40),
('GG', 'Extra Grande (44)', 'letra', '102-106', '84-88', '112-116', '115', 1, 50),
('Extra G', 'Extra Grande Especial (46)', 'letra', '108-112', '90-94', '118-122', '116', 1, 60),
('G1', 'Plus Size 48 (G1)', 'letra', '114-118', '96-100', '124-128', '117', 0, 70),
('G2', 'Plus Size 50 (G2)', 'letra', '120-124', '102-106', '130-134', '118', 0, 80),
('G3', 'Plus Size 52 (G3)', 'letra', '126-130', '108-112', '136-140', '119', 0, 90),
('Único', 'Tamanho Único (U)', 'letra', '86-96', '68-78', '96-106', '113', 0, 100),
('34', 'Tamanho 34 (PP)', 'numero', '80-84', '62-66', '90-94', '110', 0, 110),
('36', 'Tamanho 36 (P)', 'numero', '84-88', '66-70', '94-98', '111', 0, 120),
('38', 'Tamanho 38 (P/M)', 'numero', '88-92', '70-74', '98-102', '112', 0, 130),
('40', 'Tamanho 40 (M)', 'numero', '92-96', '74-78', '102-106', '113', 0, 140),
('42', 'Tamanho 42 (G)', 'numero', '96-100', '78-82', '106-110', '114', 0, 150),
('44', 'Tamanho 44 (GG)', 'numero', '102-106', '84-88', '112-116', '115', 0, 160),
('46', 'Tamanho 46 (Extra G)', 'numero', '108-112', '90-94', '118-122', '116', 0, 170),
('48', 'Tamanho 48 (Plus)', 'numero', '114-118', '96-100', '124-128', '117', 0, 180),
('50', 'Tamanho 50 (Plus)', 'numero', '120-124', '102-106', '130-134', '118', 0, 190),
('52', 'Tamanho 52 (Plus)', 'numero', '126-130', '108-112', '136-140', '119', 0, 200)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `category`=VALUES(`category`), `busto_hint`=VALUES(`busto_hint`), `cintura_hint`=VALUES(`cintura_hint`), `quadril_hint`=VALUES(`quadril_hint`), `comprimento_hint`=VALUES(`comprimento_hint`), `sort_order`=VALUES(`sort_order`);

-- --------------------------------------------------------------------------
-- PARTE 3: LIMPEZA DE DADOS FICTÍCIOS / DEMONSTRAÇÃO
-- --------------------------------------------------------------------------

TRUNCATE TABLE `order_events`;
TRUNCATE TABLE `order_items`;
TRUNCATE TABLE `orders`;
TRUNCATE TABLE `customers`;
TRUNCATE TABLE `cart_items`;
TRUNCATE TABLE `stock_notifications`;
TRUNCATE TABLE `product_images`;
TRUNCATE TABLE `product_sizes`;
TRUNCATE TABLE `products`;
TRUNCATE TABLE `blog_posts`;
TRUNCATE TABLE `contact_messages`;
TRUNCATE TABLE `newsletter_subscribers`;
DELETE FROM `promotions` WHERE `code` IN ('DUAS20', 'VERAO26', 'BF2025', 'PRIMEIRA');
DELETE FROM `free_shipping_rules` WHERE `label` = 'Campanha de aniversário';

-- --------------------------------------------------------------------------
-- PARTE 4: INSERÇÃO DOS 22 PRODUTOS OFICIAIS (Infos site.docx)
-- --------------------------------------------------------------------------

INSERT INTO `products` (`id`, `name`, `slug`, `category`, `description`, `composition`, `care_instructions`, `instagram_url`, `measurements`, `price`, `sale_price`, `sale_starts_at`, `sale_ends_at`, `is_new_release`, `is_active`, `created_at`) VALUES
(1, 'Conjunto 10pm', 'conjunto-10pm', 'Conjuntos', 'O Conjunto 10pm é confeccionado em tecido vinil, trazendo um acabamento brilhante e um visual moderno. Composto por jaqueta cropped e short de cintura alta, o conjunto valoriza a silhueta com uma modelagem sofisticada e cheia de personalidade. A jaqueta possui gola alta, mangas longas, ombros estruturados e fechamento frontal por zíper, enquanto o short oferece um caimento confortável e elegante. Perfeito para festas, eventos e produções fashionistas, é uma peça versátil para quem busca um look marcante e contemporâneo.', 'Tecido vinil com acabamento brilhante.', 'Lavar à mão em água fria. Não torcer. Secar à sombra.', NULL, '{\"note\": \"A modelo veste PP. Cor: Azul Claro.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}', '1399.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(2, 'Vestido Martini', 'vestido-martini', 'Vestidos', 'O Vestido Martini é curto, em paetê grande com efeito perolado num tom off white que reflete a luz a cada movimento. O contraste é o que encanta: gola alta e frente comportada dão lugar a costas totalmente nuas, num decote profundo que revela na medida certa. Peça-desejo para as ocasiões que pedem protagonismo — réveillon, aniversários, festas e noites em que você quer ser a atração. Deixe o vestido falar por si: finalize com sandália de tira fina e brincos discretos para o brilho do paetê ser a estrela.', 'Paetê perolado de alto brilho com forro confortável.', 'Limpeza especializada a seco ou lavagem manual suave.', NULL, '{\"note\": \"A modelo veste PP. Cor: Bege / Off White.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"42\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"40\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"38\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"36\"}}}', '649.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(3, 'Vestido Champagne', 'vestido-champagne', 'Vestidos', 'O Vestido Champagne é longo, em malha lurex metalizada num tom marrom profundo que reflete a luz com sofisticação. O decote cowl drapeado cai fluido sobre o colo, as alças finas frente-única deixam as costas em evidência, e a fenda na barra libera o movimento uma peça que veste como líquido. Feito para as noites em que você quer elegância com atitude: jantares, festas e eventos que pedem um look de impacto sem esforço. Finalize com sandália de tira fina e acessórios minimalistas para o brilho do tecido ser protagonista.', 'Malha lurex metalizada com caimento líquido.', 'Para preservar a beleza da peça, vista e guarde conforme recebida. Manter amarrado original sem desfazê-lo. Evitar contato com anéis e superfícies ásperas.', NULL, '{\"note\": \"A modelo veste P. Cor: Bege / Marrom Metalizado.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"42\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"40\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"38\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"36\"}}}', '1349.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(4, 'Vestido Merlot', 'vestido-merlot', 'Vestidos', 'Modelo curto, tomara que caia, confeccionado em crepe musseline leve e com caimento impecável. Possui camadas de babados que criam movimento e volume na peça, deixando o visual moderno e super elegante.', 'Crepe musseline fluido e leve.', 'Lavar à mão ou a seco profissionalmente. Secar na horizontal à sombra.', NULL, '{\"note\": \"A modelo veste PP. Cor: Vermelho.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}', '749.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(5, 'Vestido Amour - Vermelho', 'vestido-amour-vermelho', 'Vestidos', 'Vestido curto tomara que caia com modelagem moderna e elegante na cor vermelho. Confeccionado em tecido estruturado, possui caimento impecável que valoriza a silhueta com sofisticação. O design minimalista e ajustado ao corpo traz um visual versátil e atemporal, perfeito para composições elegantes em festas, eventos e ocasiões especiais.', 'Tecido estruturado nobre encorpado.', 'Lavar à mão em temperatura ambiente. Não alvejar.', NULL, '{\"note\": \"A modelo veste PP. Cor: Vermelho.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}', '399.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(6, 'Vestido Amour - Preto', 'vestido-amour-preto', 'Vestidos', 'Vestido curto tomara que caia com modelagem moderna e elegante na cor preto. Confeccionado em tecido estruturado, possui caimento impecável que valoriza a silhueta com sofisticação. O design minimalista e ajustado ao corpo traz um visual versátil e atemporal, perfeito para composições elegantes em festas, eventos e ocasiões especiais.', 'Tecido estruturado nobre encorpado.', 'Lavar à mão em temperatura ambiente. Não alvejar.', NULL, '{\"note\": \"A modelo veste PP. Cor: Preto.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}', '399.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(7, 'Conjunto Moonlight', 'conjunto-moonlight', 'Conjuntos', 'Conjunto em alfaiataria, moderno e sofisticado, ideal para produções fashionistas com elegância minimalista. O cropped de gola alta possui modelagem estruturada e mangas curtas, trazendo um toque contemporâneo e refinado. O comprimento mais curto valoriza a silhueta e cria um contraste equilibrado com a parte inferior. A mini saia em alfaiataria apresenta corte preciso e caimento impecável, com detalhe de faixa alongada lateral que adiciona movimento e personalidade ao look, elevando a proposta clássica com um design atual. Confeccionado em tecido de alfaiataria encorpado, o conjunto une estrutura, conforto e acabamento elegante.', 'Alfaiataria encorpada premium com forro macio.', 'Lavagem delicada à mão ou a seco profissional.', NULL, '{\"note\": \"A modelo veste PP. Cor: Branco.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}', '749.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(8, 'Vestido Noir', 'vestido-noir', 'Vestidos', 'Confeccionado em tecido crepe musseline fluido de alta qualidade, o Noir combina sensualidade sofisticada com elegância atemporal. O bustier estruturado com cut out frontal cria um recorte estratégico que valoriza o decote com ousadia calculada, enquanto as alças finas adicionam delicadeza e leveza ao visual. A modelagem sereia abraça cada curva do corpo com precisão, culminando em uma cauda generosa que transforma qualquer passagem em um momento cinematográfico. Do tapete vermelho à formatura, do baile de gala ao casamento — o Noir foi criado para ocasiões que merecem ser eternas.', 'Crepe musseline fluido de alta gramatura.', 'Apenas lavagem especializada a seco.', NULL, '{\"note\": \"A modelo veste PP. Cor: Preta.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}', '649.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(9, 'Calça Sunrise', 'calca-sunrise', 'Calças', 'Calça Sunrise em tom off white com modelagem sofisticada e corte impecável. Desenvolvida para valorizar a silhueta com leveza e movimento, proporcionando conforto e elegância tanto para o cotidiano quanto para ocasiões especiais.', 'Tecido creponado encorpado com caimento pesado.', 'Lavar à mão ou em ciclo suave. Secar à sombra.', NULL, '{\"note\": \"A modelo veste P. Cor: Off White.\", \"rows\": {\"M\": {\"busto\": \"\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}}}', '224.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(10, 'Top Sunset - Amarelo Claro', 'top-sunset-amarelo-claro', 'Tops', 'Top Sunset na cor amarelo claro com modelagem minimalista e caimento confortável. Uma peça charmosa e fresca para combinações solares e descontraídas.', 'Algodão com elastano de toque suave.', 'Lavar com cores similares em água fria.', NULL, '{\"note\": \"A modelo veste P. Cor: Amarelo Claro.\", \"rows\": {\"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}', '49.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(11, 'Top Sunset - Marrom', 'top-sunset-marrom', 'Tops', 'Top Sunset na cor marrom com corte moderno e ajuste ao corpo. Uma peça básica sofisticada que transita perfeitamente do dia para a noite.', 'Algodão com elastano de toque macio.', 'Lavar com cores similares em água fria.', NULL, '{\"note\": \"A modelo veste M. Cor: Marrom.\", \"rows\": {\"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}', '49.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(12, 'Regata Lumi - Preta', 'regata-lumi-preta', 'Regatas', 'Regata Lumi em preto essencial com decote sutil e acabamento refinado. Perfeita para sobreposições contemporâneas com blazers ou saias fluidas.', 'Toque de seda com caimento fluido.', 'Lavar à mão com sabão neutro.', NULL, '{\"note\": \"A modelo veste PP. Cor: Preta.\", \"rows\": {\"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}', '149.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(13, 'Regata Lumi - Branca', 'regata-lumi-branca', 'Regatas', 'Regata Lumi em branco puro com visual limpo e toque suave. Essencial em qualquer armário inteligente para compor looks elegantes e leves.', 'Toque de seda com caimento fluido.', 'Lavar à mão com sabão neutro.', NULL, '{\"note\": \"A modelo veste PP. Cor: Branca.\", \"rows\": {\"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}', '149.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(14, 'Saia Midnight - Branca', 'saia-midnight-branca', 'Saias', 'Saia Midnight na cor branca com acabamento delicado e corte fluido. Proporciona movimento elegante e harmonia em composições monocromáticas.', 'Tecido creponado leve com forro.', 'Lavar delicadamente. Secar à sombra.', NULL, '{\"note\": \"A modelo veste PP. Cor: Branca.\", \"rows\": {\"PP\": {\"busto\": \"\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}', '229.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(15, 'Saia Midnight - Preta', 'saia-midnight-preta', 'Saias', 'Saia Midnight na cor preta clássica com caimento envolvente e acabamento impecável. Uma peça versátil e atemporal indispensável para diversas ocasiões.', 'Tecido creponado leve com forro.', 'Lavar delicadamente. Secar à sombra.', NULL, '{\"note\": \"A modelo veste PP. Cor: Preta.\", \"rows\": {\"M\": {\"busto\": \"\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}', '229.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(16, 'Blusa Clair - Off White', 'blusa-clair-off-white', 'Blusas', 'Blusa Clair em tom off white sofisticado, com modelagem requintada e linhas elegantes. Transmite personalidade e refinamento em todas as produções.', 'Tecido nobre de toque macio e caimento fluido.', 'Lavar à mão ou a seco.', NULL, '{\"note\": \"A modelo veste PP e P. Cor: Off White.\", \"rows\": {\"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}', '359.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(17, 'Blusa Clair - Marrom', 'blusa-clair-marrom', 'Blusas', 'Blusa Clair na cor marrom profunda com modelagem sofisticada e acabamento de alta alfaiataria. Elegância incomparável com toque aconchegante.', 'Tecido nobre de toque macio e caimento fluido.', 'Lavar à mão ou a seco.', NULL, '{\"note\": \"A modelo veste PP e P. Cor: Marrom.\", \"rows\": {\"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}', '359.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(18, 'Blusa Aurora - Off White', 'blusa-aurora-off-white', 'Blusas', 'Blusa Aurora em tom off white com modelagem confortável e gola elegante. Uma peça contemporânea pensada para enriquecer qualquer produção.', 'Fibras nobres respiráveis com elastano.', 'Lavar delicadamente em água fria.', NULL, '{\"note\": \"A modelo veste PP. Cor: Off White.\", \"rows\": {\"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}', '179.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(19, 'Blusa Aurora - Verde', 'blusa-aurora-verde', 'Blusas', 'Blusa Aurora em verde refinado com corte preciso e ajuste harmônico. Perfeita para quem valoriza elegância contemporânea com um ponto de cor exclusivo.', 'Fibras nobres respiráveis com elastano.', 'Lavar delicadamente em água fria.', NULL, '{\"note\": \"A modelo veste PP. Cor: Verde.\", \"rows\": {\"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}', '179.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(20, 'Vestido Latte', 'vestido-latte', 'Vestidos', 'Vestido Latte na marcante tonalidade marrom, com linhas fluidas e silhueta feminina bem marcada. Peça autoral sofisticada perfeita para ocasiões especiais.', 'Tecido acetinado nobre e encorpado.', 'Limpeza especializada a seco.', NULL, '{\"note\": \"A modelo veste PP. Cor: Marrom.\", \"rows\": {\"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}', '669.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(21, 'Vestido Bonjour', 'vestido-bonjour', 'Vestidos', 'Vestido Bonjour em tom off white estonteante com modelagem de alta costura e acabamento impecável. Criado para momentos inesquecíveis com presença inigualável.', 'Tecido acetinado estruturado com forro nobre.', 'Limpeza a seco profissional recomendada.', NULL, '{\"note\": \"A modelo veste PP. Cor: Off White.\", \"rows\": {\"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}', '979.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54'),
(22, 'Top Dusk', 'top-dusk', 'Tops', 'Top Dusk em renda preta com desenho delicado e caimento primoroso. Versátil para compor desde propostas refinadas até produções marcantes de fim de noite.', 'Renda delicada macia com elastano.', 'Lavagem manual suave em água fria.', NULL, '{\"note\": \"A modelo veste P. Cor: Preto de renda.\", \"rows\": {\"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}', '59.90', NULL, NULL, NULL, 1, 1, '2026-09-29 15:41:54');

INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `position`) VALUES
(1, 1, 'uploads/products/image1.JPG', 0),
(2, 2, 'uploads/products/image2.JPG', 0),
(3, 3, 'uploads/products/image3.JPG', 0),
(4, 4, 'uploads/products/image4.JPG', 0),
(5, 5, 'uploads/products/image5.JPG', 0),
(6, 6, 'uploads/products/image6.JPG', 0),
(7, 7, 'uploads/products/image7.JPG', 0),
(8, 8, 'uploads/products/image8.JPG', 0),
(9, 9, 'uploads/products/image9.JPG', 0),
(10, 10, 'uploads/products/image10.JPG', 0),
(11, 11, 'uploads/products/image11.jpg', 0),
(12, 12, 'uploads/products/image12.JPG', 0),
(13, 13, 'uploads/products/image13.JPG', 0),
(14, 14, 'uploads/products/image14.JPG', 0),
(15, 15, 'uploads/products/image15.JPG', 0),
(16, 16, 'uploads/products/image16.JPG', 0),
(17, 17, 'uploads/products/image17.JPG', 0),
(18, 18, 'uploads/products/image18.JPG', 0),
(19, 19, 'uploads/products/image19.JPG', 0),
(20, 20, 'uploads/products/image20.JPG', 0),
(21, 21, 'uploads/products/image21.jpg', 0),
(22, 22, 'uploads/products/image22.JPG', 0);

INSERT INTO `product_sizes` (`id`, `product_id`, `size`, `stock`, `position`) VALUES
(1, 1, 'PP', 2, 0),
(2, 1, 'P', 1, 1),
(3, 2, 'PP', 1, 0),
(4, 2, 'P', 1, 1),
(5, 2, 'M', 2, 2),
(6, 2, 'G', 1, 3),
(7, 3, 'P', 3, 0),
(8, 4, 'PP', 3, 0),
(9, 4, 'P', 1, 1),
(10, 4, 'M', 2, 2),
(11, 5, 'PP', 1, 0),
(12, 5, 'P', 2, 1),
(13, 6, 'PP', 2, 0),
(14, 6, 'P', 1, 1),
(15, 6, 'M', 2, 2),
(16, 7, 'PP', 2, 0),
(17, 7, 'G', 1, 1),
(18, 8, 'PP', 1, 0),
(19, 8, 'M', 1, 1),
(20, 9, 'P', 2, 0),
(21, 9, 'M', 2, 1),
(22, 10, 'P', 2, 0),
(23, 11, 'M', 1, 0),
(24, 12, 'PP', 1, 0),
(25, 12, 'P', 1, 1),
(26, 13, 'P', 1, 0),
(27, 13, 'M', 1, 1),
(28, 14, 'PP', 2, 0),
(29, 15, 'PP', 2, 0),
(30, 15, 'P', 2, 1),
(31, 15, 'M', 2, 2),
(32, 16, 'P', 2, 0),
(33, 16, 'M', 2, 1),
(34, 17, 'PP', 2, 0),
(35, 18, 'PP', 1, 0),
(36, 18, 'M', 2, 1),
(37, 19, 'PP', 1, 0),
(38, 19, 'P', 2, 1),
(39, 19, 'M', 2, 2),
(40, 20, 'PP', 1, 0),
(41, 20, 'P', 2, 1),
(42, 21, 'PP', 2, 0),
(43, 21, 'P', 2, 1),
(44, 22, 'P', 2, 0);

SET FOREIGN_KEY_CHECKS = 1;

-- === FIM DA ATUALIZAÇÃO DE PRODUÇÃO ===
