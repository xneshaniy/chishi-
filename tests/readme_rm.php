<?php
$f = 'README.md'; $s = file_get_contents($f);
$pairs = [
    "php tests\\payroll_partial_test.php     # salary payments: partial payments via approval, TB stays balanced, legacy full-pay compatibility (14 assertions)" =>
    "php tests\\payroll_partial_test.php     # salary payments: partial payments via approval, TB stays balanced, legacy full-pay compatibility (14 assertions)\nphp tests\\rm_packaging_test.php          # raw-material purchasing (TON/KG/MAN), KG base-unit stock, factory consumption, no double-deduct production, FG packaging display (24 assertions)",
    "tests (275 assertions passing - 44 legacy + 63 v2 modules + 25 notification tests + 26 claim-goods tests + 18 purchase-approval tests + 6 payment-allocation tests + 22 maintenance E2E tests + 25 salesman-bonus E2E tests + 14 payroll partial-payment E2E tests:" =>
    "tests (299 assertions passing - 44 legacy + 63 v2 modules + 25 notification tests + 26 claim-goods tests + 18 purchase-approval tests + 6 payment-allocation tests + 22 maintenance E2E tests + 25 salesman-bonus E2E tests + 14 payroll partial-payment E2E tests + 24 RM purchasing/packaging E2E tests:",
    "approvals, bonus, salesman credit ledger, loans, advances, payroll types, area gating, language, notification dispatch/deduplication/role-scoping, claim goods lifecycle, raw-material purchase request -> owner approval exactly-once, FIFO supplier-payment allocation, partial salary payments with approval gating and per-employee salary ledgers)" =>
    "approvals, bonus, salesman credit ledger, loans, advances, payroll types, area gating, language, notification dispatch/deduplication/role-scoping, claim goods lifecycle, raw-material purchase request -> owner approval exactly-once, FIFO supplier-payment allocation, partial salary payments with approval gating and per-employee salary ledgers, TON/KG/MAN raw-material purchasing, factory consumption with exact-KG deduction, FG packaging conversion display)",
    "tests/                  schema_check, functional (44 assertions), extended (63), notifications (25), claim-goods (26), costing, sweep, dev_init, purchase_approve_test, allocation_test, maintenance_test, payroll_partial_test (14)" =>
    "tests/                  schema_check, functional (44 assertions), extended (63), notifications (25), claim-goods (26), costing, sweep, dev_init, purchase_approve_test, allocation_test, maintenance_test, payroll_partial_test (14), rm_packaging_test (24)",
];
foreach ($pairs as $old => $new) {
    $s = str_replace($old, $new, $s, $n);
    echo str_pad((string)$n, 4), " replaced\n";
}
file_put_contents($f, $s);
