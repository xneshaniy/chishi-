<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('gifts','expenses','personal_loans','rm_daily_usage')") as $r) echo $r['name'], "\n";
echo "--- expenses columns:\n";
foreach ($pdo->query("PRAGMA table_info(expenses)") as $r) echo $r['name'], "\n";
echo "--- maintenance_assets columns:\n";
foreach ($pdo->query("PRAGMA table_info(maintenance_assets)") as $r) echo $r['name'], "\n";
