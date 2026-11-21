<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$stock = $pdo->query("SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE product_id = 3 AND warehouse_id = (SELECT id FROM warehouses WHERE code='WH-MAIN')")->fetchColumn();
echo "VIR stock now: $stock KG\n";
foreach ($pdo->query("SELECT id, type, qty, ref_type, ref_id FROM stock_movements WHERE ref_type IN ('grinding_send','grinding_return') ORDER BY id") as $r)
    echo "movement id={$r['id']} type={$r['type']} qty={$r['qty']} ref={$r['ref_type']}#{$r['ref_id']}\n";
foreach ($pdo->query("SELECT * FROM grinding_sends") as $r)
    echo "send id={$r['id']} no={$r['send_no']} qty={$r['qty_kg']} cost={$r['unit_cost']}\n";
foreach ($pdo->query("SELECT * FROM grinding_records") as $r)
    echo "record id={$r['id']} received={$r['qty_received_kg']} loss={$r['loss_kg']} charges={$r['charges']} status={$r['charges_status']}\n";
foreach ($pdo->query("SELECT * FROM grinding_returns") as $r)
    echo "return id={$r['id']} qty={$r['qty_kg']}\n";
