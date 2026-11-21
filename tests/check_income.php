<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT id, income_no, amount, status, description, created_at FROM other_income ORDER BY id") as $r)
    echo "income: #{$r['id']} {$r['income_no']} {$r['amount']} {$r['status']} '{$r['description']}' {$r['created_at']}\n";
foreach ($pdo->query("SELECT id, entry_no, reference_type, memo, status, created_at FROM journal_entries WHERE reference_type LIKE 'capital_%' OR reference_type = 'other_income' ORDER BY id") as $r)
    echo "entry: #{$r['id']} {$r['entry_no']} {$r['reference_type']} '{$r['memo']}' {$r['status']} {$r['created_at']}\n";
