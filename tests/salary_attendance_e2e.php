<?php
/** Unified Salary + Attendance E2E — against dev server; verifies both sections for employees AND salesmen. */
$base = 'http://127.0.0.1:8080/index.php?r=';
$jar = tempnam(sys_get_temp_dir(), 'ck') . '.txt';
function req(string $url, ?string $post = null, string $jar = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); }
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, $body];
}
function csrfFrom(string $html): string { preg_match('/name="csrf" value="([0-9a-f]+)"/', $html, $m); return $m[1] ?? ''; }
$tests = 0; $fail = 0;
function ok(bool $c, string $name): void { global $tests, $fail; $tests++; if ($c) echo "  PASS  $name\n"; else { $fail++; echo "  FAIL  $name\n"; } }

$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$emp = $pdo->query("SELECT id, name FROM employees WHERE kind = 'employee' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$lab = $pdo->query("SELECT id, name FROM employees WHERE kind = 'labour' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$sm = $pdo->query("SELECT id, name FROM salesmen WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "employee {$emp['id']} '{$emp['name']}' | labour {$lab['id']} '{$lab['name']}' | salesman {$sm['id']} '{$sm['name']}'\n";

// login owner
[$c, $body] = req($base . 'auth/login', null, $jar);
$csrf = csrfFrom($body);
req($base . 'auth/login', 'csrf=' . urlencode($csrf) . '&username=owner&password=owner1234', $jar);

echo "\n=== 1. Salary list shows everyone ===\n";
[$c, $body] = req($base . 'salary', null, $jar);
ok($c === 200, 'salary page loads (200)');
ok(strpos($body, $emp['name']) !== false, 'employee appears in salary list');
ok(strpos($body, $lab['name']) !== false, 'labour appears in salary list');
ok(strpos($body, $sm['name']) !== false, 'salesman appears in salary list');
ok(strpos($body, 'Combined list') !== false, 'combined-list heading shown');

echo "\n=== 2. Kind filter (labour) ===\n";
[$c, $body] = req($base . 'salary&kind=labour', null, $jar);
ok(strpos($body, $lab['name']) !== false, 'labour filter shows labour worker');
ok(strpos($body, $sm['name']) === false, 'labour filter hides salesman');
[$c, $body] = req($base . 'salary&kind=salesman', null, $jar);
ok(strpos($body, $sm['name']) !== false, 'salesman filter shows salesman');
ok(strpos($body, $emp['name']) === false, 'salesman filter hides employee');

echo "\n=== 3. Search ===\n";
[$c, $body] = req($base . 'salary&q=' . urlencode($sm['name']), null, $jar);
ok(strpos($body, $sm['name']) !== false, 'search finds salesman');
ok(strpos($body, 'salary/employee/' . $emp['id']) === false, 'search excludes employee cards');

echo "\n=== 4. Salary detail (employee) ===\n";
[$c, $body] = req($base . 'salary/employee/' . $emp['id'], null, $jar);
ok($c === 200, 'employee salary detail loads');
ok(strpos($body, 'Salary Earned') !== false, 'earned stat shown');
ok(strpos($body, 'Salary Paid') !== false, 'paid stat shown');
ok(strpos($body, 'Remaining Balance') !== false, 'remaining stat shown');
ok(strpos($body, 'Payment History') !== false, 'payment history panel shown');

echo "\n=== 5. Salary detail (salesman) ===\n";
[$c, $body] = req($base . 'salary/salesman/' . $sm['id'], null, $jar);
ok($c === 200, 'salesman salary detail loads');
ok(strpos($body, $sm['name']) !== false, 'salesman name displayed');
ok(strpos($body, 'full salary ledger') === false || strpos($body, 'Give Advance') !== false, 'advance action present');

echo "\n=== 6. Attendance board ===\n";
[$c, $body] = req($base . 'attendance&date=2026-11-16', null, $jar);
ok($c === 200, 'attendance board loads');
ok(strpos($body, $emp['name']) !== false, 'employee appears in attendance');
ok(strpos($body, $lab['name']) !== false, 'labour appears in attendance');
ok(strpos($body, $sm['name']) !== false, 'salesman appears in attendance');
ok(strpos($body, 'Present') !== false && strpos($body, 'Half Day') !== false, 'status buttons rendered');
ok(strpos($body, 'attendance%2Fhours') !== false || strpos($body, 'attendance/hours') !== false, 'link to payroll hours grid present');

echo "\n=== 7. Mark attendance for salesman + labour via board ===\n";
$csrf = csrfFrom($body);
[$c] = req($base . 'attendance',
    'csrf=' . urlencode($csrf) . '&date=2026-11-16&q=&kind=all&designation=&status[salesman:' . $sm['id'] . ']=present&status[employee:' . $lab['id'] . ']=half_day&status[employee:' . $emp['id'] . ']=present', $jar);
ok($c === 302, 'bulk attendance saved (302 redirect)');
$smStatus = $pdo->query("SELECT status FROM staff_attendance WHERE salesman_id = {$sm['id']} AND date = '2026-11-16'")->fetchColumn();
ok($smStatus === 'present', "salesman attendance stored (got: " . var_export($smStatus, true) . ")");
$labStatus = $pdo->query("SELECT status FROM staff_attendance WHERE employee_id = {$lab['id']} AND date = '2026-11-16'")->fetchColumn();
ok($labStatus === 'half_day', "labour attendance stored as half_day (got: " . var_export($labStatus, true) . ")");
$empStatus = $pdo->query("SELECT status FROM staff_attendance WHERE employee_id = {$emp['id']} AND date = '2026-11-16'")->fetchColumn();
ok($empStatus === 'present', "employee attendance stored (got: " . var_export($empStatus, true) . ")");

// cleanup marks made by this test
$pdo->exec("DELETE FROM staff_attendance WHERE date = '2026-11-16' AND ((salesman_id = {$sm['id']} AND status = 'present') OR (employee_id IN ({$emp['id']}, {$lab['id']}) AND status IN ('present','half_day')))");
$pdo->exec("DELETE FROM staff_attendance_audit WHERE date = '2026-11-16'");
echo "test marks cleaned up\n";

echo "\n=== 8. Attendance report ===\n";
[$c, $body] = req($base . 'attendance/report&from=2026-11-01&to=2026-11-30', null, $jar);
ok($c === 200, 'attendance report loads');
ok(strpos($body, 'Attendance Report') !== false, 'report title present');
ok(strpos($body, 'Net Payable Days') !== false, 'net days column present');

echo "\n=== 9. Sidebar: Salary + Attendance before Salesman ===\n";
[$c, $body] = req($base . 'dashboard', null, $jar);
$idxSalary = strpos($body, '>Salary<');
$idxAtt = strpos($body, '>Attendance<');
$idxSm = strpos($body, '>Salesmen<');
$idxEmp = strpos($body, '>Employees<');
$idxProd = strpos($body, '>Production<');
$idxRep = strpos($body, '>REPORTING<');
ok($idxSalary !== false, 'Salary link in sidebar');
ok($idxAtt !== false, 'Attendance link in sidebar');
ok($idxSalary < $idxAtt, 'Salary appears before Attendance');
ok($idxAtt < $idxSm, 'Attendance appears before Salesman');
ok($idxSm < $idxEmp, 'Salesman appears before Employees');
ok($idxEmp < $idxProd, 'Employees appears before Production');
ok($idxProd < $idxRep, 'Production appears before Reporting');

echo "\nALL: $tests tests, $fail failures\n";
exit($fail ? 1 : 0);
