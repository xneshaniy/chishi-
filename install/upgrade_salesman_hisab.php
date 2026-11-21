<?php
/**
 * JUSTI NUMBER ONE ERP — Salesman Collections & Settlement upgrade for EXISTING installations.
 * Adds the `salesman_collections` table used by the Hisab/settlement feature. No other changes.
 * Safe to run multiple times; does not touch existing records.
 *
 * Usage (on the server):  php install/upgrade_salesman_hisab.php
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

$sql = ($driver === 'sqlite')
    ? "CREATE TABLE salesman_collections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        collection_no VARCHAR(30) NOT NULL UNIQUE,
        salesman_id INT NOT NULL,
        customer_id INT NULL,
        date DATE NOT NULL,
        sales_invoice_id INT NULL,
        amount DECIMAL(18,2) NOT NULL,
        cash_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        bank_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        account_id INT NULL,
        reference VARCHAR(60) NULL,
        notes VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_scol_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
        CONSTRAINT fk_scol_cust FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        CONSTRAINT fk_scol_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
        CONSTRAINT fk_scol_inv FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id) ON DELETE SET NULL
      )"
    : "CREATE TABLE salesman_collections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        collection_no VARCHAR(30) NOT NULL UNIQUE,
        salesman_id INT NOT NULL,
        customer_id INT NULL,
        date DATE NOT NULL,
        sales_invoice_id INT NULL,
        amount DECIMAL(18,2) NOT NULL,
        cash_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        bank_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        account_id INT NULL,
        reference VARCHAR(60) NULL,
        notes VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_scol_sm (salesman_id, date),
        CONSTRAINT fk_scol_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
        CONSTRAINT fk_scol_cust FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        CONSTRAINT fk_scol_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
        CONSTRAINT fk_scol_inv FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(id) ON DELETE SET NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$indexSql = 'CREATE INDEX IF NOT EXISTS idx_scol_sm ON salesman_collections (salesman_id, date)';

$pdo = ($driver === 'sqlite')
    ? new PDO(strpos((string)($db['dsn'] ?? ''), 'sqlite:') === 0 ? $db['dsn'] : 'sqlite:' . $db['dsn'])
    : new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''), $db['user'] ?? '', $db['pass'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

try {
    $pdo->exec($sql);
    $pdo->exec($indexSql);
    echo "salesman_collections table ensured (safe to re-run).\n";
} catch (Throwable $e) {
    if (stripos($e->getMessage(), 'already exists') !== false || stripos($e->getMessage(), 'duplicate') !== false) {
        echo "salesman_collections already exists — nothing to do.\n";
    } else {
        fwrite(STDERR, "FAILED: " . $e->getMessage() . "\n");
        exit(1);
    }
}
