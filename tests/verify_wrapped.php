<?php
/** For every occurrence of "') " + closing-tag: valid iff preceded by __('...' ) i.e. window ending at match must match /__\('[^']*'\) $/ */
$close = chr(63) . chr(62);
$needle = "'" . ')' . ' ' . $close;
foreach (['reports/raw_daily.php','reports/fg_daily.php','purchase/raw.php','dashboard/index.php'] as $f) {
    $s = file_get_contents('app/views/' . $f);
    $pos = 0; $bad = 0; $badCtx = [];
    while (($pos = strpos($s, $needle, $pos)) !== false) {
        $winStart = max(0, $pos - 400);
        $win = substr($s, $winStart, $pos - $winStart);
        if (!preg_match('/__\(\'[^\']*\'\) $/', $win)) {
            $bad++;
            if (count($badCtx) < 5) $badCtx[] = trim(substr($s, max(0, $pos - 80), 90));
        }
        $pos++;
    }
    echo '=== ', $f, ' : ', $bad, " suspect\n";
    foreach ($badCtx as $c) echo '   ...', $c, "\n";
}
