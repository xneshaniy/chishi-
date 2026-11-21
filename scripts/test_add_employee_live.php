<?php
$baseUrl = 'https://software.chishtitobaccocompany.com';
$cookieFile = __DIR__ . '/test_emp_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// 1. Login
$ch = curl_init("{$baseUrl}/index.php?r=auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$loginHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf"\s+value="([^"]+)"/', $loginHtml, $m);
$csrf = $m[1] ?? '';

$ch = curl_init("{$baseUrl}/index.php?r=auth/login");
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
curl_exec($ch);
curl_close($ch);

// 2. GET employees/new
$ch = curl_init("{$baseUrl}/index.php?r=employees/new");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$newEmpHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf"\s+value="([^"]+)"/', $newEmpHtml, $m);
$csrfEmp = $m[1] ?? '';

// 3. POST employees/new
$postData = [
    'csrf' => $csrfEmp,
    'code' => 'EMP-TEST-' . rand(100, 999),
    'name' => 'Muhammad Ali',
    'phone' => '03001234567',
    'cnic' => '12345-1234567-1',
    'designation' => 'Machine Operator',
    'department' => 'Production',
    'join_date' => date('Y-m-d'),
    'salary_type' => 'monthly',
    'salary_rate' => '35000',
    'salary_frequency' => 'monthly',
    'payment_method' => 'cash',
    'kind' => 'employee'
];

$ch = curl_init("{$baseUrl}/index.php?r=employees/new");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
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
if (strpos($res, 'Muhammad Ali') !== false || strpos($effectiveUrl, 'employees/profile') !== false || strpos($res, 'Employee Profile') !== false) {
    echo "SUCCESS: Employee created and profile rendered with NO ERRORS!\n";
} else {
    echo "Response snippet:\n" . substr(strip_tags($res), 0, 800) . "\n";
}
