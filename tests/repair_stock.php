<?php
$count = 0;
foreach (['app/views/inventory/raw_stock.php', 'app/views/inventory/finished_stock.php'] as $f) {
    $s = file_get_contents($f);
    $n = 0;
    $s2 = preg_replace_callback('/>\'([^\'<]{1,300})\'\) \?>/', function ($m) use (&$n) {
        $n++;
        return '><' . chr(63) . '= __(' . var_export($m[1], true) . ') ' . chr(63) . '>';
    }, $s);
    file_put_contents($f, $s2);
    echo $f, ' repaired ', $n, " fragments\n";
    $count += $n;
}
echo "total $count\n";
