<?php
/**
 * JUSTI NUMBER ONE ERP — Daily Raw Material Usage upgrade for existing installations.
 * Creates `rm_daily_usage` — one active record per (product, usage_date, warehouse), with
 * `status` posted|cancelled. Stock is deducted exactly once (Inventory::issue) on post and
 * reversed on cancel. Safe to run multiple times.
 *
 * Usage (on the server): php install/upgrade_raw_usage.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';
if (!file_exists($configPath)) { fwrite(STDERR, "config/config.php missing — run the installer first.\n"); exit(1); }
$config = require $configPath;
$db = $config['db'] ?? [];
$driver = $db['driver'] ?? 'mysql';

if ($driver === 'sqlite') {
    $pdo = new PDO(strpos((string)($db['dsn'] ?? ''), 'sqlite:') === 0 ? $db['dsn'] : 'sqlite:' . $db['dsn']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $exists = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='rm_daily_usage'")->fetchColumn();
    if ($exists) { echo "OK (already present): rm_daily_usage\n"; exit(0); }
    $pdo->exec("CREATE TABLE rm_daily_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usage_date DATE NOT NULL,
        product_id INT NOT NULL,
        warehouse_id INT NOT NULL,
        qty_kg DECIMAL(18,3) NOT NULL,
        unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
        total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'posted',
        recorded_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_rdu_prod FOREIGN KEY (product_id) REFERENCES products(id),
        CONSTRAINT fk_rdu_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
        CONSTRAINT fk_rdu_user FOREIGN KEY (recorded_by) REFERENCES users(id)
    )");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_rm_usage_active ON rm_daily_usage (product_id, usage_date, warehouse_id)");
    echo "ADDED rm_daily_usage\n";
    exit(0);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
    $db['user'] ?? '', $db['pass'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'rm_daily_usage'")->fetchColumn();
if ($exists) { echo "OK (already present): rm_daily_usage\n"; exit(0); }
$pdo->exec("CREATE TABLE rm_daily_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usage_date DATE NOT NULL,
    product_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    qty_kg DECIMAL(18,3) NOT NULL,
    unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
    total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'posted',
    recorded_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rdu_prod FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_rdu_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    CONSTRAINT fk_rdu_user FOREIGN KEY (recorded_by) REFERENCES users(id),
    UNIQUE KEY uq_rm_usage_active (product_id, usage_date, warehouse_id)
) ENGINE=InnoDB");
echo "ADDED rm_daily_usage\n";
