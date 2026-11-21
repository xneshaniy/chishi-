<?php
header('Content-Type: text/plain');
require __DIR__ . '/config/config.php';
require __DIR__ . '/app/core/bootstrap.php';
$routes = require __DIR__ . '/app/routes.php';

echo "=== CHECKING ALL ROUTE HANDLERS ===\n";
$missingHandlers = [];
$totalRoutes = count($routes);
echo "Total defined routes: $totalRoutes\n";

foreach ($routes as $path => $def) {
    if (is_array($def)) {
        $class = $def[0];
        $method = $def[1];
        if (!class_exists($class)) {
            $missingHandlers[] = "Class '$class' missing for route '$path'";
        } elseif (!method_exists($class, $method)) {
            $missingHandlers[] = "Method '$class::$method' missing for route '$path'";
        }
    }
}

if ($missingHandlers) {
    echo "❌ Found " . count($missingHandlers) . " missing handlers:\n";
    foreach ($missingHandlers as $mh) echo "  $mh\n";
} else {
    echo "✅ ALL $totalRoutes route controller classes and methods exist!\n";
}

// Check database table list
echo "\n=== VERIFYING DATABASE TABLES ===\n";
$tables = array_map('reset', db_all("SHOW TABLES"));
echo "Total tables in live DB: " . count($tables) . "\n";
echo "Tables: " . implode(', ', $tables) . "\n";
