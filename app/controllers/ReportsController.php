<?php
final class ReportsController {

    /* ============ Salesman selling BELOW issue rate — discount/loss report ============ */
    public static function priceDifference(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $rows = db_all("SELECT i.invoice_no, i.date, COALESCE(c.name, 'Walk-in') AS customer, s.name AS salesman, p.sku, p.name AS product,
                       ii.qty, p.issue_rate, ii.unit_price AS selling_price,
                       ROUND((p.issue_rate - ii.unit_price) * ii.qty, 2) AS difference, ii.is_bonus
                FROM sales_invoice_items ii
                JOIN sales_invoices i ON i.id = ii.invoice_id
                JOIN products p ON p.id = ii.product_id
                LEFT JOIN customers c ON c.id = i.customer_id
                LEFT JOIN salesmen s ON s.id = i.salesman_id
                WHERE i.status = 'posted' AND i.date BETWEEN ? AND ? AND ii.is_bonus = 0 AND p.issue_rate > 0
                ORDER BY difference DESC", [$from, $to]);
        $loss = 0.0; $gain = 0.0;
        foreach ($rows as &$r) { $d = (float)$r['difference']; if ($d > 0) $loss += $d; else $gain += -$d; $r['difference'] = $d; }
        unset($r);
        if (($_GET['export'] ?? '') === 'csv') {
            StockController::sendCsv('price_difference.csv', ['Invoice','Date','Customer','Salesman','SKU','Product','Qty (Packets)','Issue Rate','Selling Price','Difference'],
                array_map(fn($r) => [$r['invoice_no'], $r['date'], $r['customer'], $r['salesman'], $r['sku'], $r['product'], $r['qty'], $r['issue_rate'], $r['selling_price'], $r['difference']], $rows));
        }
        view('reports/price_difference', ['from' => $from, 'to' => $to, 'rows' => $rows, 'loss' => round($loss, 2), 'gain' => round($gain, 2), 'title' => 'Salesman Price Difference (Issue vs Selling)']);
    }

    /* ============ Bonus stock report — customer / salesman / purchase bonus ============ */
    public static function bonusReport(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        // Salesman issuance with bonus quantities
        $salesmanBonus = db_all("SELECT s.name AS salesman, COUNT(DISTINCT si.id) AS issues,
                                        COALESCE(SUM(sii.paid_qty),0) AS paid_qty, COALESCE(SUM(sii.bonus_qty),0) AS bonus_qty,
                                        COALESCE(SUM(sii.line_payable),0) AS payable, COALESCE(SUM(sii.bonus_cost),0) AS bonus_cost
                                 FROM salesman_issue_items sii
                                 JOIN salesman_issues si ON si.id = sii.issue_id AND si.issue_type IN ('credit_issue','transfer')
                                 JOIN salesmen s ON s.id = si.salesman_id
                                 WHERE si.date BETWEEN ? AND ?
                                 GROUP BY s.id ORDER BY bonus_qty DESC", [$from, $to]);
        // Customer bonus lines (free goods on sales invoices)
        $customerBonus = db_all("SELECT p.sku, p.name AS product, i.bonus_scheme, COUNT(*) AS lines, COALESCE(SUM(ii.qty),0) AS qty, COALESCE(SUM(ii.cost_total),0) AS cost
                                 FROM sales_invoice_items ii
                                 JOIN sales_invoices i ON i.id = ii.invoice_id
                                 JOIN products p ON p.id = ii.product_id
                                 WHERE ii.is_bonus = 1 AND i.status = 'posted' AND i.date BETWEEN ? AND ?
                                 GROUP BY p.id, i.bonus_scheme
                                 ORDER BY qty DESC", [$from, $to]);
        // Purchase bonus (supplier free goods)
        $purchaseBonus = db_all("SELECT p.sku, p.name AS product, COUNT(*) AS lines, COALESCE(SUM(pi.qty),0) AS qty
                                 FROM purchase_items pi
                                 JOIN purchase_invoices i ON i.id = pi.invoice_id
                                 JOIN products p ON p.id = pi.product_id
                                 WHERE pi.is_bonus = 1 AND i.status = 'posted' AND i.date BETWEEN ? AND ?
                                 GROUP BY p.id", [$from, $to]);
        $summary = [
            'salesman_bonus_qty' => array_sum(array_column($salesmanBonus, 'bonus_qty')),
            'salesman_bonus_cost' => array_sum(array_column($salesmanBonus, 'bonus_cost')),
            'customer_bonus_qty' => array_sum(array_column($customerBonus, 'qty')),
            'customer_bonus_cost' => array_sum(array_column($customerBonus, 'cost')),
            'purchase_bonus_qty' => array_sum(array_column($purchaseBonus, 'qty')),
        ];
        if (($_GET['export'] ?? '') === 'csv') {
            $rows = [];
            foreach ($salesmanBonus as $r) $rows[] = ['salesman_issue', $r['salesman'], 'paid ' . $r['paid_qty'], 'bonus ' . $r['bonus_qty'], 'payable ' . $r['payable'], 'bonus cost ' . $r['bonus_cost']];
            foreach ($customerBonus as $r) $rows[] = ['customer_bonus', ($r['bonus_scheme'] ?: 'no scheme') . ' — ' . $r['product'], 'lines ' . $r['lines'], 'qty ' . $r['qty'], 'cost ' . $r['cost'], ''];
            StockController::sendCsv('bonus_report.csv', ['Source','Detail','Metric1','Metric2','Metric3','Metric4'], $rows);
        }
        view('reports/bonus_report', ['from' => $from, 'to' => $to, 'salesmanBonus' => $salesmanBonus, 'customerBonus' => $customerBonus, 'purchaseBonus' => $purchaseBonus, 'summary' => $summary, 'title' => 'Bonus & Free Stock Report']);
    }

    /* ============ Employee salary expense report ============ */
    public static function salaryExpense(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $rows = db_all("SELECT e.code, e.name, e.department,
                               COUNT(p.id) AS payslips,
                               COALESCE(SUM(p.basic),0) AS basic,
                               COALESCE(SUM(p.overtime),0) AS overtime,
                               COALESCE(SUM(p.advances_deducted),0) AS advances,
                               COALESCE(SUM(p.loan_deduction),0) AS loans,
                               COALESCE(SUM(p.net_pay),0) AS net_paid
                        FROM payroll p JOIN employees e ON e.id = p.employee_id
                        WHERE p.status = 'paid' AND p.period_start BETWEEN ? AND ?
                        GROUP BY p.employee_id ORDER BY net_paid DESC", [$from, $to]);
        $sum = array_sum(array_column($rows, 'net_paid'));
        if (($_GET['export'] ?? '') === 'csv') {
            StockController::sendCsv('salary_expense.csv', ['Code','Employee','Department','Payslips','Basic','Overtime','Advances Deducted','Loan Deducted','Net Paid'],
                array_map(fn($r) => [$r['code'], $r['name'], $r['department'], $r['payslips'], $r['basic'], $r['overtime'], $r['advances'], $r['loans'], $r['net_paid']], $rows));
        }
        view('reports/salary_expense', ['from' => $from, 'to' => $to, 'rows' => $rows, 'sum' => $sum, 'title' => 'Employee Salary Expense']);
    }

    /* ============ Pending salary report ============ */
    public static function pendingSalary(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', date('Y-m-t'));
        // Employees with no payslip row for the period
        $rows = db_all("SELECT e.code, e.name, e.salary_type, e.salary_rate, e.department, e.designation
                        FROM employees e
                        WHERE e.is_active = 1
                          AND NOT EXISTS (SELECT 1 FROM payroll p WHERE p.employee_id = e.id AND p.period_start = ? AND p.period_end = ?)
                        ORDER BY e.salary_type, e.name", [$from, $to]);
        if (($_GET['export'] ?? '') === 'csv') {
            StockController::sendCsv('pending_salary.csv', ['Code','Name','Type','Rate','Department','Designation'], array_map(fn($r) => [$r['code'], $r['name'], $r['salary_type'], $r['salary_rate'], $r['department'], $r['designation']], $rows));
        }
        view('reports/pending_salary', ['from' => $from, 'to' => $to, 'rows' => $rows, 'title' => 'Pending Salary — No Payslip Yet']);
    }

    public static function hub(): void {
        view('reports/hub', ['title' => 'Reports Hub']);
    }

    public static function dailySales(): void {
        $date = inp('date', today());
        $rows = db_all("SELECT i.*, COALESCE(c.name, 'Walk-in') AS customer, s.name AS salesman FROM sales_invoices i LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN salesmen s ON s.id = i.salesman_id WHERE i.date = ? AND i.status = 'posted' ORDER BY i.id", [$date]);
        $totals = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t, COALESCE(SUM(tax),0) AS x, COALESCE(SUM(cost_total),0) AS cost FROM sales_invoices WHERE date = ? AND status='posted'", [$date]);
        $collections = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE date = ?", [$date]);
        if (($_GET['export'] ?? '') === 'csv') {
            StockController::sendCsv("daily_sales_$date.csv", ['Invoice','Type','Customer','Salesman','Subtotal','Discount','Tax','Total','Cost'],
                array_map(fn($r) => [$r['invoice_no'], $r['payment_type'], $r['customer'], $r['salesman'], $r['subtotal'], $r['discount'], $r['tax'], $r['total'], $r['cost_total']], $rows));
        }
        view('reports/daily_sales', ['date' => $date, 'rows' => $rows, 'totals' => $totals, 'collections' => $collections, 'title' => 'Daily Sales']);
    }

    public static function salesSummary(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today()); $group = inp('group', 'product');
        $rows = [];
        switch ($group) {
            case 'salesman':
                $rows = db_all("SELECT COALESCE(s.name, 'Counter / House') AS label, COUNT(i.id) AS invoices, COALESCE(SUM(i.total),0) AS total, COALESCE(SUM(i.cost_total),0) AS cost
                    FROM sales_invoices i LEFT JOIN salesmen s ON s.id = i.salesman_id WHERE i.status='posted' AND i.date BETWEEN ? AND ? GROUP BY s.id ORDER BY total DESC", [$from, $to]);
                break;
            case 'area':
                $rows = db_all("SELECT COALESCE(c.area, 'Walk-in / Counter') AS label, COUNT(i.id) AS invoices, COALESCE(SUM(i.total),0) AS total, COALESCE(SUM(i.cost_total),0) AS cost
                    FROM sales_invoices i LEFT JOIN customers c ON c.id = i.customer_id WHERE i.status='posted' AND i.date BETWEEN ? AND ? GROUP BY c.area ORDER BY total DESC", [$from, $to]);
                break;
            case 'customer':
                $rows = db_all("SELECT COALESCE(c.name, 'Walk-in / Counter') AS label, COUNT(i.id) AS invoices, COALESCE(SUM(i.total),0) AS total, COALESCE(SUM(i.cost_total),0) AS cost
                    FROM sales_invoices i LEFT JOIN customers c ON c.id = i.customer_id WHERE i.status='posted' AND i.date BETWEEN ? AND ? GROUP BY i.customer_id ORDER BY total DESC", [$from, $to]);
                break;
            default: // product
                $rows = db_all("SELECT p.sku AS label, p.name, COALESCE(SUM(ii.qty),0) AS qty, COALESCE(SUM(CASE WHEN ii.is_bonus=0 THEN ii.line_total ELSE 0 END),0) AS total, COALESCE(SUM(ii.cost_total),0) AS cost, COALESCE(SUM(CASE WHEN ii.is_bonus=1 THEN ii.qty ELSE 0 END),0) AS bonus_qty
                    FROM sales_invoice_items ii JOIN sales_invoices i ON i.id = ii.invoice_id JOIN products p ON p.id = ii.product_id WHERE i.status='posted' AND i.date BETWEEN ? AND ? GROUP BY ii.product_id ORDER BY total DESC", [$from, $to]);
        }
        if (($_GET['export'] ?? '') === 'csv') {
            if ($group === 'product') StockController::sendCsv('sales_summary.csv', ['SKU','Product','Qty Sold (Packets)','Bonus Qty (Packets)','Sales','Cost'], array_map(fn($r) => [$r['label'], $r['name'], $r['qty'], $r['bonus_qty'], $r['total'], $r['cost']], $rows));
            else StockController::sendCsv('sales_summary.csv', ['Group','Invoices','Sales','Cost'], array_map(fn($r) => [$r['label'], $r['invoices'], $r['total'], $r['cost']], $rows));
        }
        view('reports/sales_summary', ['from' => $from, 'to' => $to, 'group' => $group, 'rows' => $rows, 'title' => 'Sales Summary']);
    }

    public static function collections(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $rows = db_all("SELECT r.*, COALESCE(c.name, r.contact_name) AS customer, a.name AS account FROM receipts r LEFT JOIN customers c ON c.id = r.customer_id JOIN cash_bank_accounts a ON a.id = r.account_id WHERE r.date BETWEEN ? AND ? ORDER BY r.date, r.id", [$from, $to]);
        $sum = db_val("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE date BETWEEN ? AND ?", [$from, $to]);
        if (($_GET['export'] ?? '') === 'csv') {
            StockController::sendCsv('collections.csv', ['Receipt','Date','Customer','Account','Reference','Amount'], array_map(fn($r) => [$r['receipt_no'], $r['date'], $r['customer'], $r['account'], $r['reference'], $r['amount']], $rows));
        }
        view('reports/collections', ['from' => $from, 'to' => $to, 'rows' => $rows, 'sum' => $sum, 'title' => 'Collection Report']);
    }

    public static function customerAging(): void {
        $aging = Reporting::aging('customer');
        $customers = [];
        foreach ($aging['invoices'] as $inv) {
            $name = db_val('SELECT name FROM customers WHERE id = ?', [$inv['party_id']]);
            $customers[$inv['party_id']]['name'] = $name;
            $customers[$inv['party_id']]['total'] = ($customers[$inv['party_id']]['total'] ?? 0) + $inv['outstanding'];
            $customers[$inv['party_id']]['invoices'][] = $inv;
        }
        uasort($customers, fn($a, $b) => $b['total'] <=> $a['total']);
        view('reports/customer_aging', ['aging' => $aging, 'customers' => $customers, 'title' => 'Customer Aging']);
    }

    public static function supplierAging(): void {
        $aging = Reporting::aging('supplier');
        $suppliers = [];
        foreach ($aging['invoices'] as $inv) {
            $name = db_val('SELECT name FROM suppliers WHERE id = ?', [$inv['party_id']]);
            $suppliers[$inv['party_id']]['name'] = $name;
            $suppliers[$inv['party_id']]['total'] = ($suppliers[$inv['party_id']]['total'] ?? 0) + $inv['outstanding'];
            $suppliers[$inv['party_id']]['invoices'][] = $inv;
        }
        uasort($suppliers, fn($a, $b) => $b['total'] <=> $a['total']);
        view('reports/supplier_aging', ['aging' => $aging, 'suppliers' => $suppliers, 'title' => 'Supplier Aging']);
    }

    public static function stockValue(): void {
        $rows = db_all("SELECT w.code, w.name, w.type, COALESCE(SUM(b.qty),0) AS qty, COALESCE(SUM(b.qty * b.avg_cost),0) AS value, COUNT(*) AS lines
            FROM warehouses w LEFT JOIN stock_balances b ON b.warehouse_id = w.id WHERE w.is_active = 1 GROUP BY w.id ORDER BY value DESC");
        $grand = array_sum(array_column($rows, 'value'));
        $byKind = db_all("SELECT p.kind, COALESCE(SUM(b.qty),0) AS qty, COALESCE(SUM(b.qty * b.avg_cost),0) AS value
            FROM stock_balances b JOIN products p ON p.id = b.product_id GROUP BY p.kind");
        if (($_GET['export'] ?? '') === 'csv') {
            StockController::sendCsv('stock_value.csv', ['Warehouse','Type','Qty','Value'], array_map(fn($r) => [$r['name'], $r['type'], $r['qty'], round($r['value'], 2)], $rows));
        }
        view('reports/stock_value', ['rows' => $rows, 'grand' => $grand, 'byKind' => $byKind, 'title' => 'Stock Valuation']);
    }

    public static function expenseSummary(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $rows = db_all("SELECT c.name AS label, COUNT(e.id) AS n, COALESCE(SUM(e.amount),0) AS total FROM expenses e JOIN expense_categories c ON c.id = e.category_id WHERE e.status = 'posted' AND e.date BETWEEN ? AND ? GROUP BY c.id ORDER BY total DESC", [$from, $to]);
        $sum = array_sum(array_column($rows, 'total'));
        if (($_GET['export'] ?? '') === 'csv') {
            StockController::sendCsv('expense_summary.csv', ['Category','Vouchers','Amount'], array_map(fn($r) => [$r['label'], $r['n'], $r['total']], $rows));
        }
        view('reports/expense_summary', ['from' => $from, 'to' => $to, 'rows' => $rows, 'sum' => $sum, 'title' => 'Expense Summary']);
    }

    /** Company operating expenses — category/item/date/payment-mode breakdown with CSV. */
    public static function companyExpensesReport(): void {
        if (!Auth::can('company_expenses.view')) { http_response_code(403); exit('Forbidden'); }
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('from', '')) ? inp('from') : date('Y-m-01');
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('to', ''))   ? inp('to')   : today();
        $group = in_array(inp('group'), ['category','item','day','month','year'], true) ? inp('group') : 'category';
        $where = ['e.expense_date BETWEEN ? AND ?', "e.status IN ('pending','posted')"]; $params = [$from, $to];
        $catId = intv(inp('category_id')); if ($catId) { $where[] = 'e.category_id = ?'; $params[] = $catId; }
        $whereSql = implode(' AND ', $where);

        switch ($group) {
            case 'item':
                $rows = db_all("SELECT e.item_name AS label, COUNT(*) AS n, COALESCE(SUM(e.amount),0) AS total FROM company_expenses e WHERE $whereSql GROUP BY e.item_name ORDER BY total DESC", $params);
                $sum = array_sum(array_column($rows, 'total'));
                break;
            case 'day':
                $rows = db_all("SELECT e.expense_date AS label, COUNT(*) AS n, COALESCE(SUM(e.amount),0) AS total FROM company_expenses e WHERE $whereSql GROUP BY e.expense_date ORDER BY e.expense_date DESC", $params);
                $sum = array_sum(array_column($rows, 'total'));
                break;
            case 'month':
                $driver = (string)db()->getAttribute(PDO::ATTR_DRIVER_NAME);
                $ym = $driver === 'sqlite' ? "strftime('%Y-%m', e.expense_date)" : "DATE_FORMAT(e.expense_date, '%Y-%m')";
                $rows = db_all("SELECT $ym AS label, COUNT(*) AS n, COALESCE(SUM(e.amount),0) AS total FROM company_expenses e WHERE $whereSql GROUP BY label ORDER BY label DESC", $params);
                $sum = array_sum(array_column($rows, 'total'));
                break;
            case 'year':
                $driver = (string)db()->getAttribute(PDO::ATTR_DRIVER_NAME);
                $yy = $driver === 'sqlite' ? "strftime('%Y', e.expense_date)" : "YEAR(e.expense_date)";
                $rows = db_all("SELECT $yy AS label, COUNT(*) AS n, COALESCE(SUM(e.amount),0) AS total FROM company_expenses e WHERE $whereSql GROUP BY label ORDER BY label DESC", $params);
                $sum = array_sum(array_column($rows, 'total'));
                break;
            case 'category':
            default:
                $rows = db_all("SELECT c.name AS label, COUNT(*) AS n, COALESCE(SUM(e.amount),0) AS total FROM company_expenses e JOIN expense_categories c ON c.id = e.category_id WHERE $whereSql GROUP BY e.category_id ORDER BY total DESC", $params);
                $sum = array_sum(array_column($rows, 'total'));
                break;
        }
        $sum = (float)$sum;
        $cashBucket  = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM company_expenses e WHERE e.payment_type = 'cash' AND $whereSql", $params);
        $bankBucket  = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM company_expenses e WHERE e.payment_type = 'bank' AND $whereSql", $params);
        $pendingCnt  = (int)db_val("SELECT COUNT(*) FROM company_expenses WHERE status = 'pending'");
        $totalCnt    = (int)db_val("SELECT COUNT(*) FROM company_expenses e WHERE $whereSql", $params);
        $paidAmt     = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM company_expenses e WHERE e.status = 'posted' AND $whereSql", $params);
        $unpaidAmt   = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM company_expenses e WHERE e.status IN ('pending') AND $whereSql", $params);

        if (($_GET['export'] ?? '') === 'csv') {
            StockController::sendCsv('company_expenses.csv', ['Label','Vouchers','Amount'], array_map(fn($r) => [$r['label'], $r['n'], $r['total']], $rows));
        }
        $categories = db_all('SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name');

        view('reports/company_expenses', ['from' => $from, 'to' => $to, 'rows' => $rows, 'sum' => $sum, 'group' => $group, 'cash' => $cashBucket, 'bank' => $bankBucket, 'pendingCnt' => $pendingCnt, 'totalCnt' => $totalCnt, 'paid' => $paidAmt, 'unpaid' => $unpaidAmt, 'categories' => $categories, 'catId' => $catId, 'title' => __('Company Expenses Report')]);
    }

    /** Today at a glance — the seven things an everyday employee asks for, in plain words. */
    public static function giftsReport(): void {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('from', '')) ? inp('from') : date('Y-m-01');
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('to', ''))   ? inp('to')   : today();

        $rows = db_all("SELECT g.*, p.name AS product_name, p.sku, u.username AS recorded_by_username
            FROM gifts g LEFT JOIN products p ON p.id = g.product_id LEFT JOIN users u ON u.id = g.recorded_by
            WHERE g.gift_date BETWEEN ? AND ? AND g.status <> 'rejected' ORDER BY g.gift_date DESC, g.id DESC", [$from, $to]);

        $money = 0.0; $goodsCount = 0; $people = [];
        foreach ($rows as $r) {
            if ($r['gift_type'] === 'money') $money += (float)$r['amount'];
            else $goodsCount += (float)$r['qty_pieces'];
            $people[$r['recipient_name']] = ($people[$r['recipient_name']] ?? 0) + 1;
        }
        if ((string)db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $byMonth = db_all("SELECT strftime('%Y-%m', gift_date) AS ym, COALESCE(SUM(CASE WHEN gift_type='money' THEN amount ELSE 0 END),0) AS money, COUNT(*) AS c FROM gifts WHERE gift_date BETWEEN ? AND ? AND status <> 'rejected' GROUP BY ym ORDER BY ym DESC", [$from, $to]);
        } else {
            $byMonth = db_all("SELECT DATE_FORMAT(gift_date, '%Y-%m') AS ym, COALESCE(SUM(CASE WHEN gift_type='money' THEN amount ELSE 0 END),0) AS money, COUNT(*) AS c FROM gifts WHERE gift_date BETWEEN ? AND ? AND status <> 'rejected' GROUP BY ym ORDER BY ym DESC", [$from, $to]);
        }
        view('reports/gifts_report', ['rows' => $rows, 'from' => $from, 'to' => $to, 'money' => $money, 'goodsCount' => $goodsCount, 'people' => $people, 'byMonth' => $byMonth, 'title' => __('Gift Reports')]);
    }

    public static function simple(): void {
        $today = today();
        $todayPurchases = (float)db_val("SELECT COALESCE(SUM(total),0) FROM purchase_invoices WHERE date = ? AND status <> 'reversed'", [$today]);
        $todayPurchasesCount = (int)db_val("SELECT COUNT(*) FROM purchase_invoices WHERE date = ? AND status <> 'reversed'", [$today]);
        $todaySales = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE status = 'posted' AND date = ?", [$today]);
        $todaySalesCount = (int)db_val("SELECT COUNT(*) FROM sales_invoices WHERE status = 'posted' AND date = ?", [$today]);
        $todayExpenses = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE status = 'posted' AND date = ?", [$today]);
        $todayProduction = (float)db_val("SELECT COALESCE(SUM(qty_produced),0) FROM production_orders WHERE status = 'completed' AND date(completed_at) = ?", [$today]);
        $fgStock = (float)db_val("SELECT COALESCE(SUM(sb.qty),0) FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman' JOIN products p ON p.id = sb.product_id WHERE p.kind = 'finished'");
        $rawStock = (float)db_val("SELECT COALESCE(SUM(sb.qty),0) FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman' JOIN products p ON p.id = sb.product_id WHERE p.kind IN ('raw','packaging')");
        $stockValue = (float)db_val("SELECT COALESCE(SUM(sb.qty * sb.avg_cost),0) FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id AND w.type <> 'salesman'");
        $salesmanPayable = (float)db_val("SELECT COALESCE(SUM(debit - credit),0) FROM salesman_ledger");
        $salaryBalance = (float)db_val("SELECT COALESCE(SUM(net_pay - amount_paid),0) FROM payroll WHERE status IN ('draft','partial')");
        $receivable = (float)db_val("SELECT COALESCE(SUM(l.debit) - SUM(l.credit),0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id JOIN chart_of_accounts a ON a.id = l.account_id WHERE e.status IN ('posted','reversed') AND a.system_key = 'AR'");
        view('reports/simple', [
            'today' => $today,
            'todayPurchases' => $todayPurchases, 'todayPurchasesCount' => $todayPurchasesCount, 'todaySales' => $todaySales, 'todaySalesCount' => $todaySalesCount,
            'todayExpenses' => $todayExpenses, 'todayProduction' => $todayProduction,
            'fgStock' => $fgStock, 'rawStock' => $rawStock, 'stockValue' => $stockValue,
            'salesmanPayable' => $salesmanPayable, 'salaryBalance' => $salaryBalance, 'receivable' => $receivable,
            'title' => 'Simple Reports',
        ]);
    }

    public static function salesmanStock(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $rows = db_all("SELECT s.*, w.name AS warehouse,
            (SELECT COALESCE(SUM(b.qty),0) FROM stock_balances b JOIN products p ON p.id = b.product_id AND p.kind = 'finished' WHERE b.warehouse_id = s.warehouse_id) AS current_stock,
            (SELECT COALESCE(SUM(b.qty * b.avg_cost),0) FROM stock_balances b JOIN products p ON p.id = b.product_id AND p.kind = 'finished' WHERE b.warehouse_id = s.warehouse_id) AS current_value,
            (SELECT COUNT(*) FROM sales_invoices i WHERE i.salesman_id = s.id AND i.status='posted' AND i.date BETWEEN ? AND ?) AS invoices,
            (SELECT COALESCE(SUM(total),0) FROM sales_invoices i WHERE i.salesman_id = s.id AND i.status='posted' AND i.date BETWEEN ? AND ?) AS sales,
            (SELECT COALESCE(SUM(amount),0) FROM receipts r JOIN customers c ON c.id = r.customer_id WHERE c.salesman_id = s.id AND r.date BETWEEN ? AND ?) AS collected,
            (SELECT COALESCE(SUM(sii.paid_qty),0) FROM salesman_issue_items sii JOIN salesman_issues i2 ON i2.id = sii.issue_id WHERE i2.salesman_id = s.id AND date(i2.date) BETWEEN ? AND ? AND i2.status <> 'reversed') AS issued_paid,
            (SELECT COALESCE(SUM(sii.line_payable),0) FROM salesman_issue_items sii JOIN salesman_issues i2 ON i2.id = sii.issue_id WHERE i2.salesman_id = s.id AND date(i2.date) BETWEEN ? AND ? AND i2.status <> 'reversed') AS issued_payable,
            (SELECT COALESCE(SUM(sii.bonus_qty),0) FROM salesman_issue_items sii JOIN salesman_issues i2 ON i2.id = sii.issue_id WHERE i2.salesman_id = s.id AND date(i2.date) BETWEEN ? AND ? AND i2.status <> 'reversed') AS issued_bonus,
            (SELECT COALESCE(SUM(i2.total_bonus_cost),0) FROM salesman_issues i2 WHERE i2.salesman_id = s.id AND date(i2.date) BETWEEN ? AND ? AND i2.status <> 'reversed') AS issued_bonus_cost
            FROM salesmen s LEFT JOIN warehouses w ON w.id = s.warehouse_id WHERE s.is_active = 1 ORDER BY s.name", array_merge([$from, $to], [$from, $to], [$from, $to], [$from, $to], [$from, $to], [$from, $to], [$from, $to]));
        view('reports/salesman_stock', ['from' => $from, 'to' => $to, 'rows' => $rows, 'title' => 'Salesman Stock & Settlement']);
    }

    public static function dayBook(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $rows = db_all("SELECT e.*, (SELECT COALESCE(SUM(debit),0) FROM journal_lines WHERE entry_id = e.id) AS debit, (SELECT COALESCE(SUM(credit),0) FROM journal_lines WHERE entry_id = e.id) AS credit FROM journal_entries e WHERE e.date BETWEEN ? AND ? AND e.status IN ('posted','reversed') ORDER BY e.date, e.id", [$from, $to]);
        if (($_GET['export'] ?? '') === 'csv') {
            StockController::sendCsv('day_book.csv', ['Entry','Date','Type','Reference','Memo','Debit','Credit','Status'],
                array_map(fn($r) => [$r['entry_no'], $r['date'], $r['voucher_type'], ($r['reference_type'] ?? '') . '#' . ($r['reference_id'] ?? ''), $r['memo'], $r['debit'], $r['credit'], $r['status']], $rows));
        }
        view('reports/day_book', ['from' => $from, 'to' => $to, 'rows' => $rows, 'title' => 'Day Book']);
    }

    /**
     * Company Stock Daily — one page, three sections, no double counting.
     * §4 of the stock spec: factory daily position, each salesman van daily position
     * (opening + received incl. bonus split − sold − returned = closing), and combined company totals.
     */
    public static function companyStock(): void {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('date', '')) ? inp('date') : today();
        $smId = intv(inp('salesman_id'));
        $productId = intv(inp('product_id'));
        $vanCond = "w.type = 'salesman'";
        $vanFilter = ''; $vanParams = [];
        if ($smId) { $vanFilter = ' AND s.id = ?'; $vanParams[] = $smId; }

        /* ---------- Part 1: factory (non-salesman warehouses) daily position ---------- */
        $where = ["p.kind = 'finished'", "p.is_active = 1"]; $params = [];
        if ($productId) { $where[] = 'p.id = ?'; $params[] = $productId; }
        $factoryRows = db_all("SELECT p.id, p.sku, p.name, u.short AS unit_short,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) < ? AND sm.warehouse_id IN (SELECT id FROM warehouses WHERE type <> 'salesman')), 0) AS opening,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND sm.warehouse_id IN (SELECT id FROM warehouses WHERE type <> 'salesman') AND sm.type = 'production_in'), 0) AS produced,
                       COALESCE((SELECT -SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND sm.warehouse_id IN (SELECT id FROM warehouses WHERE type <> 'salesman') AND sm.type = 'salesman_issue_out'), 0) AS issued,
                       COALESCE((SELECT -SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND sm.warehouse_id IN (SELECT id FROM warehouses WHERE type <> 'salesman') AND sm.type = 'sale'), 0) AS sold,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND sm.warehouse_id IN (SELECT id FROM warehouses WHERE type <> 'salesman') AND sm.type = 'sale_return'), 0) AS returns,
                       COALESCE((SELECT SUM(qty) FROM stock_movements sm WHERE sm.product_id = p.id AND date(sm.created_at) = ? AND sm.warehouse_id IN (SELECT id FROM warehouses WHERE type <> 'salesman')), 0) AS net_today
                FROM products p LEFT JOIN units u ON u.id = p.unit_id WHERE " . implode(' AND ', $where) . " ORDER BY p.name", array_merge([$date, $date, $date, $date, $date, $date], $params));
        $factoryTot = ['opening' => 0, 'produced' => 0, 'issued' => 0, 'sold' => 0, 'returns' => 0, 'closing' => 0];
        foreach ($factoryRows as &$r) {
            foreach (['opening','produced','issued','sold','returns','net_today'] as $k) $r[$k] = (float)$r[$k];
            $r['closing'] = $r['opening'] + $r['net_today'];
            foreach (['opening','produced','issued','sold','returns','closing'] as $k) $factoryTot[$k] += $r[$k];
        }
        unset($r);

        /* ---------- Part 2: salesman van daily position (per salesman × product) ---------- */
        $pairs = db_all("SELECT DISTINCT s.id AS salesman_id, s.name AS salesman_name, s.code AS salesman_code, w.id AS warehouse_id, p.id AS product_id, p.sku, p.name AS product, u.short AS unit_short
                FROM salesmen s
                JOIN warehouses w ON w.id = s.warehouse_id AND $vanCond
                JOIN stock_balances sb ON sb.warehouse_id = w.id
                JOIN products p ON p.id = sb.product_id AND p.kind = 'finished' AND p.is_active = 1
                LEFT JOIN units u ON u.id = p.unit_id
                WHERE s.is_active = 1 $vanFilter
                UNION
                SELECT DISTINCT s.id AS salesman_id, s.name AS salesman_name, s.code AS salesman_code, w.id AS warehouse_id, p.id AS product_id, p.sku, p.name AS product, u.short AS unit_short
                FROM salesmen s
                JOIN warehouses w ON w.id = s.warehouse_id AND $vanCond
                JOIN stock_movements m ON m.warehouse_id = w.id
                JOIN products p ON p.id = m.product_id AND p.kind = 'finished' AND p.is_active = 1
                LEFT JOIN units u ON u.id = p.unit_id
                WHERE s.is_active = 1 AND date(m.created_at) = ? $vanFilter", array_merge($vanParams, [$date], $vanParams));
        $salesmanRows = [];
        foreach ($pairs as $pair) {
            if ($productId && (int)$pair['product_id'] !== $productId) continue;
            $wh = (int)$pair['warehouse_id']; $pid = (int)$pair['product_id'];
            $opening = (float)db_val("SELECT COALESCE(SUM(qty),0) FROM stock_movements WHERE product_id = ? AND warehouse_id = ? AND date(created_at) < ?", [$pid, $wh, $date]);
            $received = (float)db_val("SELECT COALESCE(SUM(qty),0) FROM stock_movements WHERE product_id = ? AND warehouse_id = ? AND date(created_at) = ? AND type = 'salesman_issue_in'", [$pid, $wh, $date]);
            $paidIn = (float)db_val("SELECT COALESCE(SUM(sii.paid_qty),0) FROM salesman_issue_items sii JOIN salesman_issues i2 ON i2.id = sii.issue_id WHERE sii.product_id = ? AND i2.salesman_id = ? AND date(i2.date) = ? AND i2.status <> 'reversed'", [$pid, (int)$pair['salesman_id'], $date]);
            $bonusIn = (float)db_val("SELECT COALESCE(SUM(sii.bonus_qty),0) FROM salesman_issue_items sii JOIN salesman_issues i2 ON i2.id = sii.issue_id WHERE sii.product_id = ? AND i2.salesman_id = ? AND date(i2.date) = ? AND i2.status <> 'reversed'", [$pid, (int)$pair['salesman_id'], $date]);
            $sold = (float)db_val("SELECT COALESCE(-SUM(qty),0) FROM stock_movements WHERE product_id = ? AND warehouse_id = ? AND date(created_at) = ? AND type = 'sale'", [$pid, $wh, $date]);
            $returned = (float)db_val("SELECT COALESCE(-SUM(qty),0) FROM stock_movements WHERE product_id = ? AND warehouse_id = ? AND date(created_at) = ? AND type = 'salesman_issue_out' AND ref_type = 'salesman_return'", [$pid, $wh, $date]);
            $net = (float)db_val("SELECT COALESCE(SUM(qty),0) FROM stock_movements WHERE product_id = ? AND warehouse_id = ? AND date(created_at) = ?", [$pid, $wh, $date]);
            $salesmanRows[] = ['salesman_id' => (int)$pair['salesman_id'], 'salesman_name' => $pair['salesman_name'], 'salesman_code' => $pair['salesman_code'],
                'product_id' => $pid, 'sku' => $pair['sku'], 'product' => $pair['product'], 'unit_short' => $pair['unit_short'],
                'opening' => $opening, 'received' => $received, 'paid_in' => $paidIn, 'bonus_in' => $bonusIn, 'sold' => $sold, 'returned' => $returned, 'closing' => $opening + $net];
        }
        $vanTot = ['opening' => 0, 'received' => 0, 'bonus_in' => 0, 'sold' => 0, 'returned' => 0, 'closing' => 0];
        foreach ($salesmanRows as $r) {
            $vanTot['opening'] += $r['opening']; $vanTot['received'] += $r['received']; $vanTot['bonus_in'] += $r['bonus_in'];
            $vanTot['sold'] += $r['sold']; $vanTot['returned'] += $r['returned']; $vanTot['closing'] += $r['closing'];
        }

        /* ---------- Part 3: combined, zero double count (warehouses are disjoint) ---------- */
        // Company totals are finished-goods Packets only — raw/packaging stock lives in other units.
        $factoryNow = (float)db_val("SELECT COALESCE(SUM(sb.qty),0) FROM stock_balances sb JOIN products p ON p.id = sb.product_id AND p.kind = 'finished' WHERE sb.warehouse_id IN (SELECT id FROM warehouses WHERE type <> 'salesman')");
        $vansNow = (float)db_val("SELECT COALESCE(SUM(sb.qty),0) FROM stock_balances sb JOIN products p ON p.id = sb.product_id AND p.kind = 'finished' WHERE sb.warehouse_id IN (SELECT id FROM warehouses WHERE type = 'salesman')");

        if (($_GET['export'] ?? '') === 'factory') {
            StockController::sendCsv('factory_stock_' . $date . '.csv', ['SKU','Product','Opening (Packets)','Produced (Packets)','Issued (Packets)','Sold (Packets)','Returns (Packets)','Closing (Packets)','Bundle Equivalent'],
                array_map(fn($r) => [$r['sku'], $r['name'], $r['opening'], $r['produced'], $r['issued'], $r['sold'], $r['returns'], $r['closing'], round(UnitConverter::packetsToBundles((float)$r['closing']), 2)], $factoryRows));
        }
        if (($_GET['export'] ?? '') === 'salesman') {
            StockController::sendCsv('salesman_stock_' . $date . '.csv', ['Code','Salesman','SKU','Product','Opening (Packets)','Received (Packets)','— Received (paid, Packets)','— Received (bonus, Packets)','Sold (Packets)','Returned to factory (Packets)','Closing (Packets)','Bundle Equivalent'],
                array_map(fn($r) => [$r['salesman_code'], $r['salesman_name'], $r['sku'], $r['product'], $r['opening'], $r['received'], $r['paid_in'], $r['bonus_in'], $r['sold'], $r['returned'], $r['closing'], round(UnitConverter::packetsToBundles((float)$r['closing']), 2)], $salesmanRows));
        }
        $salespeople = db_all('SELECT id, name, code FROM salesmen WHERE is_active = 1 ORDER BY name');
        $products = db_all("SELECT id, sku, name FROM products WHERE kind = 'finished' AND is_active = 1 ORDER BY name");
        view('reports/company_stock', ['date' => $date, 'smId' => $smId, 'productId' => $productId,
            'factoryRows' => $factoryRows, 'factoryTot' => $factoryTot,
            'salesmanRows' => $salesmanRows, 'vanTot' => $vanTot,
            'factoryNow' => $factoryNow, 'vansNow' => $vansNow, 'combinedNow' => $factoryNow + $vansNow,
            'salespeople' => $salespeople, 'products' => $products, 'title' => __('Company Stock Daily')]);
    }
}
