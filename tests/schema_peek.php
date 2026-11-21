<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach (['bills_of_materials', 'bom_lines', 'production_orders', 'production_inputs'] as $t) {
    echo "== $t\n";
    foreach ($db->query("PRAGMA table_info($t)") as $c) echo $c['name'], ' ';
    echo "\n";
}
