<?php
// Add "Daily Usage (ڈیلی یوز)" link to the Raw Material section in layout nav
$file = __DIR__ . '/../app/views/layout.php';
$src  = file_get_contents($file);

$old = "    // 5. Raw Material\n    '__section:rawmaterial' => [__('Raw Material'), 'raw'],\n    'purchase/raw' => ['purchase', __('Purchases (خریداری)'), 'raw'],\n    'purchase/raw-suppliers' => ['purchase', __('Suppliers & Products (سپلائرز اور پروڈکٹس)'), 'employee'],";

$new = "    // 5. Raw Material\n    '__section:rawmaterial' => [__('Raw Material'), 'raw'],\n    'purchase/raw' => ['purchase', __('Purchases (خریداری)'), 'raw'],\n    'purchase/raw-suppliers' => ['purchase', __('Suppliers & Products (سپلائرز اور پروڈکٹس)'), 'employee'],\n    'inventory/raw-usage' => ['rm.usage', __('Daily Usage (ڈیلی یوز)'), 'raw'],";

if (strpos($src, $old) === false) {
    // Try alternate line endings
    $old2 = str_replace("\n", "\r\n", $old);
    $new2 = str_replace("\n", "\r\n", $new);
    if (strpos($src, $old2) !== false) {
        $src = str_replace($old2, $new2, $src);
        file_put_contents($file, $src);
        echo "OK: layout.php patched (CRLF).\n";
        exit(0);
    }
    echo "ERROR: target not found!\n";
    // Show context around raw material section
    $pos = strpos($src, 'rawmaterial');
    echo "Context: " . substr($src, $pos, 300) . "\n";
    exit(1);
}
$src = str_replace($old, $new, $src);
file_put_contents($file, $src);
echo "OK: layout.php patched (LF).\n";
