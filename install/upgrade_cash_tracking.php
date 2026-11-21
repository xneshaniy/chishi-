<?php
/**
 * JUSTI NUMBER ONE ERP — cash/bank tracking upgrade for EXISTING installations.
 * Adds sales_invoices.cash_account_id and purchase_invoices.cash_account_id (nullable, no data touched).
 * Enables the receiving/paying account to be chosen on cash sales/purchases instead of the default cash account.
 * Safe to run multiple times; does not touch existing records.
 *
 * Usage (on the server):  php install/upgrade_cash_tracking.php
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
    $dsn = (string)($db['dsn'] ?? '');
    $pdo = new PDO(strpos($dsn, 'sqlite:') === 0 ? $dsn : 'sqlite:' . $dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach (['sales_invoices', 'purchase_invoices'] as $table) {
        $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('cash_account_id', $cols, true)) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN cash_account_id INT NULL");
            echo "cash_account_id added to $table\n";
        } else {
            echo "$table.cash_account_id already exists — skipped\n";
        }
    }
} else {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
        $db['user'] ?? '', $db['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    foreach (['sales_invoices', 'purchase_invoices'] as $table) {
        $cols = array_column($pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_ASSOC), 'Field');
        if (!in_array('cash_account_id', $cols, true)) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN cash_account_id INT NULL");
            echo "cash_account_id added to $table\n";
        } else {
            echo "$table.cash_account_id already exists — skipped\n";
        }
    }
}
echo "cash tracking upgrade complete\n";
