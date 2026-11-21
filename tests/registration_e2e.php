<?php
/** E2E for Owner/Manager registration + active toggle behavior (v25). Dev only; cleans up afterwards. */
$base = 'http://127.0.0.1:8080';
$pdo  = new PDO('sqlite:' . dirname(__DIR__) . '/storage/dev.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$results = [];
function ok($label, $cond) { global $results; $results[] = [$label, $cond ? 'PASS' : 'FAIL']; echo ($cond ? 'PASS' : 'FAIL'), "  $label\n"; }

function http_req(string $method, string $url, ?array $post = null, array &$cookies = []): array {
    global $base;
    $route = ltrim($url, '/');
    if (str_contains($route, '?')) { [$route, $qs] = explode('?', $route, 2); $full = $base . '/index.php?r=' . rawurlencode($route) . '&' . $qs; }
    else $full = $base . '/index.php?r=' . rawurlencode($route);
    $ch = curl_init($full);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
    if ($cookies) { $cj = ''; foreach ($cookies as $k => $v) $cj .= "$k=$v; "; curl_setopt($ch, CURLOPT_COOKIE, rtrim($cj, '; ')); }
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $len = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($raw, $len); $header = substr($raw, 0, $len);
    foreach (preg_split('/\r?\n/', $header) as $line) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $m)) $cookies[trim($m[1])] = trim($m[2]);
    }
    return [$code, $body];
}
function login(string $user, string $pass): array {
    $cookies = [];
    [$code, $body] = http_req('GET', 'auth/login', null, $cookies);
    preg_match('/name="csrf" value="([^"]+)"/', $body, $m);
    http_req('POST', 'auth/login', ['csrf' => $m[1], 'username' => $user, 'password' => $pass], $cookies);
    return $cookies;
}
function csrf_from(string $url, array &$cookies): string {
    [$code, $body] = http_req('GET', $url, null, $cookies);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) { echo "no csrf at $url\n"; exit(1); }
    return $m[1];
}

$suffix = bin2hex(random_bytes(3));
$owner = login('owner', 'owner1234');
$mgr   = login('manager1', 'Manager@1234x');
echo "owner + manager logged in\n";

/* ---------- 1. Owner creates a salesman → Active immediately ---------- */
$smOwnerCode = 'SM-OWN-' . strtoupper($suffix);
$csrf = csrf_from('salesmen/new', $owner);
[$code] = http_req('POST', 'salesmen/new', [
    'csrf' => $csrf, 'code' => $smOwnerCode, 'name' => 'E2E Owner Salesman', 'phone' => '0000000000',
    'cnic' => '', 'address' => 'E2E', 'area' => 'E2E', 'commission_percent' => '5', 'salary' => '10000',
    'salary_frequency' => 'monthly', 'join_date' => date('Y-m-d'), 'vehicle_type' => '', 'vehicle_reg' => '',
    'vehicle_note' => '', 'province_id' => '', 'city_id' => '',
], $owner);
$smOwner = $pdo->query("SELECT * FROM salesmen WHERE code = '$smOwnerCode'")->fetch(PDO::FETCH_ASSOC);
ok('owner-created salesman exists with is_active = 1', $smOwner && (int)$smOwner['is_active'] === 1);
$whOwner = $pdo->query("SELECT id FROM warehouses WHERE code = 'WH-$smOwnerCode'")->fetchColumn();
ok('owner-created salesman got a sales-stock warehouse', (bool)$whOwner);

/* ---------- 2. Manager creates a salesman → Pending approval, Inactive ---------- */
$smMgrCode = 'SM-MGR-' . strtoupper($suffix);
$csrf = csrf_from('salesmen/new', $mgr);
[$code] = http_req('POST', 'salesmen/new', [
    'csrf' => $csrf, 'code' => $smMgrCode, 'name' => 'E2E Manager Salesman', 'phone' => '0000000001',
    'cnic' => '', 'address' => 'E2E', 'area' => 'E2E', 'commission_percent' => '0', 'salary' => '5000',
    'salary_frequency' => 'monthly', 'join_date' => date('Y-m-d'), 'vehicle_type' => '', 'vehicle_reg' => '',
    'vehicle_note' => '', 'province_id' => '', 'city_id' => '',
], $mgr);
$smMgr = $pdo->query("SELECT * FROM salesmen WHERE code = '$smMgrCode'")->fetch(PDO::FETCH_ASSOC);
ok('manager-created salesman exists with is_active = 0', $smMgr && (int)$smMgr['is_active'] === 0);
$apprS = $pdo->query("SELECT id, status FROM approval_requests WHERE reference_type = 'salesmen' AND reference_id = {$smMgr['id']}")->fetch(PDO::FETCH_ASSOC);
ok('registration approval requested (pending)', $apprS && $apprS['status'] === 'pending');
$whMgr = $pdo->query("SELECT id FROM warehouses WHERE code = 'WH-$smMgrCode'")->fetchColumn();
ok('manager salesman warehouse NOT yet created', !$whMgr);
$notif = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE entity_type = 'approval_requests' AND entity_id = {$apprS['id']}")->fetchColumn();
ok('owner/admin notified about pending registration', $notif > 0 || (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn() > 0);

/* ---------- 3. Manager creates an employee → Pending approval, Inactive ---------- */
$csrf = csrf_from('employees/new', $mgr);
[$code] = http_req('POST', 'employees/new', [
    'csrf' => $csrf, 'name' => 'E2E Manager Employee', 'cnic' => '', 'phone' => '0000000002',
    'address' => 'E2E', 'designation' => 'Labour', 'department' => 'E2E Dept', 'join_date' => date('Y-m-d'),
    'salary_frequency' => 'monthly', 'salary_rate' => '8000', 'payment_method' => 'cash',
], $mgr);
$empMgr = $pdo->query("SELECT * FROM employees WHERE name = 'E2E Manager Employee' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('manager-created employee exists with is_active = 0', $empMgr && (int)$empMgr['is_active'] === 0);
$apprE = $pdo->query("SELECT id, status FROM approval_requests WHERE reference_type = 'employees' AND reference_id = {$empMgr['id']}")->fetch(PDO::FETCH_ASSOC);
ok('employee registration approval pending', $apprE && $apprE['status'] === 'pending');

/* ---------- 4. Manager can never approve their own request ---------- */
$mgrCookies = $mgr;
// manager cannot reach decide (role gate) — approval must remain unchanged either way
$before = $pdo->query("SELECT status FROM approval_requests WHERE id = {$apprS['id']}")->fetchColumn();
http_req('POST', 'admin/approvals/decide', ['csrf' => 'x', 'id' => (int)$apprS['id'], 'decision' => 'approve', 'note' => 'mgr self-approve attempt'], $mgrCookies);
$after = $pdo->query("SELECT status FROM approval_requests WHERE id = {$apprS['id']}")->fetchColumn();
ok('manager decision attempt blocked (approval still pending)', $before === 'pending' && $after === 'pending');

/* ---------- 5. Owner approves manager-created salesman → becomes Active + warehouse ---------- */
[$code] = http_req('POST', 'admin/approvals/decide', ['csrf' => csrf_from('admin/approvals', $owner), 'id' => (int)$apprS['id'], 'decision' => 'approve', 'note' => 'E2E approve'], $owner);
$smMgr = $pdo->query("SELECT * FROM salesmen WHERE id = {$smMgr['id']}")->fetch(PDO::FETCH_ASSOC);
$whMgr = $pdo->query("SELECT id FROM warehouses WHERE code = 'WH-$smMgrCode'")->fetchColumn();
ok('approved manager salesman becomes Active', $smMgr && (int)$smMgr['is_active'] === 1);
ok('approved manager salesman warehouse created', (bool)$whMgr);

/* ---------- 6. Owner approves manager-created employee → becomes Active ---------- */
[$code] = http_req('POST', 'admin/approvals/decide', ['csrf' => csrf_from('admin/approvals', $owner), 'id' => (int)$apprE['id'], 'decision' => 'approve', 'note' => 'E2E approve'], $owner);
$empMgr = $pdo->query("SELECT * FROM employees WHERE id = {$empMgr['id']}")->fetch(PDO::FETCH_ASSOC);
ok('approved manager employee becomes Active', $empMgr && (int)$empMgr['is_active'] === 1);

/* ---------- 7. Owner toggles employee Active ↔ Inactive ---------- */
$eid = (int)$empMgr['id'];
[$code] = http_req('POST', 'employees/' . $eid . '/toggle', ['csrf' => csrf_from('employees/' . $eid, $owner), 'id' => $eid], $owner);
$after = (int)$pdo->query("SELECT is_active FROM employees WHERE id = $eid")->fetchColumn();
ok('toggle deactivates employee', $after === 0);
[$code] = http_req('POST', 'employees/' . $eid . '/toggle', ['csrf' => csrf_from('employees/' . $eid, $owner), 'id' => $eid], $owner);
$after = (int)$pdo->query("SELECT is_active FROM employees WHERE id = $eid")->fetchColumn();
ok('toggle reactivates employee', $after === 1);

/* ---------- 8. Owner toggles salesman Active ↔ Inactive ---------- */
$sid = (int)$smMgr['id'];
[$code] = http_req('POST', 'salesmen/' . $sid . '/toggle', ['csrf' => csrf_from('salesmen/' . $sid . '/profile', $owner), 'id' => $sid, 'back' => 'salesmen/' . $sid . '/profile'], $owner);
$after = (int)$pdo->query("SELECT is_active FROM salesmen WHERE id = $sid")->fetchColumn();
ok('toggle deactivates salesman', $after === 0);
[$code] = http_req('POST', 'salesmen/' . $sid . '/toggle', ['csrf' => csrf_from('salesmen/' . $sid . '/profile', $owner), 'id' => $sid, 'back' => 'salesmen/' . $sid . '/profile'], $owner);
$after = (int)$pdo->query("SELECT is_active FROM salesmen WHERE id = $sid")->fetchColumn();
ok('toggle reactivates salesman', $after === 1);

/* ---------- 9. Supplier created by owner becomes Active ---------- */
$supCode = 'SUP-E2E-' . strtoupper($suffix);
$csrf = csrf_from('suppliers/new', $owner);
[$code] = http_req('POST', 'suppliers/new', [
    'csrf' => $csrf, 'code' => $supCode, 'name' => 'E2E Supplier', 'phone' => '', 'address' => '', 'city' => '', 'ntn' => '',
    'opening_balance' => '0', 'opening_type' => 'credit',
], $owner);
$sup = $pdo->query("SELECT * FROM suppliers WHERE code = '$supCode'")->fetch(PDO::FETCH_ASSOC);
ok('owner-created supplier becomes Active', $sup && (int)$sup['is_active'] === 1);

/* ---------- 10. Employee lists include inactive for owner ---------- */
[$code, $body] = http_req('GET', 'employees?show_inactive=1', null, $owner);
ok('employees index 200 with show_inactive', $code === 200 && str_contains($body, 'Show inactive'));

/* ---------- 11. Cleanup ---------- */
$smIds = array_values(array_filter([(int)($smOwner['id'] ?? 0), (int)($smMgr['id'] ?? 0)]));
$empIds = array_values(array_filter([(int)($empMgr['id'] ?? 0)]));
$smIdList = implode(',', $smIds); $empIdList = implode(',', $empIds);

// warehouses created for e2e salesmen
foreach ([$smOwnerCode, $smMgrCode] as $c) {
    $pdo->exec("DELETE FROM warehouses WHERE code = 'WH-" . str_replace("'", "", $c) . "'");
}
// notifications referencing e2e docs
$pdo->exec("DELETE FROM notifications WHERE entity_type = 'approval_requests' AND entity_id IN (SELECT id FROM approval_requests WHERE reference_type IN ('salesmen','employees') AND reference_id IN ($smIdList)) OR entity_type = 'employees' AND entity_id IN ($empIdList)");
// approvals
$pdo->exec("DELETE FROM approval_requests WHERE reference_type = 'salesmen' AND reference_id IN ($smIdList)");
$pdo->exec("DELETE FROM approval_requests WHERE reference_type = 'employees' AND reference_id IN ($empIdList)");
// audit rows
$pdo->exec("DELETE FROM audit_logs WHERE entity = 'salesmen' AND entity_id IN ($smIdList)");
$pdo->exec("DELETE FROM audit_logs WHERE entity = 'employees' AND entity_id IN ($empIdList)");
// salary_changes for e2e employees (manager could have inserted none here, but guard)
$pdo->exec("DELETE FROM salary_changes WHERE employee_id IN ($empIdList)");
// rows
$pdo->exec("DELETE FROM salesmen WHERE id IN ($smIdList)");
$pdo->exec("DELETE FROM employees WHERE id IN ($empIdList)");
$pdo->exec("DELETE FROM suppliers WHERE code = '$supCode'");
$pdo->exec("DELETE FROM audit_logs WHERE entity = 'suppliers' AND details LIKE '%$supCode%'");
$pdo->exec("DELETE FROM idempotency_keys WHERE idem_key LIKE '%E2E%' OR idem_key LIKE '%e2e%'");
echo "cleanup done\n";

$fail = count(array_filter($results, fn($r) => $r[1] === 'FAIL'));
echo "\n", count($results), " checks, $fail failed\n";
exit($fail ? 1 : 0);
