<?php
$url = 'https://software.chishtitobaccocompany.com/install/index.php';
$postData = [
    'action' => 'install',
    'db_host' => 'localhost',
    'db_name' => 'u997441419_software',
    'db_user' => 'u997441419_software',
    'db_pass' => 'Chishti@786@',
    'db_port' => 3306,
    'company' => 'Chishti Tobacco Company',
    'admin_name' => 'Naveed',
    'admin_username' => 'naveed',
    'admin_password' => 'naveed@1234',
    'admin_password_confirm' => 'naveed@1234',
    'https' => 'on'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

echo "Executing installation request to {$url}...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: {$httpCode}\n";
if ($curlErr) {
    echo "cURL Error: {$curlErr}\n";
}

if (strpos($response, 'Installation complete') !== false) {
    echo "SUCCESS: Installation complete!\n";
} elseif (strpos($response, 'Installer locked') !== false) {
    echo "ALREADY INSTALLED: Installer locked.\n";
} else {
    echo "Response preview (first 1000 chars):\n";
    echo substr(strip_tags($response), 0, 1000) . "\n";
}
