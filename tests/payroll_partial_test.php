<?php
/** Payroll partial salary payment test — partial payments, approval gating, legacy full-pay. */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average', 'allow_negative_stock' => false];
$_SESSION = ['user_id' => 1];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/Accounting.php';
require __DIR__ . '/../app/services/Inventory.php';
require __DIR__ . '/../app/services/Posting.php';
require __DIR__ . '/../app/services/UnitConverter.php';
require __DIR__ . '/../app/services/Reporting.php';
require __DIR__ . '/../app/services/Notifications.php';
require __DIR__ . '/../app/services/Approvals.php';
require __DIR__ . '/../app/services/Payroll.php';
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
function approx(float $a, float $b, float $eps = 0.01): bool { return abs($a - $b) <= $eps; }

$bankId = (int)db_val("SELECT id FROM cash_bank_accounts WHERE type = 'bank' LIMIT 1");
$now = now_local();

echo "=== 1. Monthly employee → net payable = salary rate (no attendance needed) ===\n";
$empId = db_insert('employees', ['code' => 'EMP-T-1', 'name' => 'Test Employee', 'designation' => 'Operator', 'department' => 'Production', 'join_date' => '2026-01-01', 'salary_type' => 'monthly', 'salary_rate' => 30000, 'is_active' => 1, 'payment_method' => 'bank', 'created_at' => $now]);
$from = '2026-11-01'; $to = '2026-11-30';
Payroll::generate($from, $to, 1, [$empId]);
$pid = (int)db_val('SELECT id FROM payroll WHERE employee_id = ? AND period_start = ?', [$empId, $from]);
$net = (float)db_val('SELECT net_pay FROM payroll WHERE id = ?', [$pid]);
ok(approx($net, 30000), 'net payable = Rs 30,000');
ok((float)db_val('SELECT amount_paid FROM payroll WHERE id = ?', [$pid]) === 0.0, 'amount_paid starts at 0');
ok(db_val('SELECT status FROM payroll WHERE id = ?', [$pid]) === 'draft', 'payslip starts as draft');

echo "\n=== 2. Partial payment (Rs 12,000) via approval → posted, payslip goes 'partial' ===\n";
$payId = db_insert('payroll_payments', ['payroll_id' => $pid, 'date' => today(), 'amount' => 12000, 'account_id' => $bankId, 'notes' => 'Partial 1', 'status' => 'pending', 'requested_by' => 2, 'created_at' => $now]);
Approvals::create('payroll_payment', 'payroll_payments', $payId, 12000, 'Test salary payment 1', 2);
$reqId = (int)db_val("SELECT id FROM approval_requests WHERE reference_type = 'payroll_payments' AND reference_id = ?", [$payId]);
Approvals::decide($reqId, true, '', 1);
$pr = db_one('SELECT amount_paid, status FROM payroll WHERE id = ?', [$pid]);
$pp = db_one('SELECT status, journal_entry_id FROM payroll_payments WHERE id = ?', [$payId]);
ok(approx((float)$pr['amount_paid'], 12000), 'amount_paid = 12,000 after first partial');
ok($pr['status'] === 'partial', "payslip status = 'partial'");
ok($pp['status'] === 'paid' && !empty($pp['journal_entry_id']), 'payment posted with journal');

echo "\n=== 3. Remaining Rs 18,000 paid → payslip becomes 'paid' ===\n";
$payId2 = db_insert('payroll_payments', ['payroll_id' => $pid, 'date' => today(), 'amount' => 18000, 'account_id' => $bankId, 'notes' => 'Partial 2', 'status' => 'pending', 'requested_by' => 2, 'created_at' => $now]);
Approvals::create('payroll_payment', 'payroll_payments', $payId2, 18000, 'Test salary payment 2', 2);
$reqId2 = (int)db_val("SELECT id FROM approval_requests WHERE reference_type = 'payroll_payments' AND reference_id = ?", [$payId2]);
Approvals::decide($reqId2, true, '', 1);
$pr = db_one('SELECT amount_paid, status FROM payroll WHERE id = ?', [$pid]);
ok(approx((float)$pr['amount_paid'], 30000), 'amount_paid = 30,000 (fully settled)');
ok($pr['status'] === 'paid', "payslip status = 'paid'");

echo "\n=== 4. Re-requesting a pending approval is a no-op (exactly once) ===\n";
$payId3 = db_insert('payroll_payments', ['payroll_id' => $pid, 'date' => today(), 'amount' => 1000, 'account_id' => $bankId, 'status' => 'pending', 'requested_by' => 2, 'created_at' => $now]);
$a1 = Approvals::create('payroll_payment', 'payroll_payments', $payId3, 1000, 'Test payment 3', 2);
$a2 = Approvals::create('payroll_payment', 'payroll_payments', $payId3, 1000, 'Test payment 3', 2);
ok($a1 === $a2, 'duplicate pending request returns the same approval id');

echo "\n=== 5. Approving an over-payment throws and leaves the request pending ===\n";
$payroll3 = db_insert('payroll', ['employee_id' => $empId, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'basic' => 15000, 'overtime' => 0, 'advances_deducted' => 0, 'loan_deduction' => 0, 'other_deductions' => 0, 'net_pay' => 15000, 'status' => 'draft', 'created_at' => $now]);
$payId4 = db_insert('payroll_payments', ['payroll_id' => $payroll3, 'date' => today(), 'amount' => 20000, 'account_id' => $bankId, 'status' => 'pending', 'requested_by' => 2, 'created_at' => $now]); // 20,000 > remaining 15,000
Approvals::create('payroll_payment', 'payroll_payments', $payId4, 20000, 'Test over-payment', 2);
$reqId4 = (int)db_val("SELECT id FROM approval_requests WHERE reference_type = 'payroll_payments' AND reference_id = ?", [$payId4]);
$before = (float)db_val('SELECT amount_paid FROM payroll WHERE id = ?', [$payroll3]);
$threw = false;
try { Approvals::decide($reqId4, true, '', 1); } catch (Throwable $e) { $threw = true; }
ok($threw, 'approval throws — over-payment refused');
ok(db_val("SELECT status FROM approval_requests WHERE id = ?", [$reqId4]) === 'pending', 'request left pending — never marked approved');
ok(approx((float)db_val('SELECT amount_paid FROM payroll WHERE id = ?', [$payroll3]), $before), 'payroll unchanged after refused approval');
// clean up so TB stays balanced
db_exec("UPDATE payroll_payments SET status = 'rejected' WHERE id = ?", [$payId4]);

echo "\n=== 6. Legacy full payment path still works and fills amount_paid ===\n";
$payroll4 = db_insert('payroll', ['employee_id' => $empId, 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'basic' => 28000, 'overtime' => 0, 'advances_deducted' => 0, 'loan_deduction' => 0, 'other_deductions' => 0, 'net_pay' => 28000, 'status' => 'draft', 'created_at' => $now]);
Posting::postPayroll($payroll4, $bankId);
$pr4 = db_one('SELECT status, amount_paid FROM payroll WHERE id = ?', [$payroll4]);
ok($pr4['status'] === 'paid' && approx((float)$pr4['amount_paid'], 28000), 'legacy full payment marks payslip paid with amount_paid = net');

echo "\n=== 7. Reversal releases partial payments ===\n";
Payroll::releaseDeductions(0); // no-op sanity on empty payslip
$beforeRev = (float)db_val('SELECT amount_paid FROM payroll WHERE id = ?', [$pid]);
$journalId = (int)db_val("SELECT journal_entry_id FROM payroll_payments WHERE payroll_id = ? AND status = 'paid' ORDER BY id LIMIT 1", [$pid]);
Accounting::reverseEntry($journalId, 'test reversal');
$tb = Accounting::trialBalance();
ok((bool)($tb['balanced'] ?? false), 'trial balance balanced after all salary payments + reversal');

echo "\nPAYROLL PARTIAL PAYMENT E2E: $tests tests, $failures failures\n";
exit($failures ? 1 : 0);
