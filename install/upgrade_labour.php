<?php
/**
 * JUSTI NUMBER ONE ERP — labour module upgrade for EXISTING installations.
 * Creates the labour helper tables if they are missing (idempotent).
 * Fresh installs already get everything from database/schema.mysql.sql.
 *
 * Usage (on the server):  php install/upgrade_labour.php
 * Safe to run multiple times; does not touch existing records.
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
    $dsn = $db['dsn'] ?? ($db['path'] ?? '');
    if ($dsn === '') { fwrite(STDERR, "config/config.php has no sqlite dsn/path — aborting.\n"); exit(1); }
    if (is_string($dsn) && !str_starts_with($dsn, 'sqlite:')) $dsn = 'sqlite:' . $dsn;
    $pdo = new PDO($dsn);
    $statements[] = "CREATE TABLE IF NOT EXISTS staff_leaves (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        days INT NOT NULL DEFAULT 1,
        leave_type VARCHAR(40) NOT NULL DEFAULT 'casual',
        is_paid INTEGER NOT NULL DEFAULT 1,
        reason VARCHAR(255) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        approved_by INT NULL,
        approved_at DATETIME NULL,
        notes VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_staff_leave FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE)";
    $statements[] = 'CREATE INDEX IF NOT EXISTS idx_staff_leaves_emp ON staff_leaves (employee_id, start_date)';
    $statements[] = "CREATE TABLE IF NOT EXISTS salary_changes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INT NOT NULL,
        salary_type VARCHAR(10) NOT NULL DEFAULT 'monthly',
        old_rate DECIMAL(18,2) NULL,
        new_rate DECIMAL(18,2) NOT NULL,
        effective_date DATE NOT NULL,
        note VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_salary_change FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE)";
    $statements[] = "CREATE TABLE IF NOT EXISTS employee_deductions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INT NOT NULL,
        date DATE NOT NULL,
        reason VARCHAR(255) NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        approved_by INT NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_edd_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE)";
} else {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
        $db['user'] ?? '', $db['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $statements[] = "CREATE TABLE IF NOT EXISTS staff_leaves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        days INT NOT NULL DEFAULT 1,
        leave_type VARCHAR(40) NOT NULL DEFAULT 'casual',
        is_paid TINYINT(1) NOT NULL DEFAULT 1,
        reason VARCHAR(255) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        approved_by INT NULL,
        approved_at DATETIME NULL,
        notes VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_leaves_emp (employee_id, start_date),
        CONSTRAINT fk_staff_leave FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $statements[] = "CREATE TABLE IF NOT EXISTS salary_changes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        salary_type VARCHAR(10) NOT NULL DEFAULT 'monthly',
        old_rate DECIMAL(18,2) NULL,
        new_rate DECIMAL(18,2) NOT NULL,
        effective_date DATE NOT NULL,
        note VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_salary_change FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $statements[] = "CREATE TABLE IF NOT EXISTS employee_deductions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        date DATE NOT NULL,
        reason VARCHAR(255) NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        approved_by INT NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_edd_emp FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}
$n = 0;
foreach ($statements as $sql) { $pdo->exec($sql); $n++; }
echo "labour upgrade: applied $n statements (tables ensured).\n";
