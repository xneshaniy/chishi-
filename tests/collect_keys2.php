<?php
/** Collect exact keys passed to __() calls — with proper quote handling. */
$dirs = [__DIR__ . '/../app/views', __DIR__ . '/../app/controllers'];
$keys = [];
foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $s = file_get_contents($f->getPathname());
        // match __('key') where key contains no unescaped single quote
        preg_match_all('/__\(\s*\'([^\']{1,200})\'\s*\)/u', $s, $m);
        foreach ($m[1] as $k) $keys[$k] = true;
    }
}
$list = array_keys($keys);
sort($list);
file_put_contents(__DIR__ . '/used_keys.txt', implode("\n", $list));
echo 'Keys used: ', count($list), "\n";
