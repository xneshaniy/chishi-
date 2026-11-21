<?php
/**
 * /admin — Owner/Admin console. Separate login, approvals inbox, quick links.
 * Server-side area gate in index.php + Auth::requireArea — managers never get in, UI or not.
 */
final class AdminController {

    public static function adminLogin(): void {
        if (Auth::check() && Auth::areaAllowed('admin')) redirect('admin');
        // Surface the area-gate denial message (set by Auth::requireArea), if any.
        $flash = flash_get();
        $error = null;
        foreach ($flash as $f) { if (($f['type'] ?? '') === 'error' && !$error) $error = $f['msg']; }
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
        $pendingAmount = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM approval_requests WHERE status = 'pending' AND request_type <> 'registration'");
        $approvedToday = (int)db_val("SELECT COUNT(*) FROM approval_requests WHERE status = 'approved' AND DATE(decided_at) = ?", [$today]);
        $rejectedToday = (int)db_val("SELECT COUNT(*) FROM approval_requests WHERE status = 'rejected' AND DATE(decided_at) = ?", [$today]);
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
            'pendingAmount' => $pendingAmount, 'approvedToday' => $approvedToday, 'rejectedToday' => $rejectedToday,
            'salesToday' => $salesToday, 'salesMonth' => $salesMonth, 'collections' => $collections,
            'payableDue' => $payableDue, 'tb' => $tb, 'lowStock' => $lowStock,
            'failedLogins' => $failedLogins, 'latestAudit' => $latestAudit, 'today' => $today, 'title' => 'Admin Console',
        ]);
    }

    /** Admin-only feed of ALL system notifications (every user) — the "admin sees everything" view. */
    /** Secure attachment stream for the Approval Center — realpath-confined to storage/, admin-only. */
    public static function adminAttachment(): void {
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        $path = trim((string)inp('path'));
        if (!preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_\-./]+$#', $path) || str_contains($path, '..')) { http_response_code(404); exit('Not found'); }
        $root = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)) . '/storage', '/');
        $abs = null;
        foreach (['/uploads/' . $path, '/' . $path] as $try) {
            $cand = realpath($root . $try);
            if ($cand && str_starts_with(str_replace('\\', '/', $cand), $root . '/') && is_file($cand)) { $abs = $cand; break; }
        }
        if (!$abs) { http_response_code(404); exit('File not found'); }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf'];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . str_replace(['"', '\\'], ['_', '_'], basename($abs)) . '"');
        header('Content-Length: ' . filesize($abs));
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    public static function adminNotifications(): void {
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        $filter = in_array(inp('filter'), ['all', 'unread', 'read'], true) ? inp('filter') : 'all';
        $type = trim(inp('type'));
        $q = trim(inp('q'));
        $page = max(1, intv(inp('page'), 1));
        $perPage = 30;
        $where = ' WHERE 1=1';
        $params = [];
        if ($filter === 'unread') $where .= ' AND n.is_read = 0';
        if ($filter === 'read') $where .= ' AND n.is_read = 1';
        if ($type !== '') { $where .= ' AND n.type = ?'; $params[] = $type; }
        if ($q !== '') { $where .= ' AND (n.title LIKE ? OR n.body LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $offset = (int)(($page - 1) * $perPage);
        $total = (int)db_val("SELECT COUNT(*) FROM notifications n JOIN users u ON u.id = n.user_id" . $where, $params);
        $rows = db_all("SELECT n.*, u.username, u.full_name FROM notifications n JOIN users u ON u.id = n.user_id" . $where . " ORDER BY n.is_read ASC, n.id DESC LIMIT " . $offset . ", " . (int)$perPage, $params);
        $types = array_column(db_all('SELECT DISTINCT type FROM notifications ORDER BY type'), 'type');
        $pages = max(1, (int)ceil($total / $perPage));
        view('admin/notifications', ['rows' => $rows, 'types' => $types, 'filter' => $filter, 'type' => $type, 'q' => $q, 'page' => $page, 'pages' => $pages, 'total' => $total, 'title' => 'All Notifications (Admin)']);
    }

    public static function approvalDetail(): void {
        $isAdminArea = (($GLOBALS['AREA'] ?? null) === 'admin');
        $roles = Auth::roleCodes();
        $canDecide = is_owner_or_admin();
        if (!$isAdminArea) {
            if (!$canDecide && !in_array('manager', $roles, true)) { http_response_code(403); exit('Forbidden'); }
        }
        $baseRoute = $isAdminArea ? 'admin/approvals' : 'management/approvals';
        if ($isAdminArea && !is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }

        $id = intv(inp('id'));
        $req = $id ? db_one('SELECT a.*, r.username AS requester, r.full_name AS requester_name FROM approval_requests a LEFT JOIN users r ON r.id = a.requested_by WHERE a.id = ?', [$id]) : null;
        if (!$req) { http_response_code(404); exit('Approval request not found'); }
        $decider = $req['approver_id'] ? db_one('SELECT username, full_name FROM users WHERE id = ?', [(int)$req['approver_id']]) : null;
        $auditRows = db_all("SELECT * FROM audit_logs WHERE entity = 'approval_requests' AND entity_id = ? ORDER BY id", [(int)$req['id']]);
        $src = Approvals::source((string)$req['reference_type'], (int)$req['reference_id']);
        view('admin/approval_detail', ['req' => $req, 'decider' => $decider, 'src' => $src, 'auditRows' => $auditRows,
            'canDecide' => $canDecide, 'baseRoute' => $baseRoute, 'title' => 'Approval #' . (int)$req['id']]);
    }

    /** The requester's own read-only view of their request + decision outcome. Any authenticated user can view their own. */
    public static function approvalMine(): void {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $id = intv(inp('id'));
        $req = $id ? db_one('SELECT a.*, r.username AS requester, r.full_name AS requester_name FROM approval_requests a LEFT JOIN users r ON r.id = a.requested_by WHERE a.id = ?', [$id]) : null;
        if (!$req) { http_response_code(404); exit('Approval request not found'); }
        if ((int)$req['requested_by'] !== $uid) { http_response_code(403); exit('Forbidden'); }
        $decider = $req['approver_id'] ? db_one('SELECT username, full_name FROM users WHERE id = ?', [(int)$req['approver_id']]) : null;
        $src = Approvals::source((string)$req['reference_type'], (int)$req['reference_id']);
        view('admin/approval_mine', ['req' => $req, 'decider' => $decider, 'src' => $src, 'title' => 'My Request #' . (int)$req['id']]);
    }

    public static function approvals(): void {
        $isAdminArea = (($GLOBALS['AREA'] ?? null) === 'admin');
        $roles = Auth::roleCodes();
        // Owner/Admin: full cockpit. Manager: read-only view of every request (no approve/reject rights).
        $canDecide = is_owner_or_admin();
        if (!$isAdminArea) {
            if ($canDecide || in_array('manager', $roles, true)) {
                // Owner/Admin wandered into management area, or the Manager — both may view; decide
                // rights still come from is_owner_or_admin() only (never from roleName alone).
            } else {
                http_response_code(403); exit('Forbidden — Payment Approval is for Owner/Admin or Manager.');
            }
        }
        $baseRoute = $isAdminArea ? 'admin/approvals' : 'management/approvals';

        $status = inp('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) $status = 'pending';
        $type = inp('type'); $q = inp('q');
        $where = []; $params = [];
        if ($status !== 'all') { $where[] = 'a.status = ?'; $params[] = $status; }
        if ($type !== '') { $where[] = 'a.request_type = ?'; $params[] = $type; }
        if ($q !== '') { $where[] = '(r.username LIKE ? OR r.full_name LIKE ? OR a.reason LIKE ? OR a.reference_type LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $sql = 'SELECT a.*, r.username AS requester, r.full_name AS requester_name, ap.username AS decider FROM approval_requests a LEFT JOIN users r ON r.id = a.requested_by LEFT JOIN users ap ON ap.id = a.approver_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.id DESC';
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        foreach ($p['rows'] as &$r) { $r['src'] = Approvals::source((string)$r['reference_type'], (int)$r['reference_id']); }
        unset($r);
        // Dashboard header stats: count + total of ALL pending requests (regardless of current filter)
        $pendingCount = (int)db_val("SELECT COUNT(*) FROM approval_requests WHERE status = 'pending'");
        $pendingAmount = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM approval_requests WHERE status = 'pending' AND request_type <> 'registration'");
        view('admin/approvals', ['p' => $p, 'status' => $status, 'type' => $type, 'q' => $q,
            'pendingCount' => $pendingCount, 'pendingAmount' => $pendingAmount, 'title' => 'Payment Approval',
            'canDecide' => $canDecide, 'baseRoute' => $baseRoute]);
    }

    public static function approvalsDecide(): void {
        require_post(); csrf_verify();
        // Hard server-side guard: only Owner/Admin may decide approvals — Manager can never reach this,
        // even via a direct URL/API call (defence in depth alongside the /admin/* area gate).
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden — only Owner/Admin can approve or reject payments.'); }
        $id = intv(inp('id'));
        $approve = inp('decision') === 'approve';
        $note = inp('note');
        try {
            [$action, $refId] = Approvals::decide($id, $approve, (string)$note, (int)($_SESSION['user_id'] ?? 0));
            flash('success', $approve ? __('Approved — journal posted exactly once.') : __('Rejected — no balance or ledger entry was created.'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/approvals');
    }

    /* ============ Management Team (Owner/Admin only) ============ */

    /** List Accountant & Manager profiles. */
    public static function team(): void {
        $q = trim(inp('q'));
        $profiles = ManagementTeam::listProfiles();
        if ($q !== '') {
            $lq = mb_strtolower($q);
            $profiles = array_values(array_filter($profiles, function ($p) use ($lq) {
                return str_contains(mb_strtolower(($p['full_name'] ?? '') . ' ' . ($p['username'] ?? '') . ' ' . ($p['designation'] ?? '') . ' ' . ($p['code'] ?? '') . ' ' . ($p['role_code'] ?? '')), $lq);
            }));
        }
        $unlinked = db_all("SELECT u.id, u.username, u.full_name, r.code AS role_code, r.name AS role_name
            FROM users u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id
            LEFT JOIN employees e ON e.user_id = u.id
            WHERE r.code IN ('accountant','manager') AND e.id IS NULL AND u.is_active = 1
            ORDER BY r.code, u.full_name");
        view('admin/team', ['profiles' => $profiles, 'unlinked' => $unlinked, 'q' => $q, 'title' => 'Management Team']);
    }

    /** Show the create-profile form. */
    public static function teamNew(): void {
        $branches = db_all('SELECT * FROM branches ORDER BY name');
        view('admin/team_form', ['profile' => null, 'linkedUser' => null, 'branches' => $branches, 'title' => 'Create Accountant / Manager']);
    }

    /** Create (or link) an accountant/manager profile. */
    public static function teamNewPost(): void {
        require_post(); csrf_verify();
        try {
            $empId = ManagementTeam::createProfile($_POST);
            self::handleTeamPhoto($empId);
            audit('create', 'employees', $empId, 'Management profile saved');
            flash('success', __('Profile created. Login: ') . ($_POST['username'] ?? ''));
            redirect('admin/team');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('admin/team/new');
        }
    }

    /** Show edit form for an existing management profile. */
    public static function teamEdit(): void {
        $id = intv(inp('id'));
        $p = $id ? self::teamProfileRow($id) : null;
        if (!$p) { http_response_code(404); view('error/404', ['title' => 'Page not found']); return; }
        $branches = db_all('SELECT * FROM branches ORDER BY name');
        view('admin/team_form', ['profile' => $p, 'linkedUser' => $p['employee_id'] ? self::linkedUserRow((int)$p['employee_id']) : null, 'branches' => $branches, 'title' => 'Edit ' . ($p['role_code'] === 'accountant' ? 'Accountant' : 'Manager')]);
    }

    /** Save edits to a management profile. */
    public static function teamEditPost(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        try {
            ManagementTeam::updateProfile($id, $_POST);
            self::handleTeamPhoto($id);
            flash('success', __('Profile saved.'));
            redirect('admin/team/' . $id);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('admin/team/' . $id . '/edit');
        }
    }

    /** Management profile detail page. */
    public static function teamProfile(): void {
        $id = intv(inp('id'));
        $p = $id ? self::teamProfileRow($id) : null;
        if (!$p) { http_response_code(404); view('error/404', ['title' => 'Page not found']); return; }
        $totals = [
            'earned' => (float)db_val("SELECT COALESCE(SUM(net_pay),0) FROM payroll WHERE employee_id = ? AND status <> 'reversed'", [(int)$p['employee_id']]),
            'paid'   => (float)db_val("SELECT COALESCE(SUM(amount_paid),0) FROM payroll WHERE employee_id = ? AND status <> 'reversed'", [(int)$p['employee_id']]),
            'advanced' => (float)db_val("SELECT COALESCE(SUM(amount),0) FROM employee_advances WHERE employee_id = ? AND status = 'disbursed'", [(int)$p['employee_id']]),
            'outstanding' => (float)db_val("SELECT COALESCE(SUM(CASE WHEN amount > deducted THEN amount - deducted ELSE 0 END),0) FROM employee_advances WHERE employee_id = ? AND status = 'disbursed'", [(int)$p['employee_id']]),
        ];
        $recentPayslips = db_all("SELECT * FROM payroll WHERE employee_id = ? ORDER BY period_start DESC LIMIT 6", [(int)$p['employee_id']]);
        $salaryChanges = db_all('SELECT * FROM salary_changes WHERE employee_id = ? ORDER BY effective_date DESC, id DESC LIMIT 8', [(int)$p['employee_id']]);
        view('admin/team_profile', ['p' => $p, 'totals' => $totals, 'recentPayslips' => $recentPayslips, 'salaryChanges' => $salaryChanges, 'title' => 'Management — ' . $p['full_name']]);
    }

    /** Persist an effective-dated salary change for the linked management employee. */
    public static function teamSetSalary(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $emp = $id ? db_one('SELECT id, name, salary_rate, salary_type FROM employees WHERE id = ? AND kind = \'employee\'', [$id]) : null;
        if (!$emp) { flash('error', __('Profile not found.')); redirect('admin/team'); }
        $salaryType = in_array(inp('salary_type'), ['monthly', 'daily'], true) ? inp('salary_type') : 'monthly';
        $rate = round(num(inp('salary_rate')), 2);
        $effective = inp('effective_date', today());
        $note = inp('note');
        if ($rate <= 0) { flash('error', __('Rate must be positive.')); redirect('admin/team/' . $id); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective)) $effective = today();
        Database::transaction(function () use ($id, $salaryType, $rate, $effective, $note, $emp) {
            db_exec('UPDATE employees SET salary_type = ?, salary_rate = ? WHERE id = ?', [$salaryType, $rate, $id]);
            db_insert('salary_changes', ['employee_id' => $id, 'salary_type' => $salaryType, 'old_rate' => (float)$emp['salary_rate'], 'new_rate' => $rate, 'effective_date' => $effective, 'note' => $note ?: null, 'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local()]);
        });
        audit('update', 'employees', $id, 'Management salary set to ' . number_format($rate, 2) . ' (' . $salaryType . ')');
        flash('success', __('Salary saved.'));
        redirect('admin/team/' . $id);
    }

    /** Activate/deactivate the linked account + profile. */
    public static function teamToggle(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $p = $id ? self::teamProfileRow($id) : null;
        if (!$p) { redirect('admin/team'); }
        $newState = (int)$p['user_active'] ? 0 : 1;
        if ($p['user_id']) db_exec('UPDATE users SET is_active = ? WHERE id = ?', [$newState, (int)$p['user_id']]);
        if ($p['employee_id']) db_exec('UPDATE employees SET is_active = ? WHERE id = ?', [$newState, (int)$p['employee_id']]);
        audit('update', 'employees', $p['employee_id'], $newState ? 'Management profile reactivated' : 'Management profile deactivated');
        flash('success', $newState ? __('Account reactivated.') : __('Account deactivated.'));
        redirect('admin/team');
    }

    /** Private: fetch one management profile by employee id (or user id). */
    private static function teamProfileRow(int $id): ?array {
        $row = db_one("SELECT u.id AS user_id, u.username, u.full_name, u.email, u.phone, u.is_active AS user_active, u.force_password_change,
                              e.id AS employee_id, e.code, e.cnic, e.designation, e.department, e.join_date, e.photo_path,
                              e.salary_type, e.salary_rate, e.payment_method, e.is_active AS emp_active, e.address,
                              r.code AS role_code, r.name AS role_name, b.name AS branch_name, b.id AS branch_id
                        FROM employees e
                        JOIN users u ON u.id = e.user_id
                        LEFT JOIN user_roles ur ON ur.user_id = u.id
                        LEFT JOIN roles r ON r.id = ur.role_id
                        LEFT JOIN branches b ON b.id = u.branch_id
                        WHERE e.id = ?", [$id]);
        return $row ?: null;
    }

    private static function linkedUserRow(int $empId): ?array {
        return db_one("SELECT u.*, r.code AS role_code FROM users u JOIN employees e ON e.user_id = u.id LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id WHERE e.id = ?", [$empId]) ?: null;
    }

    /** Handle an optional photo upload on create/edit (same pipeline as employees). */
    private static function handleTeamPhoto(int $employeeId): void {
        if (empty($_FILES['photo']['name'])) return;
        $path = PhotoUpload::store($_FILES['photo'], 'employees', $employeeId);
        if (!$path) {
            flash('error', __('Profile photo could not be saved. Use JPG, JPEG, PNG or WebP up to 2 MB.'));
            return;
        }
        $old = db_val('SELECT photo_path FROM employees WHERE id = ?', [$employeeId]);
        db_exec('UPDATE employees SET photo_path = ? WHERE id = ?', [$path, $employeeId]);
        if ($old) {
            $p = dirname(__DIR__, 2) . '/storage/uploads/' . $old;
            if (is_file($p)) @unlink($p);
        }
    }
}
