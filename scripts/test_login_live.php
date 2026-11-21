<?php
$loginUrl = 'https://software.chishtitobaccocompany.com/index.php?r=auth/login';
$cookieFile = tempnam(sys_get_temp_dir(), 'ctc_cookie_');

// 1. Get login page to grab any CSRF token if needed
$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';

// 2. Post login
$postData = [
    'username' => 'naveed',
    'password' => 'naveed@1234',
    'csrf_token' => $csrf
];

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);
unlink($cookieFile);

echo "Status: {$code}\n";
echo "Final URL: {$effectiveUrl}\n";
if (strpos($res, 'Dashboard') !== false || strpos($res, 'factory') !== false || strpos($res, 'Naveed') !== false) {
    echo "SUCCESS: Logged in to Dashboard successfully!\n";
} else {
    echo "Response preview:\n";
    echo substr(strip_tags($res), 0, 500) . "\n";
}
