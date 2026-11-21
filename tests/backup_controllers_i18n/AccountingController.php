<?php
final class AccountingController {

    /* ============ CHART OF ACCOUNTS ============ */
    public static function accounts(): void {
        $rows = db_all("SELECT a.*, COALESCE(s.net, 0) AS net FROM chart_of_accounts a
            LEFT JOIN (SELECT account_id, SUM(debit - credit) AS net FROM journal_lines GROUP BY account_id) s ON s.account_id = a.id
            ORDER BY a.code");
        view('accounting/accounts', ['rows' => $rows, 'title' => 'Chart of Accounts']);
    }

    public static function accountNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $code = inp('code'); $name = inp('name');
            $type = in_array(inp('type'), ['asset', 'liability', 'equity', 'income', 'expense'], true) ? inp('type') : 'expense';
            $parentId = intv(inp('parent_id')) ?: null;
            if ($code === '' || $name === '') { flash('error', __('Code and name required.')); redirect('accounting/accounts/new'); }
            if (db_val('SELECT 1 FROM chart_of_accounts WHERE code = ?', [$code])) { flash('error', __('Account code already exists.')); redirect('accounting/accounts/new'); }
            db_insert('chart_of_accounts', ['code' => $code, 'name' => $name, 'type' => $type, 'parent_id' => $parentId, 'is_system' => 0, 'allow_posting' => inp('allow_posting') ? 1 : 0, 'is_active' => 1]);
            audit('create', 'chart_of_accounts', null, "$code — $name");
            flash('success', __('Account created.'));
            redirect('accounting/accounts');
        }
        $parents = db_all("SELECT id, code, name FROM chart_of_accounts WHERE allow_posting = 0 ORDER BY code");
        view('accounting/account_new', ['parents' => $parents, 'title' => 'New GL Account']);
    }

    /* ============ JOURNAL ============ */
    public static function journal(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today()); $type = inp('voucher_type'); $status = inp('status');
        $where = ['e.date BETWEEN ? AND ?']; $params = [$from, $to];
        if ($type !== '') { $where[] = 'e.voucher_type = ?'; $params[] = $type; }
        if ($status !== '') { $where[] = 'e.status = ?'; $params[] = $status; }
        $sql = 'SELECT e.*, u.username FROM journal_entries e LEFT JOIN users u ON u.id = e.created_by WHERE ' . implode(' AND ', $where) . ' ORDER BY e.id DESC';
        $p = paginate($sql, $params, intv(inp('page', 1)), 25);
        $types = db_all('SELECT DISTINCT voucher_type FROM journal_entries ORDER BY voucher_type');
        view('accounting/journal', ['p' => $p, 'from' => $from, 'to' => $to, 'type' => $type, 'status' => $status, 'types' => $types, 'title' => 'Journal Entries']);
    }

    public static function journalNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $accountIds = $_POST['items']['account_id'] ?? [];
            $debits = $_POST['items']['debit'] ?? [];
            $credits = $_POST['items']['credit'] ?? [];
            $memos = $_POST['items']['memo'] ?? [];
            $lines = [];
            foreach ($accountIds as $i => $aid) {
                $aid = (int)$aid; $d = round((float)($debits[$i] ?? 0), 2); $c = round((float)($credits[$i] ?? 0), 2);
                if ($aid && ($d > 0 || $c > 0)) $lines[] = ['account' => $aid, 'debit' => $d, 'credit' => $c, 'memo' => $memos[$i] ?? null];
            }
            if (count($lines) < 2) { flash('error', __('At least two lines required.')); redirect('accounting/journal/new'); }
            try {
                $id = Accounting::postEntry($lines, [
                    'date' => inp('date', today()), 'voucher_type' => 'JV',
                    'memo' => inp('memo'), 'status' => inp('action') === 'post' ? 'posted' : 'draft',
                ]);
                audit('create', 'journal_entries', $id, inp('action') === 'post' ? 'Posted manual journal' : 'Draft journal');
                flash('success', inp('action') === 'post' ? 'Journal posted.' : 'Draft journal saved. Review and post it.');
                redirect('accounting/journal/' . $id);
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                redirect('accounting/journal/new');
            }
        }
        view('accounting/journal_new', ['accounts' => db_all('SELECT id, code, name FROM chart_of_accounts WHERE allow_posting = 1 AND is_active = 1 ORDER BY code'), 'title' => 'New Manual Journal']);
    }

    public static function journalView(): void {
        $id = intv(inp('id'));
        $entry = db_one('SELECT e.*, u.username FROM journal_entries e LEFT JOIN users u ON u.id = e.created_by WHERE e.id = ?', [$id]);
        if (!$entry) { flash('error', __('Journal entry not found.')); redirect('accounting/journal'); }
        $lines = db_all("SELECT l.*, a.code, a.name FROM journal_lines l JOIN chart_of_accounts a ON a.id = l.account_id WHERE l.entry_id = ? ORDER BY l.id", [$id]);
        $date = $entry['date'];
        $t = db_one("SELECT COALESCE(SUM(debit),0) AS d, COALESCE(SUM(credit),0) AS c FROM journal_lines WHERE entry_id = ?", [$id]);
        view('accounting/journal_view', ['entry' => $entry, 'lines' => $lines, 'totals' => $t, 'title' => 'Journal ' . $entry['entry_no']]);
    }

    public static function journalPost(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $entry = db_one("SELECT * FROM journal_entries WHERE id = ? AND status = 'draft'", [$id]);
        if (!$entry) { flash('error', __('Only draft entries can be posted.')); redirect('accounting/journal/' . $id); }
        // Re-validate balance + period (both at post time)
        $periodId = Accounting::periodFor($entry['date']);
        $t = db_one('SELECT SUM(debit) AS d, SUM(credit) AS c FROM journal_lines WHERE entry_id = ?', [$id]);
        if (abs((float)$t['d'] - (float)$t['c']) > 0.009) { flash('error', __('Entry is not balanced — cannot post.')); redirect('accounting/journal/' . $id); }
        db_exec("UPDATE journal_entries SET status = 'posted', fiscal_period_id = ?, posted_by = ?, posted_at = ? WHERE id = ?", [$periodId, $_SESSION['user_id'] ?? null, now_local(), $id]);
        audit('post', 'journal_entries', $id, 'Manual journal posted');
        try { Notifications::notifyAccounting('journal', 'Manual journal posted', ($entry['memo'] ?? '') . ' — ' . ($entry['entry_no'] ?? '#' . $id), 'index.php?r=accounting/journal/' . $id, $id, 'journal_posted:' . $id); } catch (Throwable $e) { error_log($e->getMessage()); }
        flash('success', __('Journal posted.'));
        redirect('accounting/journal/' . $id);
    }

    public static function journalReverse(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        try {
            $rid = Accounting::reverseEntry($id, inp('reason', 'Manual reversal'));
            try { Notifications::notifyAccounting('journal', 'Journal reversed', 'Reversal entry #' . $rid . ' posted for entry #' . $id, 'index.php?r=accounting/journal/' . $rid, $rid, 'journal_reversed:' . $rid); } catch (Throwable $e) { error_log($e->getMessage()); }
            flash('success', 'Reversal entry posted (#' . $rid . '). The original is marked REVERSED.');
            redirect('accounting/journal/' . $rid);
        } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('accounting/journal/' . $id); }
    }

    /* ============ GENERAL LEDGER ============ */
    public static function ledger(): void {
        $accountId = intv(inp('account_id'));
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $account = $accountId ? db_one('SELECT * FROM chart_of_accounts WHERE id = ?', [$accountId]) : null;
        $rows = []; $opening = 0.0; $closing = 0.0;
        if ($account) {
            $opening = (float)db_val("SELECT COALESCE(SUM(l.debit - l.credit),0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id WHERE l.account_id = ? AND e.status IN ('posted','reversed') AND e.date < ?", [$accountId, $from]);
            $rows = db_all("SELECT e.date, e.entry_no, e.voucher_type, e.memo, l.debit, l.credit, COALESCE(l.memo, '') AS line_memo
                FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id
                WHERE l.account_id = ? AND e.status IN ('posted','reversed') AND e.date BETWEEN ? AND ? ORDER BY e.date, e.id, l.id", [$accountId, $from, $to]);
            $closing = (float)db_val("SELECT COALESCE(SUM(l.debit - l.credit),0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id WHERE l.account_id = ? AND e.status IN ('posted','reversed') AND e.date <= ?", [$accountId, $to]);
        }
        view('accounting/ledger', ['account' => $account, 'rows' => $rows, 'opening' => $opening, 'closing' => $closing, 'from' => $from, 'to' => $to,
            'accounts' => db_all('SELECT id, code, name FROM chart_of_accounts WHERE allow_posting = 1 ORDER BY code'), 'title' => 'General Ledger']);
    }

    /* ============ TRIAL BALANCE ============ */
    public static function trialBalance(): void {
        $fyStart = ((int)date('n') >= 7 ? date('Y') : (string)((int)date('Y') - 1)) . '-07-01';
        $from = inp('from', $fyStart); $to = inp('to', today());
        $tb = Accounting::trialBalance($from, $to);
        if (($_GET['export'] ?? '') === 'csv') {
            StockController::sendCsv('trial_balance.csv', ['Code','Account','Type','Debit','Credit'],
                array_map(fn($r) => [$r['code'], $r['name'], $r['type'], $r['debit'], $r['credit']], $tb['rows']));
        }
        view('accounting/trial_balance', ['tb' => $tb, 'from' => $from, 'to' => $to, 'title' => 'Trial Balance']);
    }

    /* ============ P&L ============ */
    public static function pnl(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $pl = Accounting::profitAndLoss($from, $to);
        if (($_GET['export'] ?? '') === 'csv') {
            $rows = [];
            foreach ($pl['income'] as $r) $rows[] = [$r['code'], $r['name'], 'Income', $r['amount']];
            foreach ($pl['expense'] as $r) $rows[] = [$r['code'], $r['name'], 'Expense', $r['amount']];
            $rows[] = ['', 'NET PROFIT / (LOSS)', '', $pl['net_profit']];
            StockController::sendCsv('profit_and_loss.csv', ['Code','Account','Type','Amount'], $rows);
        }
        view('accounting/pnl', ['pl' => $pl, 'from' => $from, 'to' => $to, 'title' => 'Profit & Loss']);
    }

    /* ============ BALANCE SHEET ============ */
    public static function balanceSheet(): void {
        $asOf = inp('as_of', today());
        $bs = Accounting::balanceSheet($asOf);
        if (($_GET['export'] ?? '') === 'csv') {
            $rows = [];
            foreach ($bs['assets'] as $r) $rows[] = [$r['code'], $r['name'], 'Asset', $r['amount']];
            foreach ($bs['liabilities'] as $r) $rows[] = [$r['code'], $r['name'], 'Liability', $r['amount']];
            foreach ($bs['equity'] as $r) $rows[] = [$r['code'], $r['name'], 'Equity', $r['amount']];
            StockController::sendCsv('balance_sheet.csv', ['Code','Account','Type','Amount'], $rows);
        }
        view('accounting/balance_sheet', ['bs' => $bs, 'asOf' => $asOf, 'title' => 'Balance Sheet']);
    }

    /* ============ CASH FLOW ============ */
    public static function cashFlow(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $cf = Accounting::cashFlow($from, $to);
        view('accounting/cash_flow', ['cf' => $cf, 'from' => $from, 'to' => $to, 'title' => 'Cash Flow']);
    }

    /* ============ FISCAL YEAR CLOSING ============ */
    public static function closing(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (inp('confirm') !== 'CLOSE') { flash('error', __('Type CLOSE to confirm.')); redirect('accounting/closing'); }
            $fyId = intv(inp('fiscal_year_id'));
            try {
                $entries = Accounting::closeFiscalYear($fyId);
                try { Notifications::notifyAccounting('accounting', 'Fiscal year closed', 'Closing entries posted: ' . implode(', ', $entries) . '. Income & expense accounts carry nil balance into next year.', 'index.php?r=accounting/closing', $fyId, 'fy_closed:' . $fyId); } catch (Throwable $e) { error_log($e->getMessage()); }
                flash('success', 'Fiscal year closed. Closing entries posted: ' . implode(', ', $entries) . '. Income & expense accounts now carry nil balance into next year.');
            } catch (Throwable $e) { flash('error', $e->getMessage()); }
            redirect('accounting/closing');
        }
        $years = db_all('SELECT * FROM fiscal_years ORDER BY start_date DESC');
        view('accounting/closing', ['years' => $years, 'title' => 'Fiscal Year Closing']);
    }
}
