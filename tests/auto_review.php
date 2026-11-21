<?php
$lang = require __DIR__ . '/../app/lang.php';
$auto = require __DIR__ . '/tr_roman_auto.php';
$en = $lang['en']; $ur = $lang['ur'];
$rows = [];
foreach ($auto as $k => $v) {
    if ($v === $k) $rows[$k] = $ur[$k] ?? '';
}
echo count($rows), " to review\n";
foreach ($rows as $k => $v) echo "EN: $k\nUR: $v\n\n";
