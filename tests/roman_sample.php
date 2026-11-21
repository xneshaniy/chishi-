<?php
$lang = require __DIR__ . '/../app/lang.php';
$en = $lang['en']; $roman = $lang['roman'];
$same = [];
foreach ($en as $k => $_) if (isset($roman[$k]) && $roman[$k] === $k) $same[] = $k;
echo count($same), " identical strings:\n";
foreach (array_slice($same, 0, 60) as $k) echo "   $k\n";
