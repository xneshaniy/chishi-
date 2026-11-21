<?php
/**
 * JUSTI NUMBER ONE ERP — production upgrade for EXISTING installations.
 * Adds products.photo_path + products.description columns and the daily_production / daily_production_items tables.
 * Safe to run multiple times; does not touch existing records.
 *
 * Usage (on the server):  php install/upgrade_production.php
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

$statements = [];
if ($driver === 'sqlite') {
    $pdo = new PDO(strpos((string)($db['dsn'] ?? ''), 'sqlite:') === 0 ? $db['dsn'] : 'sqlite:' . $db['dsn']);
    $statements[] = "CREATE TABLE IF NOT EXISTS daily_production (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        run_no VARCHAR(30) NOT NULL UNIQUE,
        date DATE NOT NULL,
        notes VARCHAR(255) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'posted',
        reversed_at DATETIME NULL,
        reversed_by INT NULL,
        reversal_reason VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )";
    $statements[] = 'CREATE INDEX IF NOT EXISTS idx_dp_date ON daily_production (date)';
    $statements[] = "CREATE TABLE IF NOT EXISTS daily_production_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        run_id INT NOT NULL,
        product_id INT NOT NULL,
        qty DECIMAL(18,3) NOT NULL,
        batch_no VARCHAR(60) NULL,
        line_notes VARCHAR(255) NULL,
        CONSTRAINT fk_dpi_run FOREIGN KEY (run_id) REFERENCES daily_production(id) ON DELETE CASCADE,
        CONSTRAINT fk_dpi_p FOREIGN KEY (product_id) REFERENCES products(id)
    )";
    $statements[] = 'CREATE INDEX IF NOT EXISTS idx_dpi_p ON daily_production_items (product_id, run_id)';
} else {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
        $db['user'] ?? '', $db['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $statements[] = "CREATE TABLE IF NOT EXISTS daily_production (
        id INT AUTO_INCREMENT PRIMARY KEY,
        run_no VARCHAR(30) NOT NULL UNIQUE,
        `date` DATE NOT NULL,
        notes VARCHAR(255) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'posted',
        reversed_at DATETIME NULL,
        reversed_by INT NULL,
        reversal_reason VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dp_date (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $statements[] = "CREATE TABLE IF NOT EXISTS daily_production_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        run_id INT NOT NULL,
        product_id INT NOT NULL,
        qty DECIMAL(18,3) NOT NULL,
        batch_no VARCHAR(60) NULL,
        line_notes VARCHAR(255) NULL,
        INDEX idx_dpi_p (product_id, run_id),
        CONSTRAINT fk_dpi_run FOREIGN KEY (run_id) REFERENCES daily_production(id) ON DELETE CASCADE,
        CONSTRAINT fk_dpi_p FOREIGN KEY (product_id) REFERENCES products(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

$products = $driver === 'sqlite' ? $pdo->query('PRAGMA table_info(products)')->fetchAll(PDO::FETCH_ASSOC) : $pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($products, $driver === 'sqlite' ? 'name' : 'Field');
if (!in_array('photo_path', $names, true)) {
    $statements[] = 'ALTER TABLE products ADD COLUMN photo_path VARCHAR(255) NULL';
}
if (!in_array('description', $names, true)) {
    $statements[] = 'ALTER TABLE products ADD COLUMN description TEXT NULL';
}

$n = 0;
foreach ($statements as $sql) { $pdo->exec($sql); $n++; }
echo "production upgrade: applied $n statements (tables + columns ensured).\n";
