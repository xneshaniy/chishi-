<?php
/** Bootstrap: config, session, autoload, headers, error handling */

date_default_timezone_set('Asia/Karachi');

$configPath = dirname(__DIR__, 2) . '/config/config.php';
if (!file_exists($configPath)) {
    http_response_code(503);
    echo '<h1>JUSTI NUMBER ONE ERP — not installed</h1><p>Missing <code>config/config.php</code>. Run the installer: <a href="install/index.php">install/index.php</a></p>';
    exit;
}
$CONFIG = require $configPath;
$GLOBALS['CONFIG'] = $CONFIG;

date_default_timezone_set($CONFIG['app']['timezone'] ?? 'Asia/Karachi');

$debug = (bool)($CONFIG['app']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__, 2) . '/storage/logs/error.log');
error_reporting(E_ALL);
if (!$debug) {
    set_exception_handler(function (Throwable $e) {
        error_log('Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
        http_response_code(500);
        echo '<h1 style="font-family:sans-serif">Something went wrong</h1><p>The error has been logged. Please contact the administrator.</p>';
        exit;
    });
}

// Secure session
$sess = $CONFIG['session'] ?? [];
$secure = !empty($CONFIG['app']['https']) || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name($sess['name'] ?? 'CTC_ERP_SESS');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (int)($sess['lifetime'] ?? 120) * 60);
session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/../services/Accounting.php';
require_once __DIR__ . '/../services/Inventory.php';
require_once __DIR__ . '/../services/UnitConverter.php';
require_once __DIR__ . '/../services/Posting.php';
require_once __DIR__ . '/../services/Backup.php';
require_once __DIR__ . '/../services/Approvals.php';
require_once __DIR__ . '/../services/Notifications.php';
require_once __DIR__ . '/../services/Reporting.php';
require_once __DIR__ . '/../services/Payroll.php';
require_once __DIR__ . '/../services/Production.php';

/** Render a view inside the app layout. */
function view(string $name, array $data = [], bool $bare = false): void {
    extract($data, EXTR_SKIP);
    $viewFile = dirname(__DIR__) . '/views/' . $name . '.php';
    if (!file_exists($viewFile)) {
        error_log('view missing: ' . $name);
        http_response_code(500);
        echo 'View not found: ' . e($name);
        return;
    }
    if ($bare) { require $viewFile; return; }
    require dirname(__DIR__) . '/views/layout.php';
}

/** Render a partial file (no layout). */
function partial(string $name, array $data = []): void {
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . '/views/' . $name . '.php';
}
