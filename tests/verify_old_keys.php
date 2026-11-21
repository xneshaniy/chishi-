<?php
$total = 0;
$all = [];
foreach (glob(__DIR__ . '/used_keys_old_*.txt') as $f) {
    $lines = file($f, FILE_IGNORE_NEW_LINES);
    echo basename($f), ': ', count($lines), "\n";
    $total += count($lines);
    $all = array_merge($all, $lines);
}
echo "total: $total\n";
// consistency: unique count
echo 'unique: ', count(array_unique($all)), "\n";
// sorted check
$sorted = $all;
sort($sorted);
echo $sorted === $all ? "ORDER OK (sorted)\n" : "ORDER MISMATCH at " . array_search(false, array_map('strval', $sorted), true) . "\n";
