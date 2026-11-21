<?php
$f = 'app/controllers/SalesmenController.php';
$s = file_get_contents($f);
$bad = "\xe2\x80\xc2\x9d";   // broken tail of the ” (U+201D) mojibake
$good = "\xe2\x80\x9d";     // ”
echo 'occurrences: ', substr_count($s, $bad), "\n";
$s = str_replace($bad, $good, $s);
file_put_contents($f, $s);
echo 'utf8 valid: ', (mb_check_encoding(file_get_contents($f), 'UTF-8') ? 'yes' : 'no'), "\n";

// full-file scan for any remaining invalid UTF-8
foreach (['app/controllers/SalesmenController.php', 'app/controllers/SalesController.php'] as $ff) {
    $t = file_get_contents($ff);
    $len = strlen($t); $i = 0; $bad_found = 0;
    while ($i < $len) {
        $b = ord($t[$i]);
        if ($b < 0x80) { $i++; continue; }
        $l = ($b & 0xF8) === 0xF0 ? 4 : (($b & 0xF0) === 0xE0 ? 3 : (($b & 0xE0) === 0xC0 ? 2 : 0));
        $ok = $l && $i + $l <= $len;
        for ($k = 1; $k < $l && $ok; $k++) if ((ord($t[$i + $k]) & 0xC0) !== 0x80) $ok = false;
        if (!$ok) { $bad_found++; echo "invalid at $i in $ff\n"; break; }
        $i += $l;
    }
    if (!$bad_found) echo "clean: $ff\n";
}
