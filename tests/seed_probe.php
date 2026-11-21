<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
echo "=== roles ===\n";
foreach ($pdo->query('SELECT code, name FROM roles') as $r) echo $r['code'], " | ", $r['name'], "\n";
