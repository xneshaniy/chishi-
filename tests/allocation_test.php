<?php
/** Supplier payment allocation FIFO across credit purchase invoices (Paid/Unpaid/Partial). */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average', 'allow_negative_stock' => false];
$_SESSION = [];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Inventory.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/UnitConverter.php';
require __DIR__ . '/../app/services/Reporting.php';
require __DIR__ . '/../app/services/Notifications.php';
require __DIR__ . '/../app/services/Approvals.php';
require __DIR__ . '/../database/seed.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys = ON');
Database::setPdo($pdo);
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sqlite.sql'));
seed_base($pdo);

$tests = 0; $failures = 0;
function ok(bool $cond, string $name): void {
    global $tests, $failures;
    $tests++;
    if ($cond) { echo "  PASS  $name\n"; } else { $failures++; echo "  FAIL  $name\n"; }
}

$whMain = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN'");
$cashId = (int)db_val("SELECT id FROM cash_bank_accounts WHERE type = 'cash' ORDER BY id LIMIT 1");
$now = now_local();
$rm = db_insert('products', ['sku' => 'RAW-ALLOC', 'name' => 'Alloc RM', 'kind' => 'raw', 'purchase_price' => 800, 'reorder_level' => 0, 'is_active' => 1, 'created_at' => $now]);
$supId = db_insert('suppliers', ['code' => 'SUP-ALLOC', 'name' => 'Alloc Supplier', 'opening_balance' => 0, 'opening_type' => 'credit', 'is_active' => 1, 'created_at' => $now]);

$mkInvoice = function (float $qty, float $cost) use ($supId, $whMain, $rm, $now) {
    $id = db_insert('purchase_invoices', ['invoice_no' => 'TMP-A-' . bin2hex(random_bytes(2)), 'supplier_id' => $supId, 'date' => today(), 'payment_type' => 'credit', 'warehouse_id' => $whMain, 'subtotal' => $qty * $cost, 'discount' => 0, 'tax' => 0, 'total' => $qty * $cost, 'status' => 'posted', 'payment_status' => 'unpaid', 'amount_paid' => 0, 'created_at' => $now]);
    db_insert('purchase_items', ['invoice_id' => $id, 'product_id' => $rm, 'qty' => $qty, 'unit_cost' => $cost, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => $qty * $cost, 'is_bonus' => 0]);
    return (int)$id;
};
$inv1 = $mkInvoice(100, 800);   // 80,000
$inv2 = $mkInvoice(50, 800);    // 40,000

$paySupplier = function (float $amount) use ($supId, $cashId, $now) {
    $pid = db_insert('payments', ['payment_no' => 'PY-T-' . bin2hex(random_bytes(2)), 'party_type' => 'supplier', 'party_id' => $supId, 'account_id' => $cashId, 'date' => today(), 'amount' => $amount, 'status' => 'pending', 'created_at' => $now]);
    Posting::postPayment((int)$pid);
    return (int)$pid;
};
$st = fn(int $id) => db_one('SELECT payment_status, amount_paid FROM purchase_invoices WHERE id = ?', [$id]);

$paySupplier(30000);
$a = $st($inv1);
ok($a['payment_status'] === 'partial' && approx2((float)$a['amount_paid'], 30000), 'after 30,000 payment: inv1 PARTIAL 30,000');
$a = $st($inv2);
ok($a['payment_status'] === 'unpaid' && approx2((float)$a['amount_paid'], 0), 'after 30,000 payment: inv2 still UNPAID');

$paySupplier(50000);
$a = $st($inv1);
ok($a['payment_status'] === 'paid' && approx2((float)$a['amount_paid'], 80000), 'after 50,000 more: inv1 PAID in full');
$a = $st($inv2);
ok($a['payment_status'] === 'unpaid' && approx2((float)$a['amount_paid'], 0), 'after 50,000 more: inv2 still UNPAID (FIFO)');

$paySupplier(40000);
$a = $st($inv2);
ok($a['payment_status'] === 'paid' && approx2((float)$a['amount_paid'], 40000), 'after 40,000: inv2 PAID');
$a = $st($inv1);
ok($a['payment_status'] === 'paid', 'inv1 remains PAID');

function approx2(float $a, float $b, float $eps = 0.01): bool { return abs($a - $b) <= $eps; }
echo "\nPAYMENT ALLOCATION: $tests tests, $failures failures\n";
exit($failures > 0 ? 1 : 0);
