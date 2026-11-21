<?php
/** Update docs for the bonus-stock-issue feature. */
$f = 'README.md';
$s = file_get_contents($f);
$anchor = "- **Two login areas**";
$pos = strpos($s, $anchor);
$insert = "- **Optional Bonus on Salesman Issues** — every issue now has a simple **Give Bonus? Yes/No** toggle. When Yes, per-line bonus qty + optional bonus reason are enabled (when No, the bonus columns hide and the issue behaves exactly as before). Bonus quantity is deducted from finished goods stock too; the salesman pays only for the paid quantity. Paid qty, bonus qty and total qty are tracked separately per line; the printable issue receipt, salesman settlement, FG daily report, bonus report and salesman stock report all show the paid/free split, and the bonus cost posts to the PROMO account (never to the salesman's ledger). Restricted schemes / above-threshold bonus quantity still require Owner/Admin approval before stock moves; approval console posts with an atomic status flip so a bonus can never post twice.\n";
$s = substr($s, 0, $pos) . $insert . substr($s, $pos);
$old = "tests (236 assertions passing - 44 legacy + 63 v2 modules + 25 notification tests + 26 claim-goods tests + 18 purchase-approval tests + 6 payment-allocation tests + 22 maintenance E2E tests:";
$new = "tests (258 assertions passing - 44 legacy + 63 v2 modules + 25 notification tests + 26 claim-goods tests + 18 purchase-approval tests + 6 payment-allocation tests + 22 maintenance E2E tests + 22 salesman-bonus E2E tests:";
$s = str_replace($old, $new, $s, $n1);
$old2 = "php tests\\maintenance_test.php      # maintenance record -> approval -> expense exactly-once -> reversal (22 assertions)";
$new2 = $old2 . "\nphp tests\\salesman_bonus_test.php    # salesman issue with bonus: stock, payable, PROMO, approval threshold, free transfer (22 assertions)";
$s = str_replace($old2, $new2, $s, $n2);
file_put_contents($f, $s);
echo "readme bonus: $n1 $n2\n";
