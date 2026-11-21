<?php
$f = 'README.md'; $s = file_get_contents($f);
$pairs = [
  "php tests\\allocation_test.php        # supplier payment FIFO allocation: unpaid -> partial -> paid (6 assertions)" =>
  "php tests\\allocation_test.php        # supplier payment FIFO allocation: unpaid -> partial -> paid (6 assertions)\nphp tests\\payroll_partial_test.php     # salary payments: partial payments via approval, TB stays balanced, legacy full-pay compatibility (14 assertions)",
  "tests (261 assertions passing - 44 legacy + 63 v2 modules + 25 notification tests + 26 claim-goods tests + 18 purchase-approval tests + 6 payment-allocation tests + 22 maintenance E2E tests + 25 salesman-bonus E2E tests:" =>
  "tests (275 assertions passing - 44 legacy + 63 v2 modules + 25 notification tests + 26 claim-goods tests + 18 purchase-approval tests + 6 payment-allocation tests + 22 maintenance E2E tests + 25 salesman-bonus E2E tests + 14 payroll partial-payment E2E tests:",
  "approvals, bonus, salesman credit ledger, loans, advances, payroll types, area gating, language, notification dispatch/deduplication/role-scoping, claim goods lifecycle, raw-material purchase request -> owner approval exactly-once, FIFO supplier-payment allocation)" =>
  "approvals, bonus, salesman credit ledger, loans, advances, payroll types, area gating, language, notification dispatch/deduplication/role-scoping, claim goods lifecycle, raw-material purchase request -> owner approval exactly-once, FIFO supplier-payment allocation, partial salary payments with approval gating and per-employee salary ledgers)",
  "php tests\\salesman_bonus_test.php    # salesman issue with bonus: stock, payable, PROMO, approval threshold, free transfer, rate snapshot (25 assertio" => "KEEPSAME",
  "tests/                  schema_check, functional (44 assertions), extended (63), notifications (25), claim-goods (26), costing, sweep, dev_init, purchase_approve_test, allocation_test, maintenance_test" =>
  "tests/                  schema_check, functional (44 assertions), extended (63), notifications (25), claim-goods (26), costing, sweep, dev_init, purchase_approve_test, allocation_test, maintenance_test, payroll_partial_test (14)",
  "- **Salary advances** - request \u{2192} Owner/Admin approval \u{2192} disbursement \u{2192} automatic deduction from next salary (full outstanding or capped monthly deduction)" =>
  "- **Salary advances** - request \u{2192} Owner/Admin approval \u{2192} disbursement \u{2192} automatic deduction from next salary (full outstanding or capped monthly deduction). Optional company-wide max advance (Settings \u{2192} Company Profile) applies to non-Owner/Admin requesters",
];
foreach ($pairs as $old => $new) {
    if ($new === 'KEEPSAME') continue;
    $s = str_replace($old, $new, $s, $n);
    echo str_pad((string)$n, 4), 'replaced', "\n";
}
// Salary section: add partial-payment/ledger mention after the salary bullet
$needle = "- **Salary advances**";
$extra = "- **Payroll** - full or partial salary payments per payslip, each approval-gated, accumulated on the payslip; complete per-employee salary ledger (earned/paid/remaining payable + payment history), printable payslip with payment status and remaining balance\n";
if (strpos($s, $extra) === false && ($i = strpos($s, $needle)) !== false) {
    $s = substr($s, 0, $i) . $extra . substr($s, $i);
    echo "payroll bullet inserted\n";
}
file_put_contents($f, $s);
