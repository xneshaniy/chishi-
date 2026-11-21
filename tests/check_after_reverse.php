<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$inv = $pdo->query("SELECT invoice_no, status FROM sales_invoices WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
echo "SI-00001: {$inv['invoice_no']} status={$inv['status']}\n";
foreach ($pdo->query("SELECT sb.qty, w.code FROM stock_balances sb JOIN warehouses w ON w.id=sb.warehouse_id WHERE sb.product_id=1") as $r)
    echo "FG-10 {$r['code']}: {$r['qty']} cartons\n";
$tot = $pdo->query("SELECT SUM(qty) FROM stock_balances WHERE product_id=1")->fetchColumn();
echo "Total FG-10 across all warehouses: $tot\n";
