<?php
/** Extract ONLY literal single-quoted __('key') arguments — no embedded quotes. */
$dirs = [__DIR__ . '/../app/views', __DIR__ . '/../app/controllers'];
$keys = [];
foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $s = file_get_contents($f->getPathname());
        // __\( followed by optional whitespace, a single-quoted string with no quotes inside, then \)
        preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\')*)'\\s*\\)/u", $s, $m);
        foreach ($m[1] as $k) $keys[str_replace(chr(92) . chr(39), chr(39), $k)] = true;
    }
}
$list = array_keys($keys);
sort($list);
file_put_contents(__DIR__ . '/used_keys.txt', implode("\n", $list));
echo 'Clean keys: ', count($list), "\n";
