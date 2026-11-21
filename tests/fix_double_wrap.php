<?php
/** Fix double-wrapped __(__('x')) → __('x') globally. */
$files = array_merge(glob(__DIR__ . '/../app/controllers/*.php'), glob(__DIR__ . '/../app/services/*.php'), glob(__DIR__ . '/../app/views/**/*.php') ?: []);
// also scan views recursively
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) { if ($f->getExtension() === 'php') $files[] = $f->getPathname(); }
foreach ($it as $f) { } // noop
$count = 0;
foreach (array_unique($files) as $f) {
    $src = file_get_contents($f);
    $new = preg_replace("/__\\(__(\\('(?:[^'\\\\]|\\\\.)*'\\))\\)/", '__$1', $src);
    if ($new !== $src) { file_put_contents($f, $new); $count++; echo "fixed: ", basename($f), "\n"; }
}
echo "files fixed: $count\n";
