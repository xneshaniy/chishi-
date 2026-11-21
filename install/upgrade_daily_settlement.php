<?php
/**
 * JUSTI NUMBER ONE ERP — Salesman Daily Settlement upgrade for EXISTING installations.
 * Extends `salesman_settlements` with the full daily settlement snapshot columns and
 * drops the one-settlement-per-day UNIQUE key so a salesman can sign off several times per day.
 * Existing settlement rows are preserved (new columns default to 0 / NULL).
 * Safe to run multiple times.
 *
 * Usage (on the server):  php install/upgrade_daily_settlement.php
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

$newCols = [
    'reference' => 'VARCHAR(24) NULL',
    'issued_value' => 'DECIMAL(18,2) NOT NULL DEFAULT 0',
    'additional_issued' => 'DECIMAL(18,2) NOT NULL DEFAULT 0',
    'returns_value' => 'DECIMAL(18,2) NOT NULL DEFAULT 0',
    'collections_total' => 'DECIMAL(18,2) NOT NULL DEFAULT 0',
    'cash_received' => 'DECIMAL(18,2) NOT NULL DEFAULT 0',
    'bank_received' => 'DECIMAL(18,2) NOT NULL DEFAULT 0',
    'expenses_total' => 'DECIMAL(18,2) NOT NULL DEFAULT 0',
    'remaining_payable' => 'DECIMAL(18,2) NOT NULL DEFAULT 0',
    'received_by' => 'INT NULL',
];

if ($driver === 'sqlite') {
    $dsn = (string)($db['dsn'] ?? '');
    $pdo = new PDO(strpos($dsn, 'sqlite:') === 0 ? $dsn : 'sqlite:' . $dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $cols = $pdo->query('PRAGMA table_info(salesman_settlements)')->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($cols, 'name');
    $added = 0;
    foreach ($newCols as $col => $def) {
        if (!in_array($col, $names, true)) {
            $pdo->exec("ALTER TABLE salesman_settlements ADD COLUMN $col $def");
            $added++;
        }
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sett_sm ON salesman_settlements (salesman_id, date)');
    echo "salesman_settlements extended ($added new columns added).\n";
    exit(0);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
    $db['user'] ?? '', $db['pass'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$existing = [];
foreach ($pdo->query('SHOW COLUMNS FROM salesman_settlements') as $c) { $existing[] = $c['Field']; }
$indexes = $pdo->query('SHOW INDEX FROM salesman_settlements')->fetchAll(PDO::FETCH_ASSOC);
$indexNames = array_column($indexes, 'Key_name');
$added = 0;
$pdo->beginTransaction();
try {
    // Drop the one-settlement-per-day UNIQUE key if present (multiple sign-offs per day are allowed)
    if (in_array('uq_settle', $indexNames, true)) {
        $pdo->exec('ALTER TABLE salesman_settlements DROP INDEX uq_settle');
        echo "uq_settle dropped (multiple settlements per day allowed).\n";
    }
    foreach ($newCols as $col => $def) {
        if (!in_array($col, $existing, true)) {
            $pdo->exec("ALTER TABLE salesman_settlements ADD COLUMN $col $def");
            $added++;
        }
    }
    if (!in_array('idx_sett_sm', $indexNames, true)) {
        $pdo->exec('CREATE INDEX idx_sett_sm ON salesman_settlements (salesman_id, date)');
    }
    $pdo->commit();
    echo "salesman_settlements extended ($added new columns added).\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "MIGRATION FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
