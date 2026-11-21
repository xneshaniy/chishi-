<?php
/**
 * JUSTI NUMBER ONE ERP — Salary Payment Frequency upgrade for existing installations.
 * Adds `salary_frequency` ('monthly'|'weekly', default 'monthly' — preserves current behavior)
 * to `employees` and `salesmen`. Safe to run multiple times.
 *
 * Usage (on the server): php install/upgrade_salary_frequency.php
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

$tables = ['employees', 'salesmen'];

if ($driver === 'sqlite') {
    $pdo = new PDO(strpos((string)($db['dsn'] ?? ''), 'sqlite:') === 0 ? $db['dsn'] : 'sqlite:' . $db['dsn']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ($tables as $t) {
        $cols = array_column($pdo->query('PRAGMA table_info(' . $t . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!$cols) { echo "SKIP (missing table): $t\n"; continue; }
        if (!in_array('salary_frequency', $cols, true)) {
            $pdo->exec("ALTER TABLE $t ADD COLUMN salary_frequency VARCHAR(10) NOT NULL DEFAULT 'monthly'");
            echo "ADDED salary_frequency → $t\n";
        } else {
            echo "OK (already present): $t\n";
        }
        // keep employees.salary_type aligned with frequency for the payroll engine (monthly/weekly only)
        if ($t === 'employees' && in_array('salary_type', $cols, true)) {
            $pdo->exec("UPDATE employees SET salary_frequency = salary_type WHERE salary_type IN ('monthly','weekly','contract')");
            $pdo->exec("UPDATE employees SET salary_frequency = 'monthly' WHERE salary_frequency NOT IN ('monthly','weekly')");
            $pdo->exec("UPDATE employees SET salary_type = salary_frequency WHERE salary_type IN ('monthly','weekly','contract') AND salary_frequency IN ('monthly','weekly')");
            echo "aligned employees.salary_type ↔ salary_frequency\n";
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
    $cols = array_column($pdo->query('SHOW COLUMNS FROM ' . $t)->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('salary_frequency', $cols, true)) {
        $pdo->exec("ALTER TABLE $t ADD COLUMN salary_frequency VARCHAR(10) NOT NULL DEFAULT 'monthly'");
        echo "ADDED salary_frequency → $t\n";
    } else {
        echo "OK (already present): $t\n";
    }
    if ($t === 'employees') {
        $pdo->exec("UPDATE employees SET salary_frequency = salary_type WHERE salary_type IN ('monthly','weekly','contract')");
        $pdo->exec("UPDATE employees SET salary_frequency = 'monthly' WHERE salary_frequency NOT IN ('monthly','weekly')");
        $pdo->exec("UPDATE employees SET salary_type = salary_frequency WHERE salary_type IN ('monthly','weekly','contract') AND salary_frequency IN ('monthly','weekly')");
        echo "aligned employees.salary_type ↔ salary_frequency\n";
    }
}
