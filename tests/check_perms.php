<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT code, module FROM permissions WHERE module IN ('employees','attendance','reports','staff') ORDER BY module, code") as $r)
    echo $r['module'], ' / ', $r['code'], "\n";
