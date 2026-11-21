<?php
$extra = require __DIR__ . '/tr_extra.php';
$flash = require __DIR__ . '/tr_flash_keys.php';
foreach (['ur','roman'] as $lg) foreach ($flash[$lg] as $k => $v) $extra[$lg][$k] = $v;
$missing = ['created', 'Attendance saved for ', 'Claim not found.', 'KB, '];
foreach ($missing as $k) echo var_export($k, true), ' in extra[ur]: ', isset($extra['ur'][$k]) ? 'YES' : 'NO', "\n";
$used = file(__DIR__ . '/used_keys.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$hit = 0;
foreach ($missing as $k) if (in_array($k, $used, true)) $hit++;
echo "used_keys contains sample missing keys: $hit\n";
// check how 'created' appears in used_keys
foreach ($used as $line) if (strpos($line, 'created') !== false) echo "used: [", $line, "]\n";
