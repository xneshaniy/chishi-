<?php
/** Focused check: dashboard/layout HTML markers + CSS link health (direct HTTP). */
$base = 'http://127.0.0.1:8080';
$http = curl_init();
curl_setopt_array($http, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'render-check', CURLOPT_TIMEOUT => 20]);
$jar = tempnam(sys_get_temp_dir(), 'ck'); file_put_contents($jar, '');
curl_setopt($http, CURLOPT_COOKIEFILE, $jar); curl_setopt($http, CURLOPT_COOKIEJAR, $jar);
// login
curl_setopt($http, CURLOPT_URL, $base . '/index.php?r=auth/login'); $resp = curl_exec($http);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $resp, $m);
$csrf = $m[1] ?? '';
curl_setopt($http, CURLOPT_URL, $base . '/index.php?r=auth/login');
curl_setopt($http, CURLOPT_POST, true);
curl_setopt($http, CURLOPT_POSTFIELDS, http_build_query(['username' => 'admin1', 'password' => 'Admin@1234x', 'csrf' => $csrf]));
curl_exec($http); curl_setopt($http, CURLOPT_POST, false);

// fetch dashboard
curl_setopt($http, CURLOPT_URL, $base . '/index.php?r=dashboard'); $resp = curl_exec($http);
$markers = [
    'hero-grid' => 'hero KPI grid',
    'hero-card dark' => 'dark hero card',
    'quick-grid' => 'quick actions grid',
    'qa-ic' => 'quick-action icons',
    'section-head' => 'section headers',
    'role-chip' => 'sidebar role chip',
    'avatar' => 'topbar avatar',
    'sidebar-foot' => 'sidebar footer',
    'Current language' => '?',
];
echo "Dashboard markers:\n";
foreach ($markers as $mk => $label) {
    $hit = strpos((string)$resp, $mk) !== false;
    echo '  ', ($hit ? 'OK ' : '?? '), $label, " ('", $mk, "')\n";
}
// CSS link present and fetchable
preg_match('/assets\/css\/app.css\?v=([0-9]+)/', $resp, $cm);
echo "CSS link present: ", $cm ? ('yes (v=' . $cm[1] . ')') : 'NO', "\n";
if ($cm) {
    curl_setopt($http, CURLOPT_URL, $base . '/assets/css/app.css?v=' . $cm[1]);
    $css = curl_exec($http); $code = curl_getinfo($http, CURLINFO_HTTP_CODE);
    echo "CSS fetch: HTTP $code, ", strlen((string)$css), " bytes\n";
    foreach (['.hero-card', '.hero-grid', '.quick-action', '.nav a.active', '.topbar .page-title', '.user-chip', '.avatar', '.section-head', '.notif-panel', '@media print'] as $sel) {
        echo '  CSS has ', str_pad($sel, 24), ': ', (strpos((string)$css, $sel) !== false ? 'yes' : 'NO'), "\n";
    }
}
