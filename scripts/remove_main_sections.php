<?php
$file = __DIR__ . '/../app/views/dashboard/index.php';
$content = file_get_contents($file);

// Target block: Main Sections header and tiles
$start = '<div class="section-head"><h3><span class="tick"></span><?= __(\'Main Sections\') ?></h3></div>';
$end = '<div class="section-head" style="margin-top:20px"><h3><span class="tick"></span><?= __(\'Salesmen\') ?></h3>';

$startPos = strpos($content, $start);
$endPos = strpos($content, $end);

if ($startPos !== false && $endPos !== false) {
    // Remove the whole block from startPos to endPos
    $content = substr($content, 0, $startPos) . substr($content, $endPos);
    file_put_contents($file, $content);
    echo "Main Sections removed from dashboard. Salesmen section kept.\n";
} else {
    echo "Could not find Main Sections block\n";
}
