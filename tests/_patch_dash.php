<?php
$s = file_get_contents('tests/tr_expense_gifts_keys.php');
$urLine = "        'repair jobs' => 'مرمت کے کام',\n";
$romLine = "        'repair jobs' => 'repair jobs',\n";
$newUr = "        'repair jobs' => 'مرمت کے کام',\n        '—' => '—',\n";
$newRom = "        'repair jobs' => 'repair jobs',\n        '—' => '—',\n";
// replace FIRST occurrence only (first is in ur section)
$parts = explode($urLine, $s, 2);
$s = implode($newUr, $parts);
$parts = explode($romLine, $s, 2);
$s = implode($newRom, $parts);
file_put_contents('tests/tr_expense_gifts_keys.php', $s);

// check
$d = require 'tests/tr_expense_gifts_keys.php';
echo "ur dash: ", isset($d['ur']['—']) ? 'yes' : 'no', "\n";
echo "roman dash: ", isset($d['roman']['—']) ? 'yes' : 'no', "\n";
