<?php
$used = file(__DIR__ . '/used_keys.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$current = require __DIR__ . '/../app/lang.php';
$extra = require __DIR__ . '/tr_extra.php';
foreach (require __DIR__ . '/tr_salesman_account_keys.php' as $lg => $a) foreach ($a as $k => $v) $extra[$lg][$k] = $v;
foreach (require __DIR__ . '/tr_hardcoded_keys.php' as $lg => $a) foreach ($a as $k => $v) $extra[$lg][$k] = $v;
foreach (require __DIR__ . '/tr_flash_keys.php' as $lg => $a) foreach ($a as $k => $v) $extra[$lg][$k] = $v;
// used is the source of truth; check what's missing against current en + extra ur keys
$allUrKeys = array_combine(array_keys($extra['ur']), array_keys($extra['ur'])) + $current['ur'];
$allUrKeys = $allUrKeys + array_combine(array_keys($current['en']), array_keys($current['en']));
$missing = array_diff($used, array_keys($allUrKeys));
// also compare vs romanDict
$romanDict = require __DIR__ . '/dict_roman_clean.php';
foreach ($romanDict as $k => $v) { if (!isset($allUrKeys[$k])) $allUrKeys[$k] = $v; }
$missing = array_diff($used, array_keys($allUrKeys));
echo count($missing), " missing\n";
echo "<?php return array (\n";
foreach ($missing as $k) echo '  ', var_export($k, true), ",\n";
echo ");\n";
