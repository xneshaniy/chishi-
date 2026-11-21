<?php
$dir = __DIR__ . '/../app';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$patterns = [
    'strftime' => '/strftime\s*\(/i',
    'date_now' => '/date\s*\(\s*[\'"]now[\'"]/i',
    'datetime_now' => '/datetime\s*\(\s*[\'"]now[\'"]/i',
    'random' => '/\brandom\s*\(\s*\)/i',
    'rowid' => '/\browid\b/i',
    'glob' => '/\bGLOB\b/i',
];

$results = [];

foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $lines = file($file->getPathname());
    foreach ($lines as $ln => $line) {
        foreach ($patterns as $k => $pat) {
            if (preg_match($pat, $line)) {
                $rel = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $results[] = [
                    'file' => $rel,
                    'line' => $ln + 1,
                    'type' => $k,
                    'text' => trim($line)
                ];
            }
        }
    }
}

echo "Found " . count($results) . " potential SQLite-specific occurrences:\n\n";
foreach ($results as $r) {
    echo "{$r['file']}:{$r['line']} [{$r['type']}] => {$r['text']}\n";
}
