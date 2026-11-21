<?php
$f = 'README.md';
$s = file_get_contents($f);
$old = "tests (214 assertions passing - 44 legacy + 63 v2 modules + 25 notification tests + 26 claim-goods tests + 18 purchase-approval tests + 6 payment-allocation tests:";
$new = "tests (236 assertions passing - 44 legacy + 63 v2 modules + 25 notification tests + 26 claim-goods tests + 18 purchase-approval tests + 6 payment-allocation tests + 22 maintenance E2E tests:";
$s = str_replace($old, $new, $s, $n1);
// insert What's-new bullet right after the Raw Material & FG bullet block (before '- **Two login areas**')
$anchor = "- **Two login areas**";
$insert = "- **Factory Maintenance & Repairs** — record damaged machines/equipment/vehicles: parts used (qty × rate), labour, other costs, technician/vendor, payment method (cash/bank/credit) and paid/pending status. Records go to Owner/Admin for approval before any expense posts; approved records create a Factory Maintenance & Repairs expense posted exactly once and appear in all expense reports and the ledger. Per-machine repair cost history, asset registry with cumulative cost, totals by day/month/year/machine/department/vendor — printable + CSV. Dashboard shows this-month maintenance cost and pending approvals.\n- **Printing everywhere** — print buttons on every document and report; browser print dialog = preview + Save as PDF; documents print with company logo, NTN/STRN, doc number, date and signature lines; A4 and thermal-80mm modes on vouchers; Urdu prints right-to-left automatically when the language is set to اردو.\n";
$pos = strpos($s, $anchor);
$s = substr($s, 0, $pos) . $insert . substr($s, $pos);
// test list line in README
$oldList = "php tests\\purchase_approve_test.php  # manager raw-purchase request -> owner approval exactly-once (18 assertions)";
$newList = "php tests\\purchase_approve_test.php  # manager raw-purchase request -> owner approval exactly-once (18 assertions)\nphp tests\\maintenance_test.php      # maintenance record -> approval -> expense exactly-once -> reversal (22 assertions)";
$s = str_replace($oldList, $newList, $s, $n2);
// ZIP contents mention
$s = str_replace("costing, sweep, dev_init, purchase_approve_test, allocation_test", "costing, sweep, dev_init, purchase_approve_test, allocation_test, maintenance_test", $s, $n3);
file_put_contents($f, $s);
echo "readme updated: $n1 $n2 $n3\n";
