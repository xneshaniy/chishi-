<?php
$file = __DIR__ . '/../app/controllers/DashboardController.php';
$content = file_get_contents($file);

$target = <<<'PHP'
        $rmAgg2 = db_one("SELECT COALESCE(SUM(po.qty_produced),0) AS used_today FROM production_orders po WHERE po.status = 'completed' AND date(po.completed_at) = ? AND product_id IN (SELECT id FROM products WHERE kind IN ('raw','packaging'))", [$today]);
        // Raw material consumed today = production_inputs joined to completed orders today on raw/packaging materials
        $rmUsedToday = (float)db_val("SELECT COALESCE(SUM(pi.qty),0) FROM production_inputs pi JOIN production_orders po ON po.id = pi.production_order_id JOIN products p ON p.id = pi.product_id WHERE po.status = 'completed' AND date(po.completed_at) = ? AND p.kind IN ('raw','packaging')", [$today]);
        $fgProducedToday = (float)db_val("SELECT COALESCE(SUM(po.qty_produced),0) FROM production_orders po WHERE po.status = 'completed' AND date(po.completed_at) = ?", [$today]);
        $fgIssuedToday = (float)db_val("SELECT COALESCE(SUM(sii.qty),0) FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE date(i.date) = ?", [$today]);
PHP;

$replacement = <<<'PHP'
        // Comprehensive today's factory calculations:
        $rmUsageToday = (float)db_val("SELECT COALESCE(SUM(qty_kg),0) FROM rm_daily_usage WHERE status = 'posted' AND usage_date = ?", [$today]);
        $rmProdToday  = (float)db_val("SELECT COALESCE(SUM(pi.qty),0) FROM production_inputs pi JOIN production_orders po ON po.id = pi.production_order_id JOIN products p ON p.id = pi.product_id WHERE po.status = 'completed' AND date(po.completed_at) = ? AND p.kind IN ('raw','packaging')", [$today]);
        $rmUsedToday = round($rmUsageToday + $rmProdToday, 3);

        $fgDailyToday = (float)db_val("SELECT COALESCE(SUM(dpi.qty),0) FROM daily_production_items dpi JOIN daily_production dp ON dp.id = dpi.run_id WHERE dp.status = 'posted' AND dp.date = ?", [$today]);
        $fgOrdersToday = (float)db_val("SELECT COALESCE(SUM(po.qty_produced),0) FROM production_orders po WHERE po.status = 'completed' AND date(po.completed_at) = ?", [$today]);
        $fgProducedToday = round($fgDailyToday + $fgOrdersToday, 3);
        $fgIssuedToday = (float)db_val("SELECT COALESCE(SUM(sii.qty),0) FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE date(i.date) = ?", [$today]);

        $salesCountToday = (int)db_val("SELECT COUNT(*) FROM sales_invoices WHERE status='posted' AND date = ? $smFilter", [$today]);
        $todayExpenses = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE status = 'posted' AND date = ?", [$today]);
        $todaySalaries = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM payroll_payments WHERE date = ?", [$today])
                       + (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_salary_payments WHERE date = ?", [$today]);
        $todayExpensesAndSalaries = round($todayExpenses + $todaySalaries, 2);
PHP;

$content = str_replace($target, $replacement, $content);

// Also update collectionsToday line
$targetColl = '$collectionsToday = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE date = ?", [$today]);';
$replColl = '$collectionsToday = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE date = ?", [$today]) + (float)db_val("SELECT COALESCE(SUM(credit),0) FROM salesman_ledger WHERE doc_type IN (\'payment\',\'receipt\') AND date = ?", [$today]);';
$content = str_replace($targetColl, $replColl, $content);

// And update compact call
$targetView = "view('dashboard/index', compact('today','salesToday'";
$replView = "view('dashboard/index', compact('today','salesToday','salesCountToday','todayExpensesAndSalaries'";
$content = str_replace($targetView, $replView, $content);

file_put_contents($file, $content);
echo "DashboardController updated successfully\n";
