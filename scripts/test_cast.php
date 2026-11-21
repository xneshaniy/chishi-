<?php
header('Content-Type: text/plain');
require __DIR__ . '/config/config.php';
require __DIR__ . '/app/core/bootstrap.php';

try {
    $max = (int)db_val("SELECT MAX(CAST(SUBSTR(expense_no, 4) AS INTEGER)) FROM company_expenses WHERE expense_no LIKE 'CE-%'");
    echo "Query 1 (INTEGER): $max\n";
} catch (Throwable $e) {
    echo "Query 1 (INTEGER) ERROR: " . $e->getMessage() . "\n";
}

try {
    $max = (int)db_val("SELECT MAX(CAST(SUBSTR(expense_no, 4) AS SIGNED)) FROM company_expenses WHERE expense_no LIKE 'CE-%'");
    echo "Query 2 (SIGNED): $max\n";
} catch (Throwable $e) {
    echo "Query 2 (SIGNED) ERROR: " . $e->getMessage() . "\n";
}
