<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT code, name, type, system_key FROM chart_of_accounts WHERE system_key IS NOT NULL ORDER BY code") as $r)
    echo $r['code'], ' | ', $r['name'], ' | ', $r['type'], ' | ', $r['system_key'], "\n";
