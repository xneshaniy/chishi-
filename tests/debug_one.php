<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
$outDir = 'C:/Users/Haseeb/Desktop/chishti/preview';
@mkdir($outDir . '/assets/css', 0775, true);
@mkdir($outDir . '/assets/img', 0775, true);
foreach (glob('C:/Users/Haseeb/Desktop/chishti/assets/css/*.css') as $css) copy($css, $outDir . '/assets/css/' . basename($css));
if (!is_dir('C:/Users/Haseeb/Desktop/chishti/assets/img')) { /* no img */ } else { foreach (glob('C:/Users/Haseeb/Desktop/chishti/assets/img/*') as $img) copy($img, $outDir . '/assets/img/' . basename($img)); }

require 'C:/Users/Haseeb/Desktop/chishti/app/core/helpers.php';
require 'C:/Users/Haseeb/Desktop/chishti/app/core/Database.php';
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite:C:/Users/Haseeb/Desktop/chishti/storage/dev.sqlite'], 'costing_method' => 'weighted_average', 'allow_negative_stock' => false];
$admin1 = (int)db_val("SELECT id FROM users WHERE username = 'admin1'");
require 'C:/Users/Haseeb/Desktop/chishti/app/core/bootstrap.php';
$_SESSION = ['user_id' => $admin1, 'username' => 'admin1', 'full_name' => 'Dev Admin One', 'lang' => 'en'];
echo "session after inject: ", var_export($_SESSION, true), "\n";
echo "auth check: ", var_export(Auth::check(), true), "\n";
echo "can dashboard.view: ", var_export(Auth::can('dashboard.view'), true), "\n";
$_GET = ['r' => 'dashboard']; $_SERVER['REQUEST_METHOD'] = 'GET'; $GLOBALS['AREA'] = null;
$routes = require 'C:/Users/Haseeb/Desktop/chishti/app/routes.php';
[$class, $action, $perm] = $routes['dashboard'];
echo "route: $class::$action perm=$perm\n";
echo "ob level before: ", ob_get_level(), "\n";
ob_start();
try { call_user_func([$class, $action]); } catch (Throwable $e) { echo "\nEXCEPTION: ", $e->getMessage(), "\n", $e->getTraceAsString(), "\n"; }
$html = ob_get_clean();
echo "html bytes: ", strlen($html), "\n";
echo "first 200: ", substr($html, 0, 200), "\n";
