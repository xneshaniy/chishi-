<?php
foreach (['reports/raw_daily.php','reports/fg_daily.php','purchase/raw.php','dashboard/index.php','inventory/raw_stock.php','inventory/finished_stock.php'] as $f) {
    $s = file_get_contents('app/views/' . $f);
    // remove all valid <?= __('...') ?> pairs (no nested quotes assumption: keys here have no escaped quotes)
    $s2 = preg_replace('/<\?= __\(\'[^\']*\'\) \?>/s', '', $s);
    $n = substr_count($s2, "') ?>");
    echo '=== ', $f, ' : ', $n, " stray\n";
    $pos = 0;
    while (($pos = strpos($s2, "') ?>", $pos)) !== false) {
        echo '   ...', substr($s2, max(0, $pos - 70), 80), "\n";
        $pos++;
    }
}
