<?php
$lang = require __DIR__ . '/../app/lang.php';
$auto = require __DIR__ . '/tr_roman_auto.php';
$en = $lang['en']; $ur = $lang['ur'];
$rows = [];
foreach ($auto as $k => $v) {
    if ($v === $k) $rows[$k] = $ur[$k] ?? '';
}
file_put_contents(__DIR__ . '/auto_review_utf8.txt', "=== " . count($rows) . " ===\n");
foreach ($rows as $k => $v) {
    file_put_contents(__DIR__ . '/auto_review_utf8.txt', "EN: $k\nUR: $v\n\n", FILE_APPEND);
}
echo "written\n";
