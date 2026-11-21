<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$p = $pdo->query("SELECT id, sku, name, kind, is_active FROM products WHERE sku='DEMO-FG-10'")->fetch(PDO::FETCH_ASSOC);
echo "product: {$p['id']} {$p['sku']} {$p['name']} kind={$p['kind']}\n";
foreach ($pdo->query("SELECT sb.warehouse_id, w.code, sb.qty, sb.avg_cost FROM stock_balances sb JOIN warehouses w ON w.id=sb.warehouse_id WHERE sb.product_id = {$p['id']}") as $r)
    echo "balance wh={$r['code']} qty={$r['qty']} avg={$r['avg_cost']}\n";
echo "\n--- last 25 movements ---\n";
foreach ($pdo->query("SELECT m.id, m.created_at, m.type, m.qty, m.balance_qty, w.code AS wh, m.ref_type, m.ref_id, m.notes FROM stock_movements m JOIN warehouses w ON w.id=m.warehouse_id WHERE m.product_id = {$p['id']} ORDER BY m.id DESC LIMIT 25") as $r)
    echo "#{$r['id']} {$r['created_at']} {$r['type']} qty={$r['qty']} bal={$r['balance_qty']} {$r['wh']} ref={$r['ref_type']}\n";
