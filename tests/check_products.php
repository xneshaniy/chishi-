<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT id, code, name FROM customers LIMIT 3") as $r) echo "customer#{$r['id']} {$r['code']} {$r['name']}\n";
foreach ($pdo->query("SELECT id, sku, name, sale_price, kind FROM products WHERE kind='finished' LIMIT 4") as $r) echo "product#{$r['id']} {$r['sku']} {$r['name']} price={$r['sale_price']} kind={$r['kind']}\n";
foreach ($pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances sb JOIN products p ON p.id=sb.product_id WHERE p.kind='finished'") as $r) echo "finished stock total: {$r[0]}\n";
foreach ($pdo->query("SELECT p.sku, COALESCE(SUM(sb.qty),0) stock FROM products p LEFT JOIN stock_balances sb ON sb.product_id=p.id WHERE p.kind='finished' GROUP BY p.id") as $r) echo "  {$r['sku']}: {$r['stock']}\n";
