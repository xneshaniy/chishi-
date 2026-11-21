<?php
$f = 'README.md';
$s = file_get_contents($f);
$s = str_replace('claim-goods (26), costing, sweep, dev_init', 'claim-goods (26), costing, sweep, dev_init, purchase_approve_test, allocation_test', $s, $n);
file_put_contents($f, $s);
echo 'replaced: ', $n, "\n";
