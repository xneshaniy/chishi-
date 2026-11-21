<?php
/**
 * /management — Manager / Accountant console. Separate login, read-only approvals view,
 * team KPIs, links into the ERP modules they are permissioned for.
 * Server-side gate: only owner|admin|manager|accountant — others get the management login with an error.
 */
final class ManagementController {

    public static function managementLogin(): void {
        if (Auth::check() && Auth::areaAllowed('management')) redirect('management');
        // Surface the area-gate denial message (set by Auth::requireArea), if any.
        $flash = flash_get();
        $error = null;
        foreach ($flash as $f) { if (($f['type'] ?? '') === 'error' && !$error) $error = $f['msg']; }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            csrf_verify();
            [$ok, $error] = Auth::attempt(inp('username'), $_POST['password'] ?? '');
            if ($ok && !Auth::areaAllowed('management')) {
                Auth::logout();
                $error = 'Access denied — the Management area is for Managers, Accountants and Admin.';
            } elseif ($ok) {
                redirect('management');
            }
        }
        view('admin/management_login', ['error' => $error, 'title' => 'Management Console'], true);
    }

    public static function management(): void {
        $today = today();
        $monthStart = date('Y-m-01');
        $salesToday = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE status='posted' AND date = ?", [$today]);
        $salesMonth = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE status='posted' AND date >= ?", [$monthStart]);
        $collectionsToday = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE date = ?", [$today]);
        $pendingApprovals = Approvals::pendingCount();
        $unpaidPayroll = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(net_pay),0) AS t FROM payroll WHERE status IN ('draft','pending_approval')");
        $lowStock = count(Inventory::lowStock());
        $outstandingCustomers = (float)db_val("SELECT COALESCE(SUM(l.debit - l.credit),0) FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id JOIN chart_of_accounts a ON a.id=l.account_id WHERE e.status IN ('posted','reversed') AND a.system_key='AR'");
        $salesmen = db_all("SELECT s.name, COALESCE(SUM(i.total),0) AS sales, COUNT(i.id) AS invoices FROM salesmen s LEFT JOIN sales_invoices i ON i.salesman_id = s.id AND i.status='posted' AND i.date >= ? WHERE s.is_active = 1 GROUP BY s.id ORDER BY sales DESC LIMIT 6", [$monthStart]);
        view('admin/management_index', [
            'salesToday' => $salesToday, 'salesMonth' => $salesMonth, 'collectionsToday' => $collectionsToday,
            'pendingApprovals' => $pendingApprovals, 'unpaidPayroll' => $unpaidPayroll,
            'lowStock' => $lowStock, 'outstandingCustomers' => $outstandingCustomers, 'salesmen' => $salesmen,
            'today' => $today, 'title' => 'Management Console',
        ]);
    }
}
