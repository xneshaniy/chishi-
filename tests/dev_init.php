<?php
/** Rebuild the LOCAL dev SQLite database: schema + base seed + dev-only owner account. */
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/core/helpers.php';
$dbFile = __DIR__ . '/../storage/dev.sqlite';
@unlink($dbFile);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite:' . $dbFile]];
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sqlite.sql'));
require __DIR__ . '/../database/seed.php';
seed_base($pdo);
// Dev-only owner (never ship to production)
$pdo->prepare('INSERT INTO users (username, full_name, password_hash, is_active, created_at) VALUES (?,?,?,1,?)')
    ->execute(['owner', 'Dev Owner (CTC)', password_hash('owner1234', PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
$uid = (int)$pdo->lastInsertId();
$ownerRoleId = (int)$pdo->query("SELECT id FROM roles WHERE code = 'owner'")->fetchColumn();
$pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)')->execute([$uid, $ownerRoleId]);
// THREE-ROLE POLICY: employees/labour/salesmen never get login accounts — they are managed as records.
// Demo salesman profile kept (business record only, NO login account)
$pdo->prepare("INSERT INTO salesmen (code, name, phone, area, commission_percent, warehouse_id, is_active) VALUES ('SM-001','Demo Salesman','0300-0000001','Main Area',2.5,(SELECT id FROM warehouses WHERE code='WH-MAIN'),1)")->execute();
// give SM his own warehouse
$smId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO warehouses (branch_id, code, name, type, is_default, is_active) VALUES ((SELECT id FROM branches WHERE code='MAIN'), ?, ?, 'salesman', 0, 1)")
    ->execute(['WH-SM-001', 'Demo Salesman Van']);
$smWh = (int)$pdo->lastInsertId();
$pdo->prepare('UPDATE salesmen SET warehouse_id = ? WHERE id = ?')->execute([$smWh, $smId]);
echo "Dev database ready (three-role policy: Owner/Admin, Manager, Accountant only; demo salesman has no login).\n owner / owner1234\n";
