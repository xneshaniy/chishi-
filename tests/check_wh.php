<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
$rows = $pdo->query("SELECT sb.warehouse_id, w.code, w.name, w.type, p.sku, sb.qty, sb.avg_cost FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id JOIN products p ON p.id = sb.product_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo "WH{$r['warehouse_id']} ({$r['code']}) | {$r['sku']} | qty {$r['qty']} | avg ", number_format($r['avg_cost'], 2), "\n";
