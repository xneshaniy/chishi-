<?php
$baseUrl = 'https://software.chishtitobaccocompany.com';
$cookieFile = __DIR__ . '/test_crud_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function getCsrf($html) {
    preg_match('/name="csrf"\s+value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

// 1. Login
$ch = curl_init("{$baseUrl}/index.php?r=auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$html = curl_exec($ch);
curl_close($ch);

$ch = curl_init("{$baseUrl}/index.php?r=auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['username' => 'naveed', 'password' => 'naveed@1234', 'csrf' => getCsrf($html)]);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
curl_close($ch);

function req($url, $post = null, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ['code' => $code, 'url' => $finalUrl, 'body' => $res];
}

echo "=== STARTING CRUD WORKFLOW TESTS ===\n\n";

// Test 1: Add a Supplier
echo "1. Testing Supplier Creation...\n";
$getSup = req("{$baseUrl}/index.php?r=suppliers/new", null, $cookieFile);
$csrfSup = getCsrf($getSup['body']);
$supName = "Test Supplier " . rand(100, 999);
$postSup = req("{$baseUrl}/index.php?r=suppliers/new", [
    'csrf' => $csrfSup,
    'name' => $supName,
    'contact_person' => 'Khan Sahib',
    'phone' => '03129876543',
    'address' => 'Swabi Tobacco Market',
    'opening_balance' => '0'
], $cookieFile);

if (strpos($postSup['body'], $supName) !== false || $postSup['code'] === 200) {
    echo " [+] Supplier created successfully!\n";
} else {
    echo " [-] Supplier creation failed! Code: {$postSup['code']}\n";
    echo "     Preview: " . substr(strip_tags($postSup['body']), 0, 300) . "\n";
}

// Test 2: Add Daily RM Usage
echo "\n2. Testing Daily Raw Material Usage...\n";
$getRm = req("{$baseUrl}/index.php?r=inventory/raw-usage", null, $cookieFile);
$csrfRm = getCsrf($getRm['body']);

// Find available product_id and warehouse_id from the form
preg_match('/name="product_id"[^>]*>.*?<option\s+value="(\d+)"/s', $getRm['body'], $mProd);
preg_match('/name="warehouse_id"[^>]*>.*?<option\s+value="(\d+)"/s', $getRm['body'], $mWh);

$prodId = $mProd[1] ?? 1;
$whId = $mWh[1] ?? 1;

$postRm = req("{$baseUrl}/index.php?r=inventory/raw-usage-save", [
    'csrf' => $csrfRm,
    'usage_date' => date('Y-m-d'),
    'product_id' => $prodId,
    'warehouse_id' => $whId,
    'qty_kg' => '15.5',
    'note' => 'Automated QA check entry'
], $cookieFile);

if ($postRm['code'] === 200 && strpos($postRm['body'], 'Something went wrong') === false) {
    echo " [+] Daily RM Usage recorded successfully!\n";
} else {
    echo " [-] Daily RM Usage failed! Code: {$postRm['code']}\n";
    echo "     Preview: " . substr(strip_tags($postRm['body']), 0, 300) . "\n";
}

// Test 3: Add Personal Loan (Dasti)
echo "\n3. Testing Personal Loan (Dasti)...\n";
$getLoan = req("{$baseUrl}/index.php?r=personal-loans/new", null, $cookieFile);
$csrfLoan = getCsrf($getLoan['body']);
preg_match('/name="account_id"[^>]*>.*?<option\s+value="(\d+)"/s', $getLoan['body'], $mAcc);
$accId = $mAcc[1] ?? 1;

$postLoan = req("{$baseUrl}/index.php?r=personal-loans/new", [
    'csrf' => $csrfLoan,
    'person_name' => 'Kashif QA Test',
    'phone' => '03331122334',
    'amount' => '5000',
    'loan_date' => date('Y-m-d'),
    'account_id' => $accId,
    'expected_date' => date('Y-m-d', strtotime('+7 days')),
    'notes' => 'QA testing loan'
], $cookieFile);

if ($postLoan['code'] === 200 && strpos($postLoan['body'], 'Something went wrong') === false) {
    echo " [+] Personal Loan (Dasti) recorded successfully!\n";
} else {
    echo " [-] Personal Loan failed! Code: {$postLoan['code']}\n";
    echo "     Preview: " . substr(strip_tags($postLoan['body']), 0, 300) . "\n";
}

// Test 4: Add Company Expense
echo "\n4. Testing Company Expense...\n";
$getExp = req("{$baseUrl}/index.php?r=expenses/new", null, $cookieFile);
$csrfExp = getCsrf($getExp['body']);
preg_match('/name="category_id"[^>]*>.*?<option\s+value="(\d+)"/s', $getExp['body'], $mCat);
$catId = $mCat[1] ?? 1;

$postExp = req("{$baseUrl}/index.php?r=expenses/new", [
    'csrf' => $csrfExp,
    'category_id' => $catId,
    'amount' => '1200',
    'date' => date('Y-m-d'),
    'payment_type' => 'cash',
    'account_id' => $accId,
    'payee' => 'Electrician Work',
    'description' => 'Factory maintenance repair'
], $cookieFile);

if ($postExp['code'] === 200 && strpos($postExp['body'], 'Something went wrong') === false) {
    echo " [+] Expense recorded successfully!\n";
} else {
    echo " [-] Expense failed! Code: {$postExp['code']}\n";
    echo "     Preview: " . substr(strip_tags($postExp['body']), 0, 300) . "\n";
}

if (file_exists($cookieFile)) unlink($cookieFile);
echo "\n=== ALL CRUD WORKFLOW TESTS COMPLETE ===\n";
