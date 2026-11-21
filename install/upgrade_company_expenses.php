<?php
/**
 * JUSTI NUMBER ONE ERP — Company Expenses upgrade for existing installations.
 * Creates `company_expenses` table — dedicated simple form for operating expenses
 * such as Plastic Bags, Electricity, Water, Petrol/Diesel, Packaging Material, etc.
 * Uses a companion `expenses` row so the existing Approvals + Posting pipeline drives
 * the cash/bank balance update exactly once.
 * Safe to run multiple times.
 *
 * Usage (on the server): php install/upgrade_company_expenses.php
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
    $exists = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='company_expenses'")->fetchColumn();
    if ($exists) { echo "OK (already present): company_expenses\n"; exit(0); }
    $pdo->exec("CREATE TABLE company_expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        expense_no VARCHAR(30) NOT NULL UNIQUE,
        linked_expense_id INT NULL,
        expense_date DATE NOT NULL,
        category_id INT NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        description VARCHAR(255) NULL,
        quantity DECIMAL(18,3) NULL,
        unit VARCHAR(20) NULL,
        rate DECIMAL(18,2) NULL,
        amount DECIMAL(18,2) NOT NULL,
        supplier VARCHAR(150) NULL,
        payment_type VARCHAR(6) NOT NULL,
        account_id INT NULL,
        attachment VARCHAR(255) NULL,
        notes TEXT NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'pending',
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_ce_category FOREIGN KEY (category_id) REFERENCES expense_categories(id),
        CONSTRAINT fk_ce_account FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
        CONSTRAINT fk_ce_linked FOREIGN KEY (linked_expense_id) REFERENCES expenses(id) ON DELETE SET NULL
    )");
    echo "ADDED company_expenses\n";
    exit(0);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
    $db['user'] ?? '', $db['pass'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'company_expenses'")->fetchColumn();
if ($exists) { echo "OK (already present): company_expenses\n"; exit(0); }
$pdo->exec("CREATE TABLE company_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_no VARCHAR(30) NOT NULL UNIQUE,
    linked_expense_id INT NULL,
    expense_date DATE NOT NULL,
    category_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    quantity DECIMAL(18,3) NULL,
    unit VARCHAR(20) NULL,
    rate DECIMAL(18,2) NULL,
    amount DECIMAL(18,2) NOT NULL,
    supplier VARCHAR(150) NULL,
    payment_type VARCHAR(6) NOT NULL,
    account_id INT NULL,
    attachment VARCHAR(255) NULL,
    notes TEXT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'pending',
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ce_category FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    CONSTRAINT fk_ce_account FOREIGN KEY (account_id) REFERENCES cash_bank_accounts(id),
    CONSTRAINT fk_ce_linked FOREIGN KEY (linked_expense_id) REFERENCES expenses(id) ON DELETE SET NULL
) ENGINE=InnoDB");
echo "ADDED company_expenses\n";
