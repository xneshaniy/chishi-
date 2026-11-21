<?php
$all = [];
foreach (glob(__DIR__ . '/used_keys_old_*.txt') as $f) {
    $all = array_merge($all, file($f, FILE_IGNORE_NEW_LINES));
}
$prev = null;
$bad = 0;
foreach ($all as $i => $k) {
    if ($prev !== null && strcmp($k, $prev) < 0) { echo "ORDER MISMATCH at line ", ($i + 1), ": [", $prev, "] -> [", $k, "]\n"; $bad++; }
    $prev = $k;
}
echo $bad ? "$bad mismatches (binary strcmp)\n" : "ORDER OK (binary strcmp)\n";
