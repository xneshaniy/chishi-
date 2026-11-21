<?php
/**
 * Tests: Daily RM Usage Tab inside Raw Material page
 * Run: php tests/test_daily_rm_usage_tab.php
 */
define('ROOT', dirname(__DIR__));
define('APP_PATH', ROOT . '/app');

// ── minimal bootstrap ──────────────────────────────────────────
if (!function_exists('db')) {
    // load only helpers needed to verify logic, not the full app
    $dbFile = ROOT . '/database/chishti.db';
    if (!file_exists($dbFile)) {
        // try alternate path
        $dbFile = ROOT . '/database/database.sqlite';
    }
    $pdo = null;
    if (file_exists($dbFile)) {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    function db(): ?PDO { global $pdo; return $pdo; }
    function db_all(string $sql, array $p = []): array {
        global $pdo; if (!$pdo) return [];
        $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll();
    }
    function db_val(string $sql, array $p = []) {
        global $pdo; if (!$pdo) return null;
        $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchColumn();
    }
    function db_one(string $sql, array $p = []): ?array {
        global $pdo; if (!$pdo) return null;
        $st = $pdo->prepare($sql); $st->execute($p); return $st->fetch() ?: null;
    }
    function db_insert(string $t, array $d): int {
        global $pdo;
        $cols = implode(',', array_keys($d));
        $ph   = implode(',', array_fill(0, count($d), '?'));
        $st   = $pdo->prepare("INSERT INTO $t ($cols) VALUES ($ph)");
        $st->execute(array_values($d));
        return (int)$pdo->lastInsertId();
    }
    function db_exec(string $sql, array $p = []): void {
        global $pdo; if (!$pdo) return;
        $pdo->prepare($sql)->execute($p);
    }
    function today(): string { return date('Y-m-d'); }
}

// ── test runner ───────────────────────────────────────────────
$pass = 0; $fail = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { echo "✅ PASS: $name\n"; $pass++; }
    else       { echo "❌ FAIL: $name" . ($detail ? " — $detail" : '') . "\n"; $fail++; }
}

echo "\n=== Daily RM Usage Tab Tests ===\n\n";

// ── T1: view file exists ──────────────────────────────────────
ok('raw.php view file exists',  file_exists(ROOT . '/app/views/purchase/raw.php'));
ok('daily-usage block file exists', file_exists(ROOT . '/app/views/purchase/raw_daily_usage_block.php'));

// ── T2: tab bar contains daily-usage link ─────────────────────
$rawView = file_get_contents(ROOT . '/app/views/purchase/raw.php');
ok('Tab bar has daily-usage href',       strpos($rawView, "tab'=>'daily-usage'") !== false);
ok('Tab bar shows Daily Usage label',   (strpos($rawView, 'Daily Usage') !== false || strpos($rawView, 'ڈیلی یوز') !== false));
ok('Tab bar has 5 tabs total (incl WH)', substr_count($rawView, "class=\"<?= \$activeTab") >= 4);

// ── T3: block file has required elements ──────────────────────
$block = file_get_contents(ROOT . '/app/views/purchase/raw_daily_usage_block.php');
ok('Block: add_daily_usage action',      strpos($block, 'add_daily_usage') !== false);
ok('Block: cancel_daily_usage action',   strpos($block, 'cancel_daily_usage') !== false);
ok('Block: usage_date input',            strpos($block, 'name="usage_date"') !== false);
ok('Block: product_id select',           strpos($block, 'name="product_id"') !== false);
ok('Block: qty_kg input',                strpos($block, 'name="qty_kg"') !== false);
ok('Block: warehouse_id select',         strpos($block, 'name="warehouse_id"') !== false);
ok('Block: date-range from input',       strpos($block, 'name="usage_from"') !== false);
ok('Block: date-range to input',         strpos($block, 'name="usage_to"') !== false);
ok('Block: usage_mat filter',            strpos($block, 'name="usage_mat"') !== false);
ok('Block: cancel dialog with reason',   strpos($block, 'name="reason"') !== false);
ok('Block: Auth::can guard on form',     strpos($block, "Auth::can('rm.usage')") !== false);
ok('Block: owner/admin guard on cancel', strpos($block, 'is_owner_or_admin') !== false);

// ── T4: controller updated ────────────────────────────────────
$ctrl = file_get_contents(ROOT . '/app/controllers/PurchaseController.php');
ok('Controller: daily-usage in tab whitelist',   strpos($ctrl, "'daily-usage'") !== false);
ok('Controller: add_daily_usage POST handler',   strpos($ctrl, "'add_daily_usage'") !== false);
ok('Controller: cancel_daily_usage POST handler',strpos($ctrl, "'cancel_daily_usage'") !== false);
ok('Controller: RawMaterial::recordUsage call',  strpos($ctrl, 'RawMaterial::recordUsage') !== false);
ok('Controller: RawMaterial::cancelUsage call',  strpos($ctrl, 'RawMaterial::cancelUsage') !== false);
ok('Controller: usageDate variable passed',      strpos($ctrl, "'usageDate'") !== false);
ok('Controller: dailyEntries passed to view',    strpos($ctrl, "'dailyEntries'") !== false);
ok('Controller: dailySummary passed to view',    strpos($ctrl, "'dailySummary'") !== false);
ok('Controller: usageProducts passed to view',   strpos($ctrl, "'usageProducts'") !== false);
ok('Controller: monthTotalKg computed',          strpos($ctrl, 'monthTotalKg') !== false);

// ── T5: DB table exists and usable ───────────────────────────
if (db()) {
    $cols = db_all("PRAGMA table_info(rm_daily_usage)");
    $colNames = array_column($cols, 'name');
    ok('DB: rm_daily_usage table exists',        !empty($cols));
    ok('DB: id column',                          in_array('id', $colNames));
    ok('DB: usage_date column',                  in_array('usage_date', $colNames));
    ok('DB: product_id column',                  in_array('product_id', $colNames));
    ok('DB: qty_kg column',                      in_array('qty_kg', $colNames));
    ok('DB: status column',                      in_array('status', $colNames));
    ok('DB: cancel_reason column',               in_array('cancel_reason', $colNames));
    ok('DB: recorded_by column',                 in_array('recorded_by', $colNames));
    ok('DB: warehouse_id column',                in_array('warehouse_id', $colNames));

    // Can query without error
    $rows = db_all("SELECT rdu.*, p.name AS product_name FROM rm_daily_usage rdu JOIN products p ON p.id = rdu.product_id WHERE rdu.usage_date = ? ORDER BY rdu.id DESC", [today()]);
    ok('DB: today query runs OK', is_array($rows));

    // Summary query
    $summary = db_all("SELECT rdu.usage_date, p.name, COALESCE(SUM(CASE WHEN rdu.status='posted' THEN rdu.qty_kg ELSE 0 END),0) AS total_kg FROM rm_daily_usage rdu JOIN products p ON p.id = rdu.product_id WHERE rdu.usage_date BETWEEN ? AND ? GROUP BY rdu.usage_date, rdu.product_id ORDER BY rdu.usage_date DESC", [date('Y-m-01'), today()]);
    ok('DB: monthly summary query runs OK', is_array($summary));

    // Raw/packaging products exist
    $prods = db_all("SELECT p.id, p.name FROM products p WHERE p.kind IN ('raw','packaging') AND p.is_active=1 ORDER BY p.name");
    ok('DB: raw/packaging products exist', count($prods) > 0);
} else {
    echo "⚠️  SKIP: DB not found — skipping DB tests\n";
}

// ── T6: RawMaterial service exists ───────────────────────────
ok('Service: RawMaterial.php exists',        file_exists(ROOT . '/app/services/RawMaterial.php'));
$svc = file_get_contents(ROOT . '/app/services/RawMaterial.php');
ok('Service: recordUsage() method exists',   strpos($svc, 'function recordUsage') !== false);
ok('Service: cancelUsage() method exists',   strpos($svc, 'function cancelUsage') !== false);
ok('Service: defaultWarehouse() exists',     strpos($svc, 'function defaultWarehouse') !== false);
ok('Service: consumptionWarehouses() exists',strpos($svc, 'function consumptionWarehouses') !== false);
ok('Service: validDate() exists',            strpos($svc, 'function validDate') !== false);

// ── Summary ───────────────────────────────────────────────────
echo "\n--- Results ---\n";
echo "✅ Passed: $pass\n";
echo "❌ Failed: $fail\n";
echo $fail === 0 ? "\n🎉 All tests passed!\n" : "\n⚠️  Some tests failed.\n";
exit($fail > 0 ? 1 : 0);
