<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
require_once __DIR__ . '/../app/services/PersonalLoans.php';
require_once __DIR__ . '/../app/controllers/PersonalLoansController.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

$pass = 0; $fail = 0;
function ok($l) { global $pass; echo "✅ $l\n"; $pass++; }
function no($l, $d='') { global $fail; echo "❌ $l" . ($d ? " — $d" : '') . "\n"; $fail++; }

echo "=== Test: Dasti / Loans System with Bank Repayment & Date Range ===\n\n";

// 1. Language labels check
$_SESSION['lang'] = 'ur';
if (dasti_label() === 'قرض (دستی)') ok("Urdu label: 'قرض (دستی)'"); else no("Urdu label failed: " . dasti_label());

$_SESSION['lang'] = 'roman';
if (dasti_label() === 'Dasti') ok("Roman Urdu label: 'Dasti'"); else no("Roman label failed: " . dasti_label());

$_SESSION['lang'] = 'en';
if (dasti_label() === 'Loans (Dasti)') ok("English label: 'Loans (Dasti)'"); else no("English label failed: " . dasti_label());

// 2. Fetch cash/bank accounts
$cashAcc = db_one("SELECT id, name FROM cash_bank_accounts WHERE type = 'cash' LIMIT 1") ?: ['id' => 1, 'name' => 'Cash in Hand'];
$bankAcc = db_one("SELECT id, name FROM cash_bank_accounts WHERE type = 'bank' LIMIT 1") ?: ['id' => 2, 'name' => 'Main Bank Account'];

// 3. Create Loan: Rs 10,000 to Ahmed Khan
$today = today();
$nextMonth = date('Y-m-d', strtotime('+30 days'));
$loanId = PersonalLoans::createLoan([
    'person_name' => 'Ahmed Khan Test',
    'phone' => '0300-1112233',
    'loan_date' => $today,
    'expected_date' => $nextMonth,
    'original_amount' => 10000.00,
    'account_id' => $cashAcc['id'],
    'description' => 'Personal emergency loan',
    'notes' => 'Will return next month'
], 1);

$loan = PersonalLoans::find($loanId);
if ($loan && abs((float)$loan['original_amount'] - 10000.00) < 0.01 && abs((float)$loan['remaining_amount'] - 10000.00) < 0.01) {
    ok("Created Loan #{$loan['loan_no']} for Rs 10,000 to Ahmed Khan. Remaining = Rs 10,000");
} else {
    no("Loan creation failed");
}

// Verify payment record created
$pay = db_one("SELECT * FROM payments WHERE party_id = ? AND party_type = 'other'", [$loanId]);
if ($pay && (float)$pay['amount'] == 10000.00 && (int)$pay['account_id'] === (int)$cashAcc['id']) {
    ok("Payment recorded: Rs 10,000 paid from '{$cashAcc['name']}'");
} else {
    no("Payment deduction record missing");
}

// 4. Repayment 1: Returns Rs 5,000 into Bank Account
PersonalLoans::addRepayment($loanId, $today, 5000.00, 'First installment via online bank', 1, $bankAcc['id']);
$loanAfter1 = PersonalLoans::find($loanId);
if (abs((float)$loanAfter1['remaining_amount'] - 5000.00) < 0.01 && (float)$loanAfter1['total_repaid'] == 5000.00 && $loanAfter1['status'] === 'partial') {
    ok("Repayment 1 recorded: Rs 5,000 returned. Remaining = Rs 5,000. Status = partial");
} else {
    no("Repayment 1 failed: remaining is " . $loanAfter1['remaining_amount']);
}

// Verify receipt record created
$rct = db_one("SELECT * FROM receipts WHERE contact_name = ? ORDER BY id DESC LIMIT 1", ['Ahmed Khan Test']);
if ($rct && (float)$rct['amount'] == 5000.00 && (int)$rct['account_id'] === (int)$bankAcc['id']) {
    ok("Receipt recorded: Rs 5,000 deposited into '{$bankAcc['name']}'");
} else {
    no("Receipt deposit record missing");
}

// 5. Repayment 2: Returns remaining Rs 5,000 (Settled)
PersonalLoans::addRepayment($loanId, $today, 5000.00, 'Final settlement', 1, $cashAcc['id']);
$loanAfter2 = PersonalLoans::find($loanId);
if (abs((float)$loanAfter2['remaining_amount'] - 0.00) < 0.01 && (float)$loanAfter2['total_repaid'] == 10000.00 && $loanAfter2['status'] === 'paid') {
    ok("Repayment 2 recorded: Rs 5,000 returned. Remaining = Rs 0.00. Status = paid (Fully Settled)");
} else {
    no("Repayment 2 failed: remaining is " . $loanAfter2['remaining_amount']);
}

// 6. Overdue Loan Check
$pastDate = date('Y-m-d', strtotime('-5 days'));
$overdueLoanId = PersonalLoans::createLoan([
    'person_name' => 'Overdue Borrower Test',
    'loan_date' => date('Y-m-d', strtotime('-35 days')),
    'expected_date' => $pastDate,
    'original_amount' => 8000.00,
    'account_id' => $cashAcc['id']
], 1);
$overdueLoan = PersonalLoans::find($overdueLoanId);
if ($overdueLoan['status'] === 'overdue') {
    ok("Overdue loan detected: return date was $pastDate, status is correctly 'overdue'");
} else {
    no("Overdue status check failed: got " . $overdueLoan['status']);
}

// 7. Date range filter check
$filteredLoans = PersonalLoans::listLoans('all', '', date('Y-m-01'), date('Y-m-t'), 'loan_date');
if (!empty($filteredLoans)) {
    ok("Date range filtering works: returned " . count($filteredLoans) . " loans in current month");
} else {
    no("Date range filtering returned empty");
}

// 8. View Render Checks
ob_start();
PersonalLoansController::index();
$htmlIndex = ob_get_clean();

strpos($htmlIndex, 'قرض کی تاریخ') !== false || strpos($htmlIndex, 'Loan Date') !== false ? ok("Index has Loan Date filter") : no("Missing Loan Date filter");
strpos($htmlIndex, 'واپسی کی تاریخ') !== false || strpos($htmlIndex, 'Return Date') !== false ? ok("Index has Return Date filter") : no("Missing Return Date filter");
strpos($htmlIndex, 'Account') !== false ? ok("Index table has Account column") : no("Missing Account column");
strpos($htmlIndex, 'dlg-repay-') !== false ? ok("Index has quick repayment modal dialogs") : no("Missing repayment dialogs");

// Cleanup test loans
db_exec("DELETE FROM personal_loan_repayments WHERE loan_id IN (?, ?)", [$loanId, $overdueLoanId]);
db_exec("DELETE FROM personal_loans WHERE id IN (?, ?)", [$loanId, $overdueLoanId]);

echo "\n============================================\n";
echo "SUMMARY: Passes: $pass, Failures: $fail\n";
echo "============================================\n";

if ($fail > 0) exit(1);
