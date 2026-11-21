<?php
$d = require __DIR__ . '/dict_roman_generated.php';
$lang = require __DIR__ . '/../app/lang.php';
$all = array_keys($lang['en']);
$inDict = 0; $same = 0;
foreach ($all as $k) {
    if (isset($d[$k])) { $inDict++; if ($d[$k] === $k) $same++; }
}
echo count($d), " entries in dict_roman_generated\n";
echo "used keys covered by dict: $inDict / ", count($all), "   identical: $same\n";
$urDict = require __DIR__ . '/dict_ur_generated.php';
$inUr = 0; $sameUr = 0;
foreach ($all as $k) {
    if (isset($urDict[$k])) { $inUr++; if ($urDict[$k] === $k) $sameUr++; }
}
echo count($urDict), " entries in dict_ur_generated\n";
echo "used keys covered: $inUr / ", count($all), "   identical: $sameUr\n";
