<?php
$s = file_get_contents('tests/tr_expense_gifts_keys.php');
$needle = " — ";
$j = 0;
while (true) {
    $j = strpos($s, $needle, $j);
    if ($j === false) break;
    echo $j, ' :: ', bin2hex(substr($s, max(0, $j - 4), 14)), "\n";
    $j++;
}
