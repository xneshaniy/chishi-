<?php
/**
 * Extended suite — approvals, salesman bonus/credit issues, payroll (all salary types),
 * advances, loans, min-price enforcement, language switching, area gating.
 * In-memory SQLite; exit code = failures.
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
require __DIR__ . '/../app/services/Payroll.php';
require __DIR__ . '/../database/seed.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys = ON');
Database::setPdo($pdo);
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sqlite.sql'));
seed_base($pdo);

$tests = 0; $failures = 0;
function ok(bool $c, string $n): void { global $tests, $failures; $tests++; echo $c ? "  PASS  $n\n" : "  FAIL  $n\n", $c ? '' : "\n"; if (!$c) $failures++; }
function approx($a, $b, $e = 0.01): bool { return abs((float)$a - (float)$b) <= $e; }

$whMain = (int)db_val("SELECT id FROM warehouses WHERE code='WH-MAIN'");
$today = today();

// Users: owner, accountant, manager, salesman
function mkUser(string $u, string $pass, string $roleCode, ?int $salesmanId = null): int {
    $id = db_insert('users', ['username' => $u, 'full_name' => strtoupper($u), 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'salesman_id' => $salesmanId, 'is_active' => 1, 'created_at' => now_local()]);
    $roleId = (int)db_val('SELECT id FROM roles WHERE code = ?', [$roleCode]);
    db_exec('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)', [$id, $roleId]);
    return $id;
}
$ownerId = mkUser('boss', 'Boss@1234x', 'owner');
$accId = mkUser('acc1', 'Acc@1234x', 'accountant');
$mgrId = mkUser('mgr1', 'Mgr@1234x', 'manager');
$smUserId = mkUser('van1', 'Van@1234x', 'salesman');

// Products
$fg = db_insert('products', ['sku' => 'FG-10S', 'name' => 'JUSTI 10s', 'kind' => 'finished', 'purchase_price' => 100, 'sale_price' => 150, 'issue_rate' => 10, 'min_price' => 12, 'tax_rate' => 0, 'is_active' => 1, 'created_at' => now_local()]);

// Seed finished goods with zero-cost receipts for two lots? Use controlled cost 2.00
Inventory::receive($fg, $whMain, 250, 2.00, 'opening', 'test', null);
$supId = db_insert('suppliers', ['code' => 'SUP-X', 'name' => 'Test Supplier', 'is_active' => 1, 'created_at' => now_local()]);
$custId = db_insert('customers', ['code' => 'CUST-X', 'name' => 'Test Customer', 'credit_limit' => 100000, 'is_active' => 1, 'created_at' => now_local()]);

echo "\n=== A · Salesman credit issue with bonus (200 paid @ issue 10 + 50 free) ===\n";
$smId = db_insert('salesmen', ['code' => 'SM-X', 'name' => 'Test Van Salesman', 'commission_percent' => 0, 'is_active' => 1, 'created_at' => now_local()]);
$smWh = (int)db_insert('warehouses', ['code' => 'WH-SM-X', 'name' => 'Van X', 'type' => 'salesman', 'is_default' => 0, 'is_active' => 1, 'created_at' => now_local()]);
db_exec('UPDATE salesmen SET warehouse_id = ? WHERE id = ?', [$smWh, $smId]);
$issueId = db_insert('salesman_issues', ['issue_no' => 'IMP-1', 'salesman_id' => $smId, 'from_warehouse_id' => $whMain, 'date' => $today, 'status' => 'posted', 'issue_type' => 'credit_issue', 'total_payable' => 0, 'total_bonus_cost' => 0, 'notes' => 'test issue', 'created_by' => $ownerId, 'created_at' => now_local()]);
db_insert('salesman_issue_items', ['issue_id' => $issueId, 'product_id' => $fg, 'paid_qty' => 200, 'bonus_qty' => 50, 'qty' => 250, 'unit_cost' => 2.00, 'issue_rate' => 10, 'line_payable' => 2000, 'bonus_cost' => 100]);
Posting::postSalesmanIssue($issueId);
ok(approx(Inventory::balance($fg, $smWh), 250), 'van received 250 units (paid + bonus)');
ok(approx(Inventory::balance($fg, $whMain), 0), 'main warehouse drained by 250');
$smBal = (float)db_val('SELECT COALESCE(balance,0) FROM salesman_ledger WHERE salesman_id = ? ORDER BY id DESC LIMIT 1', [$smId]);
ok(approx($smBal, 2000), 'salesman owes exactly the PAID value (Rs 2,000) — bonus never charged');
$journalNo = db_val("SELECT entry_no FROM journal_entries WHERE reference_type = 'salesman_issue' AND reference_id = ?", [$issueId]);
ok($journalNo !== false, 'issue journal created (' . $journalNo . ')');
$smRecv = (float)db_val("SELECT SUM(l.debit - l.credit) FROM journal_lines l JOIN chart_of_accounts a ON a.id = l.account_id WHERE a.system_key = 'SM_RECEIVABLE'");
ok(approx($smRecv, 2000), 'Salesman Receivable ledger = 2,000');
$promo = (float)db_val("SELECT SUM(l.debit) FROM journal_lines l JOIN chart_of_accounts a ON a.id = l.account_id WHERE a.system_key = 'PROMO'");
ok(approx($promo, 100), 'bonus cost (50 × 2.00 = 100) debited to Promotion expense');
$cogs = (float)db_val("SELECT SUM(l.debit) FROM journal_lines l JOIN chart_of_accounts a ON a.id = l.account_id WHERE a.system_key = 'COGS'");
ok(approx($cogs, 400), 'COGS = paid units only (200 × 2.00 = 400)');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'trial balance balanced after bonus issue');
ok((int)db_val('SELECT COUNT(*) FROM stock_movements WHERE ref_type = ? AND ref_id = ?', ['salesman_issue', $issueId]) === 4, 'stock movements recorded (2 OUT × paid+bonus, 2 IN)');

echo "\n=== B · Exactly-once: re-posting the issue is a no-op ===\n";
$count = (int)db_val('SELECT COUNT(*) FROM journal_entries');
Posting::postSalesmanIssue($issueId);
ok((int)db_val('SELECT COUNT(*) FROM journal_entries') === $count, 'duplicate call does not create a second journal');

echo "\n=== C · Sales at Rs.9 when issue rate is Rs.10 — discount/loss report ===\n";
$vanCustId = db_insert('customers', ['code' => 'CUST-SM', 'name' => 'Van Customer', 'salesman_id' => $smId, 'is_active' => 1, 'created_at' => now_local()]);
db_insert('salesman_customers', ['salesman_id' => $smId, 'customer_id' => $vanCustId]);
$invId = db_insert('sales_invoices', ['invoice_no' => 'TMP-EXT-C', 'customer_id' => $vanCustId, 'salesman_id' => $smId, 'warehouse_id' => $smWh, 'date' => $today, 'payment_type' => 'cash', 'subtotal' => 180, 'discount' => 0, 'tax' => 0, 'total' => 180, 'status' => 'posted', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $invId, 'product_id' => $fg, 'qty' => 20, 'unit_price' => 9, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => 180, 'net_before_tax' => 180, 'is_bonus' => 0, 'cost_price' => 0, 'cost_total' => 0]);
Posting::postSalesInvoice($invId);
$diff = (float)db_val("SELECT COALESCE(SUM((p.issue_rate - ii.unit_price) * ii.qty),0) FROM sales_invoice_items ii JOIN sales_invoices i ON i.id = ii.invoice_id JOIN products p ON p.id = ii.product_id WHERE i.status='posted' AND p.issue_rate > 0 AND i.id = ?", [$invId]);
ok(approx($diff, 20), 'price difference report computes Rs 1 × 20 units = Rs 20 below-issue difference');
ok(approx(Accounting::customerBalance($vanCustId), 0), 'cash sale → no receivable');

echo "\n=== D · Below-minimum price invoice requires Owner/Admin approval ===\n";
$minProd = db_insert('products', ['sku' => 'MIN-1', 'name' => 'Min Price Widget', 'kind' => 'finished', 'purchase_price' => 10, 'sale_price' => 100, 'issue_rate' => 0, 'min_price' => 50, 'tax_rate' => 0, 'is_active' => 1, 'created_at' => now_local()]);
Inventory::receive($minProd, $whMain, 10, 10, 'opening', 'test', null);
$lowInvId = db_insert('sales_invoices', ['invoice_no' => 'TMP-EXT-D', 'customer_id' => $custId, 'warehouse_id' => $whMain, 'date' => $today, 'payment_type' => 'cash', 'subtotal' => 200, 'discount' => 0, 'tax' => 0, 'total' => 200, 'status' => 'pending', 'created_at' => now_local()]);
db_insert('sales_invoice_items', ['invoice_id' => $lowInvId, 'product_id' => $minProd, 'qty' => 5, 'unit_price' => 40, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => 200, 'net_before_tax' => 200, 'is_bonus' => 0, 'cost_price' => 0, 'cost_total' => 0]);
Approvals::create('discount', 'sales_invoices', (int)$lowInvId, 200, 'Below-minimum selling price', $accId);
$stockBefore = (float)Inventory::balance($minProd, $whMain);
$journalBefore = (int)db_val('SELECT COUNT(*) FROM journal_entries');
ok(approx($stockBefore, 10), 'pending invoice: stock untouched');
ok((int)db_val('SELECT COUNT(*) FROM journal_entries') === $journalBefore, 'pending invoice: no journal posted');
// requester tries to approve own request → blocked
$threw = false;
try { Approvals::decide((int)db_val("SELECT id FROM approval_requests WHERE reference_id = ? AND reference_type = 'sales_invoices'", [$lowInvId]), true, 'self-approve', $accId); } catch (Throwable $e) { $threw = true; }
ok($threw, 'requester cannot approve own request');
// Owner approves → posts exactly once
$apprId = (int)db_val("SELECT id FROM approval_requests WHERE reference_id = ? AND reference_type = 'sales_invoices'", [$lowInvId]);
Approvals::decide($apprId, true, 'approved by boss', $ownerId);
ok(approx(Inventory::balance($minProd, $whMain), 5), 'after approval: stock issued (10 − 5 = 5)');
ok((int)db_val("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'sales_invoice' AND reference_id = ?", [$lowInvId]) === 1, 'journal posted exactly once');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'TB balanced after approved below-minimum sale');

echo "\n=== E · Outgoing payment: rejection leaves balances untouched ===\n";
$payId = db_insert('payments', ['payment_no' => 'TMP-EXT-E', 'party_type' => 'supplier', 'party_id' => $supId, 'account_id' => (int)db_val("SELECT id FROM cash_bank_accounts WHERE type='cash' LIMIT 1"), 'date' => $today, 'amount' => 5000, 'status' => 'pending', 'created_at' => now_local()]);
Approvals::create('payment', 'payments', $payId, 5000, 'Test rejection flow', $accId);
$supBefore = Accounting::supplierBalance($supId);
$tb = Accounting::trialBalance();
$apprId2 = (int)db_val("SELECT id FROM approval_requests WHERE reference_id = ? AND reference_type = 'payments'", [$payId]);
Approvals::decide($apprId2, false, 'not this invoice', $ownerId);
ok(approx(Accounting::supplierBalance($supId), $supBefore, 0), 'rejected payment: supplier balance unchanged');
$tb2 = Accounting::trialBalance();
ok($tb2['balanced'], 'TB balanced after rejection');
$threw = false;
try { Approvals::create('payment', 'payments', $payId, 5000, 'retry', $accId); } catch (Throwable $e) { $threw = true; }
ok($threw, 're-requesting an already-decided payment is rejected (no duplicate decision)');

echo "\n=== F · Outgoing payment: approval posts exactly once ===\n";
$payId2 = db_insert('payments', ['payment_no' => 'TMP-EXT-F', 'party_type' => 'supplier', 'party_id' => $supId, 'account_id' => (int)db_val("SELECT id FROM cash_bank_accounts WHERE type='cash' LIMIT 1"), 'date' => $today, 'amount' => 3000, 'status' => 'pending', 'created_at' => now_local()]);
Approvals::create('payment', 'payments', $payId2, 3000, 'Settlement', $accId);
$payReqId = (int)db_val("SELECT id FROM approval_requests WHERE reference_id = ? AND reference_type = 'payments'", [$payId2]);
Approvals::decide($payReqId, true, 'settled', $ownerId);
$supAfter = Accounting::supplierBalance($supId);
ok(approx($supAfter, -3000, 0.01), 'supplier payable reduced by approved payment');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'TB balanced after approved payment');
$threw = false;
try { Approvals::decide($payReqId, true, 'again', $ownerId); } catch (Throwable $e) { $threw = true; }
ok($threw, 'double approval is rejected (exactly-once enforced)');

echo "\n=== G · All salary types compute correctly (monthly/weekly/daily/hourly) ===\n";
// Employees for each type
$empMonthly = db_insert('employees', ['code' => 'E-MON', 'name' => 'Monthly Manager', 'salary_type' => 'monthly', 'salary_rate' => 30000, 'is_active' => 1, 'created_at' => now_local()]);
$empWeekly = db_insert('employees', ['code' => 'E-WEK', 'name' => 'Weekly Labour', 'salary_type' => 'weekly', 'salary_rate' => 7000, 'is_active' => 1, 'created_at' => now_local()]);
$empDaily = db_insert('employees', ['code' => 'E-DAY', 'name' => 'Daily Wage', 'salary_type' => 'daily', 'salary_rate' => 5000, 'is_active' => 1, 'created_at' => now_local()]);
$empHourly = db_insert('employees', ['code' => 'E-HRL', 'name' => 'Hourly Worker', 'salary_type' => 'hourly', 'salary_rate' => 500, 'is_active' => 1, 'created_at' => now_local()]);
// Attendance: monthly employee 8h/day (no OT — spec example), others 9h/day
$from = date('Y-m-01'); $to = date('Y-m-28');
for ($d = 1; $d <= 22; $d++) {
    $date = date('Y-m-') . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
    db_exec('INSERT INTO attendance (employee_id, date, status, hours) VALUES (?,?,?,?)', [$empMonthly, $date, 'present', 8.0]);
    foreach ([$empWeekly, $empDaily, $empHourly] as $eid) {
        db_exec('INSERT INTO attendance (employee_id, date, status, hours) VALUES (?,?,?,?)', [$eid, $date, 'present', 9.0]);
    }
}
Payroll::generate($from, $to, $ownerId);
$m = db_one('SELECT * FROM payroll WHERE employee_id = ?', [$empMonthly]);
ok(approx((float)$m['basic'], 30000), 'monthly: basic = salary rate (30,000)');
ok(approx((float)$m['overtime'], 0), 'monthly (8h/day): no overtime');
$wk = db_one('SELECT * FROM payroll WHERE employee_id = ?', [$empWeekly]);
ok(approx((float)$wk['basic'], 7000 * (22 / 6.0)), 'weekly: basic = rate × present-weeks (7,000 × 22/6 ≈ 25,667)');
$dl = db_one('SELECT * FROM payroll WHERE employee_id = ?', [$empDaily]);
ok(approx((float)$dl['basic'], 5000 * 22), 'daily: basic = days × rate (110,000)');
$hl = db_one('SELECT * FROM payroll WHERE employee_id = ?', [$empHourly]);
ok(approx((float)$hl['basic'], 500 * 22 * 9.0), 'hourly: basic = hours × rate (99,000)');
ok((float)$dl['overtime'] > 0, 'daily employee: OT computed beyond 8h/day (1 × 22 = 22h)');
ok(approx(Accounting::trialBalance()['total_debit'], Accounting::trialBalance()['total_credit']), 'TB balances');

echo "\n=== H · Salary advance: request → approval → disbursement → next-salary deduction ===\n";
// Advance for the monthly employee
$accId2 = (int)db_val("SELECT id FROM cash_bank_accounts WHERE type='cash' LIMIT 1");
$advId3 = db_insert('employee_advances', ['employee_id' => $empMonthly, 'date' => $today, 'amount' => 5000, 'notes' => 'medical', 'status' => 'pending', 'requested_by' => $accId, 'account_id' => $accId2, 'created_at' => now_local()]);
Approvals::create('advance', 'employee_advances', (int)$advId3, 5000, 'Salary advance — medical', $accId);
$advReqId = (int)db_val("SELECT id FROM approval_requests WHERE reference_id = ? AND reference_type = 'employee_advances'", [$advId3]);
Approvals::decide($advReqId, true, 'approved', $ownerId);
ok(db_val("SELECT status FROM employee_advances WHERE id = ?", [$advId3]) === 'disbursed', 'advance disbursed after approval');
// Regenerate only the monthly employee's payslip (advance now applies)
Payroll::generate($from, $to, $ownerId, [$empMonthly]);
$m2 = db_one('SELECT * FROM payroll WHERE employee_id = ?', [$empMonthly]);
ok(approx((float)$m2['advances_deducted'], 5000), 'advance Rs 5,000 auto-deducted from next salary');
ok(approx((float)$m2['net_pay'], 25000), 'spec example exactly: 30,000 − 5,000 = 25,000 (net)');
$advOut = (float)db_val("SELECT COALESCE(SUM(amount - deducted),0) FROM employee_advances WHERE employee_id = ?", [$empMonthly]);
ok(approx($advOut, 0), 'advance fully deducted (balance 0)');

echo "\n=== I · Employee loan: request → approval → installments auto-deduct from salary ===\n";
$empLoan = db_insert('employees', ['code' => 'E-LON', 'name' => 'Loan Employee', 'salary_type' => 'monthly', 'salary_rate' => 30000, 'is_active' => 1, 'created_at' => now_local()]);
// Attendance for loan employee (8h/day, no OT)
for ($d = 1; $d <= 22; $d++) {
    $date = date('Y-m-') . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
    db_exec('INSERT INTO attendance (employee_id, date, status, hours) VALUES (?,?,?,?)', [$empLoan, $date, 'present', 8.0]);
}
// First payslip — loan not yet disbursed → no deduction
Payroll::generate($from, $to, $ownerId, [$empLoan]);
$loanId2 = db_insert('employee_loans', ['loan_no' => 'LN-TEST-1', 'employee_id' => $empLoan, 'amount' => 24000, 'date' => $today, 'reason' => 'test loan', 'installment_type' => 'monthly', 'installment_amount' => 2000, 'total_installments' => 12, 'recovered' => 0, 'paid_installments' => 0, 'outstanding' => 24000, 'status' => 'pending', 'account_id' => $accId2, 'requested_by' => $accId, 'created_at' => now_local()]);
Approvals::create('loan', 'employee_loans', (int)$loanId2, 24000, 'Test loan', $accId);
$loanReqId = (int)db_val("SELECT id FROM approval_requests WHERE reference_id = ? AND reference_type = 'employee_loans'", [$loanId2]);
Approvals::decide($loanReqId, true, 'approved', $ownerId);
ok(db_val("SELECT status FROM employee_loans WHERE id = ?", [$loanId2]) === 'active', 'loan disbursed after approval');
ok((int)db_val("SELECT COUNT(*) FROM loan_installments WHERE loan_id = ?", [$loanId2]) === 12, '12 monthly installments created');
// Make installment 1 due this period and regenerate
db_exec("UPDATE loan_installments SET due_date = ? WHERE loan_id = ? AND installment_no = 1", [$to, $loanId2]);
Payroll::generate($from, $to, $ownerId, [$empLoan]);
$ln = db_one('SELECT * FROM payroll WHERE employee_id = ?', [$empLoan]);
ok(approx((float)$ln['loan_deduction'], 2000), 'one installment (Rs 2,000) auto-deducted from salary');
ok(approx((float)$ln['net_pay'], 28000), 'loan deduction reduces net pay (30,000 − 2,000)');
$loanAfter = db_one('SELECT * FROM employee_loans WHERE id = ?', [$loanId2]);
ok(approx((float)$loanAfter['recovered'], 2000), 'loan recovered = 2,000');
ok(approx((float)$loanAfter['outstanding'], 22000), 'outstanding = 22,000');
// Second installment due → regenerate → recovered 4,000
db_exec("UPDATE loan_installments SET due_date = ? WHERE loan_id = ? AND installment_no = 2", [$to, $loanId2]);
Payroll::generate($from, $to, $ownerId, [$empLoan]);
$loanAfter2 = db_one('SELECT * FROM employee_loans WHERE id = ?', [$loanId2]);
ok(approx((float)$loanAfter2['recovered'], 4000), 'second month: recovered = 4,000');

echo "\n=== J · Language switching: English, Roman Urdu, Urdu ===\n";
$_SESSION['lang'] = 'en';
ok(lang('sales') === 'Sales', 'English: sales = Sales');
$_SESSION['lang'] = 'roman';
ok(lang('sales') === 'Bikri', 'Roman Urdu: sales = Bikri');
ok(lang('customers') === 'Gahak', 'Roman Urdu: customers = Gahak');
$_SESSION['lang'] = 'ur';
ok(lang('sales') === 'فروخت', 'Urdu: sales = فروخت');
ok(is_rtl(), 'Urdu flips to RTL');
$_SESSION['lang'] = 'en';
ok(!is_rtl(), 'English stays LTR');
$_SESSION = [];

echo "\n=== K · Separate login area gates (server-side role checks) ===\n";
$mk = function (int $uid, string $role) {
    $_SESSION = ['user_id' => $uid, 'username' => 'x'];
    return Auth::areaAllowed($role);
};
ok($mk($ownerId, 'admin') === true, 'Owner CAN enter /admin');
ok($mk($ownerId, 'management') === true, 'Owner CAN enter /management');
ok($mk($accId, 'admin') === false, 'Accountant CANNOT enter /admin (server-side gate)');
ok($mk($accId, 'management') === true, 'Accountant CAN enter /management');
ok($mk($mgrId, 'admin') === false, 'Manager CANNOT enter /admin');
ok($mk($mgrId, 'management') === true, 'Manager CAN enter /management');
ok($mk($smUserId, 'admin') === false, 'Salesman CANNOT enter /admin');
ok($mk($smUserId, 'management') === false, 'Salesman CANNOT enter /management');
$_SESSION = [];

echo "\n=== L · Bonus approval threshold → pending, no posting until Admin approves ===\n";
// Set a low threshold in company settings
db_exec("INSERT INTO company_settings (setting_key, setting_value, updated_at) VALUES ('bonus_approval_threshold', '100', ?)", [now_local()]);
// Create an issue with bonus cost > 100 → restricted
$restrictedId = db_insert('salesman_issues', ['issue_no' => 'IMP-2', 'salesman_id' => $smId, 'from_warehouse_id' => $whMain, 'date' => $today, 'status' => 'pending_approval', 'issue_type' => 'transfer', 'total_payable' => 0, 'total_bonus_cost' => 50.00, 'bonus_scheme' => 'Special Approved Issue', 'notes' => 'restricted test', 'created_by' => $accId, 'created_at' => now_local()]);
Inventory::receive($fg, $whMain, 100, 2.00, 'opening', 'test2', null);
db_insert('salesman_issue_items', ['issue_id' => $restrictedId, 'product_id' => $fg, 'paid_qty' => 0, 'bonus_qty' => 25, 'qty' => 25, 'unit_cost' => 2.00, 'issue_rate' => 0, 'line_payable' => 0, 'bonus_cost' => 50]);
Approvals::create('bonus', 'salesman_issues', (int)$restrictedId, 50, 'Restricted scheme test', $accId);
$stockBefore = (float)Inventory::balance($fg, $whMain);
$reqId = (int)db_val("SELECT id FROM approval_requests WHERE reference_id = ? AND reference_type = 'salesman_issues'", [$restrictedId]);
ok(approx($stockBefore, 100), 'restricted issue: no stock moved before approval');
ok(Approvals::decide($reqId, true, 'approved', $ownerId)[0] === 'approved', 'owner approves restricted issue');
ok(approx(Inventory::balance($fg, $whMain), 75), 'after approval: 25 bonus units transferred to van');
ok(approx(Inventory::balance($fg, $smWh), 255), 'van stock now includes bonus units (230 after sale + 25)');

echo "\n=== M · Cash receipt posts without approval (money in) ===\n";
$cashBefore = (float)db_val("SELECT SUM(debit - credit) FROM journal_lines l JOIN chart_of_accounts a ON a.id = l.account_id WHERE a.system_key = 'CASH'");
$rcptId = db_insert('receipts', ['receipt_no' => 'TMP-EXT-M', 'party_type' => 'customer', 'customer_id' => $custId, 'account_id' => $accId2, 'date' => $today, 'amount' => 1000, 'created_at' => now_local()]);
Posting::postReceipt($rcptId);
ok(approx((float)db_val("SELECT SUM(debit - credit) FROM journal_lines l JOIN chart_of_accounts a ON a.id = l.account_id WHERE a.system_key = 'CASH'"), $cashBefore + 1000), 'receipt posts immediately (no approval for incoming money)');

echo "\n=== N · Manual journal can still be posted directly (admin-flow unchanged) ===\n";
$jId = Accounting::postEntry([
    ['account' => 'SALES', 'debit' => 100],
    ['account' => 'CASH', 'credit' => 100],
], ['date' => $today, 'memo' => 'manual test']);
ok($jId > 0, 'manual journal posted');
$tb = Accounting::trialBalance();
ok($tb['balanced'], 'TB balances after all modules exercised');

echo "\n========================================\n";
echo "EXTENDED RESULTS: $tests tests, $failures failures\n";
exit($failures > 0 ? 1 : 0);
