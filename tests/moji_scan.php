<?php
$targets = [
    ['app/controllers/SalesController.php', 'Invoice posted'],
    ['app/controllers/SalesController.php', 'Invoice reversed'],
    ['app/controllers/SalesController.php', 'Sales return posted'],
    ['app/controllers/SalesmenController.php', 'Payment received and posted'],
    ['app/controllers/SalesmenController.php', 'Settlement saved'],
    ['app/controllers/SalesmenController.php', 'Bonus recorded'],
    ['app/controllers/SalesmenController.php', 'Salesman return posted'],
];
foreach ($targets as [$file, $needle]) {
    $s = file_get_contents($file);
    $p = strpos($s, $needle);
    if ($p === false) { echo "NOT FOUND: $needle\n"; continue; }
    // print 60 bytes from needle start, hex and raw
    $seg = substr($s, $p, 70);
    echo "=== $file :: $needle\n";
    echo implode(' ', array_map(fn($b) => str_pad(dechex(ord($b)), 2, '0', STR_PAD_LEFT), str_split($seg))), "\n";
    echo $seg, "\n\n";
}
