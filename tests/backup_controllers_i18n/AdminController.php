<?php
/**
 * /admin — Owner/Admin console. Separate login, approvals inbox, quick links.
 * Server-side area gate in index.php + Auth::requireArea — managers never get in, UI or not.
 */
final class AdminController {

    public static function adminLogin(): void {
        if (Auth::check() && Auth::areaAllowed('admin')) redirect('admin');
        $error = null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            csrf_verify();
            [$ok, $error] = Auth::attempt(inp('username'), $_POST['password'] ?? '');
            if ($ok && !Auth::areaAllowed('admin')) {
                Auth::logout();
                $error = 'Access denied — the Admin area is for the Owner and Admin only.';
            } elseif ($ok) {
                redirect('admin');
            }
        }
        view('admin/login', ['error' => $error, 'title' => 'Admin Console'], true);
    }

    public static function admin(): void {
        $today = today();
        $monthStart = date('Y-m-01');
        $pending = Approvals::pendingCount();
        $pendingList = db_all("SELECT a.*, r.username AS requester FROM approval_requests a LEFT JOIN users r ON r.id = a.requested_by WHERE a.status = 'pending' ORDER BY a.id DESC LIMIT 8");
        $users = (int)db_val('SELECT COUNT(*) FROM users WHERE is_active = 1');
        $salesToday = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE status='posted' AND date = ?", [$today]);
        $salesMonth = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE status='posted' AND date >= ?", [$monthStart]);
        $collections = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE date = ?", [$today]);
        $payableDue = (float)db_val("SELECT COALESCE(SUM(total),0) FROM purchase_invoices WHERE status='posted' AND payment_type='credit'");
        $tb = Accounting::trialBalance($monthStart, $today);
        $lowStock = count(Inventory::lowStock());
        $failedLogins = (int)db_val("SELECT COUNT(*) FROM login_history WHERE status='failed' AND created_at > ?", [date('Y-m-d H:i:s', time() - 86400)]);
        $latestAudit = db_all("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 6");
        view('admin/index', [
            'pending' => $pending, 'pendingList' => $pendingList, 'users' => $users,
            'salesToday' => $salesToday, 'salesMonth' => $salesMonth, 'collections' => $collections,
            'payableDue' => $payableDue, 'tb' => $tb, 'lowStock' => $lowStock,
            'failedLogins' => $failedLogins, 'latestAudit' => $latestAudit, 'today' => $today, 'title' => 'Admin Console',
        ]);
    }

    public static function approvals(): void {
        $status = inp('status', 'pending');
        $type = inp('type'); $q = inp('q');
        $where = []; $params = [];
        if ($status !== 'all') { $where[] = 'a.status = ?'; $params[] = $status; }
        if ($type !== '') { $where[] = 'a.request_type = ?'; $params[] = $type; }
        if ($q !== '') { $where[] = '(r.username LIKE ? OR a.reason LIKE ? OR a.reference_type LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $sql = 'SELECT a.*, r.username AS requester, ap.username AS decider FROM approval_requests a LEFT JOIN users r ON r.id = a.requested_by LEFT JOIN users ap ON ap.id = a.approver_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.id DESC';
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        view('admin/approvals', ['p' => $p, 'status' => $status, 'type' => $type, 'q' => $q, 'title' => 'Payment Approvals']);
    }

    public static function approvalsDecide(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $approve = inp('decision') === 'approve';
        $note = inp('note');
        try {
            [$action, $refId] = Approvals::decide($id, $approve, (string)$note, (int)($_SESSION['user_id'] ?? 0));
            flash('success', $approve ? 'Approved — journal posted exactly once.' : 'Rejected — no balance or ledger entry was created.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/approvals');
    }
}
