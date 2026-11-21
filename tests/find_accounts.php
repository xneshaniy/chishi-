<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT code, name, type FROM chart_of_accounts WHERE name LIKE '%apital%' OR name LIKE '%rawing%' OR name LIKE '%ncome%' OR name LIKE '%rawings%' OR code LIKE '5%' ORDER BY code") as $r)
    echo $r['code'], ' | ', $r['name'], ' | ', $r['type'], "\n";
