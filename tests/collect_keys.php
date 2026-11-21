<?php
/** Collect every key passed to __() in views and controllers, so we can complete the dictionary. */
$dirs = [__DIR__ . '/../app'];
$keys = [];
foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $s = file_get_contents($f->getPathname());
        preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*\\)/", $s, $m);
        foreach ($m[1] as $k) $keys[str_replace(["\\'", "\\\\"], ["'", "\\"], $k)] = true;
        preg_match_all('/__\(\s*"((?:[^"\\\\]|\\\\.)*)"\s*\)/', $s, $m);
        foreach ($m[1] as $k) $keys[str_replace(['\\"', "\\\\"], ['"', "\\"], $k)] = true;
    }
}
$list = array_keys($keys);
sort($list);
file_put_contents(__DIR__ . '/used_keys.txt', implode("\n", $list));
echo 'Keys used: ', count($list), "\n";
