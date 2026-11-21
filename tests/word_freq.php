<?php
$lang = require __DIR__ . '/../app/lang.php';
$en = $lang['en']; $roman = $lang['roman'];
$freq = [];
foreach ($en as $k => $v) {
    if (!isset($roman[$k]) || $roman[$k] !== $k) continue;
    // split into words incl. hyphenated
    preg_match_all("/[A-Za-z][A-Za-z\-']*/", $k, $m);
    foreach ($m[0] as $w) {
        $lw = strtolower($w);
        $freq[$lw] = ($freq[$lw] ?? 0) + 1;
    }
}
arsort($freq);
echo count($freq), " unique words\n";
foreach ($freq as $w => $n) echo "$n\t$w\n";
