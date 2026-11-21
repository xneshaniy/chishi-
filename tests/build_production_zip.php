<?php
/**
 * Build justi_erp_production.zip — lint all PHP, package only production files, verify no secrets.
 * Usage: php tests/build_production_zip.php
 * Never ship this file's output for anything except Hostinger production deployment.
 */
$root = dirname(__DIR__);
$zipPath = $root . '/justi_erp_production.zip';

/* ---------- Exclusions (relative paths) ---------- */
$excludeExact = [
    'justi_erp_production.zip',
    'config/config.php',
    'database/seed_demo.php',
    'database/schema.sqlite.sql',
    'dev-router.php',
];
$excludeDirs = ['tests/', 'preview/'];
function isExcluded(string $rel): bool {
    global $excludeExact, $excludeDirs, $root;
    $rel = str_replace('\\', '/', $rel);
    if (in_array($rel, $excludeExact, true)) return true;
    foreach ($excludeDirs as $d) { if (str_starts_with($rel, $d)) return true; }
    // storage: keep only .htaccess and .gitkeep files
    if (str_starts_with($rel, 'storage/')) {
        $base = basename($rel);
        return !($base === '.htaccess' || $base === '.gitkeep');
    }
    // junk files anywhere
    if (preg_match('/\.(log|tmp|bak|swp|sqlite|sqlite3)$/i', $rel)) return true;
    if (preg_match('/(Thumbs\.db|\.DS_Store)$/i', $rel)) return true;
    // dev sqlite regardless of path
    if (str_contains($rel, 'dev.sqlite')) return true;
    return false;
}

/* ---------- 1 · Lint every PHP file ---------- */
$phpFiles = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if ($file->getExtension() !== 'php') continue;
    $rel = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $root)) + 1);
    if (isExcluded($rel)) continue;
    $phpFiles[] = $rel;
}
echo "Linting ", count($phpFiles), " PHP files…\n";
$lintFail = 0;
foreach ($phpFiles as $rel) {
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel)) . ' 2>&1');
    if ($out === null || !str_contains($out, 'No syntax errors')) { echo "  LINT FAIL: $rel\n    $out\n"; $lintFail++; }
}
if ($lintFail) { echo "Build aborted: $lintFail lint failure(s).\n"; exit(1); }
echo "Lint: all OK\n";

/* ---------- 2 · Build the ZIP ---------- */
@unlink($zipPath);
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) { echo "Cannot create $zipPath\n"; exit(1); }

$included = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if ($file->isDir()) continue;
    $rel = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $root)) + 1);
    if (isExcluded($rel)) continue;
    if (!$zip->addFile($file->getPathname(), $rel)) { echo "  ZIP ADD FAIL: $rel\n"; exit(1); }
    $included++;
}
$zip->close();
echo "Packaged $included files → ", basename($zipPath), " (", number_format(filesize($zipPath) / 1024, 1), " KB)\n";

/* ---------- 3 · Verify: no secrets / dev artifacts ---------- */
$forbidden = [
    'owner1234' => 'development password',
    'salesman1234' => 'development password',
    'dev.sqlite' => 'development database',
    'demo_seed' => 'demo seed marker',
    'DEMO-' => 'demo record marker',
    'OB-DEMO' => 'demo opening-balance voucher',
    'Dev Owner' => 'development account',
    'Demo Salesman' => 'development account',
];
$verify = new ZipArchive();
$verify->open($zipPath);
$bad = 0;
for ($i = 0; $i < $verify->numFiles; $i++) {
    $name = $verify->getNameIndex($i);
    $contents = $verify->getFromName($name);
    if ($contents === false) continue;
    foreach ($forbidden as $needle => $why) {
        if (stripos($contents, $needle) !== false) { echo "  FORBIDDEN [$why] in $name\n"; $bad++; }
    }
}
$verify->close();
if ($bad) { @unlink($zipPath); echo "Build FAILED verification — ZIP deleted. Fix the files above and rebuild.\n"; exit(1); }
echo "Verification: no development credentials or demo data in the package.\n";
echo "Done.\n";
