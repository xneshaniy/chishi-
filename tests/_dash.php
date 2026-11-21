<?php
$used = file('tests/used_keys.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$urDict = require 'tests/dict_ur_generated.php';
$romanDict = require 'tests/dict_roman_clean.php';
$d = require 'tests/tr_expense_gifts_keys.php';
$ur = $urDict + $d['ur'] + (require 'app/lang.php')['ur'];
$key = " — ";
echo "raw key hex: ", bin2hex($key), "\n";
echo "isset ur: ", isset($ur[$key]) ? 'yes' : 'no', "\n";
foreach ($used as $u) if ($u === $key) { echo "used_keys match\n"; break; }
echo "dict has key as raw: ", array_key_exists($key, $d['ur']) ? 'yes' : 'no', "\n";
// check what keys start with space-dash
foreach ($d['ur'] as $k => $v) if (strpos($k, "—") !== false && mb_strlen(trim($k)) <= 4) echo "found variant: ", bin2hex($k), " => ", $v, "\n";
