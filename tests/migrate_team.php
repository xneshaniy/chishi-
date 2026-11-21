<?php
/** Add employees.user_id to dev.sqlite (idempotent). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$cols = array_column($pdo->query("PRAGMA table_info(employees)")->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('user_id', $cols, true)) {
    $pdo->exec('ALTER TABLE employees ADD COLUMN user_id INT NULL');
    echo "user_id column added\n";
} else {
    echo "user_id column already present\n";
}
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_employees_user_id ON employees (user_id)');
