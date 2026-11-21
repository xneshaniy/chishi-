<?php
final class PayrollController {

    public static function employees(): void {
        $q = inp('q');
        $where = []; $params = [];
        if ($q !== '') { $where[] = '(code LIKE ? OR name LIKE ? OR phone LIKE ? OR designation LIKE ?)'; $params = array_fill(0, 4, "%$q%"); }
        $sql = 'SELECT * FROM employees' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY name';
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        view('payroll/employees', ['p' => $p, 'q' => $q, 'title' => 'Employees']);
    }

    /** Shared advance-request handling (used by the employee edit page and the standalone request page). */
    private static function submitAdvance(int $id): void {
        $amount = round(num(inp('amount')), 2);
        $accountId = intv(inp('account_id'));
        $monthlyDeduction = num(inp('monthly_deduction')) > 0 ? round(num(inp('monthly_deduction')), 2) : null;
        $reason = inp('reason');
        $limit = (float)setting('advance_max_amount', '0');
        $errors = [];
        if ($amount <= 0) $errors[] = 'Advance amount must be positive.';
        if (!$accountId) $errors[] = 'Select the payout account (needed to disburse after approval).';
        if ($reason === '') $errors[] = 'Reason required.';
        if ($limit > 0 && $amount > $limit && !is_owner_or_admin()) {
            $errors[] = 'Advance exceeds the maximum allowed (Rs ' . number_format($limit, 2) . '). Ask an Owner/Admin to approve a larger advance.';
        }
        $back = 'employees/' . $id . '/advance';
        if ($errors) { flash('error', implode(' ', $errors)); redirect($back); }
        Database::transaction(function () use ($id, $amount, $accountId, $monthlyDeduction, $reason, &$advId) {
            $advId = db_insert('employee_advances', ['employee_id' => $id, 'date' => inp('date', today()), 'amount' => $amount, 'notes' => $reason, 'monthly_deduction' => $monthlyDeduction, 'status' => 'pending', 'requested_by' => $_SESSION['user_id'] ?? null, 'account_id' => $accountId, 'created_at' => now_local()]);
            Approvals::create('advance', 'employee_advances', $advId, $amount, 'Salary advance — ' . ($reason ?: 'no reason given'), (int)($_SESSION['user_id'] ?? 0));
        });
        audit('request', 'employee_advances', $id, "Advance request Rs $amount — pending approval");
        flash('success', __('Advance request submitted — disburses only after Owner/Admin approval.'));
        redirect($back);
    }

    /** Standalone advance-request page — reachable by anyone with advances.request (not only editors). */
    public static function advanceRequest(): void {
        $id = intv(inp('id'));
        $employee = db_one('SELECT * FROM employees WHERE id = ?', [$id]);
        if (!$employee) { flash('error', __('Employee not found.')); redirect('employees'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { require_post(); csrf_verify(); self::submitAdvance($id); }
        $advances = db_all('SELECT * FROM employee_advances WHERE employee_id = ? ORDER BY date DESC', [$id]);
        view('payroll/advance_request', ['employee' => $employee, 'advances' => $advances, 'advanceMax' => (float)setting('advance_max_amount', '0'), 'advanceOutstanding' => (float)db_val("SELECT COALESCE(SUM(CASE WHEN amount > deducted THEN amount - deducted ELSE 0 END),0) FROM employee_advances WHERE employee_id = ? AND status = 'disbursed'", [$id]), 'title' => 'Request Advance — ' . $employee['name']]);
    }

    public static function employeeNew(): void { self::saveEmployee(null); }
    public static function employeeEdit(): void { self::saveEmployee(intv(inp('id'))); }

    private static function saveEmployee(?int $id): void {
        $employee = $id ? db_one('SELECT * FROM employees WHERE id = ?', [$id]) : null;
        if ($id && !$employee) { flash('error', __('Employee not found.')); redirect('employees'); }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $action = inp('action', 'save');

            if ($action === 'advance') {
                self::submitAdvance($id);
            }

            $payMethod = in_array(inp('payment_method'), ['cash', 'bank'], true) ? inp('payment_method') : 'cash';
            // Owner/Admin-only: salary figures may not be changed by other roles (viewers can't inflate pay)
            if (is_owner_or_admin()) {
                $salaryRate = round(num(inp('salary_rate')), 2);
                $overtimeRate = num(inp('overtime_rate')) > 0 ? round(num(inp('overtime_rate')), 2) : null;
            } elseif ($employee) {
                $salaryRate = (float)$employee['salary_rate'];
                $overtimeRate = $employee['overtime_rate'] !== null ? (float)$employee['overtime_rate'] : null;
            } else {
                $salaryRate = 0.0;
                $overtimeRate = null;
            }
            $data = [
                'code' => strtoupper(inp('code')), 'name' => inp('name'), 'cnic' => inp('cnic'), 'phone' => inp('phone'),
                'designation' => inp('designation'), 'department' => inp('department'),
                'join_date' => inp('join_date') ?: null,
                'salary_type' => in_array(inp('salary_type'), ['monthly', 'weekly', 'daily', 'hourly', 'contract'], true) ? inp('salary_type') : 'monthly',
                'salary_rate' => $salaryRate,
                'overtime_rate' => $overtimeRate,
                'payment_method' => $payMethod,
                // New people are Active by default for Owner/Admin-created records; manager-created records follow their module's approval rules (withholding only where approvals exist)
                'is_active' => $id ? (isset($_POST['is_active']) ? 1 : 0) : 1,
            ];
            $errors = [];
            if ($data['code'] === '' || $data['name'] === '') $errors[] = 'Code and name are required.';
            if (db_val('SELECT 1 FROM employees WHERE code = ? AND id != ?', [$data['code'], $id ?? 0])) $errors[] = 'Employee code already exists.';
            if ($errors) { flash('error', implode(' ', $errors)); redirect($id ? 'employees/' . $id . '/edit' : 'employees/new'); }
            if ($id) {
                db_exec('UPDATE employees SET code=?, name=?, cnic=?, phone=?, designation=?, department=?, join_date=?, salary_type=?, salary_rate=?, overtime_rate=?, payment_method=?, is_active=? WHERE id=?',
                    array_merge(array_values($data), [$id]));
                audit('update', 'employees', $id, 'Updated ' . $data['code']);
            } else {
                $newId = db_insert('employees', $data + ['created_at' => now_local()]);
                audit('create', 'employees', $newId, 'Created ' . $data['code']);
            }
            // profile photo (same storage as Employee Section — served via permission-gated route)
            if (!empty($_FILES['photo']['name'])) {
                $pid = $id ?: (int)$newId;
                $path = PhotoUpload::store($_FILES['photo'], 'employees', (int)$pid);
                if ($path) {
                    $old = db_val('SELECT photo_path FROM employees WHERE id = ?', [(int)$pid]);
                    db_exec('UPDATE employees SET photo_path = ? WHERE id = ?', [$path, (int)$pid]);
                    if ($old) { $p = dirname(__DIR__, 2) . '/storage/uploads/' . $old; if (is_file($p)) @unlink($p); }
                } else {
                    flash('error', __('Profile photo could not be saved. Use JPG, JPEG, PNG or WebP up to 2 MB.'));
                }
            }
            flash('success', __('Employee saved.'));
            redirect('employees');
        }
        $advances = $id ? db_all('SELECT * FROM employee_advances WHERE employee_id = ? ORDER BY date DESC', [$id]) : [];
        $suggestedCode = 'EMP-' . str_pad((string)((int)db_val('SELECT MAX(id) FROM employees') + 1), 4, '0', STR_PAD_LEFT);
        view('payroll/employee_form', ['employee' => $employee, 'advances' => $advances, 'suggestedCode' => $suggestedCode, 'canSalary' => is_owner_or_admin(), 'advanceMax' => (float)setting('advance_max_amount', '0'), 'title' => $employee ? 'Edit Employee' : 'New Employee']);
    }

    public static function attendance(): void {
        $date = inp('date', today());
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['status'])) {
            require_post(); csrf_verify();
            $date = inp('date', today());
            $statuses = $_POST['status'];
            $hoursMap = $_POST['hours'] ?? [];
            Database::transaction(function () use ($statuses, $hoursMap, $date) {
                foreach ($statuses as $empId => $status) {
                    $empId = (int)$empId;
                    if (!in_array($status, ['present', 'absent', 'leave', 'half_day', 'off'], true)) continue;
                    $hours = round(max(0, (float)($hoursMap[$empId] ?? 8)), 2);
                    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
                    if ($driver === 'sqlite') {
                        db_exec('INSERT INTO attendance (employee_id, date, status, hours) VALUES (?,?,?,?) ON CONFLICT(employee_id, date) DO UPDATE SET status = excluded.status, hours = excluded.hours', [$empId, $date, $status, $hours]);
                    } else {
                        db_exec('INSERT INTO attendance (employee_id, date, status, hours) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status = VALUES(status), hours = VALUES(hours)', [$empId, $date, $status, $hours]);
                    }
                }
            });
            audit('mark', 'attendance', null, "Attendance for $date");
            flash('success', __('Attendance saved for ') . $date . '.');
            redirect('attendance', ['date' => $date]);
        }
        $employees = db_all('SELECT * FROM employees WHERE is_active = 1 ORDER BY name');
        $existing = [];
        foreach (db_all('SELECT employee_id, status, hours FROM attendance WHERE date = ?', [$date]) as $a) $existing[(int)$a['employee_id']] = ['status' => $a['status'], 'hours' => $a['hours']];
        view('payroll/attendance', ['date' => $date, 'employees' => $employees, 'existing' => $existing, 'title' => 'Attendance']);
    }

    public static function payroll(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', date('Y-m-t'));
        $rows = db_all('SELECT p.*, e.name AS employee, e.code, e.salary_type FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.period_start = ? AND p.period_end = ? ORDER BY e.name', [$from, $to]);
        $sum = array_sum(array_column($rows, 'net_pay'));
        $paidSum = array_sum(array_column($rows, 'amount_paid'));
        // undecided salary payments per payslip (to show "awaiting approval" instead of a pay button)
        $pending = [];
        foreach (db_all("SELECT pp.payroll_id, SUM(pp.amount) AS amt FROM payroll_payments pp JOIN payroll p ON p.id = pp.payroll_id WHERE pp.status = 'pending' AND p.period_start = ? AND p.period_end = ? GROUP BY pp.payroll_id", [$from, $to]) as $r) {
            $pending[(int)$r['payroll_id']] = (float)$r['amt'];
        }
        view('payroll/payroll', ['from' => $from, 'to' => $to, 'rows' => $rows, 'sum' => $sum, 'paidSum' => $paidSum, 'pendingPay' => $pending, 'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'title' => 'Payroll Run']);
    }

    public static function payrollGenerate(): void {
        require_post(); csrf_verify();
        $from = inp('period_start', date('Y-m-01')); $to = inp('period_end', date('Y-m-t'));
        $frequency = in_array(inp('frequency'), ['monthly', 'weekly', 'all'], true) ? inp('frequency') : 'all';
        if ((new DateTimeImmutable($to)) < (new DateTimeImmutable($from))) { flash('error', __('Period end before start.')); redirect('payroll'); }
        try {
            $created = Payroll::generate($from, $to, (int)($_SESSION['user_id'] ?? 0), null, $frequency === 'all' ? null : $frequency);
            $which = $frequency === 'all' ? __('All employees') : ($frequency === 'weekly' ? __('Weekly') : __('Monthly'));
            flash('success', sprintf(__('Draft payroll generated for %d employee(s) — %s. Review and click Pay.'), $created, $which));
        } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('payroll', ['from' => $from, 'to' => $to]); }
        redirect('payroll', ['from' => $from, 'to' => $to]);
    }

    /**
     * Submit a salary payment for Owner/Admin approval — full or PARTIAL.
     * Creates a payroll_payments row (pending) + approval request; journal posts only after approval.
     */
    public static function payrollPay(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id')); $accountId = intv(inp('account_id')); $amount = round(num(inp('amount', '0')), 2);
        if (!$accountId) { flash('error', __('Select the cash/bank account used for payment.')); redirect('payroll'); }
        $p = db_one("SELECT p.*, e.name AS employee FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.id = ?", [$id]);
        if (!$p || !in_array($p['status'], ['draft', 'partial'], true)) { flash('error', __('Only draft or partially paid payslips can be submitted for payment.')); redirect('payroll'); }
        $remaining = round(max(0, (float)$p['net_pay'] - (float)$p['amount_paid']), 2);
        if ($amount <= 0) $amount = $remaining; // blank amount = pay everything remaining
        if ($amount > $remaining + 0.004) { flash('error', __('Amount exceeds the remaining payable (') . money($remaining) . ').'); redirect('payroll', ['from' => $p['period_start'], 'to' => $p['period_end']]); }
        if ($remaining <= 0) { flash('error', __('This payslip is already fully paid.')); redirect('payroll'); }
        if (db_val("SELECT 1 FROM payroll_payments WHERE payroll_id = ? AND status = 'pending'", [$id])) {
            flash('error', __('A payment for this payslip is already awaiting approval.')); redirect('payroll', ['from' => $p['period_start'], 'to' => $p['period_end']]);
        }
        $notes = inp('notes');
        $empName = $p['employee'];
        $payId = 0;
        Database::transaction(function () use ($id, $amount, $accountId, $notes, $p, $empName, &$payId) {
            $payId = db_insert('payroll_payments', ['payroll_id' => $id, 'date' => today(), 'amount' => $amount, 'account_id' => $accountId, 'notes' => $notes ?: null, 'status' => 'pending', 'requested_by' => (int)($_SESSION['user_id'] ?? 0), 'created_at' => now_local()]);
            Approvals::create('payroll_payment', 'payroll_payments', $payId, $amount, 'Salary ' . date_display($p['period_start']) . ' → ' . date_display($p['period_end']) . ' — ' . $empName . ($amount < (float)$p['net_pay'] - (float)$p['amount_paid'] - 0.004 ? ' (partial)' : ''), (int)($_SESSION['user_id'] ?? 0));
        });
        audit('request', 'payroll_payments', $payId, 'Salary payment of Rs ' . number_format($amount, 2) . ' submitted for approval');
        flash('success', __('Salary payment submitted — Owner/Admin must approve it before the journal posts.'));
        redirect('payroll', ['from' => $p['period_start'], 'to' => $p['period_end']]);
    }

    /** Printable payslip for one payslip row. */
    public static function payslip(): void {
        $id = intv(inp('id'));
        $p = db_one('SELECT p.*, e.name AS employee, e.code, e.designation, e.department, e.salary_type, e.join_date FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.id = ?', [$id]);
        if (!$p) { flash('error', __('Payslip not found.')); redirect('payroll'); }
        $deductions = db_all("SELECT d.*, CASE WHEN d.source_type='advance' THEN (SELECT notes FROM employee_advances WHERE id = d.source_id) ELSE (SELECT l.loan_no FROM loan_installments li JOIN employee_loans l ON l.id = li.loan_id WHERE li.id = d.source_id) END AS reference FROM payroll_deductions d WHERE d.payroll_id = ?", [$id]);
        $payments = db_all("SELECT pp.*, cb.name AS account FROM payroll_payments pp LEFT JOIN cash_bank_accounts cb ON cb.id = pp.account_id WHERE pp.payroll_id = ? ORDER BY pp.id", [$id]);
        view('payroll/payslip', ['p' => $p, 'deductions' => $deductions, 'payments' => $payments, 'title' => 'Payslip — ' . $p['employee']]);
    }

    public static function payrollReverse(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $p = db_one('SELECT * FROM payroll WHERE id = ?', [$id]);
        if (!$p) { flash('error', __('Payroll record not found.')); redirect('payroll'); }
        Database::transaction(function () use ($p, $id) {
            if ($p['status'] === 'paid' && $p['journal_entry_id']) {
                Accounting::reverseEntry((int)$p['journal_entry_id'], 'Payroll reversal');
            }
            // Reverse every approved partial payment journal for this payslip too
            foreach (db_all("SELECT id, journal_entry_id FROM payroll_payments WHERE payroll_id = ? AND status = 'paid'", [$id]) as $pp) {
                if (!empty($pp['journal_entry_id'])) Accounting::reverseEntry((int)$pp['journal_entry_id'], 'Salary payment reversal');
            }
            db_exec("UPDATE payroll_payments SET status = 'reversed' WHERE payroll_id = ? AND status = 'paid'", [$id]);
            Payroll::releaseDeductions($id);
            db_exec("UPDATE payroll SET status = 'reversed', amount_paid = 0 WHERE id = ?", [$id]);
            audit('reverse', 'payroll', $id, 'Payroll reversed; deductions released');
        });
        flash('success', __('Payroll reversed; advances and loans released to outstanding.'));
        redirect('payroll', ['from' => $p['period_start'], 'to' => $p['period_end']]);
    }

    /** Per-employee salary statement: payslips, payments history, remaining payable, advances. */
    public static function employeeLedger(): void {
        $id = intv(inp('id'));
        $emp = db_one('SELECT * FROM employees WHERE id = ?', [$id]);
        if (!$emp) { flash('error', __('Employee not found.')); redirect('employees'); }
        $payslips = db_all("SELECT p.*, (SELECT COUNT(*) FROM payroll_payments pp WHERE pp.payroll_id = p.id AND pp.status = 'paid') AS pay_count FROM payroll p WHERE p.employee_id = ? ORDER BY p.period_end DESC LIMIT 60", [$id]);
        $earned = (float)db_val("SELECT COALESCE(SUM(net_pay),0) FROM payroll WHERE employee_id = ? AND status <> 'reversed'", [$id]);
        $paid = (float)db_val("SELECT COALESCE(SUM(amount_paid),0) FROM payroll WHERE employee_id = ? AND status <> 'reversed'", [$id]);
        $monthEarned = (float)db_val("SELECT COALESCE(SUM(net_pay),0) FROM payroll WHERE employee_id = ? AND status <> 'reversed' AND period_start <= ? AND period_end >= ?", [$id, today(), today()]);
        $monthPaid = (float)db_val("SELECT COALESCE(SUM(am.amount),0) FROM payroll_payments am JOIN payroll p ON p.id = am.payroll_id WHERE p.employee_id = ? AND am.status = 'paid' AND am.date >= ?", [$id, date('Y-m-01')]);
        $payRows = db_all("SELECT pp.*, cb.name AS account, p.period_start, p.period_end, p.id AS payroll_id FROM payroll_payments pp JOIN payroll p ON p.id = pp.payroll_id LEFT JOIN cash_bank_accounts cb ON cb.id = pp.account_id WHERE p.employee_id = ? ORDER BY pp.date DESC, pp.id DESC LIMIT 100", [$id]);
        $advanceOutstanding = (float)db_val("SELECT COALESCE(SUM(CASE WHEN amount > deducted THEN amount - deducted ELSE 0 END),0) FROM employee_advances WHERE employee_id = ? AND status = 'disbursed'", [$id]);
        $loanOutstanding = (float)db_val("SELECT COALESCE(SUM(outstanding),0) FROM employee_loans WHERE employee_id = ? AND status = 'active'", [$id]);
        view('payroll/employee_ledger', [
            'emp' => $emp, 'payslips' => $payslips, 'payments' => $payRows,
            'earned' => $earned, 'paid' => $paid, 'remaining' => round(max(0, $earned - $paid), 2),
            'monthEarned' => $monthEarned, 'monthPaid' => $monthPaid,
            'advanceOutstanding' => $advanceOutstanding, 'loanOutstanding' => $loanOutstanding,
            'title' => 'Salary Ledger — ' . $emp['name'],
        ]);
    }

    /** Printable advance voucher for one advance (pending/disbursed/etc). */
    public static function advancePrint(): void {
        $id = intv(inp('id')); $aid = intv(inp('aid'));
        $a = db_one("SELECT a.*, e.name AS employee, e.code, e.designation, e.department, cb.name AS account, (SELECT username FROM users WHERE id = a.approved_by) AS approver FROM employee_advances a JOIN employees e ON e.id = a.employee_id LEFT JOIN cash_bank_accounts cb ON cb.id = a.account_id WHERE a.id = ? AND a.employee_id = ?", [$aid, $id]);
        if (!$a) { flash('error', __('Advance not found.')); redirect('employees'); }
        view('payroll/advance_print', ['a' => $a, 'title' => 'Advance Voucher — ' . $a['employee']]);
    }

    /** Requester cancels their own still-pending advance request (or Owner/Admin cancels any). */
    public static function advanceCancel(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id')); $aid = intv(inp('aid'));
        $a = db_one('SELECT * FROM employee_advances WHERE id = ? AND employee_id = ?', [$aid, $id]);
        if (!$a) { flash('error', __('Advance not found.')); redirect('employees'); }
        if ($a['status'] !== 'pending') { flash('error', __('Only pending advances can be cancelled.')); redirect('employees/' . $id . '/edit'); }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ((int)($a['requested_by'] ?? 0) !== $uid && !is_owner_or_admin()) {
            flash('error', __('You can only cancel advances you requested.')); redirect('employees/' . $id . '/edit');
        }
        Database::transaction(function () use ($a, $aid, $uid) {
            db_exec("UPDATE employee_advances SET status = 'cancelled' WHERE id = ?", [$aid]);
            db_exec("UPDATE approval_requests SET status = 'rejected', approver_id = ?, decided_at = ?, decision_note = 'Cancelled by requester' WHERE reference_type = 'employee_advances' AND reference_id = ? AND status = 'pending'", [$uid, now_local(), $aid]);
            audit('cancel', 'employee_advances', $aid, 'Advance cancelled by requester');
        });
        flash('success', __('Advance request cancelled — nothing was paid.'));
        redirect('employees/' . $id . '/edit');
    }
}
