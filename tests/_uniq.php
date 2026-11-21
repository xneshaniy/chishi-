<?php
$used = file('tests/used_keys.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$key = null;
foreach ($used as $u) { if (mb_strlen($u) <= 4 && mb_strpos($u, "—") !== false) { $key = $u; break; } }
echo "key hex: ", bin2hex((string)$key), "\n";
$d = require 'tests/tr_expense_gifts_keys.php';
echo "in tr_expense_gifts: ", isset($d['ur'][$key]) ? 'yes' : 'no', "\n";
// check every dict file
foreach (glob('tests/tr_*.php') as $f) {
    $o = require $f;
    foreach (['ur', 'roman', 'romanFull', 'roman_full'] as $lg) {
        if (!isset($o[$lg])) continue;
        if (isset($o[$lg][$key])) echo "$lg => found in $f\n";
    }
}
