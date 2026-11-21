<?php
$f = 'app/views/reports/hub.php';
$s = file_get_contents($f);
$s = str_replace("'x'],\n    ['reports/fg-daily'", "'🛠'],\n    ['reports/fg-daily'", $s, $n1);
$s = str_replace("'x'],\n    ['reports/raw-daily'", "'📦'],\n    ['reports/raw-daily'", $s, $n2);
$s = str_replace("'x'],\n", "'🧱'],\n", $s, $n3);
file_put_contents($f, $s);
echo "fg icon edits: $n1, raw icon edits: $n2, remaining x edits: $n3\n";
