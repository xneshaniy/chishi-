<?php
final class CashBankController {

    public static function accounts(): void {
        $rows = db_all("SELECT a.*, (SELECT COALESCE(SUM(debit - credit),0) FROM journal_lines WHERE account_id = a.gl_account_id) AS gl_net FROM cash_bank_accounts a ORDER BY a.type, a.id");
        foreach ($rows as &$r) { $r['balance'] = round((float)$r['opening_balance'] + (float)$r['gl_net'], 2); }
        unset($r);
        view('cashbank/accounts', ['rows' => $rows, 'title' => 'Cash & Bank Accounts']);
    }

    public static function accountNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $name = inp('name'); $type = in_array(inp('type'), ['cash', 'bank'], true) ? inp('type') : 'cash';
            if ($name === '') { flash('error', __('Name required.')); redirect('cashbank/accounts'); }
            $id = db_insert('cash_bank_accounts', ['name' => $name, 'type' => $type, 'bank_name' => inp('bank_name'), 'account_no' => inp('account_no'), 'iban' => inp('iban'), 'branch' => inp('branch'), 'opening_balance' => round(num(inp('opening_balance')), 2), 'gl_account_id' => intv(inp('gl_account_id')) ?: null, 'is_active' => 1]);
            // Link a GL account: use chosen one, else the system default for the type
            if (!intv(inp('gl_account_id'))) {
                $glId = Accounting::accountId($type === 'bank' ? 'BANK' : 'CASH');
                db_exec('UPDATE cash_bank_accounts SET gl_account_id = ? WHERE id = ?', [$glId, $id]);
            }
            audit('create', 'cash_bank_accounts', $id, $name);
            flash('success', __('Account created. If you set an opening balance, post it via Settings → Opening Balances.'));
            redirect('cashbank/accounts');
        }
        view('cashbank/account_new', ['accounts' => db_all("SELECT id, code, name FROM chart_of_accounts WHERE allow_posting = 1 AND type = 'asset' ORDER BY code"), 'title' => 'New Cash/Bank Account']);
    }

    public static function receipts(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $p = paginate("SELECT r.*, COALESCE(c.name, r.contact_name) AS customer, a.name AS account FROM receipts r LEFT JOIN customers c ON c.id = r.customer_id JOIN cash_bank_accounts a ON a.id = r.account_id WHERE r.date BETWEEN ? AND ? ORDER BY r.id DESC", [$from, $to], intv(inp('page', 1)), 20);
        $sum = db_val("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE date BETWEEN ? AND ?", [$from, $to]);
        view('cashbank/receipts', ['p' => $p, 'from' => $from, 'to' => $to, 'sum' => $sum, 'title' => 'Receipts']);
    }

    public static function receiptNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $customerId = intv(inp('customer_id')); $contactName = trim((string)inp('contact_name', '')); $accountId = intv(inp('account_id')); $amount = round(num(inp('amount')), 2);
            if (!$accountId || $amount <= 0) { flash('error', __('Account and a positive amount are required.')); redirect('cashbank/receipts/new'); }
            if (!$customerId && $contactName === '') { flash('error', __('Choose the customer, or enter who paid (person / shop name).')); redirect('cashbank/receipts/new'); }
            if (idempotency_seen('cashbank/receipts/new', inp('idem_key'), (int)($_SESSION['user_id'] ?? 0))) {
                flash('info', __('Duplicate submission detected.')); redirect('cashbank/receipts');
            }
            try {
                $id = Database::transaction(function () use ($customerId, $contactName, $accountId, $amount) {
                    $rid = db_insert('receipts', ['receipt_no' => 'TMP-' . bin2hex(random_bytes(6)), 'party_type' => 'customer', 'customer_id' => $customerId > 0 ? $customerId : null, 'contact_name' => $contactName !== '' ? $contactName : null, 'account_id' => $accountId, 'date' => inp('date', today()), 'amount' => $amount, 'reference' => inp('reference'), 'notes' => inp('notes'), 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
                    Posting::postReceipt($rid);
                    return $rid;
                });
                flash('success', __('Receipt posted — Dr Cash/Bank, Cr Receivable.'));
                redirect('cashbank/receipts');
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('cashbank/receipts/new'); }
        }
        $customers = is_salesman_only()
            ? db_all('SELECT id, code, name FROM customers WHERE salesman_id = ? OR id IN (SELECT customer_id FROM salesman_customers WHERE salesman_id = ?) ORDER BY name', [my_salesman_id() ?: 0, my_salesman_id() ?: 0])
            : db_all('SELECT id, code, name FROM customers WHERE is_active = 1 ORDER BY name');
        $balances = [];
        foreach ($customers as $c) $balances[(int)$c['id']] = Accounting::customerBalance((int)$c['id']);
        view('cashbank/receipt_new', ['customers' => $customers, 'balances' => $balances, 'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'title' => 'New Customer Receipt']);
    }

    public static function payments(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $p = paginate("SELECT p.*, a.name AS account, CASE WHEN p.party_type = 'supplier' THEN s.name ELSE 'Owner / Personal' END AS party FROM payments p JOIN cash_bank_accounts a ON a.id = p.account_id LEFT JOIN suppliers s ON s.id = p.party_id WHERE p.date BETWEEN ? AND ? ORDER BY p.id DESC", [$from, $to], intv(inp('page', 1)), 20);
        view('cashbank/payments', ['p' => $p, 'from' => $from, 'to' => $to, 'title' => 'Payments']);
    }

    public static function paymentView(): void {
        $id = intv(inp('id'));
        $p = db_one("SELECT p.*, a.name AS account, CASE WHEN p.party_type = 'supplier' THEN s.name ELSE 'Owner / Personal' END AS party, CASE WHEN p.party_type = 'supplier' THEN s.code ELSE null END AS party_code FROM payments p JOIN cash_bank_accounts a ON a.id = p.account_id LEFT JOIN suppliers s ON s.id = p.party_id WHERE p.id = ?", [$id]);
        if (!$p) { http_response_code(404); exit('Payment not found'); }
        view('cashbank/payment_view', ['p' => $p, 'thermal' => inp('thermal') === '1', 'title' => 'Payment Voucher ' . $p['payment_no']]);
    }

    public static function receiptView(): void {
        $id = intv(inp('id'));
        $r = db_one("SELECT r.*, COALESCE(c.name, r.contact_name) AS customer, c.code AS customer_code, c.address AS customer_address, c.phone AS customer_phone, a.name AS account FROM receipts r LEFT JOIN customers c ON c.id = r.customer_id JOIN cash_bank_accounts a ON a.id = r.account_id WHERE r.id = ?", [$id]);
        if (!$r) { http_response_code(404); exit('Receipt not found'); }
        view('cashbank/receipt_view', ['r' => $r, 'thermal' => inp('thermal') === '1', 'title' => 'Receipt ' . $r['receipt_no']]);
    }

    public static function paymentNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $partyType = in_array(inp('party_type'), ['supplier', 'owner'], true) ? inp('party_type') : 'supplier';
            $partyId = $partyType === 'supplier' ? intv(inp('party_id')) : null;
            $accountId = intv(inp('account_id')); $amount = round(num(inp('amount')), 2);
            if ($amount <= 0 || !$accountId || ($partyType === 'supplier' && !$partyId)) { flash('error', __('Provide payee, account and a positive amount.')); redirect('cashbank/payments/new'); }
            if (idempotency_seen('cashbank/payments/new', inp('idem_key'), (int)($_SESSION['user_id'] ?? 0))) {
                flash('info', __('Duplicate submission detected.')); redirect('cashbank/payments');
            }
            try {
                Database::transaction(function () use ($partyType, $partyId, $accountId, $amount) {
                    $pid = db_insert('payments', ['payment_no' => 'TMP-' . bin2hex(random_bytes(6)), 'party_type' => $partyType, 'party_id' => $partyId, 'account_id' => $accountId, 'date' => inp('date', today()), 'amount' => $amount, 'reference' => inp('reference'), 'notes' => inp('notes'), 'status' => 'pending', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
                    $who = $partyType === 'owner' ? 'Owner drawing' : 'Supplier payment';
                    Approvals::create('payment', 'payments', $pid, $amount, $who . ($partyType === 'supplier' ? ' — ' . db_val('SELECT name FROM suppliers WHERE id = ?', [$partyId]) : ''), (int)($_SESSION['user_id'] ?? 0));
                    audit('request', 'payments', $pid, "$who requested — awaiting approval");
                });
                flash('success', __('Payment request submitted — Owner/Admin must approve it before any cash/bank/ledger entry posts.'));
                redirect('cashbank/payments');
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('cashbank/payments/new'); }
        }
        view('cashbank/payment_new', ['suppliers' => db_all('SELECT id, code, name FROM suppliers WHERE is_active = 1 ORDER BY name'), 'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'title' => 'New Payment']);
    }

    public static function transfers(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $p = paginate("SELECT t.*, fa.name AS from_account, ta.name AS to_account FROM bank_transfers t JOIN cash_bank_accounts fa ON fa.id = t.from_account_id JOIN cash_bank_accounts ta ON ta.id = t.to_account_id WHERE t.date BETWEEN ? AND ? ORDER BY t.id DESC", [$from, $to], intv(inp('page', 1)), 20);
        view('cashbank/transfers', ['p' => $p, 'from' => $from, 'to' => $to, 'title' => 'Transfers']);
    }

    public static function transferNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $from = intv(inp('from_account_id')); $to = intv(inp('to_account_id')); $amount = round(num(inp('amount')), 2);
            if (!$from || !$to || $from === $to || $amount <= 0) { flash('error', __('Choose two different accounts and a positive amount.')); redirect('cashbank/transfers/new'); }
            if (idempotency_seen('cashbank/transfers/new', inp('idem_key'), (int)($_SESSION['user_id'] ?? 0))) {
                flash('info', __('Duplicate submission detected.')); redirect('cashbank/transfers');
            }
            try {
                Database::transaction(function () use ($from, $to, $amount) {
                    $tid = db_insert('bank_transfers', ['transfer_no' => 'TMP-' . bin2hex(random_bytes(6)), 'from_account_id' => $from, 'to_account_id' => $to, 'date' => inp('date', today()), 'amount' => $amount, 'reference' => inp('reference'), 'notes' => inp('notes'), 'status' => 'pending', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
                    $fromName = db_val('SELECT name FROM cash_bank_accounts WHERE id = ?', [$from]);
                    $toName = db_val('SELECT name FROM cash_bank_accounts WHERE id = ?', [$to]);
                    Approvals::create('transfer', 'bank_transfers', $tid, $amount, "Transfer $fromName → $toName", (int)($_SESSION['user_id'] ?? 0));
                    audit('request', 'bank_transfers', $tid, "Transfer request — awaiting approval");
                });
                flash('success', __('Transfer request submitted — Owner/Admin must approve it before any ledger/bank entry posts.'));
                redirect('cashbank/transfers');
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('cashbank/transfers/new'); }
        }
        view('cashbank/transfer_new', ['accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'title' => 'New Transfer']);
    }

    public static function cheques(): void {
        $status = inp('status');
        $where = []; $params = [];
        if ($status !== '') { $where[] = 'c.status = ?'; $params[] = $status; }
        $sql = "SELECT c.*, a.name AS account FROM cheques c JOIN cash_bank_accounts a ON a.id = c.bank_account_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY c.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        view('cashbank/cheques', ['p' => $p, 'status' => $status, 'title' => 'Cheque Register']);
    }

    public static function chequeNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $accountId = intv(inp('bank_account_id'));
            $chequeNo = inp('cheque_no');
            $amount = round(num(inp('amount')), 2);
            if (!$accountId || $chequeNo === '' || $amount <= 0) { flash('error', __('Bank account, cheque number and amount are required.')); redirect('cashbank/cheques/new'); }
            db_insert('cheques', ['cheque_no' => $chequeNo, 'bank_account_id' => $accountId, 'direction' => inp('direction') ?: 'received', 'party_type' => inp('party_type'), 'party_name' => inp('party_name'), 'amount' => $amount, 'issue_date' => inp('issue_date') ?: null, 'due_date' => inp('due_date') ?: null, 'status' => 'pending', 'notes' => inp('notes')]);
            audit('create', 'cheques', null, $chequeNo);
            flash('success', __('Cheque recorded (pending).'));
            redirect('cashbank/cheques');
        }
        view('cashbank/cheque_new', ['accounts' => db_all("SELECT id, name FROM cash_bank_accounts WHERE type = 'bank' AND is_active = 1 ORDER BY name"), 'title' => 'New Cheque']);
    }

    public static function chequeStatus(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $status = in_array(inp('status'), ['pending', 'cleared', 'bounced', 'cancelled'], true) ? inp('status') : 'pending';
        db_exec('UPDATE cheques SET status = ? WHERE id = ?', [$status, $id]);
        audit('update', 'cheques', $id, "Status → $status");
        flash('success', __('Cheque updated.'));
        redirect('cashbank/cheques');
    }

    /** Simple reconciliation: GL balance of a bank account vs. cleared cheques + statement balance entered by user */
    public static function reconcile(): void {
        $accountId = intv(inp('account_id'));
        $data = null;
        if ($accountId) {
            $gl = (float)db_val("SELECT COALESCE(SUM(debit - credit),0) FROM journal_lines l JOIN cash_bank_accounts a ON a.gl_account_id = l.account_id WHERE a.id = ?", [$accountId]);
            $opening = (float)db_val('SELECT opening_balance FROM cash_bank_accounts WHERE id = ?', [$accountId]);
            $clearedIn = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM cheques WHERE bank_account_id = ? AND direction = 'received' AND status = 'cleared'", [$accountId]);
            $clearedOut = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM cheques WHERE bank_account_id = ? AND direction = 'issued' AND status = 'cleared'", [$accountId]);
            $pendingIn = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM cheques WHERE bank_account_id = ? AND direction = 'received' AND status = 'pending'", [$accountId]);
            $pendingOut = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM cheques WHERE bank_account_id = ? AND direction = 'issued' AND status = 'pending'", [$accountId]);
            $data = ['gl' => $gl, 'opening' => $opening, 'book' => $opening + $gl, 'clearedIn' => $clearedIn, 'clearedOut' => $clearedOut, 'pendingIn' => $pendingIn, 'pendingOut' => $pendingOut];
        }
        view('cashbank/reconcile', ['accounts' => db_all("SELECT id, name FROM cash_bank_accounts WHERE type = 'bank' AND is_active = 1 ORDER BY name"), 'accountId' => $accountId, 'data' => $data, 'title' => 'Bank Reconciliation']);
    }

    /** Daily Cash & Bank Position: Opening + Today In - Today Out = Closing */
    public static function dailyPosition(): void {
        $date = inp('date');
        $date = (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : today();

        // 1. All active cash & bank accounts
        $accounts = db_all("SELECT a.*, coa.code AS gl_code, coa.name AS gl_name
                            FROM cash_bank_accounts a
                            LEFT JOIN chart_of_accounts coa ON coa.id = a.gl_account_id
                            WHERE a.is_active = 1
                            ORDER BY a.type, a.id");

        $totalOpening = 0.0;
        $totalIn = 0.0;
        $totalOut = 0.0;
        $totalClosing = 0.0;

        foreach ($accounts as &$acc) {
            $glId = (int)$acc['gl_account_id'];
            $openingBal = (float)$acc['opening_balance'];
            $priorNet = 0.0;
            $dayIn = 0.0;
            $dayOut = 0.0;

            if ($glId > 0) {
                // Movement prior to selected date
                $priorNet = (float)db_val("SELECT COALESCE(SUM(l.debit - l.credit),0)
                    FROM journal_lines l
                    JOIN journal_entries e ON e.id = l.entry_id
                    WHERE l.account_id = ? AND e.date < ? AND e.status = 'posted'", [$glId, $date]);

                // Movement on selected date
                $dayIn = (float)db_val("SELECT COALESCE(SUM(l.debit),0)
                    FROM journal_lines l
                    JOIN journal_entries e ON e.id = l.entry_id
                    WHERE l.account_id = ? AND e.date = ? AND e.status = 'posted'", [$glId, $date]);

                $dayOut = (float)db_val("SELECT COALESCE(SUM(l.credit),0)
                    FROM journal_lines l
                    JOIN journal_entries e ON e.id = l.entry_id
                    WHERE l.account_id = ? AND e.date = ? AND e.status = 'posted'", [$glId, $date]);
            }

            $acc['day_opening'] = round($openingBal + $priorNet, 2);
            $acc['day_in']      = round($dayIn, 2);
            $acc['day_out']     = round($dayOut, 2);
            $acc['day_closing'] = round($acc['day_opening'] + $acc['day_in'] - $acc['day_out'], 2);

            $totalOpening += $acc['day_opening'];
            $totalIn      += $acc['day_in'];
            $totalOut     += $acc['day_out'];
            $totalClosing += $acc['day_closing'];
        }
        unset($acc);

        // 2. Transactions on selected date touching these accounts
        $glIds = array_filter(array_column($accounts, 'gl_account_id'));
        $transactions = [];
        if (!empty($glIds)) {
            $inClause = implode(',', array_map('intval', $glIds));
            $transactions = db_all("SELECT e.id AS entry_id, e.entry_no, e.date, e.voucher_type, e.reference_type, e.reference_id,
                                           e.memo AS entry_memo, l.debit, l.credit, l.memo AS line_memo,
                                           cba.name AS account_name, cba.type AS account_type
                                    FROM journal_lines l
                                    JOIN journal_entries e ON e.id = l.entry_id
                                    JOIN cash_bank_accounts cba ON cba.gl_account_id = l.account_id
                                    WHERE l.account_id IN ($inClause) AND e.date = ? AND e.status = 'posted'
                                    ORDER BY e.id DESC, l.id DESC", [$date]);
        }

        view('cashbank/daily_position', [
            'date' => $date,
            'accounts' => $accounts,
            'totalOpening' => $totalOpening,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'totalClosing' => $totalClosing,
            'netMovement' => round($totalIn - $totalOut, 2),
            'transactions' => $transactions,
            'title' => __('Daily Cash Position')
        ]);
    }
}