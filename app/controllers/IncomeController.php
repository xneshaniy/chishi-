<?php
/**
 * OTHER INCOME — income outside sales (rent, commission, scrap, interest...).
 * Recorded as pending → Owner/Admin approves exactly-once (same pattern as expenses).
 */
final class IncomeController {

    public static function index(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today()); $status = inp('status');
        $where = ['i.date BETWEEN ? AND ?']; $params = [$from, $to];
        if ($status !== '') { $where .= ' AND i.status = ?'; $params[] = $status; }
        $p = paginate("SELECT i.*, a.name AS account FROM other_income i JOIN cash_bank_accounts a ON a.id = i.account_id WHERE " . implode(' AND ', $where) . " ORDER BY i.id DESC", $params, intv(inp('page', 1)), 20);
        $monthTotal = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM other_income WHERE status = 'posted' AND date BETWEEN ? AND ?", [date('Y-m-01'), today()]);
        $pendingCount = (int)db_val("SELECT COUNT(*) FROM other_income WHERE status = 'pending'");
        $allTime = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM other_income WHERE status = 'posted'");
        view('income/index', ['p' => $p, 'from' => $from, 'to' => $to, 'status' => $status,
            'monthTotal' => $monthTotal, 'pendingCount' => $pendingCount, 'allTime' => $allTime,
            'title' => 'Other Income']);
    }

    public static function newIncome(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $category = in_array(inp('category'), ['other', 'rent', 'commission', 'scrap', 'interest'], true) ? inp('category') : 'other';
            $amount = round(num(inp('amount')), 2);
            $accountId = intv(inp('account_id'));
            $paymentType = in_array(inp('payment_type'), ['cash', 'bank'], true) ? inp('payment_type') : 'cash';
            if ($amount <= 0 || !$accountId) { flash('error', __('Amount and cash/bank account are required.')); redirect('income/new'); }
            if (idempotency_seen('income/new', inp('idem_key'), (int)($_SESSION['user_id'] ?? 0))) {
                flash('info', __('Duplicate submission detected.')); redirect('income');
            }
            try {
                Database::transaction(function () use ($category, $amount, $accountId, $paymentType) {
                    $id = db_insert('other_income', [
                        'income_no' => 'TMP-' . bin2hex(random_bytes(6)),
                        'category' => $category,
                        'date' => inp('date', today()),
                        'amount' => $amount,
                        'payer' => inp('payer'),
                        'description' => inp('description'),
                        'payment_type' => $paymentType,
                        'account_id' => $accountId,
                        'status' => 'pending',
                        'created_by' => $_SESSION['user_id'] ?? null,
                        'created_at' => now_local(),
                    ]);
                    Approvals::create('other_income', 'other_income', $id, $amount, 'Other income — ' . (inp('description') ?: $category), (int)($_SESSION['user_id'] ?? 0));
                    audit('create', 'other_income', $id, "Other income Rs $amount — pending Admin approval");
                });
                flash('success', __('Income recorded — Owner/Admin must approve it before it posts to the ledger.'));
            } catch (Throwable $e) { flash('error', $e->getMessage()); }
            redirect('income');
        }
        view('income/new', ['accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'title' => 'Record Other Income']);
    }

    public static function post(): void {
        require_post(); csrf_verify();
        if (!Auth::can('approvals.decide')) { flash('error', __('Approvals are decided by Owner/Admin in the Admin Console (/admin/approvals).')); redirect('income'); }
        $id = intv(inp('id'));
        $req = db_one("SELECT id FROM approval_requests WHERE reference_type = 'other_income' AND reference_id = ? AND request_type = 'other_income' AND status = 'pending'", [$id]);
        if (!$req) { flash('error', __('No pending approval request found for this income.')); redirect('income'); }
        try {
            Approvals::decide((int)$req['id'], true, 'Approved from other income list', (int)($_SESSION['user_id'] ?? 0));
            flash('success', __('Income approved — posted to the ledger exactly once.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('income');
    }

    public static function reverse(): void {
        require_post(); csrf_verify();
        if (!Auth::can('journal.reverse')) { flash('error', __('Not permitted.')); redirect('income'); }
        $id = intv(inp('id'));
        try {
            Posting::reverseOtherIncome($id, 'Reversed from other income list');
            flash('success', __('Other income reversed — journal reversed.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('income');
    }
}
