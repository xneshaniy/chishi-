<?php
/**
 * Raw-material purchase-request approval flow (manager submits -> owner approves).
 * Mirrors the non-admin branch in PurchaseController::invoiceNew and the owner path
 * through Approvals::decide -> dispatchPost -> Posting::postPurchaseInvoice.
 * Runs against an in-memory SQLite database.
 */
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
$now = now_local();

// roles + users
$ownerId = (int)db_val("SELECT id FROM roles WHERE code = 'owner'");
$adminRole = (int)db_val("SELECT id FROM roles WHERE code = 'admin'");
$managerRole = (int)db_val("SELECT id FROM roles WHERE code = 'manager'");
$mkUser = function (string $u, int $roleId) use ($now) {
    $id = db_insert('users', ['username' => $u, 'full_name' => $u, 'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'is_active' => 1, 'created_at' => $now]);
    db_insert('user_roles', ['user_id' => $id, 'role_id' => $roleId]);
    return (int)$id;
};
$owner = $mkUser('owner_e2e', $ownerId);
$admin = $mkUser('admin_e2e', $adminRole);
$manager = $mkUser('manager_e2e', $managerRole);

// raw product + supplier
$rm = db_insert('products', ['sku' => 'RAW-E2E', 'name' => 'E2E Raw Tobacco', 'kind' => 'raw', 'purchase_price' => 800, 'reorder_level' => 10, 'is_active' => 1, 'created_at' => $now]);
$supId = db_insert('suppliers', ['code' => 'SUP-E2E', 'name' => 'E2E Supplier', 'opening_balance' => 0, 'opening_type' => 'credit', 'is_active' => 1, 'created_at' => $now]);

echo "\n=== 1. Manager GRN goes to pending approval; stock does NOT move ===\n";
$total = 100 * 800;
$invoiceId = db_insert('purchase_invoices', ['invoice_no' => 'TMP-E2E-1', 'supplier_id' => $supId, 'po_id' => null, 'supplier_invoice_no' => 'INV-E2E-1', 'date' => today(), 'payment_type' => 'credit', 'warehouse_id' => $whMain, 'subtotal' => $total, 'discount' => 0, 'tax' => 0, 'total' => $total, 'status' => 'pending_approval', 'created_by' => $manager, 'created_at' => $now]);
db_insert('purchase_items', ['invoice_id' => $invoiceId, 'product_id' => $rm, 'qty' => 100, 'unit_cost' => 800, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => $total, 'is_bonus' => 0]);
$reqId = Approvals::create('purchase_invoice', 'purchase_invoices', (int)$invoiceId, $total, 'E2E manager purchase', $manager);
ok($reqId > 0, 'approval request created for manager GRN');
ok(approx0(Inventory::balance($rm, $whMain)), 'stock does NOT move before approval (still 0)');
ok(db_val('SELECT status FROM purchase_invoices WHERE id = ?', [$invoiceId]) === 'pending_approval', 'invoice saved as pending_approval');
ok((int)db_val("SELECT COUNT(*) FROM approval_requests WHERE reference_type = 'purchase_invoices' AND reference_id = ? AND status = 'pending'", [$invoiceId]) === 1, 'approval request pending');

echo "\n=== 2. Manager cannot approve own request ===\n";
try {
    Approvals::decide($reqId, true, 'self', $manager);
    ok(false, 'manager self-approval rejected');
} catch (Throwable $e) {
    ok(true, 'manager self-approval blocked (' . $e->getMessage() . ')');
}

echo "\n=== 3. Owner approves -> GRN posts exactly once, stock up ===\n";
$res = Approvals::decide($reqId, true, 'approved in e2e', $owner);
ok(($res[0] ?? '') === 'approved', 'decision recorded as approved');
ok(db_val('SELECT status FROM purchase_invoices WHERE id = ?', [$invoiceId]) === 'posted', 'invoice flipped to posted');
ok(approx(Inventory::balance($rm, $whMain), 100), 'raw material stock increased to 100 after approval');
ok((int)db_val("SELECT COUNT(*) FROM journal_entries WHERE idem_key = ?", ['purchase_invoice:' . $invoiceId]) === 1, 'journal posted exactly once');
$mv = db_val("SELECT COUNT(*) FROM stock_movements WHERE ref_type = 'purchase_invoice' AND ref_id = ?", [$invoiceId]);
ok((int)$mv === 1, 'exactly one purchase movement written');

// exactly-once: decide again must refuse
try {
    Approvals::decide($reqId, true, 'again', $owner);
    ok(false, 'double approval refused');
} catch (Throwable $e) {
    ok(true, 'double approval refused (' . $e->getMessage() . ')');
}
ok(approx(Inventory::balance($rm, $whMain), 100), 'stock unchanged after refused second approval');

echo "\n=== 4. Manager purchase ORDER flow (approve exactly once) ===\n";
$poId = db_insert('purchase_orders', ['po_no' => 'PO-E2E-1', 'supplier_id' => $supId, 'order_date' => today(), 'status' => 'pending_approval', 'subtotal' => 20000, 'total' => 20000, 'created_by' => $manager, 'created_at' => $now]);
db_insert('purchase_order_items', ['po_id' => $poId, 'product_id' => $rm, 'qty' => 25, 'unit_cost' => 800, 'line_total' => 20000]);
$poReq = Approvals::create('purchase_request', 'purchase_orders', (int)$poId, 20000, 'E2E PO', $manager);
$res = Approvals::decide($poReq, true, 'ok', $owner);
ok(($res[0] ?? '') === 'approved', 'PO approved');
ok(db_val('SELECT status FROM purchase_orders WHERE id = ?', [$poId]) === 'approved', 'PO flipped to approved');
// decide again on PO
try { Approvals::decide($poReq, true, 'x', $owner); ok(false, 'double PO approval refused'); } catch (Throwable $e) { ok(true, 'double PO approval refused'); }
// idempotent service call (as if the console fired twice)
Posting::approvePurchaseOrder((int)$poId);
ok(db_val('SELECT status FROM purchase_orders WHERE id = ?', [$poId]) === 'approved', 'approvePurchaseOrder is idempotent');

echo "\n=== 5. Rejection flow marks source rejected, stock untouched ===\n";
$invoice2 = db_insert('purchase_invoices', ['invoice_no' => 'TMP-E2E-2', 'supplier_id' => $supId, 'date' => today(), 'payment_type' => 'credit', 'warehouse_id' => $whMain, 'subtotal' => 50000, 'discount' => 0, 'tax' => 0, 'total' => 50000, 'status' => 'pending_approval', 'created_by' => $manager, 'created_at' => $now]);
db_insert('purchase_items', ['invoice_id' => $invoice2, 'product_id' => $rm, 'qty' => 62, 'unit_cost' => 806.45, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => 50000, 'is_bonus' => 0]);
$req2 = Approvals::create('purchase_invoice', 'purchase_invoices', (int)$invoice2, 50000, 'E2E reject me', $manager);
Approvals::decide($req2, false, 'price too high', $owner);
ok(db_val('SELECT status FROM purchase_invoices WHERE id = ?', [$invoice2]) === 'rejected', 'rejected GRN marked rejected');
ok(approx(Inventory::balance($rm, $whMain), 100), 'stock unchanged after rejection');

echo "\n========================================\n";
echo "PURCHASE APPROVAL E2E: $tests tests, $failures failures\n";
exit($failures > 0 ? 1 : 0);

function approx(float $a, float $b, float $eps = 0.01): bool { return abs($a - $b) <= $eps; }
function approx0(float $v, float $eps = 0.001): bool { return abs($v) <= $eps; }
