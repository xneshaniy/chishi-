<?php
$lang = require __DIR__ . '/../app/lang.php';
$en = $lang['en']; $roman = $lang['roman'];
foreach ($en as $k => $_) if (isset($roman[$k]) && $roman[$k] === $k) echo $k, "\n";
