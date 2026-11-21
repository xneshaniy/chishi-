<?php
/** Add return_condition to salesman_issue_items in dev.sqlite (idempotent). */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$cols = array_column($pdo->query('PRAGMA table_info(salesman_issue_items)')->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('return_condition', $cols, true)) {
    $pdo->exec("ALTER TABLE salesman_issue_items ADD COLUMN return_condition VARCHAR(10) NULL");
    echo "return_condition added\n";
} else { echo "return_condition already exists\n"; }
