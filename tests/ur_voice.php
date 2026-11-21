<?php
$ur = require __DIR__ . '/dict_ur_generated.php';
$keys = ['Save','Cancel','Dashboard','Sales','Purchases','Stock','Production','Employees','Expenses','Salary','Payment','Report','Invoice','Customers','Supplier','Warehouse','Quantity','Date','Status','Approve','New','Amount','Total','Name','Company','Address','Phone','Search','Print','Edit','Delete','Add','Balance','Branch','Role','User','Backup','Settings','Closing','Opening','Bonus','Commission','Advance','Loan','Net Pay','Post','Draft','Receipt','Overdue','Collection','Product','Category','Unit','Hours','Designation','Department','Driver','Vehicle','Fraud'];
foreach ($keys as $k) {
    foreach ($ur as $ek => $v) if (strcasecmp($ek, $k) === 0) echo str_pad($k, 14), ' => ', $v, "\n";
}
