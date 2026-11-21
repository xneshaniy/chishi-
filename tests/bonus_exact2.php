<?php
$f = 'app/controllers/SalesmenController.php';
$s = file_get_contents($f);
$p = strpos($s, 'Sales Commission');
echo bin2hex(substr($s, $p - 30, 60)), "\n\n";
echo substr($s, $p - 30, 60), "\n";
// find where UTF-8 breaks
$len = strlen($s);
for ($i = 0; $i < $len; ) {
    $b = ord($s[$i]);
    if ($b < 0x80) { $i++; continue; }
    $l = ($b & 0xF8) === 0xF0 ? 4 : (($b & 0xF0) === 0xE0 ? 3 : (($b & 0xE0) === 0xC0 ? 2 : 0));
    if (!$l || $i + $l > $len) { echo "invalid byte at $i: ", bin2hex(substr($s, $i - 20, 30)), "\n"; break; }
    $ok = true;
    for ($k = 1; $k < $l; $k++) if ((ord($s[$i + $k]) & 0xC0) !== 0x80) { $ok = false; break; }
    if (!$ok) { echo "bad seq at $i: ", bin2hex(substr($s, $i - 20, 30)), "\n"; break; }
    $i += $l;
}
