<?php
/** Check every sidebar $nav route against registered routes. */
$layout = file_get_contents(__DIR__ . '/../app/views/layout.php');
$routesSrc = file_get_contents(__DIR__ . '/../app/routes.php');
preg_match_all("/^\\s*'([A-Za-z0-9\\-\\/{}_.]+)'\\s*=>/m", $routesSrc, $m);
$routeKeys = array_unique($m[1]);
$routeSet = array_combine($routeKeys, $routeKeys);
preg_match('/\\$nav = \\[(.*?)\\];/s', $layout, $nm);
$navSrc = $nm[0];
preg_match_all("/^\\s*'([A-Za-z0-9\\-\\/{}_.]+)'\\s*=>/m", $navSrc, $mn);
foreach ($mn[1] as $r) {
    if (str_starts_with($r, '__section:')) continue;
    echo str_pad($r, 30), isset($routeSet[$r]) ? 'OK' : '** MISSING / 404 **', "\n";
}
