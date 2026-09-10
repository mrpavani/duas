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

-- ---------- cart_items ----------
CALL duas_tab('cart_items');
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int unsigned NOT NULL,
  `selected_size` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
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
CALL duas_col('cart_items', 'selected_size', 'varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL', 'product_id');
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
  `size` enum('P','M','G') COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_size` (`product_id`,`size`),
  CONSTRAINT `fk_psize_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL duas_col('product_sizes', 'product_id', 'int unsigned NOT NULL', 'id');
CALL duas_col('product_sizes', 'size', 'enum(''P'',''M'',''G'') COLLATE utf8mb4_unicode_ci NOT NULL', 'product_id');
CALL duas_col('product_sizes', 'position', 'tinyint unsigned NOT NULL DEFAULT ''0''', 'size');
CALL duas_idx('product_sizes', 'uq_product_size', 'UNIQUE KEY `uq_product_size` (`product_id`,`size`)');
CALL duas_fk('product_sizes', 'fk_psize_product', 'CONSTRAINT `fk_psize_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE');

-- ---------- order_items ----------
CALL duas_tab('order_items');
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `product_id` int unsigned DEFAULT NULL,
  `product_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
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
CALL duas_col('order_items', 'size', 'varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL', 'product_name');
CALL duas_col('order_items', 'quantity', 'int unsigned NOT NULL DEFAULT ''1''', 'size');
CALL duas_col('order_items', 'unit_price', 'decimal(10,2) NOT NULL', 'quantity');
CALL duas_idx('order_items', 'idx_oi_order', ' KEY `idx_oi_order` (`order_id`)');
CALL duas_idx('order_items', 'idx_oi_product', ' KEY `idx_oi_product` (`product_id`)');
CALL duas_fk('order_items', 'fk_oi_order', 'CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE');
CALL duas_fk('order_items', 'fk_oi_product', 'CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL');

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
