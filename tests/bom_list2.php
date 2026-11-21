<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
echo "bills_of_materials:\n";
foreach ($db->query('SELECT id, code, product_id FROM bills_of_materials') as $r) echo $r['id'], ' | ', $r['code'], ' | product_id=', $r['product_id'], "\n";
echo "bom_lines:\n";
foreach ($db->query('SELECT bl.bom_id, p.sku, bl.qty FROM bom_lines bl JOIN products p ON p.id = bl.material_id') as $r) echo $r['bom_id'], ' | ', $r['sku'], ' | ', $r['qty'], "\n";
