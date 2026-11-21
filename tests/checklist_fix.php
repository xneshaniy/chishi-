<?php
$f = 'docs/TEST-CHECKLIST.md';
$s = file_get_contents($f);
$needle = "- [ ] **A-4** `php tests\\schema_check.php`";
$pos = strpos($s, $needle);
$lineEnd = strpos($s, "\n", $pos);
$insert = "- [ ] **A-4a** `php tests\\purchase_approve_test.php` → expect **18 tests, 0 failures** (manager purchase request → owner approval exactly-once).\n"
        . "- [ ] **A-4b** `php tests\\allocation_test.php` → expect **6 tests, 0 failures** (supplier payment FIFO allocation: Unpaid → Partial → Paid).\n";
$s = substr($s, 0, $lineEnd + 1) . $insert . substr($s, $lineEnd + 1);
file_put_contents($f, $s);
echo "inserted A-4a/A-4b\n";
