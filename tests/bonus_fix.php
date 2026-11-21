<?php
$f = 'app/controllers/SalesmenController.php';
$s = file_get_contents($f);
$bad = "\xe2\x80\xa2\x9d"; // not a valid UTF-8 sequence as-is (e280a2=U+2022, then raw 9d)
echo 'occurrences: ', substr_count($s, $bad), "\n";
$s2 = str_replace($bad, "\xe2\x80\x9d", $s);
file_put_contents($f, $s2);
$check = file_get_contents($f);
echo 'now valid-UTF8: ', (mb_check_encoding($check, 'UTF-8') ? 'yes' : 'no'), "\n";
