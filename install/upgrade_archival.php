<?php
/**
 * JUSTI NUMBER ONE ERP — Admin archival/safe-delete upgrade for existing installations.
 * Adds `is_archived` (0 by default) to every master-data table that already has the
 * concept of is_active.  The legacy `is_active = 0` still means "de-activated".
 * `is_archived = 1` means "hidden by admin; stays retrievable; restore via /admin/archives".
 * Safe to run multiple times.
 *
 * Usage (on the server): php install/upgrade_archival.php
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

$tables = [
    'products', 'suppliers', 'customers', 'salesmen', 'employees', 'warehouses',
    'branches', 'expense_categories', 'units', 'cash_bank_accounts', 'boms',
    'maintenance_assets', 'vehicles', 'product_categories',
];

if ($driver === 'sqlite') {
    $pdo = new PDO(strpos((string)($db['dsn'] ?? ''), 'sqlite:') === 0 ? $db['dsn'] : 'sqlite:' . $db['dsn']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ($tables as $t) {
        $cols = $pdo->query('PRAGMA table_info(' . $t . ')')->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        if (!$cols) { echo "SKIP (missing table): $t\n"; continue; }
        if (!in_array('is_archived', $names, true)) {
            $pdo->exec("ALTER TABLE $t ADD COLUMN is_archived INTEGER NOT NULL DEFAULT 0");
            echo "ADDED is_archived → $t\n";
        } else {
            echo "OK (already present): $t\n";
        }
    }
    exit(0);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
    $db['user'] ?? '', $db['pass'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
foreach ($tables as $t) {
    $cols = $pdo->query('SHOW COLUMNS FROM ' . $t)->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('is_archived', array_column($cols, 'Field'), true)) {
        $pdo->exec("ALTER TABLE $t ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
        echo "ADDED is_archived → $t\n";
    } else {
        echo "OK (already present): $t\n";
    }
}
