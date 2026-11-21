<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
echo "--- gift warehouses intact ---\n";
foreach ($pdo->query("SELECT id, code, type FROM warehouses") as $r) print_r($r);
echo "--- recent gift movements ---\n";
foreach ($pdo->query("SELECT id, type, product_id, warehouse_id, qty, ref_type, ref_id, created_at FROM stock_movements WHERE ref_type = 'gifts' ORDER BY id") as $r) print_r($r);
