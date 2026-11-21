<?php
$p = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "== Claim goods ==\n";
foreach ($p->query("SELECT cg.id, cg.claim_no, cg.status, cg.payment_type, cg.good_qty, cg.damaged_qty, cg.rejected_qty, cg.claim_amount, cg.cash_paid, s.name AS supplier FROM claim_goods cg JOIN suppliers s ON s.id = cg.supplier_id") as $r) echo json_encode($r), "\n";
echo "\n== Stock for DEMO-FG-10 (id 4?) ==\n";
foreach ($p->query("SELECT id, sku, name FROM products WHERE id IN (4,5)") as $r) echo json_encode($r), "\n";
foreach ($p->query("SELECT warehouse_id, product_id, qty, avg_cost FROM stock_balances WHERE product_id = 4 AND warehouse_id = 1") as $r) echo json_encode($r), "\n";
echo "\n== Journal for claim ==\n";
foreach ($p->query("SELECT entry_no, voucher_type, reference_type FROM journal_entries WHERE reference_type = 'claim_goods'") as $r) echo json_encode($r), "\n";
echo "\n== Claim journal lines ==\n";
foreach ($p->query("SELECT jl.debit, jl.credit, a.system_key, a.name FROM journal_lines jl JOIN journal_entries e ON e.id = jl.entry_id JOIN chart_of_accounts a ON a.id = jl.account_id WHERE e.reference_type = 'claim_goods'") as $r) echo json_encode($r), "\n";
echo "\n== Movements for claim ==\n";
foreach ($p->query("SELECT sm.product_id, sm.warehouse_id, sm.qty, sm.movement FROM stock_movements sm WHERE sm.ref_type = 'claim_goods'") as $r) echo json_encode($r), "\n";
