<?php
/** Run the canonical test battery, clearing the login rate limiter before each suite. */
$suites = [
    'functional.php' => 'functional 44',
    'extended.php' => 'extended 63',
    'notifications_test.php' => 'notifications 25',
    'claim_goods_test.php' => 'claim-goods 26',
    'purchase_approve_test.php' => 'purchase-approval 18',
    'allocation_test.php' => 'allocation 6',
    'maintenance_test.php' => 'maintenance 22',
    'salesman_bonus_test.php' => 'salesman-bonus 25',
    'payroll_partial_test.php' => 'payroll partial 14',
    'rm_packaging_test.php' => 'rm packaging 24',
    'labour_test.php' => 'labour 28',
    'team_test.php' => 'team 27',
    'capital_income_test.php' => 'capital/income 24',
    'staff_section_test.php' => 'staff section 21',
    'photo_e2e_test.php' => 'photo 38',
    'salesman_profile_test.php' => 'salesman profile 30',
    'jampur_test.php' => 'jampur 15',
    'costing_test.php' => 'costing 2',
    'employees_actions_e2e.php' => 'employee actions 19',
    'bonus_flow_e2e.php' => 'bonus approval flow 13',
    'employees_content_e2e.php' => 'employees pages 50',
    'salesman_payments_e2e.php' => 'salesman payments 51',
    'production_e2e.php' => 'production 47',
    'production_products_e2e.php' => 'four CTC products 24',
    'cash_tracking_e2e.php' => 'cash tracking 22',
];
function clear_attempts(): void {
    try { $db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite'); $db->exec('DELETE FROM login_attempts'); } catch (Throwable $e) { /* ignore */ }
}
foreach ($suites as $file => $label) {
    clear_attempts();
    $cmd = sprintf('php "%s" 2>&1', __DIR__ . DIRECTORY_SEPARATOR . $file);
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    $text = implode("\n", $out);
    $summary = preg_match('/(\d+)\s+tests?,\s+(\d+)\s+failures?/i', $text, $m) ? ($m[1] . ' tests, ' . $m[2] . ' failures')
        : (preg_match('/(\d+)\s+assertions/i', $text, $m2) ? $m2[1] . ' assertions' : trim(substr($text, -120)));
    printf("%-30s exit=%d  %s\n", $file, $code, $summary);
}
