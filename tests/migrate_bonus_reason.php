<?php
/** Add bonus_reason column to salesman_issues in dev DB. */
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function hasCol(PDO $db, string $table, string $col): bool {
    foreach ($db->query("PRAGMA table_info($table)") as $c) if ($c['name'] === $col) return true;
    return false;
}
if (!hasCol($db, 'salesman_issues', 'bonus_reason')) {
    $db->exec("ALTER TABLE salesman_issues ADD COLUMN bonus_reason VARCHAR(255) NULL");
    echo "bonus_reason added\n";
} else {
    echo "bonus_reason already exists\n";
}
