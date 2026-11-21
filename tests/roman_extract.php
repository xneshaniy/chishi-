<?php
$lang = require __DIR__ . '/../app/lang.php';
$en = $lang['en']; $roman = $lang['roman'];
$out = '';
foreach ($en as $k => $_) if (isset($roman[$k]) && $roman[$k] === $k) $out .= $k . "\n";
file_put_contents(__DIR__ . '/roman_missing_list.txt', $out);
echo COUNT, "\n";
