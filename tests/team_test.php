<?php
/** End-to-end test of /admin/team create (with photo) via HTTP. */
// Fixture cleanup: this suite is not idempotent by itself (it increments salary to 60000 at the end).
// Remove any prior testaccttwo users + their employees/uploads so the suite passes on every fresh run.
(function () {
    try {
        $pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
        $pdo->exec('PRAGMA foreign_keys = ON');
        foreach ($pdo->query("SELECT id FROM users WHERE username = 'testaccttwo'")->fetchAll(PDO::FETCH_ASSOC) as $u) {
            foreach ($pdo->query("SELECT id FROM employees WHERE user_id = {$u['id']}")->fetchAll(PDO::FETCH_ASSOC) as $e) {
                $dir = __DIR__ . '/../storage/uploads/employees/' . (int)$e['id'];
                if (is_dir($dir)) { foreach (glob($dir . '/*') as $f) @unlink($f); @rmdir($dir); }
                $pdo->exec("DELETE FROM employees WHERE id = {$e['id']}");
            }
            $pdo->exec("DELETE FROM user_roles WHERE user_id = {$u['id']}");
            $pdo->exec("DELETE FROM users WHERE id = {$u['id']}");
        }
    } catch (Throwable $e) { /* stale fixture is best-effort; the suite will surface real failures */ }
})();
$base = 'http://127.0.0.1:8080';
$jar = tempnam(sys_get_temp_dir(), 'ck') . '.txt';
$tests = 0; $failures = 0;
function ok(bool $cond, string $name): void {
    global $tests, $failures; $tests++;
    if ($cond) echo "  PASS  $name\n"; else { $failures++; echo "  FAIL  $name\n"; }
}
function req(string $method, string $url, ?array $post = null, ?array $files = null): array {
    global $jar, $base;
    $ch = curl_init($base . $url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30];
    curl_setopt_array($ch, $opts);
    if ($files) {
        $fields = $post ?? [];
        foreach ($files as $k => $f) { $fields[$k] = new CURLFile($f[0], $f[1], $f[2]); }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    } elseif ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$code, substr((string)$raw, 0, $hs), substr((string)$raw, $hs)];
}
function csrfFrom(string $html): string { preg_match('/name="csrf" value="([0-9a-f]+)"/', $html, $m); return $m[1] ?? ''; }

[$c, , $loginHtml] = req('GET', '/index.php?r=auth/login');
[$c2] = req('POST', '/index.php?r=auth/login', ['r' => 'auth/login', 'csrf' => csrfFrom($loginHtml), 'username' => 'owner', 'password' => 'owner1234']);
ok($c2 === 302, 'owner login (302)');

$img = 'C:/Users/Haseeb/AppData/Local/Temp/opencode/test_red.png';

echo "\n=== 1. Create accountant via form (with photo) ===\n";
[$c, , $form] = req('GET', '/index.php?r=admin/team/new');
ok($c === 200, 'create form loads');
[$c, , $body] = req('POST', '/index.php?r=admin/team/new/post',
    ['r' => 'admin/team/new/post', 'csrf' => csrfFrom($form), 'username' => 'testaccttwo', 'full_name' => 'TEST Accountant Two', 'role' => 'accountant',
     'email' => 'testacct2@example.com', 'phone' => '03009990002', 'cnic' => '35202-9999999-2',
     'designation' => 'Deputy Accountant', 'department' => 'Accounts', 'join_date' => '2026-11-10',
     'salary_type' => 'monthly', 'salary_rate' => 30000, 'payment_method' => 'bank', 'branch_id' => '', 'password' => 'TestAcct2@1234'],
    ['photo' => [$img, 'image/png', 'red.png']]);
ok($c === 302, 'create posted and redirected');
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$row = $pdo->query("SELECT e.id, e.code, e.photo_path, u.username, u.is_active AS user_active, e.salary_rate, e.salary_type FROM employees e JOIN users u ON u.id = e.user_id WHERE u.username = 'testaccttwo'")->fetch(PDO::FETCH_ASSOC);
ok((bool)$row, 'linked user+employee row created');
ok($row && $row['photo_path'] !== null && is_file(__DIR__ . '/../storage/uploads/' . $row['photo_path']), 'photo stored at ' . var_export($row['photo_path'] ?? null, true));
ok($row && abs((float)$row['salary_rate'] - 30000) < 0.01, 'salary rate stored');
ok($row && $row['salary_type'] === 'monthly', 'salary type stored');

echo "\n=== 2. List page renders the new profile ===\n";
[$c, , $body] = req('GET', '/index.php?r=admin/team');
ok(strpos($body, 'TEST Accountant Two') !== false, 'new profile appears in team list');
ok(strpos($body, 'ACCOUNTANT') !== false, 'accountant role badge appears in team list');
ok(strpos($body, 'data:image/') !== false, 'photo renders inline (data URI) in list');

echo "\n=== 3. Profile detail page loads with KPIs ===\n";
$empId = (int)($row['id'] ?? 0);
[$c, , $body] = req('GET', '/index.php?r=admin/team/' . $empId);
ok($c === 200, 'profile page loads');
ok(strpos($body, 'TEST Accountant Two') !== false, 'profile shows name');
ok(strpos($body, 'Total Earned') !== false, 'profile shows salary KPIs');
ok(strpos($body, 'Salary Ledger') !== false, 'profile shows ledger link');

echo "\n=== 4. Edit: update salary & title ===\n";
[$c, , $editForm] = req('GET', '/index.php?r=admin/team/' . $empId . '/edit');
ok($c === 200, 'edit form loads');
[$c] = req('POST', '/index.php?r=admin/team/' . $empId . '/edit/post',
    ['r' => 'admin/team/' . $empId . '/edit/post', 'id' => (string)$empId, 'csrf' => csrfFrom($editForm), 'full_name' => 'TEST Accountant Two', 'role' => 'accountant',
     'email' => 'testacct2@example.com', 'phone' => '03009990002', 'cnic' => '35202-9999999-2',
     'designation' => 'Chief Accountant', 'department' => 'Accounts', 'join_date' => '2026-11-10',
     'salary_type' => 'daily', 'salary_rate' => 1500, 'payment_method' => 'cash', 'address' => 'Test Address', 'is_active' => '1']);
ok($c === 302, 'edit posted');
$row = $pdo->query("SELECT designation, salary_type, salary_rate, payment_method FROM employees WHERE id = $empId")->fetch(PDO::FETCH_ASSOC);
ok($row['designation'] === 'Chief Accountant', 'designation updated');
ok($row['salary_type'] === 'daily' && abs((float)$row['salary_rate'] - 1500) < 0.01, 'salary updated with effective currency');

echo "\n=== 5. Toggle deactivate/reactivate ===\n";
[$c, , $profileHtml] = req('GET', '/index.php?r=admin/team/' . $empId);
$csrfT = csrfFrom($profileHtml);
[$c] = req('POST', '/index.php?r=admin/team/' . $empId . '/toggle', ['r' => 'admin/team/' . $empId . '/toggle', 'id' => (string)$empId, 'csrf' => $csrfT]);
ok($c === 302, 'toggle posted');
ok((int)$pdo->query("SELECT is_active FROM users WHERE username='testaccttwo'")->fetchColumn() === 0, 'user deactivated');
ok((int)$pdo->query("SELECT is_active FROM employees WHERE id = $empId")->fetchColumn() === 0, 'employee deactivated');
[$c] = req('POST', '/index.php?r=admin/team/' . $empId . '/toggle', ['r' => 'admin/team/' . $empId . '/toggle', 'id' => (string)$empId, 'csrf' => $csrfT]);
ok((int)$pdo->query("SELECT is_active FROM users WHERE username='testaccttwo'")->fetchColumn() === 1, 'user reactivated');
ok((int)$pdo->query("SELECT is_active FROM employees WHERE id = $empId")->fetchColumn() === 1, 'employee reactivated');

echo "\n=== 6. Permission gate: manager cannot open /admin/team ===\n";
// login as manager1 in a separate cookie jar
$jar2 = tempnam(sys_get_temp_dir(), 'ck2') . '.txt';
$ch = curl_init($base . '/index.php?r=auth/login');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar2, CURLOPT_COOKIEFILE => $jar2, CURLOPT_FOLLOWLOCATION => false]);
$loginHtml2 = curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $c1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
preg_match('/name="csrf" value="([0-9a-f]+)"/', substr($loginHtml2, $hs), $mct);
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => http_build_query(['r' => 'auth/login', 'csrf' => $mct[1], 'username' => 'manager1', 'password' => 'Manager@1234x']), CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']]);
curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
$ch = curl_init($base . '/index.php?r=admin/team');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar2, CURLOPT_COOKIEFILE => $jar2, CURLOPT_FOLLOWLOCATION => false]);
$body2 = curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$location = (preg_match('/Location: ([^\r\n]+)/i', $body2, $mm) ? $mm[1] : '');
echo "manager GET /admin/team => $code", ($location ? " -> $location" : ''), "\n";
ok($code === 302 || $code === 403 || strpos($body2, 'Admin area') !== false, 'manager blocked from /admin/team');

echo "\n=== 7. Salary set with effective date writes history ===\n";
[$c, , $profileHtml] = req('GET', '/index.php?r=admin/team/' . $empId);
$csrfS = csrfFrom($profileHtml);
[$c] = req('POST', '/index.php?r=admin/team/' . $empId . '/salary', ['r' => 'admin/team/' . $empId . '/salary', 'id' => (string)$empId, 'csrf' => $csrfS, 'salary_type' => 'monthly', 'salary_rate' => '60000', 'effective_date' => '2026-12-01', 'note' => 'increment test']);
ok($c === 302, 'salary set posted');
$row = $pdo->query("SELECT salary_type, salary_rate FROM employees WHERE id = $empId")->fetch(PDO::FETCH_ASSOC);
ok($row && abs((float)$row['salary_rate'] - 60000) < 0.01 && $row['salary_type'] === 'monthly', 'current salary updated');
$sc = $pdo->query("SELECT * FROM salary_changes WHERE employee_id = $empId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok($sc && abs((float)$sc['new_rate'] - 60000) < 0.01 && $sc['effective_date'] === '2026-12-01', 'salary_changes history row stored with effective date');

echo "\n========================================\nTEAM E2E RESULTS: $tests tests, $failures failures\n";
exit($failures ? 1 : 0);
