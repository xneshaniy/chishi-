<?php
$targets = [
    ['app/controllers/SalesmenController.php', 'Bonus recorded'],
];
foreach ($targets as [$file, $needle]) {
    $s = file_get_contents($file);
    $p = strpos($s, $needle);
    $start = strrpos(substr($s, 0, $p), "__('");
    $end = strpos($s, "'))", $p);
    echo bin2hex(substr($s, $start, $end - $start + 3)), "\n";
    echo substr($s, $start, $end - $start + 3), "\n";
}
