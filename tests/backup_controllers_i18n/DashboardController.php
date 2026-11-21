<?php
final class DashboardController {
    public static function index(): void {
        $today = today();
        $monthStart = date('Y-m-01');
        $yearStart = date('Y-01-01');
        $salesmanScope = is_salesman_only();
        $mySm = $salesmanScope ? my_salesman_id() : null;
        $smFilter = ($salesmanScope && $mySm) ? ' AND salesman_id = ' . (int)$mySm . ' ' : '';

        // Sales KPIs (posted only)
        $salesToday = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE status='posted' AND date = ? $smFilter", [$today]);
        $salesMonth = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE status='posted' AND date >= ? $smFilter", [$monthStart]);
        $salesYear  = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE status='posted' AND date >= ? $smFilter", [$yearStart]);
        $invoicesMonth = (int)db_val("SELECT COUNT(*) FROM sales_invoices WHERE status='posted' AND date >= ? $smFilter", [$monthStart]);

        // Cash & bank balances = opening + GL movement
        $cashBank = db_all("SELECT a.*, COALESCE(g.net,0) AS gl_net FROM cash_bank_accounts a
            LEFT JOIN (SELECT account_id, SUM(debit - credit) AS net FROM journal_lines GROUP BY account_id) g
              ON g.account_id = a.gl_account_id WHERE a.is_active = 1 ORDER BY a.type, a.id");
        $cashTotal = 0.0; $bankTotal = 0.0;
        foreach ($cashBank as &$a) {
            $a['balance'] = round((float)$a['opening_balance'] + (float)$a['gl_net'], 2);
            if ($a['type'] === 'cash') $cashTotal += $a['balance']; else $bankTotal += $a['balance'];
        }
        unset($a);

        // AR / AP
        $receivable = (float)db_val("SELECT COALESCE(SUM(l.debit) - SUM(l.credit),0) FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id JOIN chart_of_accounts a ON a.id = l.account_id WHERE e.status IN ('posted','reversed') AND a.system_key = 'AR'");
        $payable = (float)db_val("SELECT COALESCE(SUM(l.credit) - SUM(l.debit),0) FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id JOIN chart_of_accounts a ON a.id = l.account_id WHERE e.status IN ('posted','reversed') AND a.system_key = 'AP'");

        // Plus opening balances stored on customer/supplier masters (until posted via Opening Balances wizard)
        $custOpen = (float)db_val("SELECT COALESCE(SUM(CASE WHEN opening_type='debit' THEN opening_balance ELSE -opening_balance END),0) FROM customers WHERE is_active=1");
        $supOpen = (float)db_val("SELECT COALESCE(SUM(CASE WHEN opening_type='credit' THEN opening_balance ELSE -opening_balance END),0) FROM suppliers WHERE is_active=1");

        // Production & stock
        $producedMonth = (float)db_val("SELECT COALESCE(SUM(qty_produced),0) FROM production_orders WHERE status='completed' AND completed_at >= ?", [$monthStart . ' 00:00:00']);
        $openOrders = (int)db_val("SELECT COUNT(*) FROM production_orders WHERE status IN ('draft','released')");
        $stockValue = Inventory::stockValue();
        $lowStock = Inventory::lowStock();

        // Raw material vs finished-goods separation — separate dashboards under the hood (company-custody only)
        $rmAgg = db_one("SELECT COALESCE(SUM(x.qty),0) AS qty, COALESCE(SUM(x.value),0) AS value
                         FROM (SELECT sb.product_id, SUM(sb.qty) AS qty, SUM(sb.qty * sb.avg_cost) AS value FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman' GROUP BY sb.product_id) x
                         JOIN products p ON p.id = x.product_id WHERE p.kind IN ('raw','packaging')");
        $fgAgg = db_one("SELECT COALESCE(SUM(x.qty),0) AS qty, COALESCE(SUM(x.value),0) AS value
                         FROM (SELECT sb.product_id, SUM(sb.qty) AS qty, SUM(sb.qty * sb.avg_cost) AS value FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman' GROUP BY sb.product_id) x
                         JOIN products p ON p.id = x.product_id WHERE p.kind = 'finished'");
        $rmAgg2 = db_one("SELECT COALESCE(SUM(po.qty_produced),0) AS used_today FROM production_orders po WHERE po.status = 'completed' AND date(po.completed_at) = ? AND product_id IN (SELECT id FROM products WHERE kind IN ('raw','packaging'))", [$today]);
        // Raw material consumed today = production_inputs joined to completed orders today on raw/packaging materials
        $rmUsedToday = (float)db_val("SELECT COALESCE(SUM(pi.qty),0) FROM production_inputs pi JOIN production_orders po ON po.id = pi.production_order_id JOIN products p ON p.id = pi.product_id WHERE po.status = 'completed' AND date(po.completed_at) = ? AND p.kind IN ('raw','packaging')", [$today]);
        $fgProducedToday = (float)db_val("SELECT COALESCE(SUM(po.qty_produced),0) FROM production_orders po WHERE po.status = 'completed' AND date(po.completed_at) = ?", [$today]);
        $fgIssuedToday = (float)db_val("SELECT COALESCE(SUM(sii.qty),0) FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE date(i.date) = ?", [$today]);
        $rmLow = (int)db_val("SELECT COUNT(*) FROM products p WHERE p.is_active = 1 AND p.reorder_level > 0 AND p.kind IN ('raw','packaging') AND COALESCE((SELECT SUM(qty) FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman' WHERE sb.product_id = p.id),0) <= p.reorder_level");
        $fgLow = (int)db_val("SELECT COUNT(*) FROM products p WHERE p.is_active = 1 AND p.reorder_level > 0 AND p.kind = 'finished' AND COALESCE((SELECT SUM(qty) FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman' WHERE sb.product_id = p.id),0) <= p.reorder_level");
        $rmStock = (float)$rmAgg['qty']; $rmValue = (float)$rmAgg['value'];
        $fgStock = (float)$fgAgg['qty']; $fgValue = (float)$fgAgg['value'];
        $mntMonth = (float)db_val("SELECT COALESCE(SUM(total_cost),0) FROM maintenance_records WHERE status = 'posted' AND date(problem_date) >= ?", [$monthStart]);
        $mntPending = (int)db_val("SELECT COUNT(*) FROM maintenance_records WHERE status = 'pending'");
        $mntTotal = (float)db_val("SELECT COALESCE(SUM(total_cost),0) FROM maintenance_records");

        // Expenses & profit this month
        $expensesMonth = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE status='posted' AND date >= ?", [$monthStart]);
        $pl = Accounting::profitAndLoss($monthStart, $today);
        $cogsMonth = (float)db_val("SELECT COALESCE(SUM(cost_total),0) FROM sales_invoices WHERE status='posted' AND date >= ?", [$monthStart]);

        // Salesman performance (this month)
        $salesmen = db_all("SELECT s.id, s.name, s.code,
            COALESCE(SUM(i.total),0) AS sales, COUNT(i.id) AS invoices
            FROM salesmen s LEFT JOIN sales_invoices i ON i.salesman_id = s.id AND i.status='posted' AND i.date >= ?
            WHERE s.is_active = 1 GROUP BY s.id ORDER BY sales DESC LIMIT 8", [$monthStart]);

        // Collections today (receipts)
        $collectionsToday = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE date = ?", [$today]);

        // Overdue credit invoices
        $overdue = db_all("SELECT i.id, i.invoice_no, i.date, i.due_date, i.total, c.name AS customer
            FROM sales_invoices i JOIN customers c ON c.id = i.customer_id
            WHERE i.status='posted' AND i.payment_type='credit' AND i.due_date IS NOT NULL AND i.due_date < ?
            " . ($smFilter ? 'AND i.salesman_id = ' . (int)$mySm . ' ' : '') . "
            ORDER BY i.due_date ASC LIMIT 10", [$today]);

        // Real-time alerts — evaluated on dashboard load, deduped per record per day
        try { Notifications::checkLowStock(); } catch (Throwable $e) { error_log('low stock notify: ' . $e->getMessage()); }
        try { Notifications::checkOverdue(); } catch (Throwable $e) { error_log('overdue notify: ' . $e->getMessage()); }
        $pendingApprovals = Approvals::pendingCount();

        // Low stock shown only to stock/sales roles, AR etc to finance roles — keep dashboard role-agnostic but simple
        $title = 'Dashboard';
        view('dashboard/index', compact('today','salesToday','salesMonth','salesYear','invoicesMonth','cashBank','cashTotal','bankTotal','receivable','payable','custOpen','supOpen','producedMonth','openOrders','stockValue','lowStock','expensesMonth','pl','cogsMonth','salesmen','collectionsToday','overdue','monthStart','salesmanScope','rmStock','rmValue','rmUsedToday','rmLow','fgStock','fgValue','fgProducedToday','fgIssuedToday','fgLow','mntMonth','mntPending','mntTotal','pendingApprovals'));
    }
}
