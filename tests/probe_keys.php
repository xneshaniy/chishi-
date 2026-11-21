<?php
$ur = require __DIR__ . '/dict_ur_generated.php';
$rm = require __DIR__ . '/dict_roman_generated.php';
$keys = ['Claim not found.', 'Invoice failed: ', 'Amount must be positive.', 'Select the cash/bank account to pay from.', 'Only Owner/Admin can approve purchase orders.', 'Payment failed: ', 'Failed: ', 'Reversal failed: ', 'Consumption failed: ', 'Return failed: ', 'Purchase failed: ', 'The invoice must be approved and posted before it can be reversed.'];
foreach ($keys as $k) {
    echo str_pad($k, 70), ' ur=', isset($ur[$k]) ? 'Y' : '-', ' rm=', isset($rm[$k]) ? 'Y' : '-', "\n";
}
