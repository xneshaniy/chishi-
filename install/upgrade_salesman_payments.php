<?php
/**
 * JUSTI NUMBER ONE ERP — salesman payments module upgrade for EXISTING installations.
 * Creates the `salesman_payments` table (money received from salesmen against van goods payable).
 * Safe to run multiple times; does not touch existing records.
 *
 * Usage (on the server):  php install/upgrade_salesman_payments.php
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
    $statements = [
        "CREATE TABLE IF NOT EXISTS salesman_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            receipt_no VARCHAR(30) NULL UNIQUE,
            salesman_id INT NOT NULL,
            date DATE NOT NULL,
            total_amount DECIMAL(18,2) NOT NULL,
            cash_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            bank_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            cash_account_id INT NULL,
            bank_account_id INT NULL,
            bank_reference VARCHAR(60) NULL,
            payment_method VARCHAR(10) NOT NULL DEFAULT 'cash',
            notes VARCHAR(255) NULL,
            received_by INT NULL,
            journal_entry_id INT NULL,
            idem_key VARCHAR(80) NULL UNIQUE,
            status VARCHAR(12) NOT NULL DEFAULT 'posted',
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_smp_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
            CONSTRAINT fk_smp_cash FOREIGN KEY (cash_account_id) REFERENCES cash_bank_accounts(id),
            CONSTRAINT fk_smp_bank FOREIGN KEY (bank_account_id) REFERENCES cash_bank_accounts(id),
            CONSTRAINT fk_smp_user FOREIGN KEY (received_by) REFERENCES users(id)
        )",
        'CREATE INDEX IF NOT EXISTS idx_smp_sm ON salesman_payments (salesman_id, date)',
    ];
} else {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
        $db['user'] ?? '', $db['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $statements = [
        "CREATE TABLE IF NOT EXISTS salesman_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            receipt_no VARCHAR(30) NULL UNIQUE,
            salesman_id INT NOT NULL,
            `date` DATE NOT NULL,
            total_amount DECIMAL(18,2) NOT NULL,
            cash_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            bank_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            cash_account_id INT NULL,
            bank_account_id INT NULL,
            bank_reference VARCHAR(60) NULL,
            payment_method VARCHAR(10) NOT NULL DEFAULT 'cash',
            notes VARCHAR(255) NULL,
            received_by INT NULL,
            journal_entry_id INT NULL,
            idem_key VARCHAR(80) NULL UNIQUE,
            status VARCHAR(12) NOT NULL DEFAULT 'posted',
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_smp_sm (salesman_id, `date`),
            CONSTRAINT fk_smp_sm FOREIGN KEY (salesman_id) REFERENCES salesmen(id) ON DELETE CASCADE,
            CONSTRAINT fk_smp_cash FOREIGN KEY (cash_account_id) REFERENCES cash_bank_accounts(id),
            CONSTRAINT fk_smp_bank FOREIGN KEY (bank_account_id) REFERENCES cash_bank_accounts(id),
            CONSTRAINT fk_smp_user FOREIGN KEY (received_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}
$n = 0;
foreach ($statements as $sql) { $pdo->exec($sql); $n++; }
echo "salesman payments upgrade: applied $n statements (table ensured).\n";
