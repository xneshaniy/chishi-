<?php
require __DIR__ . '/../app/core/bootstrap.php';
$tables = db_all("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
echo "TABLES (" . count($tables) . "):\n";
foreach ($tables as $t) {
    echo "- " . $t['name'] . "\n";
}
