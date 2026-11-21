<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
$rows = $pdo->query("SELECT id, sku, name, kind, unit_id FROM products WHERE kind IN ('raw','packaging') ORDER BY kind, name")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo $r['id'], " | ", $r['sku'], " | ", $r['name'], " | ", $r['kind'], " | unit ", $r['unit_id'], "\n";
$kg = $pdo->query("SELECT id FROM units WHERE short='KG'")->fetchColumn();
echo "KG unit id: $kg\n";
echo "factory warehouse: "; print_r($pdo->query("SELECT id, code, name, type FROM warehouses ORDER BY id")->fetchAll(PDO::FETCH_ASSOC));
