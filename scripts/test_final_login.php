<?php
$loginUrl = 'https://software.chishtitobaccocompany.com/index.php?r=auth/login';
$cookieFile = __DIR__ . '/cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// Step 1: GET login page
$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf"\s+value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';
echo "CSRF token: " . substr($csrf, 0, 16) . "...\n";

// Step 2: POST login
$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'username' => 'naveed',
    'password' => 'naveed@1234',
    'csrf' => $csrf
]);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);
if (file_exists($cookieFile)) unlink($cookieFile);

echo "HTTP Code: {$code}\n";
echo "Final URL: {$effectiveUrl}\n";
if (strpos($res, 'Dashboard') !== false || strpos($res, 'Naveed') !== false || strpos($res, 'factory') !== false) {
    echo "SUCCESS: Logged in! Dashboard loaded!\n";
} else {
    echo "Output snippet: " . substr(strip_tags($res), 0, 400) . "\n";
}
