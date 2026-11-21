<?php
/** Verify Manager positive-permission path: grant → pages work → create pending expense → revoke → 403 again. */
$base = 'http://127.0.0.1:8080';
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/storage/dev.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$mgrRole = (int)$pdo->query("SELECT id FROM roles WHERE code='manager'")->fetchColumn();
$viewPid = (int)$pdo->query("SELECT id FROM permissions WHERE code='company_expenses.view'")->fetchColumn();
$createPid = (int)$pdo->query("SELECT id FROM permissions WHERE code='company_expenses.create'")->fetchColumn();

$req = function (string $route, ?array $post = null, array &$cookies = [], bool $login = false) use ($base) {
    $ch = curl_init($base . '/index.php?r=' . rawurlencode($route));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
    $cj = ''; foreach ($cookies as $k => $v) $cj .= "$k=$v; "; if ($cj) curl_setopt($ch, CURLOPT_COOKIE, rtrim($cj, '; '));
    if ($post !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    $raw = curl_exec($ch);
    $len = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    foreach (preg_split('/\r?\n/', substr($raw, 0, $len)) as $line) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $m)) $cookies[trim($m[1])] = trim($m[2]);
    }
    return [(int)curl_getinfo($ch, CURLINFO_HTTP_CODE), substr($raw, $len)];
};
$login = function (string $u, string $p, array &$cookies) use ($req, $base) {
    [$c, $page] = $req('auth/login', null, $cookies);
    preg_match('/name="csrf" value="([^"]+)"/', $page, $m);
    $req('auth/login', ['csrf' => $m[1], 'username' => $u, 'password' => $p], $cookies);
};
$pendingRows = function () use ($pdo) { return (int)$pdo->query("SELECT COUNT(*) FROM company_expenses WHERE status='pending'")->fetchColumn(); };

// ---- 1. grant view+create to manager role
$pdo->exec("INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES ($mgrRole, $viewPid)");
$pdo->exec("INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES ($mgrRole, $createPid)");
echo "granted view+create to manager role\n";

$cookies = [];
$login('manager1', 'Manager@1234x', $cookies);
[$code] = $req('company-expenses', null, $cookies);
echo "manager GET /company-expenses after grant: $code\n";
[$code] = $req('company-expenses/new', null, $cookies);
echo "manager GET /company-expenses/new after grant: $code\n";

[$code, $form] = $req('company-expenses/new', null, $cookies);
preg_match('/name="csrf" value="([^"]+)"/', $form, $m);
$before = $pendingRows();
[$code] = $req('company-expenses/new', [
    'csrf' => $m[1], 'idem_key' => 'e2e-mgr-' . bin2hex(random_bytes(6)),
    'expense_date' => date('Y-m-d'), 'category_id' => 22,
    'item_name' => 'E2E Manager Plastic Bags', 'description' => 'manager permission check',
    'quantity' => '1000', 'unit' => 'bags', 'rate' => '2', 'amount' => '2000',
    'supplier' => 'E2E Supplier', 'payment_method' => 'cash', 'notes' => 'perm test',
], $cookies);
echo "manager POST create: $code → pending rows before=$before after=" . $pendingRows() . "\n";

// manager must NOT be able to approve — hard 403
$lid = (int)$pdo->query("SELECT id FROM expenses WHERE description LIKE '%Manager Plastic Bags%' ORDER BY id DESC LIMIT 1")->fetchColumn();
$apr = (int)$pdo->query("SELECT id FROM approval_requests WHERE reference_type='expenses' AND reference_id=$lid AND status='pending'")->fetchColumn();
$mgrIdem = 'e2e-mgr-decide-' . bin2hex(random_bytes(4));
[$code, $dec] = $req('admin/approvals/decide', ['csrf' => $m[1], 'id' => $apr, 'decision' => 'approve', 'note' => 'mgr decide attempt'], $cookies);
echo "manager decide attempt: code=$code still-pending=" . ((int)$pdo->query("SELECT COUNT(*) FROM approval_requests WHERE id=$apr AND status='pending'")->fetchColumn()) . "\n";

// ---- 2. cleanup the manager's test expense + approval
$pdo->exec("DELETE FROM approval_requests WHERE id = $apr");
$pdo->exec("DELETE FROM expenses WHERE id = $lid");
$pdo->exec("DELETE FROM company_expenses WHERE item_name LIKE 'E2E Manager%'");
$pdo->exec("DELETE FROM idempotency_keys WHERE idem_key LIKE 'e2e-mgr-%'");
$pdo->exec("DELETE FROM audit_logs WHERE details LIKE '%Manager Plastic Bags%'");
echo "manager test expense cleaned up\n";

// ---- 3. revoke permissions again
$pdo->exec("DELETE FROM role_permissions WHERE role_id = $mgrRole AND permission_id IN ($viewPid, $createPid)");
echo "revoked view+create from manager role\n";

// re-login manager and confirm 403
$cookies = [];
$login('manager1', 'Manager@1234x', $cookies);
[$code] = $req('company-expenses', null, $cookies);
echo "manager GET /company-expenses after revoke: $code\n";
[$code] = $req('reports/company-expenses', null, $cookies);
echo "manager GET /reports/company-expenses after revoke: $code\n";
