<?php
/** Validate reconstructed old list vs current collection (old minus removed nested-string keys). */
$old = [];
foreach (glob(__DIR__ . '/used_keys_old_*.txt') as $f) {
    $old = array_merge($old, file($f, FILE_IGNORE_NEW_LINES));
}
$new = file(__DIR__ . '/used_keys.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
echo "old: ", count($old), " | new: ", count($new), "\n";

$j = 0;
$skipped = [];
foreach ($new as $key) {
    // unescape old-style \' too (belt and braces)
    $plain = str_replace(chr(92) . chr(39), chr(39), $key);
    $found = false;
    while ($j < count($old)) {
        $oldPlain = str_replace(chr(92) . chr(39), chr(39), $old[$j]);
        if ($oldPlain === $plain) { $found = true; $j++; break; }
        $skipped[] = $j + 1 . " :: " . $old[$j];
        $j++;
    }
    if (!$found) { echo "NOT FOUND IN OLD LIST: [$plain]\n"; }
}
while ($j < count($old)) { $skipped[] = $j + 1 . " :: " . $old[$j]; $j++; }
echo "\nskipped (vanished or mis-transcribed): ", count($skipped), "\n";
foreach ($skipped as $s) echo "  $s\n";
