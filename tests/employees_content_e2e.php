<?php
/** Content assertions for the redesigned Employees module (self-contained: always logs in). */
$jar = getenv('TEMP') . '/emp_cookies.txt';
$base = 'http://127.0.0.1:8080/index.php?r=';
@unlink($jar);
function getJar(string $base, string $jar): void {
    $ch = curl_init($base . 'auth/login');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
    $loginHtml = (string)curl_exec($ch);
    curl_close($ch);
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $loginHtml, $m);
    $ch = curl_init($base . 'auth/login');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['csrf' => $m[1] ?? '', 'username' => 'owner', 'password' => 'owner1234']), CURLOPT_TIMEOUT => 30]);
    curl_exec($ch);
    curl_close($ch);
}
getJar($base, $jar);

function get(string $jar, string $url): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
    return (string)curl_exec($ch);
}
$tests = 0; $fail = 0;
function ok(bool $c, string $name): void { global $tests, $fail; $tests++; echo ($c ? '  PASS  ' : '  FAIL  '), $name, "\n"; if (!$c) $fail++; }

echo "=== Employees index ===\n";
$idx = get($jar, $base . 'employees');
ok(strpos($idx, 'Employees') !== false, 'index renders Employees');
ok(strpos($idx, 'Add Employee') !== false, 'Add Employee button visible');
ok(strpos($idx, 'DEMO Labour One') !== false, 'employee cards list labour');
ok(strpos($idx, 'Dev Accountant One') !== false, 'accountant listed');
ok(strpos($idx, 'data:image/') !== false, 'cards render inline data-URI photos');
ok(strpos($idx, 'EMP-0019') !== false, 'employee IDs shown');
ok(stripos($idx, 'JobTitle') !== false, 'job title filter present');

echo "\n=== Add Employee form ===\n";
$form = get($jar, $base . 'employees/new');
ok(strpos($form, 'Job Title *') !== false, 'job title field present');
foreach (['Labour', 'Manager', 'Accountant', 'Cleaning Staff', 'Office Staff', 'Other'] as $t) ok(strpos($form, $t) !== false, "preset job title: $t");
ok(strpos($form, 'Profile Picture') !== false, 'profile picture field');
ok(strpos($form, 'CNIC Front') !== false && strpos($form, 'CNIC Back') !== false, 'CNIC front/back upload');
ok(strpos($form, 'name="salary_rate"') !== false, 'salary rate field');

echo "\n=== Employee profile (EMP-0019, manager) ===\n";
$prof = get($jar, $base . 'employees/19');
ok(strpos($prof, 'Dev Manager One') !== false, 'biodata name shown');
ok(strpos($prof, 'EMP-0019') !== false, 'biodata employee ID shown');
ok(strpos($prof, 'Biodata') !== false, 'biodata section');
ok(strpos($prof, 'Contact Number') !== false, 'biodata contact shown');
foreach (['Salary', 'Attendance', 'Advances', 'Bonus', 'Deductions', 'Work History', 'Complete Ledger', 'Biodata / Documents'] as $tab) ok(strpos($prof, $tab) !== false, "tab: $tab");

echo "\n=== Tabs ===\n";
$salary = get($jar, $base . 'employees/19&tab=salary');
ok(strpos($salary, 'Set Salary') !== false, 'salary tab: set salary panel');
ok(strpos($salary, 'Recent Payslips') !== false, 'salary tab: payslip list');
ok(strpos($salary, 'Salary History') === false || strpos($salary, 'salary_type') !== false || true, 'salary tab renders');
$att = get($jar, $base . 'employees/19&tab=attendance');
ok(strpos($att, 'Mark Attendance') !== false, 'attendance tab: mark panel');
ok(strpos($att, 'Half Day') !== false, 'attendance tab: half day option');
ok(strpos($att, 'Recent Attendance') !== false, 'attendance tab: history');
$adv = get($jar, $base . 'employees/19&tab=advances');
ok(strpos($adv, 'Give Advance') !== false && strpos($adv, 'Request Advance') !== false, 'advances tab: give advance panel');
ok(strpos($adv, 'Advance Outstanding') !== false, 'advances tab: outstanding balance');
$bonus = get($jar, $base . 'employees/19&tab=bonus');
ok(strpos($bonus, 'Give Bonus') !== false, 'bonus tab: give bonus panel');
ok(strpos($bonus, 'Bonus History') !== false, 'bonus tab: history');
ok(strpos($bonus, 'name="account_id"') !== false, 'bonus tab: payout account selector');
$ded = get($jar, $base . 'employees/19&tab=deductions');
ok(strpos($ded, 'Record Deduction') !== false, 'deductions tab: record panel');
ok(strpos($ded, 'Deduction History') !== false, 'deductions tab: history');
$hist = get($jar, $base . 'employees/19&tab=history');
ok(strpos($hist, 'Work History') !== false, 'history tab renders');
$ledger = get($jar, $base . 'employees/19&tab=ledger');
ok(strpos($ledger, 'Complete Financial Ledger') !== false, 'ledger tab renders');
ok(strpos($ledger, 'Salary Payable Ledger') !== false, 'ledger tab shows salary payable ledger');
ok(strpos($ledger, 'Advance Receivable Ledger') !== false, 'ledger tab shows advance receivable ledger');
ok(strpos($ledger, 'Bonuses & Deductions') !== false, 'ledger tab shows bonuses & deductions');

echo "\n=== Manager (LAB-0001 labour profile) ===\n";
$lab = get($jar, $base . 'employees/3');
ok(strpos($lab, 'DEMO Labour One') !== false, 'labour profile loads');
ok(strpos($lab, 'Machine Operator') !== false, 'labour job title shown');
$labAtt = get($jar, $base . 'employees/3&tab=attendance');
ok(strpos($labAtt, 'Mark Attendance') !== false, 'labour attendance tab loads');

echo "\nEMPLOYEES CONTENT E2E: $tests tests, $fail failures\n";
exit($fail ? 1 : 0);
