<?php
/**
 * Personal loans — private person-wise ledger owned by the Owner/Admin.
 * Company accounting is completely untouched. Permissions:
 *   personal_loans.view   → view (Owner/Admin by default; Owner may grant)
 *   personal_loans.manage → add/edit/cancel/repay (Owner/Admin by default)
 */
final class PersonalLoansController {

    public static function index(): void {
        if (!Auth::can('personal_loans.view')) { http_response_code(403); exit('Forbidden'); }
        $summary = PersonalLoans::summary();
        $filter = in_array(inp('filter'), ['all', 'pending', 'partial', 'paid', 'overdue', 'cancelled'], true) ? inp('filter') : 'all';
        $q = trim(inp('q'));
        $from = inp('from') ?: null;
        $to = inp('to') ?: null;
        $dateType = in_array(inp('date_type'), ['loan_date', 'expected_date'], true) ? inp('date_type') : 'loan_date';
        $loans = PersonalLoans::listLoans($filter, $q, $from, $to, $dateType);
        $accounts = db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
        view('personal_loans/index', [
            'summary' => $summary, 'loans' => $loans, 'filter' => $filter, 'q' => $q,
            'from' => $from, 'to' => $to, 'dateType' => $dateType, 'accounts' => $accounts,
            'title' => __('Loans')
        ]);
    }

    public static function newLoan(): void {
        if (!Auth::can('personal_loans.manage')) { http_response_code(403); exit('Forbidden'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            try {
                $id = PersonalLoans::createLoan($_POST, (int)($_SESSION['user_id'] ?? 0));
                flash('success', __('Loan / Dasti recorded.') . ' ' . __('ID') . ' #' . $id);
            } catch (Throwable $e) { flash('error', $e->getMessage()); }
            redirect('personal-loans');
        }
        $accounts = db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
        view('personal_loans/new', ['accounts' => $accounts, 'title' => __('New Loan')]);
    }

    public static function show(): void {
        if (!Auth::can('personal_loans.view')) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        $loan = PersonalLoans::find($id);
        if (!$loan) { http_response_code(404); exit(__('Loan not found.')); }
        $title = __('Loan') . ' ' . ($loan['loan_no'] ?? ('#' . $id));
        $accounts = db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
        view('personal_loans/show', ['loan' => $loan, 'accounts' => $accounts, 'title' => $title]);
    }

    public static function editLoan(): void {
        if (!Auth::can('personal_loans.manage')) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        $loan = PersonalLoans::find($id);
        if (!$loan) { http_response_code(404); exit(__('Loan not found.')); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            try {
                PersonalLoans::updateLoan($id, $_POST, (int)($_SESSION['user_id'] ?? 0));
                flash('success', __('Loan updated.'));
                redirect('personal-loans/' . $id);
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('personal-loans/' . $id . '/edit'); }
        }
        $accounts = db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
        view('personal_loans/edit', ['loan' => $loan, 'accounts' => $accounts, 'title' => __('Edit Loan') . ' ' . ($loan['loan_no'] ?? '')]);
    }

    public static function addRepayment(): void {
        require_post(); csrf_verify();
        if (!Auth::can('personal_loans.manage')) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        $loan = PersonalLoans::find($id);
        if (!$loan) { http_response_code(404); exit(__('Loan not found.')); }
        $accountId = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;
        try {
            PersonalLoans::addRepayment($id, inp('repayment_date', today()), (float)num(inp('amount')), inp('note'), (int)($_SESSION['user_id'] ?? 0), $accountId);
            flash('success', __('Repayment recorded successfully and deposited into account.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('personal-loans/' . $id);
    }

    public static function cancelLoan(): void {
        require_post(); csrf_verify();
        if (!Auth::can('personal_loans.manage')) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        $reason = trim(inp('reason'));
        try {
            PersonalLoans::cancelLoan($id, (int)($_SESSION['user_id'] ?? 0), $reason);
            flash('success', __('Loan cancelled — record kept for history.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('personal-loans/' . $id);
    }
}
