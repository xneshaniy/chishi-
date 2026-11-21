<?php
$zipFile = __DIR__ . '/../chishti_software_live.zip';
// Delete any existing zips in the root
foreach (glob(__DIR__ . '/../*.zip') as $z) {
    @unlink($z);
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Cannot open zip archive\n");
}

$root = realpath(__DIR__ . '/..');
$excludeDirs = ['.git', 'tests', 'preview'];
$excludeFiles = ['dev-router.php'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$fileCount = 0;
foreach ($iterator as $item) {
    $path = $item->getPathname();
    $relative = substr($path, strlen($root) + 1);
    $relative = str_replace('\\', '/', $relative);

    // Check excludes
    $skip = false;
    foreach ($excludeDirs as $d) {
        if ($relative === $d || strpos($relative, $d . '/') === 0) {
            $skip = true;
            break;
        }
    }
    if ($skip) continue;

    foreach ($excludeFiles as $f) {
        if ($relative === $f) {
            $skip = true;
            break;
        }
    }
    if ($skip) continue;

    // Do not include sqlite databases or dev config with local paths in the generic zip
    if (strpos($relative, 'storage/dev.sqlite') === 0 || strpos($relative, 'storage/backups/') === 0 && strlen($relative) > 16) {
        continue;
    }
    if ($relative === 'config/config.php') {
        continue; // Hostinger web installer will generate this safely
    }

    if ($item->isDir()) {
        $zip->addEmptyDir($relative);
    } else {
        $zip->addFile($path, $relative);
        $fileCount++;
    }
}

$zip->close();
echo "Created chishti_erp_hostinger_ready.zip with {$fileCount} files (" . round(filesize($zipFile) / (1024 * 1024), 2) . " MB)!\n";
