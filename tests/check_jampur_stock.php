<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT p.id, p.sku, p.name, p.kind, COALESCE((SELECT SUM(qty) FROM stock_balances sb WHERE sb.product_id = p.id),0) AS stock FROM products p WHERE p.kind IN ('raw','packaging') ORDER BY p.id") as $r)
    echo "id={$r['id']} {$r['sku']} {$r['name']} kind={$r['kind']} stock={$r['stock']}\n";
echo "--- grinding tables: ", $pdo->query("SELECT (SELECT COUNT(*) FROM grinding_sends) + (SELECT COUNT(*) FROM grinding_records) + (SELECT COUNT(*) FROM grinding_returns)")->fetchColumn(), "\n";
