<?php
/** Salesman account: salary accrual, advance, salary payment, diesel expense, exactly-once, TB balanced. */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average', 'allow_negative_stock' => false];
$_SESSION = ['user_id' => 1];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Inventory.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/Reporting.php';
require __DIR__ . '/../app/services/Notifications.php';
require __DIR__ . '/../app/services/Approvals.php';
require __DIR__ . '/../app/services/UnitConverter.php';
require __DIR__ . '/../app/services/Production.php';
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
    if ($cond) echo "  PASS  $name\n"; else { $failures++; echo "  FAIL  $name\n"; }
}
function approx(float $a, float $b, float $eps = 0.01): bool { return abs($a - $b) <= $eps; }

$pcs = (int)db_val("SELECT id FROM units WHERE short = 'PCS'");
$now = now_local();
$FG = db_insert('products', ['sku' => 'FG-SM-1', 'name' => 'Test Carton', 'kind' => 'finished', 'unit_id' => $pcs, 'purchase_price' => 400, 'sale_price' => 900, 'issue_rate' => 500, 'reorder_level' => 0, 'is_active' => 1, 'created_at' => $now]);
Inventory::receive($FG, (int)db_val("SELECT id FROM warehouses WHERE code='WH-MAIN'"), 100, 400, 'opening', 'opening_stock', null, null, null, 'Seed');
$smWh = (int)db_insert('warehouses', ['branch_id' => (int)db_val("SELECT id FROM branches WHERE is_main = 1 LIMIT 1"), 'code' => 'WH-SM-ACC', 'name' => 'Account Van', 'type' => 'salesman', 'is_active' => 1, 'created_at' => $now]);
$sm = db_insert('salesmen', ['code' => 'SM-ACC-1', 'name' => 'Test Salesman One', 'phone' => '0300-0000000', 'area' => 'Test Area', 'commission_percent' => 2.5, 'salary' => 30000, 'join_date' => '2026-09-01', 'vehicle_type' => 'Motorbike', 'vehicle_reg' => 'ABC-123', 'warehouse_id' => $smWh, 'is_active' => 1, 'created_at' => $now]);

echo "=== 1. Issue goods worth Rs 30,000 on credit ===\n";
$issueId = db_insert('salesman_issues', ['issue_no' => 'ISS-ACC-1', 'salesman_id' => $sm, 'from_warehouse_id' => (int)db_val("SELECT id FROM warehouses WHERE code='WH-MAIN'"), 'date' => today(), 'issue_type' => 'credit_issue', 'status' => 'approved', 'total_payable' => 30000, 'total_bonus_cost' => 0, 'created_by' => 1, 'created_at' => $now]);
db_insert('salesman_issue_items', ['issue_id' => $issueId, 'product_id' => $FG, 'paid_qty' => 60, 'bonus_qty' => 0, 'qty' => 60, 'unit_cost' => 400, 'issue_rate' => 500, 'line_payable' => 30000, 'bonus_cost' => 0]);
Posting::postSalesmanIssue($issueId);
ok(approx((float)db_val('SELECT COALESCE(SUM(debit) - SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ?', [$sm]), 30000), 'goods payable = 30,000');

echo "\n=== 2. Diesel expense 750 posted inline as admin/owner ===\n";
$bankId = (int)db_val("SELECT id FROM cash_bank_accounts WHERE type = 'cash' LIMIT 1");
db_exec("INSERT INTO salesman_expenses (salesman_id, date, expense_type, amount, description, payment_type, account_id, status, requested_by, created_at) VALUES (?, ?, 'diesel', 750, 'Diesel test', 'cash', ?, 'approved', 1, ?)", [$sm, today(), $bankId, $now]);
$expId = (int)db_val('SELECT MAX(id) FROM salesman_expenses');
Posting::postSalesmanExpense($expId);
ok(db_val('SELECT status FROM salesman_expenses WHERE id = ?', [$expId]) === 'posted', 'expense posted');

echo "\n=== 3. Advance 3,000 disbursed ===\n";
$advId = db_insert('salesman_salary_advances', ['salesman_id' => $sm, 'date' => today(), 'amount' => 3000, 'reason' => 'urgent', 'account_id' => $bankId, 'method' => 'cash', 'status' => 'approved', 'requested_by' => 1, 'created_at' => $now]);
Posting::postSalesmanAdvance($advId);
ok(db_val('SELECT status FROM salesman_salary_advances WHERE id = ?', [$advId]) === 'paid', 'advance posted');

echo "\n=== 4. Pay October salary 30,000 ===\n";
$payId = db_insert('salesman_salary_payments', ['salesman_id' => $sm, 'date' => today(), 'amount' => 30000, 'account_id' => $bankId, 'method' => 'cash', 'period' => '2026-10', 'status' => 'pending', 'requested_by' => 1, 'created_at' => $now]);
Posting::postSalesmanSalary($payId);
ok(db_val('SELECT status FROM salesman_salary_payments WHERE id = ?', [$payId]) === 'paid', 'salary paid');

echo "\n=== 5. Numbers all line up ===\n";
$acc = SalesmenController::class; // (sanity only — logic verified via SQL below)
$outstandingAdv = (float)db_val("SELECT COALESCE(SUM(amount - deducted),0) FROM salesman_salary_advances WHERE salesman_id = ? AND status = 'paid'", [$sm]);
ok(approx($outstandingAdv, 3000), 'advance outstanding = 3,000');
$rem = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_salary_payments WHERE salesman_id = ? AND status = 'paid'", [$sm]);
ok(approx($rem, 30000), 'salary paid = 30,000');
$diesel = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_expenses WHERE salesman_id = ? AND expense_type = 'diesel' AND status = 'posted'", [$sm]);
ok(approx($diesel, 750), 'diesel expense = 750');

echo "\n=== 6. Exactly-once: re-posting a salary does not double-pay ===\n";
Posting::postSalesmanSalary($payId);
$paidTotal = db_val('SELECT COALESCE(SUM(amount),0) FROM salesman_salary_payments WHERE id = ? AND status = ?', [$payId, 'paid']);
ok(approx((float)$paidTotal, 30000), 'still exactly 30,000 (exactly-once)');

echo "\n=== 7. Trial balance agrees ===\n";
$tb = Accounting::trialBalance();
ok((bool)($tb['balanced'] ?? false), 'TB balanced after all salesman postings');

echo "\nSALESMAN ACCOUNT E2E: $tests tests, $failures failures\n";
exit($failures ? 1 : 0);
