<?php
$l = file(__DIR__ . '/used_keys.txt', FILE_IGNORE_NEW_LINES);
foreach ([258,259,260] as $n) {
    $l2 = $l[$n-1] ?? '(missing)';
    echo $n, ': [', $l2, '] hex: ', bin2hex(trim($l2)), "\n";
}
