<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    echo "Running database migrations...\n";

    // 1. Create sizes table
    $pdo->exec("
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
    ");
    echo "[OK] Table 'sizes' ensured.\n";

    // 2. Populate default market sizes
    $defaultSizes = [
        // Letras
        ['code' => 'PP', 'name' => 'Extra Pequeno (34)', 'category' => 'letra', 'busto' => '80-84', 'cintura' => '62-66', 'quadril' => '90-94', 'comp' => '110', 'active' => 1, 'sort' => 10],
        ['code' => 'P', 'name' => 'Pequeno (36/38)', 'category' => 'letra', 'busto' => '84-88', 'cintura' => '66-70', 'quadril' => '94-98', 'comp' => '112', 'active' => 1, 'sort' => 20],
        ['code' => 'M', 'name' => 'Médio (40)', 'category' => 'letra', 'busto' => '90-94', 'cintura' => '72-76', 'quadril' => '100-104', 'comp' => '113', 'active' => 1, 'sort' => 30],
        ['code' => 'G', 'name' => 'Grande (42)', 'category' => 'letra', 'busto' => '96-100', 'cintura' => '78-82', 'quadril' => '106-110', 'comp' => '114', 'active' => 1, 'sort' => 40],
        ['code' => 'GG', 'name' => 'Extra Grande (44)', 'category' => 'letra', 'busto' => '102-106', 'cintura' => '84-88', 'quadril' => '112-116', 'comp' => '115', 'active' => 1, 'sort' => 50],
        ['code' => 'Extra G', 'name' => 'Extra Grande Especial (46)', 'category' => 'letra', 'busto' => '108-112', 'cintura' => '90-94', 'quadril' => '118-122', 'comp' => '116', 'active' => 1, 'sort' => 60],
        ['code' => 'G1', 'name' => 'Plus Size 48 (G1)', 'category' => 'letra', 'busto' => '114-118', 'cintura' => '96-100', 'quadril' => '124-128', 'comp' => '117', 'active' => 0, 'sort' => 70],
        ['code' => 'G2', 'name' => 'Plus Size 50 (G2)', 'category' => 'letra', 'busto' => '120-124', 'cintura' => '102-106', 'quadril' => '130-134', 'comp' => '118', 'active' => 0, 'sort' => 80],
        ['code' => 'G3', 'name' => 'Plus Size 52 (G3)', 'category' => 'letra', 'busto' => '126-130', 'cintura' => '108-112', 'quadril' => '136-140', 'comp' => '119', 'active' => 0, 'sort' => 90],
        ['code' => 'Único', 'name' => 'Tamanho Único (U)', 'category' => 'letra', 'busto' => '86-96', 'cintura' => '68-78', 'quadril' => '96-106', 'comp' => '113', 'active' => 0, 'sort' => 100],
        // Numericos
        ['code' => '34', 'name' => 'Tamanho 34 (PP)', 'category' => 'numero', 'busto' => '80-84', 'cintura' => '62-66', 'quadril' => '90-94', 'comp' => '110', 'active' => 0, 'sort' => 110],
        ['code' => '36', 'name' => 'Tamanho 36 (P)', 'category' => 'numero', 'busto' => '84-88', 'cintura' => '66-70', 'quadril' => '94-98', 'comp' => '111', 'active' => 0, 'sort' => 120],
        ['code' => '38', 'name' => 'Tamanho 38 (P/M)', 'category' => 'numero', 'busto' => '88-92', 'cintura' => '70-74', 'quadril' => '98-102', 'comp' => '112', 'active' => 0, 'sort' => 130],
        ['code' => '40', 'name' => 'Tamanho 40 (M)', 'category' => 'numero', 'busto' => '92-96', 'cintura' => '74-78', 'quadril' => '102-106', 'comp' => '113', 'active' => 0, 'sort' => 140],
        ['code' => '42', 'name' => 'Tamanho 42 (G)', 'category' => 'numero', 'busto' => '96-100', 'cintura' => '78-82', 'quadril' => '106-110', 'comp' => '114', 'active' => 0, 'sort' => 150],
        ['code' => '44', 'name' => 'Tamanho 44 (GG)', 'category' => 'numero', 'busto' => '102-106', 'cintura' => '84-88', 'quadril' => '112-116', 'comp' => '115', 'active' => 0, 'sort' => 160],
        ['code' => '46', 'name' => 'Tamanho 46 (Extra G)', 'category' => 'numero', 'busto' => '108-112', 'cintura' => '90-94', 'quadril' => '118-122', 'comp' => '116', 'active' => 0, 'sort' => 170],
        ['code' => '48', 'name' => 'Tamanho 48 (Plus)', 'category' => 'numero', 'busto' => '114-118', 'cintura' => '96-100', 'quadril' => '124-128', 'comp' => '117', 'active' => 0, 'sort' => 180],
        ['code' => '50', 'name' => 'Tamanho 50 (Plus)', 'category' => 'numero', 'busto' => '120-124', 'cintura' => '102-106', 'quadril' => '130-134', 'comp' => '118', 'active' => 0, 'sort' => 190],
        ['code' => '52', 'name' => 'Tamanho 52 (Plus)', 'category' => 'numero', 'busto' => '126-130', 'cintura' => '108-112', 'quadril' => '136-140', 'comp' => '119', 'active' => 0, 'sort' => 200],
    ];

    $insSize = $pdo->prepare("
        INSERT INTO `sizes` (`code`, `name`, `category`, `busto_hint`, `cintura_hint`, `quadril_hint`, `comprimento_hint`, `is_active`, `sort_order`)
        VALUES (:code, :name, :cat, :busto, :cintura, :quadril, :comp, :active, :sort)
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `category` = VALUES(`category`), `busto_hint` = VALUES(`busto_hint`), `cintura_hint` = VALUES(`cintura_hint`), `quadril_hint` = VALUES(`quadril_hint`), `comprimento_hint` = VALUES(`comprimento_hint`), `sort_order` = VALUES(`sort_order`)
    ");

    foreach ($defaultSizes as $s) {
        $insSize->execute([
            ':code' => $s['code'],
            ':name' => $s['name'],
            ':cat' => $s['category'],
            ':busto' => $s['busto'],
            ':cintura' => $s['cintura'],
            ':quadril' => $s['quadril'],
            ':comp' => $s['comp'],
            ':active' => $s['active'],
            ':sort' => $s['sort'],
        ]);
    }
    echo "[OK] Default sizes seeded.\n";

    // 3. Alter product_sizes.size to VARCHAR(20) and add stock column
    $cols = $pdo->query("SHOW COLUMNS FROM `product_sizes`")->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec("ALTER TABLE `product_sizes` MODIFY COLUMN `size` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL");
    echo "[OK] Modified product_sizes.size to VARCHAR(20).\n";

    if (!in_array('stock', $cols)) {
        $pdo->exec("ALTER TABLE `product_sizes` ADD COLUMN `stock` INT UNSIGNED NOT NULL DEFAULT 5 AFTER `size`");
        echo "[OK] Added product_sizes.stock column.\n";
    } else {
        echo "[OK] product_sizes.stock column already exists.\n";
    }

    // Set some demo stock values if currently 0
    $pdo->exec("UPDATE `product_sizes` SET `stock` = 5 WHERE `stock` = 0");

    // 4. Alter cart_items.selected_size to VARCHAR(20)
    $pdo->exec("ALTER TABLE `cart_items` MODIFY COLUMN `selected_size` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL");
    echo "[OK] Modified cart_items.selected_size to VARCHAR(20).\n";

    // 5. Alter order_items.size to VARCHAR(20)
    $pdo->exec("ALTER TABLE `order_items` MODIFY COLUMN `size` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL");
    echo "[OK] Modified order_items.size to VARCHAR(20).\n";

    // 6. Create stock_notifications table ("Avise-me quando chegar")
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `stock_notifications` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `product_id` int unsigned NOT NULL,
            `product_name` varchar(180) NOT NULL,
            `size` varchar(20) NOT NULL,
            `customer_name` varchar(160) NOT NULL,
            `customer_email` varchar(190) NOT NULL,
            `status` enum('pending','notified') NOT NULL DEFAULT 'pending',
            `notes` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `notified_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_sn_product` (`product_id`,`size`),
            KEY `idx_sn_email` (`customer_email`),
            CONSTRAINT `fk_sn_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[OK] Table 'stock_notifications' ensured.\n";

    echo "Migrations finished successfully!\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
