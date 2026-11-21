<?php
/**
 * Employee loans — separate from advances.
 * Request → Owner/Admin approval → disbursement (Dr Employee Loans / Cr Cash-Bank) →
 * installments auto-created; salary payroll deducts due installments; manual repayments via receipt form.
 */
final class LoansController {

    public static function index(): void {
        $q = inp('q'); $status = inp('status');
        $where = []; $params = [];
        if ($q !== '') { $where[] = '(l.loan_no LIKE ? OR e.name LIKE ? OR l.reason LIKE ?)'; $params = ["%$q%", "%$q%", "%$q%"]; }
        if ($status !== '') { $where[] = 'l.status = ?'; $params[] = $status; }
        $sql = 'SELECT l.*, e.name AS employee, e.code FROM employee_loans l JOIN employees e ON e.id = l.employee_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY l.id DESC';
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        $totals = db_one("SELECT COALESCE(SUM(amount),0) AS total, COALESCE(SUM(recovered),0) AS recovered, COALESCE(SUM(outstanding),0) AS outstanding FROM employee_loans WHERE status = 'active'");
        view('loans/index', ['p' => $p, 'q' => $q, 'status' => $status, 'totals' => $totals, 'title' => 'Employee Loans']);
    }

    public static function newLoan(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $employeeId = intv(inp('employee_id'));
            $amount = round(num(inp('amount')), 2);
            $reason = inp('reason');
            $type = in_array(inp('installment_type'), ['weekly', 'monthly'], true) ? inp('installment_type') : 'monthly';
            $installment = round(num(inp('installment_amount')), 2);
            $accountId = intv(inp('account_id'));
            $errors = [];
            if (!$employeeId) $errors[] = 'Employee required.';
            if ($amount <= 0) $errors[] = 'Loan amount must be positive.';
            if ($reason === '') $errors[] = 'Reason required.';
            if ($installment <= 0) $errors[] = 'Installment amount must be positive.';
            if (!$accountId) $errors[] = 'Payout account required.';
            $nInstall = $installment > 0 ? (int)ceil($amount / $installment) : 0;
            if ($nInstall < 1 || $nInstall > 600) $errors[] = 'Installment plan invalid (1–600 installments).';
            if ($errors) { flash('error', implode(' ', $errors)); redirect('loans/new'); }

            $loanId = 0;
            try {
                Database::transaction(function () use ($employeeId, $amount, $reason, $type, $installment, $nInstall, $accountId, &$loanId) {
                    $loanNo = 'LN-' . date('YmdHis') . substr(str_replace('.', '', (string)microtime(true)), -4);
                    $loanId = db_insert('employee_loans', [
                        'loan_no' => $loanNo, 'employee_id' => $employeeId, 'amount' => $amount,
                        'date' => inp('date', today()), 'reason' => $reason,
                        'installment_type' => $type, 'installment_amount' => $installment,
                        'total_installments' => $nInstall, 'recovered' => 0, 'paid_installments' => 0,
                        'outstanding' => $amount, 'status' => 'pending', 'account_id' => $accountId,
                        'requested_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                    ]);
                    Approvals::create('loan', 'employee_loans', $loanId, $amount, 'Loan for ' . db_val('SELECT name FROM employees WHERE id = ?', [$employeeId]) . ' — ' . $reason, (int)($_SESSION['user_id'] ?? 0));
                    audit('request', 'employee_loans', $loanId, "Loan $loanNo Rs $amount — pending approval");
                });
                flash('success', __('Loan request submitted — Owner/Admin approval required before disbursement.'));
                redirect('loans');
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('loans/new'); }
        }
        view('loans/new', [
            'employees' => db_all('SELECT id, code, name FROM employees WHERE is_active = 1 ORDER BY name'),
            'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"),
            'title' => 'New Employee Loan',
        ]);
    }

    public static function view(): void {
        $id = intv(inp('id'));
        $loan = db_one('SELECT l.*, e.name AS employee, e.code, e.designation FROM employee_loans l JOIN employees e ON e.id = l.employee_id WHERE l.id = ?', [$id]);
        if (!$loan) { flash('error', __('Loan not found.')); redirect('loans'); }
        $installments = db_all('SELECT * FROM loan_installments WHERE loan_id = ? ORDER BY installment_no', [$id]);
        view('loans/view', ['loan' => $loan, 'installments' => $installments, 'title' => 'Loan ' . $loan['loan_no']]);
    }

    /** Manual repayment (receipt) — marks installments paid oldest-first. */
    public static function repay(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $amount = round(num(inp('amount')), 2);
        $accountId = intv(inp('account_id'));
        $loan = db_one('SELECT * FROM employee_loans WHERE id = ?', [$id]);
        if (!$loan || $loan['status'] !== 'active') { flash('error', __('Only active loans can accept repayments.')); redirect('loans'); }
        if ($amount <= 0 || !$accountId) { flash('error', __('Amount and account required.')); redirect('loans/' . $id); }
        if ($amount > (float)$loan['outstanding'] + 0.009) { flash('error', 'Amount exceeds outstanding balance (' . money($loan['outstanding']) . ').'); redirect('loans/' . $id); }
        $gl = Posting::accountGl($accountId);
        Database::transaction(function () use ($loan, $id, $amount, $accountId, $gl) {
            $emp = db_one('SELECT name FROM employees WHERE id = ?', [(int)$loan['employee_id']]);
            $journalId = Accounting::postEntry([
                ['account' => $gl, 'debit' => $amount, 'memo' => 'Loan repayment — ' . ($emp['name'] ?? '')],
                ['account' => 'EMP_LOAN', 'credit' => $amount, 'memo' => 'Loan ' . $loan['loan_no'] . ' repaid'],
            ], [
                'date' => inp('date', today()), 'voucher_type' => 'LR', 'reference_type' => 'employee_loan_repay',
                'reference_id' => $id, 'memo' => 'Loan repayment — ' . ($emp['name'] ?? ''),
                'idem_key' => 'loan_repay:' . $id . ':' . bin2hex(random_bytes(4)),
            ]);
            // mark installments paid oldest-first
            $remaining = $amount;
            foreach (db_all("SELECT * FROM loan_installments WHERE loan_id = ? AND status = 'due' ORDER BY installment_no", [$id]) as $inst) {
                if ($remaining <= 0.004) break;
                db_exec("UPDATE loan_installments SET status = 'paid', paid_date = ? WHERE id = ?", [today(), $inst['id']]);
                $remaining -= (float)$inst['amount'];
                db_exec("UPDATE employee_loans SET paid_installments = paid_installments + 1 WHERE id = ?", [$id]);
            }
            db_exec("UPDATE employee_loans SET recovered = recovered + ?, outstanding = MAX(0, amount - (recovered + ?)), status = CASE WHEN amount - (recovered + ?) <= 0 THEN 'completed' ELSE status END WHERE id = ?", [$amount, $amount, $amount, $id]);
            audit('repay', 'employee_loans', $id, "Manual repayment Rs $amount");
        });
        flash('success', __('Repayment recorded — Dr Cash/Bank, Cr Employee Loans; installments marked paid.'));
        redirect('loans/' . $id);
    }
}
