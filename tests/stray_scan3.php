<?php
$closing = chr(63) . chr(62);            // builds the closing tag without writing it literally
$stray = "'" . ')' . ' ' . $closing;      // the broken fragment left by the aborted unwrap
$tag = '/<' . chr(63) . '= __\(\'[^\']*\'\) ' . $closing . '/s';
foreach (['reports/raw_daily.php','reports/fg_daily.php','purchase/raw.php','dashboard/index.php','inventory/raw_stock.php','inventory/finished_stock.php'] as $f) {
    $s = file_get_contents('app/views/' . $f);
    $s2 = preg_replace($tag, '', $s);
    $n = substr_count($s2, $stray);
    echo '=== ', $f, ' : ', $n, " stray\n";
    $pos = 0;
    while (($pos = strpos($s2, $stray, $pos)) !== false) {
        echo '   ...', substr($s2, max(0, $pos - 70), 80), "\n";
        $pos++;
    }
}
