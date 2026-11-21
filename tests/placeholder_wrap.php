<?php
/** Wrap placeholder="..." values containing letters in __(), except: PHP artifacts, proper-noun brands/codes. */
$skip = ['WH-MAIN', 'MAIN, LAHORE…', 'Toyota / Suzuki / Hino', 'Dyna / Mazda / Shehzore'];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
$count = 0;
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $src = file_get_contents($f->getPathname());
    $new = preg_replace_callback('/placeholder="([^"]*)"/', function ($m) use ($skip, &$count) {
        $v = $m[1];
        if (!preg_match('/[A-Za-z]{2,}/', $v)) return $m[0];
        if (strpos($v, '?>') !== false || strpos($v, '<?') !== false) return $m[0]; // PHP artifact
        if (in_array($v, $skip, true)) return $m[0];
        $count++;
        return 'placeholder="<?= e(__(' . var_export($v, true) . ')) ?>"';
    }, $src);
    if ($new !== $src) file_put_contents($f->getPathname(), $new);
}
echo "placeholders wrapped: $count\n";
