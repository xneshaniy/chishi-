<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

$sm = db_one('SELECT * FROM salesmen WHERE is_active = 1 LIMIT 1');
if (!$sm) {
    echo "No salesman found\n";
    exit(1);
}
echo "Testing salesman id: " . $sm['id'] . "\n";

if (!function_exists('route_match')) {
    function route_match(array $map, string $route): ?array {
        if (isset($map[$route])) return [$map[$route], []];
        foreach ($map as $pattern => $handler) {
            $pp = explode('/', $pattern);
            $rp = explode('/', $route);
            if (count($pp) !== count($rp)) continue;
            $params = [];
            $ok = true;
            foreach ($pp as $i => $seg) {
                if (preg_match('/^\{(\w+)\}$/', $seg, $m)) { $params[$m[1]] = $rp[$i]; }
                elseif ($seg !== $rp[$i]) { $ok = false; break; }
            }
            if ($ok) return [$handler, $params];
        }
        return null;
    }
}

// 1. Test route match for salesmen/{id}
$routes = require __DIR__ . '/../app/routes.php';
$m1 = route_match($routes, 'salesmen/' . $sm['id']);
assert($m1 !== null, 'salesmen/{id} must match route');
assert($m1[0][1] === 'account', 'salesmen/{id} must route to account');
echo "PASS: salesmen/{id} matches account\n";

// 2. Test route match for salesmen/{id}/advances
$m2 = route_match($routes, 'salesmen/' . $sm['id'] . '/advances');
assert($m2 !== null, 'salesmen/{id}/advances must match route');
assert($m2[0][1] === 'advances', 'salesmen/{id}/advances must route to advances');
echo "PASS: salesmen/{id}/advances matches advances\n";

// 3. Test Advances tab in sm_tabs
ob_start();
$tab = 'advances';
require __DIR__ . '/../app/views/partials/sm_tabs.php';
$tabsHtml = ob_get_clean();
assert(strpos($tabsHtml, url('salesmen/' . $id . '/advances')) !== false, 'sm_tabs must contain advances link');
assert(strpos($tabsHtml, 'class="on"') !== false, 'sm_tabs must highlight active tab');
echo "PASS: sm_tabs contains Advances tab and highlights when active\n";

// 4. Test Advances view render
$stats = AdvancesController::advanceStats(['id' => $sm['id'], 'person_type' => 'salesman']);
$monthly = (float)($sm['salary'] ?? 0);
$accounts = db_all('SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name');
$advances = db_all('SELECT a.*, cba.name AS account_name FROM salesman_salary_advances a LEFT JOIN cash_bank_accounts cba ON cba.id = a.account_id WHERE a.salesman_id = ? ORDER BY a.date DESC, a.id DESC', [$sm['id']]);
ob_start();
view('salesmen/advances', [
    'sm' => $sm,
    'id' => $sm['id'],
    'stats' => $stats,
    'monthly' => $monthly,
    'accounts' => $accounts,
    'advances' => $advances,
    'title' => 'Advances',
]);
$advView = ob_get_clean();
assert(strpos($advView, 'Salary Advance Management') !== false, 'view renders header');
assert(strpos($advView, 'Give / Request Advance') !== false, 'view renders form');
assert(strpos($advView, 'Advance History') !== false, 'view renders history');
echo "PASS: Advances view renders all sections correctly\n";

// 5. Test voucher route and render
ob_start();
$_GET['id'] = $sm['id'];
AdvancesController::salesmanVoucher();
$voucherHtml = ob_get_clean();
assert(strlen($voucherHtml) > 100, 'voucher renders successfully');
echo "PASS: Voucher renders successfully\n";

// 6. Test advances/index.php card link
ob_start();
$people = PeopleDirectory::allActive();
$rows = [];
$totalGiven = 0.0; $totalRecovered = 0.0; $totalOutstanding = 0.0; $totalRequested = 0.0; $totalApproved = 0.0;
foreach ($people as $p) {
    $st = AdvancesController::advanceStats($p);
    $rows[] = array_merge($p, $st);
}
view('advances/index', [
    'rows' => $rows,
    'q' => '', 'kind' => 'all', 'designation' => '',
    'designations' => [],
    'totalGiven' => $totalGiven, 'totalRecovered' => $totalRecovered, 'totalOutstanding' => $totalOutstanding,
    'totalRequested' => $totalRequested, 'totalApproved' => $totalApproved,
    'totalPeople' => count($rows),
    'title' => 'Advances',
]);
$indexHtml = ob_get_clean();
assert(strpos($indexHtml, url('salesmen/' . $sm['id'] . '/advances')) !== false, 'advances/index links salesman to advances tab');
echo "PASS: advances/index card link directs to salesmen/{id}/advances\n";

echo "\nALL ADVANCES VERIFICATIONS PASSED!\n";
