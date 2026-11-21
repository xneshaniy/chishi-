<?php
$f = 'README.md';
$s = file_get_contents($f);
$old = "tests (158 assertions passing - 44 legacy + 63 v2 modules + 25 notification tests + 26 claim-goods tests:\n> approvals, bonus, salesman credit ledger, loans, advances, payroll types, area gating, language, notification dispatch/deduplication/role-scoping, claim goods lifecycle)\n> and a browser-driven end-to-end purchase";
$new = "tests (214 assertions passing - 44 legacy + 63 v2 modules + 25 notification tests + 26 claim-goods tests + 18 purchase-approval tests + 6 payment-allocation tests:\n> approvals, bonus, salesman credit ledger, loans, advances, payroll types, area gating, language, notification dispatch/deduplication/role-scoping, claim goods lifecycle, raw-material purchase request -> owner approval exactly-once, FIFO supplier-payment allocation)\n> and a browser-driven end-to-end raw purchase -> production -> finished-goods sale cycle.";
$pos = strpos($s, 'tests (158 assertions');
if ($pos === false) { echo "anchor not found\n"; exit(1); }
$end = strpos($s, 'Read [Known limitations]');
$s = substr($s, 0, $pos) . $new . "\n> " . substr($s, $end);
file_put_contents($f, $s);
echo "updated\n";
