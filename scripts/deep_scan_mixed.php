<?php
// Comprehensive scanner across the ENTIRE codebase for mixed patterns like:
// English (اردو) or Word (لفظ)
$viewsDir = __DIR__ . '/../app/views';
$controllersDir = __DIR__ . '/../app/controllers';

function scanDirForMixed($dir) {
    $results = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $content = file_get_contents($file->getPathname());
        
        // Find strings with English words followed by parentheses with Arabic/Urdu script, or vice versa
        // e.g. "Something (کچھ)" or "(کچھ) Something" or "Something / کچھ"
        preg_match_all('/([a-zA-Z0-9_\-\s]+[\(\/][\p{Arabic}\s\-\/]+[\)\/]?|[\p{Arabic}\s\-\/]+[\(\/][a-zA-Z0-9_\-\s]+[\)\/]?)/u', $content, $m);
        
        $matches = array_filter(array_map('trim', $m[0] ?? []), function($s) {
            // Must contain both ASCII and Arabic characters
            return preg_match('/[a-zA-Z]/', $s) && preg_match('/[\p{Arabic}]/u', $s);
        });
        
        if (!empty($matches)) {
            $results[$file->getPathname()] = array_unique($matches);
        }
    }
    return $results;
}

$viewsMixed = scanDirForMixed($viewsDir);
$ctrlsMixed = scanDirForMixed($controllersDir);

echo "=== MIXED STRINGS IN VIEWS (" . count($viewsMixed) . " files) ===\n";
foreach ($viewsMixed as $file => $matches) {
    $rel = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $file);
    echo "\n[{$rel}] (" . count($matches) . " occurrences):\n";
    foreach (array_slice($matches, 0, 10) as $str) {
        echo "   • " . $str . "\n";
    }
}

echo "\n=== MIXED STRINGS IN CONTROLLERS (" . count($ctrlsMixed) . " files) ===\n";
foreach ($ctrlsMixed as $file => $matches) {
    $rel = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $file);
    echo "\n[{$rel}] (" . count($matches) . " occurrences):\n";
    foreach (array_slice($matches, 0, 10) as $str) {
        echo "   • " . $str . "\n";
    }
}
