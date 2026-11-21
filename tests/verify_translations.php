<?php
/** Verify every __('key') call in app/ resolves in all three languages. */
$lang = require __DIR__ . '/../app/lang.php';
$dirs = [__DIR__ . '/../app/views', __DIR__ . '/../app/controllers'];
$missing = [];
foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $s = file_get_contents($f->getPathname());
        // match __('...') allowing escaped \' inside
        preg_match_all("/__\\(\\s*'((?:\\\\'|[^'])*)'\\s*\\)/", $s, $m);
        foreach ($m[1] as $raw) {
            $key = str_replace("\\'", "'", $raw);
            foreach (['en', 'ur', 'roman'] as $l) {
                if (!isset($lang[$l][$key])) $missing[] = "$l :: " . str_replace("\n", ' ', $key);
            }
        }
    }
}
if ($missing) {
    echo count($missing), " missing entries:\n";
    foreach (array_unique($missing) as $m) echo "  MISS $m\n";
    exit(1);
}
echo "ALL __() CALLS RESOLVED in en, ur, roman\n";
