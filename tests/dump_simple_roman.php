<?php
$d = require __DIR__ . '/tr_simple_keys.php';
foreach (array_slice($d['roman'], 0, 45) as $k => $v) echo $k, '  =>  ', $v, "\n";
