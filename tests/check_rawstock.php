<?php
$l = file(__DIR__ . '/../app/views/inventory/raw_stock.php', FILE_IGNORE_NEW_LINES);
echo "line count: ", count($l), "\n";
foreach ([5, 6, 7, 8, 9, 10, 11, 12, 13] as $n) {
    $line = $l[$n-1] ?? '(missing)';
    echo "== $n ==\n", $line, "\n";
}
