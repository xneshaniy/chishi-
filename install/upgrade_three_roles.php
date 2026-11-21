<?php
/**
 * JUSTI NUMBER ONE ERP — three-role access upgrade for existing installations.
 *
 * 1. Deactivates (is_active = 0) every login account that does NOT hold one of the
 *    authorized roles: owner / admin / manager / accountant.
 *    Employees, Labour, Salesmen and Storekeepers lose login access, but their
 *    employee/worker/salesman records remain untouched — they are managed inside the ERP.
 * 2. Grants the Manager the employees.create and employees.edit permissions
 *    (managers manage registered people; salary figures stay owner/admin-only in code).
 *
 * Existing installs that created salesman logins (e.g. a 'salesman1' demo account)
 * will have those accounts deactivated — they are NOT deleted, and no business
 * records are removed.
 *
 * Safe to run multiple times.
 *
 * Usage (on the server): php install/upgrade_three_roles.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';
if (!file_exists($configPath)) { fwrite(STDERR, "config/config.php missing — run the installer first.\n"); exit(1); }
$config = require $configPath;
$db = $config['db'] ?? [];
$driver = $db['driver'] ?? 'mysql';

if ($driver === 'sqlite') {
    $pdo = new PDO(strpos((string)($db['dsn'] ?? ''), 'sqlite:') === 0 ? $db['dsn'] : 'sqlite:' . $db['dsn']);
} else {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''),
        $db['user'] ?? '', $db['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "== 1) Deactivating login accounts without an authorized role ==\n";
$eligible = ['owner', 'admin', 'manager', 'accountant'];
$in = implode(',', array_fill(0, count($eligible), '?'));
$targets = $pdo->prepare(
    "SELECT u.id, u.username FROM users u
     WHERE NOT EXISTS (
         SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id
         WHERE ur.user_id = u.id AND r.code IN ($in)
     )"
);
$targets->execute($eligible);
$rows = $targets->fetchAll(PDO::FETCH_ASSOC);
$now = date('Y-m-d H:i:s');
$audit = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address, created_at) VALUES (NULL, 'deactivate', 'users', ?, ?, 'cli-upgrade', ?)");
$deactivate = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND is_active = 1");
foreach ($rows as $u) {
    $deactivate->execute([(int)$u['id']]);
    $changed = $deactivate->rowCount();
    echo ($changed ? "DEACTIVATED" : "already disabled"), ": {$u['username']} (user #{$u['id']})\n";
    if ($changed) {
        try { $audit->execute([(string)$u['id'], 'Login access revoked (three-role policy: Owner/Admin, Manager, Accountant only)', $now]); } catch (Throwable $e) { echo "  (audit skipped: {$e->getMessage()})\n"; }
    }
}
if (!$rows) echo "OK — no unauthorized login accounts found.\n";

echo "\n== 2) Granting Manager permissions: employees.create, employees.edit ==\n";
$manager = $pdo->query("SELECT id FROM roles WHERE code = 'manager'")->fetchColumn();
if (!$manager) { echo "SKIP — 'manager' role not seeded yet.\n"; exit(0); }
$ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id)
    SELECT ?, id FROM permissions WHERE code = ? AND NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = permissions.id)");
foreach (['employees.create', 'employees.edit'] as $perm) {
    $exists = (int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE code = '" . str_replace("'", "''", $perm) . "'")->fetchColumn();
    if (!$exists) { echo "SKIP — permission $perm not seeded.\n"; continue; }
    $ins->execute([(int)$manager, $perm, (int)$manager]);
    echo "GRANTED manager › $perm\n";
}
echo "\n== 3) Granting Accountant permissions: purchase.create, purchase.receive, sales.create, sales.edit ==\n";
$accountant = $pdo->query("SELECT id FROM roles WHERE code = 'accountant'")->fetchColumn();
if (!$accountant) { echo "SKIP — 'accountant' role not seeded yet.\n"; } else {
    $insA = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id)
        SELECT ?, id FROM permissions WHERE code = ? AND NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = permissions.id)");
    foreach (['purchase.create', 'purchase.receive', 'sales.create', 'sales.edit'] as $perm) {
        $exists = (int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE code = '" . str_replace("'", "''", $perm) . "'")->fetchColumn();
        if (!$exists) { echo "SKIP — permission $perm not seeded.\n"; continue; }
        $insA->execute([(int)$accountant, $perm, (int)$accountant]);
        echo "GRANTED accountant › $perm\n";
    }
}
echo "\nDone. Only Owner/Admin, Manager and Accountant accounts can sign in.\n";
