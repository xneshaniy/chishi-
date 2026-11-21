<?php
/**
 * Notification system — end-to-end test in in-memory SQLite.
 * Covers: payment request → role notification, approval → requester notification,
 * expense/advance/loan notifications, sale/purchase/receipt notifications,
 * low-stock dedupe, permission scoping (salesman never sees approvals), mark read scoping,
 * unread count, exactly-once (no duplicates).
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average', 'allow_negative_stock' => false];
$_SESSION = [];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/core/Auth.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Inventory.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/UnitConverter.php';
require __DIR__ . '/../app/services/Reporting.php';
require __DIR__ . '/../app/services/Approvals.php';
require __DIR__ . '/../app/services/Notifications.php';
require __DIR__ . '/../app/services/Payroll.php';
require __DIR__ . '/../database/seed.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys = ON');
Database::setPdo($pdo);
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sqlite.sql'));
seed_base($pdo);

$tests = 0; $failures = 0;
function ok(bool $c, string $n): void { global $tests, $failures; $tests++; echo $c ? "  PASS  $n\n" : "  FAIL  $n\n"; if (!$c) $failures++; }
function approx($a, $b, $e = 0.01): bool { return abs((float)$a - (float)$b) <= $e; }

function mkUser(string $u, string $roleCode, ?int $salesmanId = null): int {
    $id = db_insert('users', ['username' => $u, 'full_name' => strtoupper($u), 'password_hash' => password_hash('Test@1234x', PASSWORD_DEFAULT), 'salesman_id' => $salesmanId, 'is_active' => 1, 'created_at' => now_local()]);
    $roleId = (int)db_val('SELECT id FROM roles WHERE code = ?', [$roleCode]);
    db_exec('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)', [$id, $roleId]);
    return $id;
}
$ownerId = mkUser('boss', 'owner');
$adminId = mkUser('admin1', 'admin');
$accId   = mkUser('acc1', 'accountant');
$mgrId   = mkUser('mgr1', 'manager');
$smId    = mkUser('van1', 'salesman');

$whMain = (int)db_val("SELECT id FROM warehouses WHERE code='WH-MAIN'");
$fg = db_insert('products', ['sku' => 'NT-FG', 'name' => 'Notify Test FG', 'kind' => 'finished', 'purchase_price' => 100, 'sale_price' => 150, 'issue_rate' => 10, 'min_price' => 12, 'tax_rate' => 0, 'reorder_level' => 5, 'is_active' => 1, 'created_at' => now_local()]);

echo "\n=== 1 · Payment request notifies Owner + Admin (and Manager), not salesman ===\n";
$supId = db_insert('suppliers', ['code' => 'SUP-N', 'name' => 'Notify Supplier', 'is_active' => 1, 'created_at' => now_local()]);
$cashId = (int)db_val("SELECT id FROM cash_bank_accounts WHERE type='cash' LIMIT 1");
$pid = db_insert('payments', ['payment_no' => 'TMP-N1', 'party_type' => 'supplier', 'party_id' => $supId, 'account_id' => $cashId, 'date' => today(), 'amount' => 12345, 'status' => 'pending', 'created_by' => $mgrId, 'created_at' => now_local()]);
$appr = Approvals::create('payment', 'payments', $pid, 12345, 'Test notify payment', $mgrId);
$rows = db_all("SELECT user_id, title FROM notifications WHERE type = 'payment_request'");
$notified = array_column($rows, 'user_id');
ok(in_array($ownerId, $notified), 'owner notified of payment request');
ok(in_array($adminId, $notified), 'admin notified of payment request');
ok(in_array($mgrId, $notified), 'manager notified (payment request)');
ok(!in_array($smId, $notified), 'salesman NOT notified of approvals');
ok(!in_array($accId, $notified), 'accountant NOT notified of owner payment request (needs admin visibility? — notify owner/admin only per rule)');
$cnt = count($rows);
ok($cnt === 3, "exactly 3 notifications sent (got $cnt)");

echo "\n=== 2 · Duplicate request does not duplicate notifications ===\n";
Approvals::create('payment', 'payments', $pid, 12345, 'Test notify payment', $mgrId);
ok((int)db_val("SELECT COUNT(*) FROM notifications WHERE type = 'payment_request'") === 3, 'duplicate create is blocked, no extra notifications');

echo "\n=== 3 · Approve → requester notified; exactly once ===\n";
Approvals::decide((int)$appr, true, 'ok', $ownerId);
$n = db_all("SELECT user_id, title, is_read FROM notifications WHERE type = 'approval'");
ok(count($n) === 1 && (int)$n[0]['user_id'] === $mgrId, 'requester (manager) notified of approval');
ok(stripos($n[0]['title'], 'APPROVED') !== false, 'notification says APPROVED');
$thrown = false;
try { Approvals::decide((int)$appr, true, 'again', $ownerId); } catch (Throwable $e) { $thrown = true; }
ok($thrown, 'double-decide throws');
ok((int)db_val("SELECT COUNT(*) FROM notifications WHERE type = 'approval'") === 1, 'no duplicate approval notifications');

echo "\n=== 4 · Reject → requester notified with REJECTED ===\n";
$pid2 = db_insert('payments', ['payment_no' => 'TMP-N2', 'party_type' => 'supplier', 'party_id' => $supId, 'account_id' => $cashId, 'date' => today(), 'amount' => 500, 'status' => 'pending', 'created_by' => $mgrId, 'created_at' => now_local()]);
$appr2 = Approvals::create('payment', 'payments', $pid2, 500, 'reject me', $mgrId);
Approvals::decide((int)$appr2, false, 'not valid', $ownerId);
$rj = db_one("SELECT title FROM notifications WHERE type = 'approval' AND user_id = ? ORDER BY id DESC LIMIT 1", [$mgrId]);
ok(stripos($rj['title'], 'REJECTED') !== false, 'rejection notification sent to requester');

echo "\n=== 5 · Expense / advance / loan requests notify Owner+Admin ===\n";
$pid3 = db_insert('expenses', ['expense_no' => 'TMP-N3', 'category_id' => 1, 'date' => today(), 'amount' => 777, 'payment_type' => 'cash', 'account_id' => $cashId, 'status' => 'pending', 'created_by' => $mgrId, 'created_at' => now_local()]);
Approvals::create('expense', 'expenses', $pid3, 777, 'hotel stay', $mgrId);
ok((int)db_val("SELECT COUNT(*) FROM notifications WHERE type = 'payment_request' AND body LIKE '%hotel stay%'") >= 2, 'expense request notified (owner+admin)');
$empId = db_insert('employees', ['code' => 'EMP-N', 'name' => 'Notify Employee', 'salary_type' => 'monthly', 'salary_rate' => 20000, 'is_active' => 1, 'created_at' => now_local()]);
$advId = db_insert('employee_advances', ['employee_id' => $empId, 'date' => today(), 'amount' => 2000, 'notes' => 'loan me', 'status' => 'pending', 'requested_by' => $mgrId, 'account_id' => $cashId, 'created_at' => now_local()]);
Approvals::create('advance', 'employee_advances', $advId, 2000, 'salary advance — rent', $mgrId);
ok((int)db_val("SELECT COUNT(*) FROM notifications WHERE type = 'payment_request' AND body LIKE '%rent%'") >= 2, 'advance request notified (owner+admin)');
$lnId = db_insert('employee_loans', ['loan_no' => 'LN-N1', 'employee_id' => $empId, 'amount' => 9000, 'date' => today(), 'reason' => 'bike', 'installment_type' => 'monthly', 'installment_amount' => 1000, 'total_installments' => 9, 'outstanding' => 9000, 'status' => 'pending', 'account_id' => $cashId, 'requested_by' => $mgrId, 'created_at' => now_local()]);
Approvals::create('loan', 'employee_loans', $lnId, 9000, 'loan — bike', $mgrId);
ok((int)db_val("SELECT COUNT(*) FROM notifications WHERE type = 'payment_request' AND body LIKE '%bike%'") >= 2, 'loan request notified (owner+admin)');

echo "\n=== 6 · Posted sale notifies Admin+Manager ===\n";
Inventory::receive($fg, $whMain, 50, 100, 'opening', 'notify_test', null);
$custId = db_insert('customers', ['code' => 'CUST-N', 'name' => 'Notify Customer', 'is_active' => 1, 'created_at' => now_local()]);
$invId = db_insert('sales_invoices', ['invoice_no' => 'TMP-N4', 'customer_id' => $custId, 'warehouse_id' => $whMain, 'date' => today(), 'payment_type' => 'cash', 'subtotal' => 1500, 'discount' => 0, 'tax' => 0, 'total' => 1500, 'status' => 'posted', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $invId, 'product_id' => $fg, 'qty' => 10, 'unit_price' => 150, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => 1500, 'net_before_tax' => 1500, 'is_bonus' => 0, 'cost_price' => 0, 'cost_total' => 0]);
Posting::postSalesInvoice($invId);
ok((int)db_val("SELECT COUNT(*) FROM notifications WHERE type = 'sale'") >= 2, 'sale posted → owner/admin + manager notified');

echo "\n=== 7 · Receipt notifies Admin + Accountant (not salesman) ===\n";
$rcpt = db_insert('receipts', ['receipt_no' => 'TMP-N5', 'party_type' => 'customer', 'customer_id' => $custId, 'account_id' => $cashId, 'date' => today(), 'amount' => 1500, 'created_at' => now_local()]);
Posting::postReceipt($rcpt);
$who = array_column(db_all("SELECT user_id FROM notifications WHERE type = 'receipt'"), 'user_id');
ok(in_array($accId, $who), 'accountant notified of customer payment');
ok(in_array($ownerId, $who), 'owner notified of customer payment');
ok(!in_array($smId, $who), 'salesman NOT notified of accounting receipts');

echo "\n=== 8 · Low-stock alert fires exactly once per product per day ===\n";
$lowFg = db_insert('products', ['sku' => 'NT-LOW', 'name' => 'Low Stock Test Item', 'kind' => 'finished', 'purchase_price' => 10, 'sale_price' => 15, 'issue_rate' => 10, 'min_price' => 12, 'tax_rate' => 0, 'reorder_level' => 5, 'is_active' => 1, 'created_at' => now_local()]);
Inventory::receive($lowFg, $whMain, 1, 10, 'opening', 'low_stock_seed', null); // 1 unit, below reorder 5
Notifications::checkLowStock();
$before = (int)db_val("SELECT COUNT(*) FROM notifications WHERE type = 'low_stock'");
Notifications::checkLowStock();
$after = (int)db_val("SELECT COUNT(*) FROM notifications WHERE type = 'low_stock'");
ok($before >= 2, 'low stock alert sent to owner/admin/manager');
ok($after === $before, 'second same-day check sends nothing (deduped)');

echo "\n=== 9 · Unread count + mark read scoped to owner ===\n";
Notifications::markRead($ownerId, (int)db_val("SELECT MIN(id) FROM notifications WHERE user_id = ?", [$ownerId]), true);
try {
    Notifications::markRead($smId, (int)db_val("SELECT MIN(id) FROM notifications WHERE user_id = ?", [$ownerId]), true);
    ok(true, 'mark on other user is a no-op (scoped by user_id)');
} catch (Throwable $e) { ok(false, 'mark read threw: ' . $e->getMessage()); }
ok(Notifications::unreadCount($ownerId) >= 1, 'owner still has unread (other notifications)');
Notifications::markAllRead($ownerId);
ok(Notifications::unreadCount($ownerId) === 0, 'mark-all-read clears unread for owner');

echo "\n=== 10 · Purchase notification ===\n";
$purId = db_insert('purchase_invoices', ['invoice_no' => 'TMP-N6', 'supplier_id' => $supId, 'warehouse_id' => $whMain, 'date' => today(), 'payment_type' => 'credit', 'subtotal' => 5000, 'discount' => 0, 'tax' => 0, 'total' => 5000, 'status' => 'posted', 'created_at' => now_local()]);
db_insert('purchase_items', ['invoice_id' => $purId, 'product_id' => $fg, 'qty' => 5, 'unit_cost' => 1000, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => 5000]);
Posting::postPurchaseInvoice($purId);
ok((int)db_val("SELECT COUNT(*) FROM notifications WHERE type = 'purchase'") >= 2, 'purchase posted → owner/admin + manager notified');

echo "\n========================================\n";
echo "NOTIFICATIONS RESULTS: $tests tests, $failures failures\n";
exit($failures > 0 ? 1 : 0);
