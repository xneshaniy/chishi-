<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "purchase_invoices:\n";
foreach ($db->query('SELECT id, invoice_no, date, payment_type, status, total FROM purchase_invoices ORDER BY id') as $r)
    echo $r['id'], ' | ', $r['invoice_no'], ' | ', $r['date'], ' | ', $r['payment_type'], ' | ', $r['status'], ' | ', $r['total'], "\n";
echo "\npurchase_items:\n";
foreach ($db->query('SELECT pi.id, pi.invoice_id, p.sku, pi.qty, pi.unit_cost FROM purchase_items pi JOIN products p ON p.id = pi.product_id ORDER BY pi.id') as $r)
    echo $r['id'], ' | inv ', $r['invoice_id'], ' | ', $r['sku'], ' | qty ', $r['qty'], ' | cost ', $r['unit_cost'], "\n";
echo "\nstock balances\n";
foreach ($db->query('SELECT p.sku, sb.qty FROM stock_balances sb JOIN products p ON p.id = sb.product_id') as $r)
    echo $r['sku'], ' => ', $r['qty'], "\n";
