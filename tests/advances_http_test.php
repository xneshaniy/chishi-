<?php
/**
 * HTTP-level test for advances tab (runs with PHP CLI, auth bypassed via bootstrap + session mock)
 */
require_once __DIR__ . '/../app/core/bootstrap.php';
$_SESSION['user_id'] = 1;
$_SESSION['role']    = 'admin';
$_SESSION['username'] = 'admin';

$sm = db_one('SELECT * FROM salesmen WHERE is_active = 1 LIMIT 1');
$id = (int)$sm['id'];
echo "Salesman ID: $id — {$sm['name']}\n\n";

// --- 1. Route resolution ---
$routes = require __DIR__ . '/../app/routes.php';
function route_match_local(array $map, string $route): ?array {
    if (isset($map[$route])) return [$map[$route], []];
    foreach ($map as $pattern => $handler) {
        $pp = explode('/', $pattern); $rp = explode('/', $route);
        if (count($pp) !== count($rp)) continue;
        $params = []; $ok = true;
        foreach ($pp as $i => $seg) {
            if (preg_match('/^\{(\w+)\}$/', $seg, $m)) $params[$m[1]] = $rp[$i];
            elseif ($seg !== $rp[$i]) { $ok = false; break; }
        }
        if ($ok) return [$handler, $params];
    }
    return null;
}

$m = route_match_local($routes, "salesmen/$id/advances");
assert($m !== null, 'Route must exist');
assert($m[0][1] === 'advances', 'Must route to advances()');
echo "PASS route: salesmen/{id}/advances → SalesmenController::advances()\n";

// --- 2. Tab bar ---
ob_start();
$tab = 'advances';
require __DIR__ . '/../app/views/partials/sm_tabs.php';
$tabHtml = ob_get_clean();
assert(strpos($tabHtml, url("salesmen/$id/advances")) !== false, 'Tab link present');
assert(strpos($tabHtml, 'class="on"') !== false, 'Advances tab is active');
echo "PASS tabs: Advances tab present and highlighted\n";

// --- 3. Full page render ---
$_GET['id'] = $id;
$stats    = AdvancesController::advanceStats(['id' => $id, 'person_type' => 'salesman']);
$monthly  = (float)($sm['salary'] ?? 0);
$accounts = db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
$advances = db_all("SELECT a.*, cba.name AS account_name
                    FROM salesman_salary_advances a
                    LEFT JOIN cash_bank_accounts cba ON cba.id = a.account_id
                    WHERE a.salesman_id = ? ORDER BY a.date DESC, a.id DESC", [$id]);
ob_start();
view('salesmen/advances', [
    'sm' => $sm, 'id' => $id, 'stats' => $stats, 'monthly' => $monthly,
    'accounts' => $accounts, 'advances' => $advances, 'title' => 'Advances',
]);
$html = ob_get_clean();
assert(strpos($html, 'Give / Request Advance') !== false, 'Give form present');
assert(strpos($html, 'Advance History')        !== false, 'History table present');
assert(strpos($html, 'Requested')              !== false, 'Stat chip: Requested');
assert(strpos($html, 'Outstanding Advance')    !== false, 'Stat chip: Outstanding');
assert(strpos($html, url("salesmen/$id/advances")) !== false, 'Form posts to correct URL');
assert(strpos($html, 'class="on"')             !== false, 'Tab highlighted');
echo "PASS render: all sections and stat chips rendered correctly\n";

// --- 4. advances/index card link ---
ob_start();
$people = PeopleDirectory::allActive();
$rows = [];
foreach ($people as $p) { $rows[] = array_merge($p, AdvancesController::advanceStats($p)); }
view('advances/index', [
    'rows' => $rows, 'q' => '', 'kind' => 'all', 'designation' => '', 'designations' => [],
    'totalGiven' => 0, 'totalRecovered' => 0, 'totalOutstanding' => 0,
    'totalRequested' => 0, 'totalApproved' => 0, 'totalPeople' => count($rows),
    'title' => 'Advances',
]);
$idx = ob_get_clean();
assert(strpos($idx, url("salesmen/$id/advances")) !== false, 'Card links to advances tab');
echo "PASS index: salesman card correctly links to salesmen/{id}/advances\n";

// --- 5. CSS has delete icon rule ---
$css = file_get_contents(__DIR__ . '/../assets/css/app.css');
assert(strpos($css, 'btn-danger::before') !== false, 'CSS has btn-danger::before');
assert(strpos($css, 'mask-image') !== false, 'CSS has SVG trash icon mask');
assert(strpos($css, 'btn-delete-outline') !== false, 'CSS has btn-delete-outline');
echo "PASS CSS: delete icon rules present in app.css\n";

echo "\n=== ALL CHECKS PASSED ===\n";
echo "✓ Advances tab: working (route + render + card link)\n";
echo "✓ Delete icon: injected via CSS ::before on all .btn-danger buttons\n";
