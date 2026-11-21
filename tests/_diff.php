<?php
$used = file('tests/used_keys.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
sort($used);
$used = array_unique($used);
$d = require 'tests/tr_expense_gifts_keys.php';
$i = array_search('Vehicle expense recorded.', $used, true);
var_dump($i);
var_dump(isset($d['ur']['Vehicle expense recorded.']));
// simulate build_lang
$urDict = require 'tests/dict_ur_generated.php';
$current = require 'app/lang.php';
$ur = $urDict + $d['ur'] + $current['ur'];
var_dump(isset($ur['Vehicle expense recorded.']));
var_dump(array_key_exists('Vehicle expense recorded.', $ur));
