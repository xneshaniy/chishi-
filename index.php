<?php
/**
 * JUSTI NUMBER ONE ERP — front controller (shared-hosting friendly; no rewrite rules required)
 * Routes: index.php?r=module/action
 */
require __DIR__ . '/app/core/bootstrap.php';

// Language switch (EN / Roman Urdu / اردو) — persisted per user account
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ur', 'roman'], true)) {
    save_user_language($_GET['lang']);
}

$routes = require __DIR__ . '/app/routes.php';

$route = $_GET['r'] ?? 'dashboard';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Separate login areas: /admin/* requires Owner|Admin, /management/* requires Owner|Admin|Manager|Accountant (server-side, not just UI)
$areaForRoute = null;
if ($route === 'admin' || str_starts_with($route, 'admin/')) $areaForRoute = 'admin';
if ($route === 'management' || str_starts_with($route, 'management/')) $areaForRoute = 'management';
$GLOBALS['AREA'] = $areaForRoute;

// Match "segment/segment/{param?}" against route patterns manually
function route_match(array $map, string $route): ?array {
    if (isset($map[$route])) return [$map[$route], []];
    foreach ($map as $pattern => $handler) {
        $pp = explode('/', $pattern);
        $rp = explode('/', $route);
        if (count($pp) !== count($rp)) continue;
        $params = [];
        $ok = true;
        foreach ($pp as $i => $seg) {
            if (preg_match('/^\{(\w+)\}$/', $seg, $m)) { $params[$m[1]] = $rp[$i]; }
            elseif ($seg !== $rp[$i]) { $ok = false; break; }
        }
        if ($ok) return [$handler, $params];
    }
    return null;
}

$matched = route_match($routes, $route);

// Public routes (no auth)
$public = ['auth/login', 'admin/login', 'management/login'];

if ($matched) {
    [$handler, $params] = $matched;
    $_GET = array_merge($_GET, $params); // params available like ?id=
    [$class, $action, $perm] = $handler;
    if (!in_array($route, $public, true)) {
        Auth::require_login();
        // Area gates enforced server-side (not just hidden UI)
        if ($GLOBALS['AREA'] === 'admin') Auth::requireArea('admin', 'admin/login');
        if ($GLOBALS['AREA'] === 'management') Auth::requireArea('management', 'management/login');
        if ($perm) Auth::require_perm($perm);
    }
    call_user_func([$class, $action]);
    exit;
}

http_response_code(404);
view('error/404', ['title' => 'Page not found']);
