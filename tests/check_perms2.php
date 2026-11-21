<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$count = 0;
foreach ($pdo->query("SELECT code, module FROM permissions ORDER BY module, code") as $r) { echo $r['module'], ' / ', $r['code'], "\n"; $count++; }
echo "total $count\n";
