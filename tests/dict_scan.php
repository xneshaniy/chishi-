<?php
$lang = require __DIR__ . '/../app/lang.php';
$missing = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
foreach ($files as $f) {
    if ($f->getExtension() !== 'php') continue;
    preg_match_all("/__\\(\\s*'((?:\\\\'|[^'])*)'\\s*\\)/", file_get_contents($f->getPathname()), $m);
    foreach ($m[1] as $raw) {
        $key = str_replace(chr(92) . chr(39), chr(39), $raw);
        if (!isset($lang['en'][$key])) $missing[] = $key;
    }
}
sort($missing);
echo count($missing), " missing keys:\n";
foreach ($missing as $m) echo '  ', $m, "\n";
