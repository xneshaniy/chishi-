<?php
$raw = file_get_contents('database/schema.sqlite.sql');
$i = strpos($raw, 'CREATE TABLE vehicle_maintenance');
$end = strpos($raw, ');', $i) + 2;
$block = substr($raw, $i, $end - $i);
echo $block, "\n";
