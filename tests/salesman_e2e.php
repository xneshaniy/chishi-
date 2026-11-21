<?php
/** E2E: salesman salary/expense/settlement flow as owner. */
function http($method, $url, $jar, $body = null): array {
    $cmd = 'curl.exe -s -i -c ' . escapeshellarg($jar) . ' -b ' . escapeshellarg($jar) . ' -X ' . strtoupper($method);
    if (is_array($body)) foreach ($body as $k => $v) $cmd .= ' --data-urlencode ' . escapeshellarg($k . '=' . $v);
    $tmpFile = tempnam(sys_get_temp_dir(), 'resp');
    $cmd .= ' -o ' . escapeshellarg($tmpFile) . ' ' . escapeshellarg('http://127.0.0.1:8080' . $url);
    shell_exec($cmd);
    $out = is_file($tmpFile) ? (string)file_get_contents($tmpFile) : '';
    @unlink($tmpFile);
    $pos = strpos($out, "\r\n\r\n");
    if ($pos === false) { $pos = strpos($out, "\n\n"); }
    $headers = $pos === false ? $out : substr($out, 0, $pos);
    $respBody = $pos === false ? '' : substr($out, $pos + 2);
    preg_match('/(\d{3})/', $headers, $m);
    return ['status' => (int)($m[1] ?? 0), 'body' => $respBody];
}
function login(string $user, string $pass): string {
    $jar = tempnam(sys_get_temp_dir(), 'jar');
    $login = http('GET', '/index.php?r=auth/login', $jar);
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $m);
    $lr = http('POST', '/index.php?r=auth/login', $jar, ['csrf' => $m[1], 'username' => $user, 'password' => $pass]);
    if ($lr['status'] !== 302) { echo "LOGIN FAILED for $user (status {$lr['status']})\n"; echo substr($lr['body'], 0, 400); exit(1); }
    return $jar;
}
$pdo = new PDO('sqlite:storage/dev.sqlite');
$owner = login('owner', 'owner1234');
// 1. Set salesman profile fields
$form = http('GET', '/index.php?r=salesmen/2/edit', $owner);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $form['body'], $m);
$save = http('POST', '/index.php?r=salesmen/2/edit', $owner, ['csrf' => $m[1], 'code' => 'DEMO-SM-001', 'name' => 'DEMO Salesman One', 'phone' => '0302-7778899', 'cnic' => '', 'area' => 'Main City', 'address' => '', 'commission_percent' => '2.5', 'salary' => '45000', 'join_date' => '2026-10-01', 'vehicle_type' => 'Rickshaw', 'vehicle_reg' => 'LEA-1234', 'vehicle_note' => 'company', 'is_active' => '1']);
echo "save salesman: {$save['status']}\n";
// 2. Advance 5,000
$form = http('GET', '/index.php?r=salesmen/2/salary', $owner);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $form['body'], $m);
$bankId = (int)$pdo->query("SELECT id FROM cash_bank_accounts WHERE type='bank' LIMIT 1")->fetchColumn();
$adv = http('POST', '/index.php?r=salesmen/2/salary', $owner, ['csrf' => $m[1], 'action' => 'advance', 'amount' => '5000', 'account_id' => $bankId, 'method' => 'bank', 'period' => '2026-11', 'notes' => 'E2E advance']);
echo "advance POST: {$adv['status']}\n";
echo "advances paid: ", $pdo->query("SELECT COUNT(*) FROM salesman_salary_advances WHERE salesman_id = 2 AND status = 'paid'")->fetchColumn(), "\n";
// 3. Pay salary 45,000
$form = http('GET', '/index.php?r=salesmen/2/salary', $owner);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $form['body'], $m);
$sal = http('POST', '/index.php?r=salesmen/2/salary', $owner, ['csrf' => $m[1], 'action' => 'pay', 'amount' => '45000', 'account_id' => $bankId, 'method' => 'bank', 'period' => '2026-11', 'notes' => 'Nov salary']);
echo "salary POST: {$sal['status']}\n";
echo "salary payments paid: ", $pdo->query("SELECT COUNT(*) FROM salesman_salary_payments WHERE salesman_id = 2 AND status = 'paid'")->fetchColumn(), "\n";
// 4. Diesel expense 1,500
$form = http('GET', '/index.php?r=salesmen/2/expense', $owner);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $form['body'], $m);
$exp = http('POST', '/index.php?r=salesmen/2/expense', $owner, ['csrf' => $m[1], 'expense_type' => 'diesel', 'amount' => '1500', 'account_id' => $bankId, 'payment_type' => 'bank', 'description' => 'diesel for rickshaw', 'notes' => 'e2e']);
echo "expense POST: {$exp['status']}\n";
// 5. Settlement page
$settle = http('GET', '/index.php?r=salesmen/2/settlement', $owner);
echo "settlement page: {$settle['status']}\n";
echo "diesel posted: ", $pdo->query("SELECT COUNT(*) FROM salesman_expenses WHERE salesman_id = 2 AND expense_type = 'diesel' AND status = 'posted'")->fetchColumn(), "\n";
echo "salary journal: ", $pdo->query("SELECT COUNT(*) FROM salesman_salary_payments WHERE salesman_id = 2 AND journal_entry_id IS NOT NULL")->fetchColumn(), "\n";
echo "advance journal: ", $pdo->query("SELECT COUNT(*) FROM salesman_salary_advances WHERE salesman_id = 2 AND journal_entry_id IS NOT NULL")->fetchColumn(), "\n";
unlink($owner);
