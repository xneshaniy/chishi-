<?php
/**
 * JUSTI NUMBER ONE ERP — Personal Loans tracking upgrade for existing installations.
 * Creates `personal_loans` and `personal_loan_repayments` — a private, person-wise
 * loan ledger for money borrowed by/lent to people personally by the owner.
 * Completely independent from ALL company accounting — no journals, no stock, no ledger.
 * Safe to run multiple times.
 *
 * Usage (on the server): php install/upgrade_personal_loans.php
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
    $exists = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='personal_loans'")->fetchColumn();
    if ($exists) { echo "OK (already present): personal_loans\n"; exit(0); }
    $pdo->exec("CREATE TABLE personal_loans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        loan_no VARCHAR(30) NOT NULL UNIQUE,
        person_name VARCHAR(150) NOT NULL,
        phone VARCHAR(40) NULL,
        loan_date DATE NOT NULL,
        original_amount DECIMAL(18,2) NOT NULL,
        expected_date DATE NULL,
        description VARCHAR(255) NULL,
        notes TEXT NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        created_by INT NULL,
        updated_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_pl_user FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    $pdo->exec("CREATE TABLE personal_loan_repayments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        loan_id INT NOT NULL,
        repayment_date DATE NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        note VARCHAR(255) NULL,
        recorded_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_plr_loan FOREIGN KEY (loan_id) REFERENCES personal_loans(id) ON DELETE CASCADE,
        CONSTRAINT fk_plr_user FOREIGN KEY (recorded_by) REFERENCES users(id)
    )");
    echo "ADDED personal_loans + personal_loan_repayments\n";
    exit(0);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
    $db['user'] ?? '', $db['pass'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'personal_loans'")->fetchColumn();
if ($exists) { echo "OK (already present): personal_loans\n"; exit(0); }
$pdo->exec("CREATE TABLE personal_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_no VARCHAR(30) NOT NULL UNIQUE,
    person_name VARCHAR(150) NOT NULL,
    phone VARCHAR(40) NULL,
    loan_date DATE NOT NULL,
    original_amount DECIMAL(18,2) NOT NULL,
    expected_date DATE NULL,
    description VARCHAR(255) NULL,
    notes TEXT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'pending',
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pl_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE personal_loan_repayments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    repayment_date DATE NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    note VARCHAR(255) NULL,
    recorded_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_plr_loan FOREIGN KEY (loan_id) REFERENCES personal_loans(id) ON DELETE CASCADE,
    CONSTRAINT fk_plr_user FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB");
echo "ADDED personal_loans + personal_loan_repayments\n";
