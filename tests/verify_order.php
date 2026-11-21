<?php
$all = [];
foreach (glob(__DIR__ . '/used_keys_old_*.txt') as $f) {
    $all = array_merge($all, file($f, FILE_IGNORE_NEW_LINES));
}
$prev = null;
foreach ($all as $i => $k) {
    if ($prev !== null && strcasecmp($k, $prev) < 0) { echo "ORDER MISMATCH at line ", ($i + 1), ": [", $prev, "] -> [", $k, "]\n"; break; }
    $prev = $k;
}
$lines = array_merge($all);
echo "checked ", count($lines), " lines for ordering (strcasecmp)\n";
