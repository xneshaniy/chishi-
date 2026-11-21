<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
$rows = db_all("SELECT id, invoice_no, total, amount_paid, payment_status, created_at FROM purchase_invoices ORDER BY id DESC LIMIT 5");
foreach ($rows as $r) {
    echo "ID: {$r['id']} | Inv: {$r['invoice_no']} | Total: {$r['total']} | Paid: {$r['amount_paid']} | Status: {$r['payment_status']} | Time: {$r['created_at']}\n";
}
