<?php
/**
 * JUSTI NUMBER ONE ERP — Salesman Expenses paid_from upgrade for EXISTING installations.
 * Adds `salesman_expenses.paid_from` ('company' | 'collection') and allows account_id to be NULL
 * (only meaningful for paid_from='collection', paid by the salesman out of customer collections).
 * Existing rows are marked 'company' (their journal credits a company cash/bank account).
 * Safe to run multiple times; preserves all rows and indexes.
 *
 * Usage (on the server):  php install/upgrade_salesman_expenses.php
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
    $cols = $pdo->query('PRAGMA table_info(salesman_expenses)')->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($cols, 'name');
    if (in_array('paid_from', $names, true)) { echo "salesman_expenses already upgraded — nothing to do.\n"; exit(0); }
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->beginTransaction();
    try {
        $pdo->exec("CREATE TABLE salesman_expenses_mig (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          salesman_id INT NOT NULL,
          date DATE NOT NULL,
          expense_type VARCHAR(20) NOT NULL,
          amount DECIMAL(18,2) NOT NULL,
          description VARCHAR(255) NOT NULL,
          payment_type VARCHAR(6) NOT NULL DEFAULT 'cash',
          account_id INT NULL,
          paid_from VARCHAR(10) NOT NULL DEFAULT 'company',
          attachment VARCHAR(255) NULL,
          notes VARCHAR(255) NULL,
          status VARCHAR(12) NOT NULL DEFAULT 'pending',
          journal_entry_id INT NULL,
          requested_by INT NULL,
          approved_by INT NULL,
          approved_at DATETIME NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_se_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
          CONSTRAINT fk_se_acc FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id)
        )");
        $pdo->exec("INSERT INTO salesman_expenses_mig (id, salesman_id, date, expense_type, amount, description, payment_type, account_id, paid_from, attachment, notes, status, journal_entry_id, requested_by, approved_by, approved_at, created_at)
                    SELECT id, salesman_id, date, expense_type, amount, description, payment_type, account_id, 'company', attachment, notes, status, journal_entry_id, requested_by, approved_by, approved_at, created_at FROM salesman_expenses");
        $pdo->exec('DROP TABLE salesman_expenses');
        $pdo->exec('ALTER TABLE salesman_expenses_mig RENAME TO salesman_expenses');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_se_sm ON salesman_expenses (salesman_id, date)');
        $pdo->commit();
        echo "salesman_expenses upgraded (paid_from column added, existing rows marked 'company').\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "MIGRATION FAILED: " . $e->getMessage() . "\n");
        exit(1);
    }
    $pdo->exec('PRAGMA foreign_keys = ON');
    exit(0);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
    $db['user'] ?? '', $db['pass'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$hasPaidFrom = false;
foreach ($pdo->query('SHOW COLUMNS FROM salesman_expenses') as $c) {
    if ($c['Field'] === 'paid_from') { $hasPaidFrom = true; break; }
}
if ($hasPaidFrom) { echo "salesman_expenses already upgraded — nothing to do.\n"; exit(0); }
$pdo->beginTransaction();
try {
    $pdo->exec("ALTER TABLE salesman_expenses MODIFY account_id INT NULL");
    $pdo->exec("ALTER TABLE salesman_expenses ADD COLUMN paid_from VARCHAR(10) NOT NULL DEFAULT 'company' AFTER account_id");
    $pdo->exec("UPDATE salesman_expenses SET paid_from = 'company' WHERE paid_from IS NULL OR paid_from = ''");
    $pdo->commit();
    echo "salesman_expenses upgraded (paid_from column added, existing rows marked 'company').\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "MIGRATION FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
