<?php
/**
 * JUSTI NUMBER ONE ERP — Packet ↔ Bundle units & conversion upgrade.
 * One conversion rule: 10 Packets = 1 Bundle.
 * Adds Packet (PKT) and Bundle (BUNDLE) as selectable units; records the default
 * FG packaging conversion: 1 Piece/Packet → 1 Packet, 1 Bundle = 10 Packets.
 * Does NOT rewrite historical transactions; only inserts missing units and writes
 * global default settings if they aren't set to the new spec values yet.
 * Idempotent — safe to run multiple times.
 *
 * Usage: php install/upgrade_units_packet_bundle.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';
if (!file_exists($configPath)) { fwrite(STDERR, "config/config.php missing — run the installer first.\n"); exit(1); }
$config = require $configPath;
$db = $config['db'] ?? [];
$driver = $db['driver'] ?? 'mysql';

if ($driver === 'sqlite') {
    $dsn = (string)($db['dsn'] ?? '');
    $pdo = new PDO($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = OFF');
    // Units — insert only if missing, matched by short code (units.short has no unique constraint)
    $exists = $pdo->prepare('SELECT COUNT(*) FROM units WHERE short = ?');
    $ins = $pdo->prepare('INSERT INTO units (name, short) VALUES (?,?)');
    foreach ([['Packet', 'PKT'], ['Bundle', 'BUNDLE']] as [$n, $s]) {
        $exists->execute([$s]);
        if (!(int)$exists->fetchColumn()) $ins->execute([$n, $s]); else echo "unit $s already present\n";
    }
    // Global packaging defaults — only set if the current value is missing or the legacy "12 pieces/pack"
    $get = $pdo->prepare('SELECT setting_value FROM company_settings WHERE setting_key = ?');
    $put = $pdo->prepare('INSERT INTO company_settings (setting_key, setting_value, updated_at) VALUES (?,?,?)');
    $replace = $pdo->prepare('UPDATE company_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?');
    $now = date('Y-m-d H:i:s');
    $setIf = function (string $key, string $newVal, array $replaceWhen) use ($get, $put, $replace, $now) {
        $get->execute([$key]);
        $cur = $get->fetchColumn();
        if ($cur === false) { $put->execute([$key, $newVal, $now]); echo "$key = $newVal (added)\n"; return true; }
        if (in_array((string)$cur, $replaceWhen, true) && (string)$cur !== $newVal) { $replace->execute([$newVal, $now, $key]); echo "$key = $newVal (was $cur)\n"; return true; }
        echo "$key = $cur (kept)\n"; return false;
    };
    $setIf('packaging_pack_pieces', '1', ['12']);       // 1 base unit = 1 packet
    $setIf('packaging_bundle_packs', '10', ['0', '12', '8', '10']); // 10 packets = 1 bundle
    // packet_bundles left at 0 (not configured) — only the two tiers needed by the spec
    echo "packaging_packet_bundles unchanged (not used for Packet↔Bundle)\n";
    $pdo->exec('PRAGMA foreign_keys = ON');
    echo "OK\n";
    exit(0);
}

$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? ''), $db['user'] ?? '', $db['pass'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('INSERT IGNORE INTO units (name, short) SELECT "Packet","PKT" FROM (SELECT 1) t WHERE NOT EXISTS (SELECT 1 FROM units WHERE short = "PKT")');
$pdo->exec('INSERT IGNORE INTO units (name, short) SELECT "Bundle","BUNDLE" FROM (SELECT 1) t WHERE NOT EXISTS (SELECT 1 FROM units WHERE short = "BUNDLE")');
$get  = function (string $k) use ($pdo) { $s = $pdo->prepare('SELECT setting_value FROM company_settings WHERE setting_key = ?'); $s->execute([$k]); return $s->fetchColumn(); };
$put  = function (string $k, string $v) use ($pdo) { $pdo->prepare('INSERT INTO company_settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=VALUES(updated_at)')->execute([$k, $v]); };
$setIf = function (string $key, string $newVal, array $replaceWhen) use ($get, $put) {
    $cur = $get($key);
    if ($cur === false) { $put($key, $newVal); echo "$key = $newVal (added)\n"; return true; }
    if (in_array((string)$cur, $replaceWhen, true) && (string)$cur !== $newVal) { $put($key, $newVal); echo "$key = $newVal (was $cur)\n"; return true; }
    echo "$key = $cur (kept)\n"; return false;
};
$setIf('packaging_pack_pieces', '1', ['12']);
$setIf('packaging_bundle_packs', '10', ['0', '12', '8', '10']);
// packet_bundles left alone — only the two tiers the spec needs
echo "OK\n";
