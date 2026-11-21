<?php
$f = 'app/views/payroll/payroll.php';
$lines = file($f);
$n = 0;
foreach ($lines as $i => $line) {
    if (str_contains($line, 'Monthly/fixed-contract staff get their full rate')) {
        $lines[$i] = "    <p class=\"help\">Monthly/fixed-contract staff get their full rate. Weekly = rate × present-weeks (6 working days/week). Daily = days × rate. Hourly = hours × rate (attendance captures hours). Overtime = hours beyond 8/day at 1.5× (or the employee's OT rate). <strong>Advances</strong> deduct automatically from this salary (full outstanding, or the employee's monthly deduction cap). <strong>Due loan installments</strong> due in the period deduct automatically. Loans never touch advance accounting. Nothing posts until Owner/Admin approves each payment.</p>\n";
        $n++;
    }
}
file_put_contents($f, implode('', $lines));
echo "replaced: $n\n";
