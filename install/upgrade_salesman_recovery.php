<?php
/**
 * JUSTI NUMBER ONE ERP — Salesman Recovery / Payment Collection upgrade.
 *
 * For EXISTING installations only. Fresh installs get these objects from
 * database/schema.sqlite.sql / database/schema.mysql.sql.
 *
 * Adds:
 *  - salesman_issues.related_issue_id  (goods return linked to its original invoice)
 *  - salesman_payment_allocations      (one payment may settle one or many invoices)
 *
 * No existing balances, invoices, payments, returns or stock rows are changed.
 * Safe to run repeatedly.
 *
 * Usage:  php install/upgrade_salesman_recovery.php
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
    $cols = array_column($pdo->query('PRAGMA table_info(salesman_issues)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('related_issue_id', $cols, true)) {
        $pdo->exec('ALTER TABLE salesman_issues ADD COLUMN related_issue_id INT NULL');
        echo "salesman_issues.related_issue_id added\n";
    } else {
        echo "salesman_issues.related_issue_id already exists — skipped\n";
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS salesman_payment_allocations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payment_id INT NOT NULL,
        issue_id INT NOT NULL,
        salesman_id INT NOT NULL,
        amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (payment_id, issue_id),
        CONSTRAINT fk_spa_payment FOREIGN KEY (payment_id) REFERENCES salesman_payments(id) ON DELETE CASCADE,
        CONSTRAINT fk_spa_issue FOREIGN KEY (issue_id) REFERENCES salesman_issues(id) ON DELETE CASCADE,
        CONSTRAINT fk_spa_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_spa_sm ON salesman_payment_allocations (salesman_id, issue_id)');
    echo "salesman_payment_allocations ready\n";
} else {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
        $db['user'] ?? '', $db['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $cols = array_column($pdo->query('SHOW COLUMNS FROM salesman_issues')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('related_issue_id', $cols, true)) {
        $pdo->exec('ALTER TABLE salesman_issues ADD COLUMN related_issue_id INT NULL AFTER notes');
        echo "salesman_issues.related_issue_id added\n";
    } else {
        echo "salesman_issues.related_issue_id already exists — skipped\n";
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS salesman_payment_allocations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT NOT NULL,
        issue_id INT NOT NULL,
        salesman_id INT NOT NULL,
        amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_spa_payment_issue (payment_id, issue_id),
        KEY idx_spa_sm (salesman_id, issue_id),
        CONSTRAINT fk_spa_payment FOREIGN KEY (payment_id) REFERENCES salesman_payments(id) ON DELETE CASCADE,
        CONSTRAINT fk_spa_issue FOREIGN KEY (issue_id) REFERENCES salesman_issues(id) ON DELETE CASCADE,
        CONSTRAINT fk_spa_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    echo "salesman_payment_allocations ready\n";
}
echo "salesman recovery upgrade complete\n";
