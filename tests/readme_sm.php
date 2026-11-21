<?php
$f = 'README.md'; $s = file_get_contents($f);
$pairs = [
    'php tests\\rm_packaging_test.php          # raw-material purchasing' =>
    "php tests\\salesman_salary_test.php      # salesman account: salary, advances, expenses, settlement, TB balanced (9 assertions)\nphp tests\\rm_packaging_test.php          # raw-material purchasing",
    'tests (299 assertions passing' => 'tests (308 assertions passing',
    '+ 24 RM purchasing/packaging E2E tests:' => '+ 24 RM purchasing/packaging E2E tests + 9 salesman account E2E tests:',
    '- payroll payroll (payroll_partial_test.php' => '- payroll payroll (payroll_partial_test.php',
    'payroll partial-payment E2E tests + 24 RM' =>
    'payroll partial-payment E2E tests + 24 RM',
];
foreach ($pairs as $old => $new) {
    $s = str_replace($old, $new, $s, $n); echo str_pad((string)$n, 4), " replaced\n";
}
// Update test inventory listing line
$s = str_replace(
    'rm_packaging_test, payroll_partial_test (14), rm_packaging_test (24)',
    'rm_packaging_test (24), payroll_partial_test (14), salesman_salary_test (9)',
    $s, $n);
echo str_pad((string)$n, 4), " inventory\n";
file_put_contents($f, $s);
