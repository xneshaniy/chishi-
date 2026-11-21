<?php
/** Add capital/income permissions to dev.sqlite (idempotent) and map to roles. */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$perms = ['capital.view' => 'capital', 'income.view' => 'income', 'income.create' => 'income'];
$ins = $pdo->prepare('INSERT OR IGNORE INTO permissions (code, module, description) VALUES (?,?,?)');
foreach ($perms as $code => $module) $ins->execute([$code, $module, ucfirst(str_replace('.', ' ', $code))]);
$ids = [];
foreach ($pdo->query("SELECT code, id FROM permissions WHERE code IN ('capital.view','income.view','income.create')") as $r) $ids[$r['code']] = (int)$r['id'];
$roleIds = [];
foreach ($pdo->query("SELECT code, id FROM roles") as $r) $roleIds[$r['code']] = (int)$r['id'];
$link = fn(string $role, string $perm) => $pdo->exec("INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (" . $roleIds[$role] . ", " . $ids[$perm] . ")");
// owner via shortcut; admin = all (link for completeness)
foreach ($ids as $code => $id) $link('admin', $code);
// income for accountant + manager
foreach (['income.view','income.create'] as $p) { $link('accountant', $p); $link('manager', $p); }
// capital stays owner/admin only (no manager/accountant links)
foreach ($pdo->query("SELECT p.code, r.code AS role FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id JOIN roles r ON r.id = rp.role_id WHERE p.code IN ('capital.view','income.view','income.create') ORDER BY r.code, p.code") as $r)
    echo $r['role'], ' => ', $r['code'], "\n";
