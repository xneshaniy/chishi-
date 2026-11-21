<?php
$s = file_get_contents(__DIR__ . '/../app/views/layout.php');
$pos = strpos($s, "lang' => 'ur'");
$chunk = substr($s, $pos - 50, 400);
$out = '';
foreach (str_split($chunk) as $b) {
    $c = ord($b);
    $out .= ($c >= 32 && $c < 127) ? $b : '<' . dechex($c) . '>';
}
echo $out, "\n";
