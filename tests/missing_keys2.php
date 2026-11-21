<?php
$lang = require __DIR__ . '/../app/lang.php';
$dirs = ['app/views/dashboard/index.php','app/views/layout.php'];
$found = [];
foreach ($dirs as $file) {
    $s = file_get_contents(__DIR__ . '/../' . $file);
    preg_match_all("/__\\(\\s*'((?:\\\\'|[^'])*)'\\s*\\)/", $s, $m);
    foreach ($m[1] as $raw) $found[str_replace("\\'", "'", $raw)] = true;
}
$missing = [];
foreach (array_keys($found) as $k) if (!isset($lang['en'][$k])) $missing[] = $k;
echo count($missing), " missing keys:\n";
foreach ($missing as $k) echo '  ', $k, "\n";
