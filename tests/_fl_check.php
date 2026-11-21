<?php
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/storage/dev.sqlite');
$pid = (int)$pdo->query("SELECT id FROM products WHERE sku='STDEMO-1'")->fetchColumn();
echo "STDEMO-1 id: ", var_export($pid, true), "\n";
if ($pid) {
    echo "STDEMO-1 movements:\n";
    foreach ($pdo->query("SELECT id, type, qty, warehouse_id, ref_type, ref_id, created_at FROM stock_movements WHERE product_id = $pid ORDER BY id") as $r) {
        echo "  id={$r['id']} type={$r['type']} qty={$r['qty']} wh={$r['warehouse_id']} ref={$r['ref_type']}#{$r['ref_id']} at={$r['created_at']}\n";
    }
    echo "stock_balances view:\n";
    foreach ($pdo->query("SELECT warehouse_id, qty FROM stock_balances WHERE product_id = $pid") as $r) {
        echo "  wh={$r['warehouse_id']} qty={$r['qty']}\n";
    }
}
echo "\nFactory FG balances before today:\n";
foreach ($pdo->query("SELECT warehouse_id, SUM(qty) AS qty FROM stock_balances WHERE product_id != 0 GROUP BY warehouse_id") as $r) {}
echo "\nToday's movements all:\n";
foreach ($pdo->query("SELECT sm.warehouse_id, SUM(sm.qty) AS net FROM stock_movements sm WHERE date(sm.created_at) = date('2026-11-17') GROUP BY sm.warehouse_id") as $r) echo "  wh={$r['warehouse_id']} net={$r['net']}\n";
echo "\nProducts with today's movements:\n";
foreach ($pdo->query("SELECT p.id, p.sku, p.name, p.kind FROM products p WHERE p.id IN (SELECT DISTINCT product_id FROM stock_movements WHERE date(created_at) = date('2026-11-17'))") as $r) echo "  id={$r['id']} sku={$r['sku']} name={$r['name']}\n";
