<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
require_once __DIR__ . '/../app/controllers/UsersController.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

try {
    ob_start();
    UsersController::index();
    $html = ob_get_clean();
    echo "UsersController::index rendered OK (" . strlen($html) . " bytes)\n";
} catch (Throwable $e) {
    echo "UsersController::index ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}
