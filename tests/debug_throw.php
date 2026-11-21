<?php
$used = file(__DIR__ . '/used_keys.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$extra = require __DIR__ . '/tr_extra.php';
foreach (require __DIR__ . '/tr_salesman_account_keys.php' as $lg => $a) foreach ($a as $k => $v) $extra[$lg][$k] = $v;
foreach (require __DIR__ . '/tr_hardcoded_keys.php' as $lg => $a) foreach ($a as $k => $v) $extra[$lg][$k] = $v;
foreach (require __DIR__ . '/tr_flash_keys.php' as $lg => $a) foreach ($a as $k => $v) $extra[$lg][$k] = $v;
foreach (require __DIR__ . '/tr_notif_keys.php' as $lg => $a) foreach ($a as $k => $v) $extra[$lg][$k] = $v;
foreach (require __DIR__ . '/tr_throw_keys.php' as $lg => $a) foreach ($a as $k => $v) $extra[$lg][$k] = $v;
foreach (require __DIR__ . '/tr_placeholder_keys.php' as $lg => $a) foreach ($a as $k => $v) $extra[$lg][$k] = $v;
$ur = require __DIR__ . '/dict_ur_generated.php';
foreach ($extra['ur'] as $k => $v) { if (!isset($ur[$k])) $ur[$k] = $v; }
$missing = array_diff($used, array_keys($ur));
echo count($missing), " missing vs ur:\n";
foreach ($missing as $k) {
    $found = false;
    foreach (array_keys($ur) as $uk) {
        if (strcasecmp(trim($uk), trim($k)) === 0) { echo "NEAR-MATCH usable [", $uk, "] for [", $k, "]\n"; $found = true; break; }
    }
    if (!$found) echo "NO MATCH: [", $k, "]\n";
}
