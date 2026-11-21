<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
echo "--- FG stock after cleanup (expect 13 central):\n";
foreach ($pdo->query("SELECT sb.product_id, sb.warehouse_id, w.code, w.type, sb.qty, p.name FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id JOIN products p ON p.id = sb.product_id WHERE p.id IN (SELECT id FROM products WHERE kind='finished') ORDER BY sb.product_id, w.code") as $r) print_r($r);
echo "--- audit 'gifts' rows count: " . (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entity='gifts'")->fetchColumn() . "\n";
echo "--- stock_movements count for gift_goods: " . (int)$pdo->query("SELECT COUNT(*) FROM stock_movements WHERE type='gift_goods'")->fetchColumn() . "\n";
