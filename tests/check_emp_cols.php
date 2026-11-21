<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query('PRAGMA table_info(employees)') as $r) echo $r['name'], ' ', $r['type'], "\n";
echo "--- attendance rows: ", $pdo->query('SELECT COUNT(*) FROM attendance')->fetchColumn(), "\n";
echo "--- employees rows: ", $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn(), "\n";
