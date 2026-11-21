<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
echo "boms:\n";
foreach ($db->query('SELECT id, code, name FROM boms') as $r) echo $r['id'], ' | ', $r['code'], ' | ', $r['name'], "\n";
echo "bom_items:\n";
try {
    foreach ($db->query('SELECT bi.bom_id, p.sku, bi.qty FROM bom_items bi JOIN products p ON p.id = bi.product_id') as $r) echo $r['bom_id'], ' | ', $r['sku'], ' | ', $r['qty'], "\n";
} catch (Throwable $e) { echo $e->getMessage(), "\n"; }
echo "tables:\n";
foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '%bom%' OR name LIKE '%production%' OR name LIKE '%input%'") as $r) echo $r['name'], "\n";
