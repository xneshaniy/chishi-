<?php
$s = file_get_contents('tests/used_keys.txt');
$i = strpos($s, 'Vehicle expense recorded.');
echo bin2hex(substr($s, $i, 30)), "\n";
echo $s === false ? "not found\n" : "found at $i\n";
// also find the dash-em key
$j = strpos($s, " — ");
echo bin2hex(substr($s, $j, 10)), " at $j\n";
