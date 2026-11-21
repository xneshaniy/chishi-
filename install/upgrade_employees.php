<?php
/**
 * JUSTI NUMBER ONE ERP — new employee module upgrade for EXISTING installations.
 * Adds the employee_bonuses table, employees.user_id column (if missing).
 * Safe to run multiple times; does not touch existing records.
 *
 * Usage (on the server):  php install/upgrade_employees.php
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
    $statements[] = "CREATE TABLE IF NOT EXISTS employee_bonuses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INT NOT NULL,
        date DATE NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        reason VARCHAR(255) NOT NULL,
        payment_method VARCHAR(10) NOT NULL DEFAULT 'cash',
        account_id INT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        notes VARCHAR(255) NULL,
        journal_entry_id INT NULL,
        requested_by INT NULL,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_eb_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    )";
    $statements[] = 'CREATE INDEX IF NOT EXISTS idx_eb_emp ON employee_bonuses (employee_id, date)';
} else {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
        $db['user'] ?? '', $db['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $statements[] = "CREATE TABLE IF NOT EXISTS employee_bonuses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        date DATE NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        reason VARCHAR(255) NOT NULL,
        payment_method VARCHAR(10) NOT NULL DEFAULT 'cash',
        account_id INT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        notes VARCHAR(255) NULL,
        journal_entry_id INT NULL,
        requested_by INT NULL,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_eb_emp (employee_id, date),
        CONSTRAINT fk_eb_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}
$cols = $driver === 'sqlite' ? $pdo->query('PRAGMA table_info(employees)')->fetchAll(PDO::FETCH_ASSOC) : $pdo->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_ASSOC);
$names = array_map(fn($c) => $c['name'], $cols);
if (!in_array('user_id', $names, true)) {
    if ($driver === 'sqlite') { $statements[] = 'ALTER TABLE employees ADD COLUMN user_id INT NULL'; }
    else { $statements[] = 'ALTER TABLE employees ADD COLUMN user_id INT NULL'; }
}
$n = 0;
foreach ($statements as $sql) { $pdo->exec($sql); $n++; }
echo "employees upgrade: applied $n statements (table + column ensured).\n";
