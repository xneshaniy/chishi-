<?php
$s = file_get_contents(__DIR__ . '/../app/views/layout.php');
foreach (['title', 'pill-toggle', 'ADMIN CONSOLE', '<title>'] as $probe) {
    $pos = strpos($s, $probe);
    if ($pos === false) continue;
    $chunk = substr($s, $pos, 260);
    // show printable ascii + <hex> for non-ascii
    $out = '';
    foreach (str_split($chunk) as $b) {
        $c = ord($b);
        $out .= ($c >= 32 && $c < 127) ? $b : '<' . dechex($c) . '>';
    }
    echo "=== $probe ===\n$out\n\n";
}
