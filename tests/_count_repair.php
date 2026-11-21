<?php
$s = file_get_contents('tests/tr_expense_gifts_keys.php');
$needle = "'repair jobs' => ";
$i = 0;
while (true) {
    $i = strpos($s, $needle, $i);
    if ($i === false) break;
    echo $i, " :: ", substr($s, $i, 50), "\n";
    $i++;
}
