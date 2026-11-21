<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("DELETE FROM stock_movements WHERE ref_type = 'grinding_send'");
$pdo->exec("DELETE FROM stock_movements WHERE ref_type = 'grinding_return'");
$pdo->exec("DELETE FROM grinding_returns");
$pdo->exec("DELETE FROM grinding_records");
$pdo->exec("DELETE FROM grinding_sends");
$cnt = $pdo->query("SELECT (SELECT COUNT(*) FROM grinding_sends) + (SELECT COUNT(*) FROM grinding_records) + (SELECT COUNT(*) FROM grinding_returns)")->fetchColumn();
$stock = $pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = 3 AND warehouse_id = (SELECT id FROM warehouses WHERE code='WH-MAIN')")->fetchColumn();
$mov = $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE ref_type IN ('grinding_send','grinding_return')")->fetchColumn();
echo "grinding rows left: $cnt, movements: $mov, VIR stock: $stock KG\n";
