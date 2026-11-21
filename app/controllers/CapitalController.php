<?php
/**
 * OWNER CAPITAL & DRAWINGS — owner/admin only, posts immediately (like receipts: incoming owner money).
 * No third-party approval for the owner's own equity moves — same rule as customer receipts.
 * Every entry posts to the GL exactly once via Posting::postCapitalInvestment / postCapitalDrawing.
 */
final class CapitalController {

    private static function requireOwnerAdmin(): void {
        if (!is_owner_or_admin()) { http_response_code(403); view('error/403', ['perm' => 'capital (owner/admin only)']); exit; }
    }

    public static function index(): void {
        self::requireOwnerAdmin();
        $capId = Accounting::accountId('CAPITAL');
        $drawId = Accounting::accountId('DRAWINGS');
        $capNet = (float)(db_one("SELECT COALESCE(SUM(l.credit - l.debit),0) AS net FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id WHERE e.status IN ('posted','reversed') AND l.account_id = ?", [$capId])['net'] ?? 0);
        $drawNet = (float)(db_one("SELECT COALESCE(SUM(l.debit - l.credit),0) AS net FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id WHERE e.status IN ('posted','reversed') AND l.account_id = ?", [$drawId])['net'] ?? 0);
        $invested = (float)(db_val("SELECT COALESCE(SUM(l.credit - l.debit),0) FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id WHERE e.status IN ('posted','reversed') AND l.account_id = ? AND e.reference_type = 'capital_investment'", [$capId]) ?? 0);
        $withdrawn = (float)(db_val("SELECT COALESCE(SUM(l.debit - l.credit),0) FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id WHERE e.status IN ('posted','reversed') AND l.account_id = ? AND e.reference_type = 'capital_drawing'", [$drawId]) ?? 0);
        $transactions = db_all("SELECT e.id, e.date, e.reference_type, e.memo, e.created_by, e.posted_at,
                (SELECT COALESCE(SUM(l.debit),0) FROM journal_lines l WHERE l.entry_id = e.id) AS amount,
                (SELECT a.name FROM journal_lines l2 JOIN chart_of_accounts a ON a.id = l2.account_id WHERE l2.entry_id = e.id AND a.type = 'asset' LIMIT 1) AS cash_account,
                (SELECT full_name FROM users WHERE id = e.created_by) AS actor
            FROM journal_entries e
            WHERE e.reference_type IN ('capital_investment','capital_drawing') AND e.status IN ('posted','reversed')
            ORDER BY e.date DESC, e.id DESC LIMIT 100");
        $accounts = db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
        view('capital/index', [
            'capNet' => $capNet, 'drawNet' => $drawNet,
            'invested' => $invested, 'withdrawn' => $withdrawn,
            'transactions' => $transactions, 'accounts' => $accounts,
            'title' => 'Capital & Drawings',
        ]);
    }

    public static function invest(): void {
        self::requireOwnerAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (idempotency_seen('capital/invest', inp('idem_key'), (int)($_SESSION['user_id'] ?? 0))) {
                flash('info', __('Duplicate submission detected.')); redirect('capital');
            }
            $amount = round(num(inp('amount')), 2);
            $accountId = (int)inp('account_id');
            $date = inp('date', today());
            $notes = inp('notes');
            if ($amount <= 0 || !$accountId) { flash('error', __('Amount and cash/bank account are required.')); redirect('capital/invest'); }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = today();
            try {
                Posting::postCapitalInvestment($amount, $accountId, $date, '', $notes, (int)($_SESSION['user_id'] ?? 0));
                flash('success', __('Owner capital investment posted — Dr Cash/Bank, Cr Owner Capital.'));
            } catch (Throwable $e) { flash('error', $e->getMessage()); }
            redirect('capital');
        }
        view('capital/form', ['kind' => 'invest', 'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'title' => __('Owner Investment')]);
    }

    public static function draw(): void {
        self::requireOwnerAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (idempotency_seen('capital/draw', inp('idem_key'), (int)($_SESSION['user_id'] ?? 0))) {
                flash('info', __('Duplicate submission detected.')); redirect('capital');
            }
            $amount = round(num(inp('amount')), 2);
            $accountId = (int)inp('account_id');
            $date = inp('date', today());
            $notes = inp('notes');
            if ($amount <= 0 || !$accountId) { flash('error', __('Amount and cash/bank account are required.')); redirect('capital/draw'); }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = today();
            try {
                Posting::postCapitalDrawing($amount, $accountId, $date, '', $notes, (int)($_SESSION['user_id'] ?? 0));
                flash('success', __('Owner drawing posted — Dr Owner Drawings, Cr Cash/Bank.'));
            } catch (Throwable $e) { flash('error', $e->getMessage()); }
            redirect('capital');
        }
        view('capital/form', ['kind' => 'draw', 'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'title' => __('Owner Drawing')]);
    }
}
