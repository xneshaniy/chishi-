<?php
$lang = require __DIR__ . '/../app/lang.php';
$checks = ["Today's Sales", 'Collections Today', 'Dashboard', 'Actions', 'Search', 'Save', 'Trial Balance', "Salesman Outstanding (owed to company)", 'Attendance', 'Cash in Hand', 'Weekly', 'Hourly', 'Employee Loans'];
foreach ($checks as $k) {
    echo "en=[", $k, "]\n  ur=[", $lang['ur'][$k] ?? 'MISSING', "]\n  ro=[", $lang['roman'][$k] ?? 'MISSING', "]\n";
}
