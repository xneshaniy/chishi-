<?php
$path = __DIR__ . '/../storage/dev.sqlite';
$db = new PDO('sqlite:' . $path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function hasCol(PDO $db, string $table, string $col): bool {
    foreach ($db->query("PRAGMA table_info($table)") as $c) if ($c['name'] === $col) return true;
    return false;
}
if (!hasCol($db, 'purchase_invoices', 'payment_status')) {
    $db->exec("ALTER TABLE purchase_invoices ADD COLUMN payment_status VARCHAR(10) NOT NULL DEFAULT 'unpaid'");
    echo "payment_status added\n";
} else echo "payment_status exists\n";
if (!hasCol($db, 'purchase_invoices', 'amount_paid')) {
    $db->exec("ALTER TABLE purchase_invoices ADD COLUMN amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0");
    echo "amount_paid added\n";
} else echo "amount_paid exists\n";
$db->exec("UPDATE purchase_invoices SET payment_status = 'paid', amount_paid = total WHERE payment_type = 'cash' AND payment_status = 'unpaid'");
echo "backfill cash paid: ", $db->query("SELECT COUNT(*) FROM purchase_invoices WHERE payment_status = 'paid'")->fetchColumn(), " paid invoices\n";
