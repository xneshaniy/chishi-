<?php
/** Actions E2E: create a throwaway employee, mark attendance (present/half-day), set salary, add deduction, verify, clean up. */
$base = 'http://127.0.0.1:8080/index.php?r=';
$jar = tempnam(sys_get_temp_dir(), 'ck');

function req(string $url, ?string $post = null, string $jar = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); }
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$code, $body];
}
function csrfFrom(string $html): string { preg_match('/name="csrf" value="([^"]+)"/', $html, $m); return $m[1] ?? ''; }
$tests = 0; $fail = 0;
function ok(bool $c, string $name): void { global $tests, $fail; $tests++; if ($c) echo "  PASS  $name\n"; else { $fail++; echo "  FAIL  $name\n"; } }

$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
echo "existing employees:\n";
foreach ($pdo->query('SELECT id, code, name FROM employees ORDER BY id') as $r) echo "  {$r['id']} | {$r['code']} | {$r['name']}\n";

/* ---- login owner ---- */
[$c, $body] = req($base . 'auth/login', null, $jar);
$csrf = csrfFrom($body);
req($base . 'auth/login', 'csrf=' . urlencode($csrf) . '&username=owner&password=owner1234', $jar);

echo "\n=== 1. Create throwaway employee ===\n";
[$c, $body] = req($base . 'employees/new', null, $jar);
$csrf = csrfFrom($body);
ok($c === 200 && $csrf !== '', 'add employee form loads');
$empName = 'TEST Actions Employee';
[$c, $body] = req($base . 'employees/new',
    'csrf=' . urlencode($csrf) . '&name=' . urlencode($empName) . '&cnic=00000-0000000-0&phone=03000000000&address=Test Address&designation_custom=Cleaning Staff&designation=Other&department=Testing&join_date=2026-11-01&salary_type=monthly&salary_rate=10000&payment_method=cash', $jar);
ok($c === 302, 'employee created (redirect)');
$empId = (int)$pdo->query("SELECT id FROM employees WHERE name = '$empName' ORDER BY id DESC LIMIT 1")->fetchColumn();
ok($empId > 0, "new employee id: $empId");
$code = $empId ? $pdo->query("SELECT code FROM employees WHERE id = $empId")->fetchColumn() : '';
$desig = $empId ? $pdo->query("SELECT designation FROM employees WHERE id = $empId")->fetchColumn() : '';
ok($desig === 'Cleaning Staff', "custom job title stored (got: $desig)");
ok(strpos($code, 'EMP-') === 0, "auto code generated: $code");

/* ---- 2. Attendance mark present, then half_day (same day, must update not duplicate) ---- */
echo "\n=== 2. Attendance ===\n";
$today = date('Y-m-d');
[$c, $body] = req($base . 'employees/' . $empId . '&tab=attendance', null, $jar);
$csrf = csrfFrom($body);
ok($csrf !== '', 'attendance tab loads');
[$c] = req($base . 'employees/' . $empId . '/attendance/mark',
    'csrf=' . urlencode($csrf) . '&employee_id=' . $empId . '&date=' . $today . '&status=present&note=', $jar);
ok($c === 302, 'marked present');
[$c] = req($base . 'employees/' . $empId . '/attendance/mark',
    'csrf=' . urlencode($csrf) . '&employee_id=' . $empId . '&date=' . $today . '&status=half_day&note=', $jar);
ok($c === 302, 'same-day correction to half_day');
$n = (int)$pdo->query("SELECT COUNT(*) FROM staff_attendance WHERE employee_id = $empId AND date = '$today'")->fetchColumn();
ok($n === 1, 'one attendance row for the day (correction, no duplicate)');
$st = $pdo->query("SELECT status FROM staff_attendance WHERE employee_id = $empId AND date = '$today'")->fetchColumn();
ok($st === 'half_day', "status corrected to half_day (got: $st)");

/* ---- 3. Set salary with effective date ---- */
echo "\n=== 3. Salary ===\n";
[$c, $body] = req($base . 'employees/' . $empId . '&tab=salary', null, $jar);
$csrf = csrfFrom($body);
[$c] = req($base . 'employees/' . $empId . '/salary/set',
    'csrf=' . urlencode($csrf) . '&employee_id=' . $empId . '&salary_type=monthly&salary_rate=15000&effective_date=2026-11-01&note=E2E test increment', $jar);
ok($c === 302, 'salary set');
$rate = (float)$pdo->query("SELECT salary_rate FROM employees WHERE id = $empId")->fetchColumn();
ok(abs($rate - 15000) < 0.01, "salary_rate updated to 15000 (got: $rate)");
$chg = (int)$pdo->query("SELECT COUNT(*) FROM salary_changes WHERE employee_id = $empId AND new_rate = 15000 AND effective_date = '2026-11-01'")->fetchColumn();
ok($chg === 1, 'salary change history recorded');
[$c, $body] = req($base . 'employees/' . $empId . '&tab=salary', null, $jar);
ok($c === 200 && strpos($body, '15,000') !== false, 'salary tab shows new rate');

/* ---- 4. Deduction ---- */
echo "\n=== 4. Deduction ===\n";
[$c, $body] = req($base . 'employees/' . $empId . '&tab=deductions', null, $jar);
$csrf = csrfFrom($body);
[$c] = req($base . 'employees/' . $empId . '/deduction/add',
    'csrf=' . urlencode($csrf) . '&employee_id=' . $empId . '&amount=500&reason=' . urlencode('E2E test deduction') . '&date=' . $today, $jar);
ok($c === 302, 'deduction recorded');
$ded = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM employee_deductions WHERE employee_id = $empId")->fetchColumn();
ok(abs($ded - 500) < 0.01, "deduction stored (got: $ded)");
[$c, $body] = req($base . 'employees/' . $empId . '&tab=deductions', null, $jar);
ok($c === 200 && strpos($body, 'E2E test deduction') !== false, 'deduction visible in history');

/* ---- 5. Read back biodata ---- */
echo "\n=== 5. Profile read-back ===\n";
[$c, $body] = req($base . 'employees/' . $empId, null, $jar);
ok($c === 200 && strpos($body, $empName) !== false, 'biodata shows the new employee');
ok(strpos($body, 'Cleaning Staff') !== false, 'job title shown in biodata');

/* ---- 6. Cleanup (throwaway employee + its attendance/deduction/salary_changes) ---- */
echo "\n=== 6. Cleanup ===\n";
$pdo->exec("DELETE FROM staff_attendance WHERE employee_id = $empId");
$pdo->exec("DELETE FROM staff_attendance_audit WHERE employee_id = $empId");
$pdo->exec("DELETE FROM employee_deductions WHERE employee_id = $empId");
$pdo->exec("DELETE FROM salary_changes WHERE employee_id = $empId");
$pdo->exec("DELETE FROM employees WHERE id = $empId");
echo "throwaway employee and all related rows deleted\n";

echo "\nEMPLOYEES ACTIONS E2E: $tests tests, $fail failures\n";
exit($fail ? 1 : 0);
