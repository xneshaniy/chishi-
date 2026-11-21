<?php
/** Salesman profile: documents table, find_or_create_area, locations seeded, photo columns exist. */
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['CONFIG'] = ['db' => ['driver' => 'sqlite', 'dsn' => 'sqlite::memory:'], 'costing_method' => 'weighted_average'];
$_SESSION = ['user_id' => 1];

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/services/StaffAttendance.php';
require __DIR__ . '/../database/seed.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys = ON');
Database::setPdo($pdo);
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sqlite.sql'));
seed_base($pdo);

$tests = 0; $failures = 0;
function ok(bool $cond, string $name): void {
    global $tests, $failures;
    $tests++;
    if ($cond) echo "  PASS  $name\n"; else { $failures++; echo "  FAIL  $name\n"; }
}

echo "=== 1. Schema: salesman profile columns & documents table ===\n";
$cols = array_column($pdo->query('PRAGMA table_info(salesmen)')->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach (['photo_path', 'cnic_front_path', 'cnic_back_path', 'province_id', 'city_id', 'area_id', 'deactivated_at', 'deactivated_by', 'join_date'] as $c)
    ok(in_array($c, $cols, true), "salesmen.$c exists");
ok((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name='salesman_documents'")->fetchColumn() === 1, 'salesman_documents table created');
ok(db_val("SELECT COUNT(*) FROM sqlite_master WHERE name='pk_provinces'") == 1, 'pk_provinces created');
ok(db_val("SELECT COUNT(*) FROM sqlite_master WHERE name='pk_cities'") == 1, 'pk_cities created');
ok(db_val("SELECT COUNT(*) FROM sqlite_master WHERE name='areas'") == 1, 'areas table created');

echo "\n=== 2. Pakistan locations seeded by seed_base ===\n";
$prov = (int)$pdo->query('SELECT COUNT(*) FROM pk_provinces')->fetchColumn();
$cit = (int)$pdo->query('SELECT COUNT(*) FROM pk_cities')->fetchColumn();
ok($prov === 7, "7 provinces seeded (got $prov)");
ok($cit > 150, "cities seeded (got $cit)");
ok(db_val("SELECT COUNT(*) FROM pk_cities WHERE name = 'Bahawalpur'") == 1, 'Bahawalpur seeded (target market)');
ok(db_val("SELECT COUNT(*) FROM pk_provinces WHERE name = 'Punjab'") == 1, 'Punjab province seeded');

echo "\n=== 3. find_or_create_area: create, reuse, province-only ===\n";
$punjab = (int)db_val("SELECT id FROM pk_provinces WHERE name = 'Punjab'");
$cityId = (int)db_val("SELECT id FROM pk_cities WHERE province_id = ? AND name = 'Bahawalpur'", [$punjab]);
$areaId1 = find_or_create_area($punjab, $cityId, 'Model Town');
ok($areaId1 > 0, 'created Model Town under Bahawalpur');
$areaId2 = find_or_create_area($punjab, $cityId, 'Model Town');
ok($areaId2 === $areaId1, 'same area reused (not duplicated)');
$areaId3 = find_or_create_area($punjab, $cityId, 'Model Town  ');
ok($areaId3 === $areaId1, 'trailing spaces still match the same area');
$other = find_or_create_area($punjab, $cityId, 'Farid Gate');
ok($other > 0 && $other !== $areaId1, 'new distinct area created separately');
$provOnly = find_or_create_area($punjab, null, 'Southern Punjab');
ok($provOnly > 0, 'province-only area created');
ok((int)db_val("SELECT COUNT(*) FROM areas WHERE name = 'Model Town'") === 1, 'exactly one Model Town area row');

echo "\n=== 4. Salesman insert with location + profile photos paths ===\n";
$wid = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN'");
$now = now_local();
$sid = (int)db_insert('salesmen', ['code' => 'SM-T-0001', 'name' => 'Test Profile Salesman', 'phone' => '0300-0000000', 'cnic' => '42101-0000000-9', 'area' => 'Model Town', 'province_id' => $punjab, 'city_id' => $cityId, 'area_id' => $areaId1, 'photo_path' => 'salesmen/1/test.jpg', 'cnic_front_path' => 'salesmen/1/cnic_front.jpg', 'cnic_back_path' => 'salesmen/1/cnic_back.jpg', 'join_date' => '2026-01-01', 'salary' => 30000, 'commission_percent' => 2.5, 'is_active' => 1, 'created_at' => $now]);
ok($sid > 0, 'salesman created with location + photo paths');
ok((int)db_val('SELECT province_id FROM salesmen WHERE id = ?', [$sid]) === $punjab, 'province_id persisted');
ok((int)db_val('SELECT city_id FROM salesmen WHERE id = ?', [$sid]) === $cityId, 'city_id persisted');
ok((int)db_val('SELECT area_id FROM salesmen WHERE id = ?', [$sid]) === $areaId1, 'area_id persisted');

echo "\n=== 5. Salesman documents table round-trip ===\n";
$did = (int)db_insert('salesman_documents', ['salesman_id' => $sid, 'doc_type' => 'agreement', 'original_name' => 'agreement.pdf', 'file_path' => 'salesmen/' . $sid . '/agreement.pdf', 'mime' => 'application/pdf', 'size_bytes' => 12345, 'note' => 'Test agreement', 'uploaded_by' => 1, 'created_at' => $now]);
ok($did > 0, 'document inserted');
ok((int)db_val('SELECT salesman_id FROM salesman_documents WHERE id = ?', [$did]) === $sid, 'document linked to salesman');
db_exec('DELETE FROM salesman_documents WHERE id = ?', [$did]);
ok((int)db_val('SELECT COUNT(*) FROM salesman_documents WHERE salesman_id = ?', [$sid]) === 0, 'document deleted');

echo "\n========================================\nSALESMAN PROFILE TEST RESULTS: $tests tests, $failures failures\n";
exit($failures ? 1 : 0);
