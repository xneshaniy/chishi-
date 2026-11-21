<?php
$f = 'app/views/reports/hub.php';
$s = file_get_contents($f);
$needle = "['reports/pending-salary', 'Pending Salary', 'Employees with no payslip for the period.',";
$pos = strpos($s, $needle);
if ($pos === false) { echo "anchor not found\n"; exit(1); }
$lineEnd = strpos($s, "\n", $pos);
$insert = "    ['reports/maintenance', 'Maintenance & Repairs', 'Maintenance cost by day, month, year, machine, department or vendor; full history per machine.', 'x'],\n"
        . "    ['reports/fg-daily', 'Finished Product Daily', 'Opening, produced, issued, sold, returns, closing for each finished product.', 'x'],\n"
        . "    ['reports/raw-daily', 'Raw Material Daily', 'Opening, purchases, consumption in production, adjustments, closing.', 'x'],\n";
$s = substr($s, 0, $lineEnd + 1) . $insert . substr($s, $lineEnd + 1);
file_put_contents($f, $s);
echo "inserted\n";
