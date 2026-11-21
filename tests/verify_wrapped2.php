<?php
$open = '<' . chr(63) . '= __(';   // opening of translation echo, built without closing tag
$close = ') ' . chr(63) . chr(62);  // closing of translation echo, built safely
$needle = "'" . ') ' . chr(63) . chr(62); // broken fragment: quote paren space close
foreach (['reports/raw_daily.php','reports/fg_daily.php','purchase/raw.php','dashboard/index.php','inventory/raw_stock.php','inventory/finished_stock.php'] as $f) {
    $s = file_get_contents('app/views/' . $f);
    // remove valid translation echoes
    $clean = '';
    $pos = 0; $n = strlen($s); $skippedOpen = 0;
    while (($i = strpos($s, $open, $pos)) !== false) {
        $end = strpos($s, $needle, $i);
        if ($end === false) break;
        $clean .= substr($s, $pos, $i - $pos);
        $pos = $end + strlen($needle);
        $skippedOpen++;
    }
    $clean .= substr($s, $pos);
    $bad = substr_count($clean, $needle);
    echo '=== ', $f, ' : valid echoes=', $skippedOpen, " leftover-fragments=", $bad, "\n";
    if ($bad) {
        $p = 0; $shown = 0;
        while (($p = strpos($clean, $needle, $p)) !== false && $shown < 3) { echo '   ...', substr($clean, max(0, $p - 80), 100), "\n"; $p++; $shown++; }
    }
}
