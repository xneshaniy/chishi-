<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
$rows = $pdo->query("SELECT p.id, p.sku, COALESCE(SUM(sb.qty),0) AS on_hand FROM products p LEFT JOIN stock_balances sb ON sb.product_id = p.id GROUP BY p.id ORDER BY p.id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo $r['id'], ' | ', $r['sku'], ' | ', $r['on_hand'], "\n";
