<?php
/**
 * Final end-to-end advances tab verification
 * Tests the complete flow: route → controller → view → tab navigation
 */
require_once __DIR__ . '/../app/core/bootstrap.php';
$_SESSION['user_id'] = 1;
$_SESSION['role']    = 'admin';
$_SESSION['username'] = 'admin';

$pass = 0; $fail = 0;
function ok(string $label): void { global $pass; echo "✅ $label\n"; $pass++; }
function no(string $label, string $detail = ''): void { global $fail; echo "❌ $label" . ($detail ? " — $detail" : '') . "\n"; $fail++; }

// Get a real salesman
$sm = db_one('SELECT * FROM salesmen WHERE is_active = 1 LIMIT 1');
$id = (int)$sm['id'];
echo "🧪 Testing Salesman: {$sm['name']} (ID $id)\n\n";

// --- ROUTE CHECK ---
echo "── Route Checks ──────────────────────\n";
$routes = require __DIR__ . '/../app/routes.php';
function matchRoute(array $routes, string $path): ?array {
    if (isset($routes[$path])) return $routes[$path];
    foreach ($routes as $pat => $h) {
        $pp = explode('/', $pat); $rp = explode('/', $path);
        if (count($pp) !== count($rp)) continue;
        $ok = true;
        foreach ($pp as $i => $s) { if (preg_match('/^\{/', $s)) continue; if ($s !== $rp[$i]) { $ok = false; break; } }
        if ($ok) return $h;
    }
    return null;
}
$h = matchRoute($routes, "salesmen/$id/advances");
if ($h && $h[1] === 'advances') ok("salesmen/{id}/advances routes to SalesmenController::advances()");
else no("Route not found or wrong handler", $h[1] ?? 'null');

$h2 = matchRoute($routes, "salesmen/$id");
if ($h2 && $h2[1] === 'account') ok("salesmen/{id} routes to account (no more 404)");
else no("salesmen/{id} route missing");

// --- CONTROLLER + VIEW RENDER ---
echo "\n── View Render Checks ────────────────\n";
$_GET['id'] = $id;
$stats = AdvancesController::advanceStats(['id' => $id, 'person_type' => 'salesman']);
$accounts = db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
$advances = db_all("SELECT a.*, cba.name AS account_name FROM salesman_salary_advances a LEFT JOIN cash_bank_accounts cba ON cba.id = a.account_id WHERE a.salesman_id = ? ORDER BY a.date DESC", [$id]);

ob_start();
view('salesmen/advances', [
    'sm' => $sm, 'id' => $id, 'stats' => $stats,
    'monthly' => (float)($sm['salary'] ?? 0),
    'accounts' => $accounts, 'advances' => $advances, 'title' => 'Advances',
]);
$html = ob_get_clean();

strpos($html, 'Salary Advance Management') !== false ? ok("Hero header visible") : no("Hero header missing");
strpos($html, 'Give / Request Advance') !== false ? ok("Give / Request form visible") : no("Give / Request form missing");
strpos($html, 'Advance Date') !== false ? ok("Date field in form") : no("Date field missing");
strpos($html, 'name="amount"') !== false ? ok("Amount field in form") : no("Amount field missing");
strpos($html, 'name="account_id"') !== false ? ok("Account select in form") : no("Account select missing");
strpos($html, 'name="notes"') !== false ? ok("Notes field in form") : no("Notes field missing");
strpos($html, 'Advance History') !== false ? ok("Advance History table visible") : no("Advance History missing");
strpos($html, 'Outstanding Advance') !== false ? ok("Outstanding stat chip") : no("Outstanding stat chip missing");
strpos($html, 'Total Paid') !== false ? ok("Total Paid stat chip") : no("Total Paid stat chip missing");
strpos($html, 'Recovered') !== false ? ok("Recovered stat chip") : no("Recovered stat chip missing");
(count($accounts) === 0 || strpos($html, 'select account') !== false || strpos($html, '<option') !== false)
    ? ok("Account options rendered (" . count($accounts) . " accounts)") : no("Account options missing");

// --- TAB BAR ---
echo "\n── Tab Bar Checks ────────────────────\n";
ob_start();
$tab = 'advances';
require __DIR__ . '/../app/views/partials/sm_tabs.php';
$tabHtml = ob_get_clean();

strpos($tabHtml, url("salesmen/$id/advances")) !== false ? ok("Advances tab link correct URL") : no("Advances link missing");
strpos($tabHtml, 'class="on"') !== false ? ok("Advances tab highlighted as active") : no("Active class missing");
strpos($tabHtml, url("salesmen/$id/account")) !== false ? ok("Account tab also present") : no("Account tab missing");
strpos($tabHtml, url("salesmen/$id/salary")) !== false ? ok("Salary tab also present") : no("Salary tab missing");

// --- ADVANCES LISTING PAGE ---
echo "\n── Advances Index Page Checks ────────\n";
$people = PeopleDirectory::allActive();
$rows = [];
foreach ($people as $p) $rows[] = array_merge($p, AdvancesController::advanceStats($p));
ob_start();
view('advances/index', [
    'rows' => $rows, 'q' => '', 'kind' => 'all', 'designation' => '', 'designations' => [],
    'totalGiven' => 0, 'totalRecovered' => 0, 'totalOutstanding' => 0,
    'totalRequested' => 0, 'totalApproved' => 0, 'totalPeople' => count($rows), 'title' => 'Advances',
]);
$idx = ob_get_clean();
strpos($idx, url("salesmen/$id/advances")) !== false ? ok("Salesman card → salesmen/{id}/advances (no more 404)") : no("Card link wrong");

// --- CSS DELETE ICON ---
echo "\n── CSS Delete Icon Checks ────────────\n";
$css = file_get_contents(__DIR__ . '/../assets/css/app.css');
strpos($css, '.btn-danger::before') !== false ? ok("btn-danger::before icon rule present") : no("Icon rule missing");
strpos($css, 'mask-image') !== false ? ok("SVG trash icon mask in CSS") : no("SVG mask missing");
strpos($css, '.btn-delete-outline') !== false ? ok("btn-delete-outline class added") : no("btn-delete-outline missing");

// --- SUMMARY ---
echo "\n══════════════════════════════════════\n";
echo "Result: $pass passed, $fail failed\n";
if ($fail === 0) echo "🎉 ALL CHECKS PASSED — Advances tab is fully working!\n";
else echo "⚠️  Some checks failed — review above.\n";
