<?php
/** Verify every class="..." used in views exists in app.css (modernization safety net). */
$css = file_get_contents(__DIR__ . '/../assets/css/app.css');
$cssClasses = [];
preg_match_all('/\.([A-Za-z][\w-]*)/', $css, $m);
foreach ($m[1] as $c) { $cssClasses[$c] = true; }

$used = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
foreach ($files as $f) {
    if ($f->getExtension() !== 'php') continue;
    $s = file_get_contents($f->getPathname());
    preg_match_all('/class="([^"]+)"/', $s, $mm);
    foreach ($mm[1] as $clsList) {
        foreach (preg_split('/\s+/', $clsList) as $c) {
            if ($c === '') continue;
            $used[$c] = $f->getPathname();
        }
    }
}
$missing = [];
foreach ($used as $cls => $file) {
    if (isset($cssClasses[$cls])) continue;
    if (preg_match('/[\${}\[\]<>?=]/', $cls)) continue;
    $missing[$cls] = $file;
}
ksort($missing);
echo count($missing), " class(es) used in views missing from app.css:\n";
foreach ($missing as $cls => $file) {
    echo '  ', $cls, "  (in ", basename($file), ")\n";
}
