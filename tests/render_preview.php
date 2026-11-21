<?php
/** Render authenticated pages to static preview HTML (bypasses dead loopback proxy). */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

$outDir = __DIR__ . '/../preview';
@mkdir($outDir . '/assets/css', 0775, true);
@mkdir($outDir . '/assets/img', 0775, true);
foreach (glob(__DIR__ . '/../assets/css/*.css') as $css) copy($css, $outDir . '/assets/css/' . basename($css));
foreach (glob(__DIR__ . '/../assets/img/*') as $img) copy($img, $outDir . '/assets/img/' . basename($img));

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
$dbFile = __DIR__ . '/../storage/dev.sqlite';
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite:' . $dbFile], 'costing_method' => 'weighted_average', 'allow_negative_stock' => false];
$admin1 = (int)db_val("SELECT id FROM users WHERE username = 'admin1'");
$seedRow = db_one("SELECT full_name FROM users WHERE id = ?", [$admin1]);

// Boot the app like index.php
require __DIR__ . '/../app/core/bootstrap.php';
// Now inject the authenticated session (bootstrap's session_start already ran)
$_SESSION = ['user_id' => $admin1, 'username' => 'admin1', 'full_name' => $seedRow['full_name'] ?: 'Dev Admin One', 'lang' => 'en', 'csrf' => 'preview'];

$routes = require __DIR__ . '/../app/routes.php';
$pages = ['dashboard', 'sales/invoices', 'purchase/raw', 'inventory/raw', 'inventory/finished', 'reports/fg-daily', 'maintenance', 'maintenance/new'];
foreach ($pages as $route) {
    $_GET = ['r' => $route];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $GLOBALS['AREA'] = null;
    if (!isset($routes[$route])) { echo "skip (no route): $route\n"; continue; }
    [$class, $action, $perm] = $routes[$route];
    ob_start();
    try {
        call_user_func([$class, $action]);
        $html = ob_get_clean();
    } catch (Throwable $e) {
        $html = ob_get_clean() ?: '';
        echo "ERROR rendering $route : ", $e->getMessage(), "\n";
    }
    $html = str_replace(['http://127.0.0.1:8080', 'http://localhost:8080', 'http://localhost:8082'], '', $html);
    $file = $outDir . '/' . str_replace('/', '_', $route) . '.html';
    file_put_contents($file, $html);
    echo 'wrote: ', basename($file), ' (', strlen($html), " bytes)\n";
}
