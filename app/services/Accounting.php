<?php
/**
 * Double-entry accounting engine.
 * - Every posted journal balances (Σdebit = Σcredit)
 * - Posted entries are immutable; corrections = reversal entries
 * - Fiscal period must be open for the entry date
 * - Auto-posting helpers used by sales/purchase/expense/etc.
 */
final class Accounting {

    /** Resolve a system key (AR, CASH…) to a chart_of_accounts id */
    public static function accountId(string $systemKey): int {
        $id = db_val('SELECT id FROM chart_of_accounts WHERE system_key = ?', [$systemKey]);
        if (!$id) throw new RuntimeException(__('System account not configured: ') . $systemKey . __(' (run installer / seed)'));
        return (int)$id;
    }

    /** Resolve account by id or system key. Accepts int id or string system_key. */
    private static function resolveAccount($acc): int {
        if (is_int($acc)) return $acc;
        if (is_numeric($acc)) return (int)$acc;
        return self::accountId((string)$acc);
    }

    /** Fiscal period for a date; throws if missing or closed. */
    public static function periodFor(string $date): ?int {
        $p = db_one('SELECT id, status FROM fiscal_periods WHERE start_date <= ? AND end_date >= ? LIMIT 1', [$date, $date]);
        if (!$p) throw new RuntimeException(__('No fiscal period covers ') . $date . __('. Create the fiscal year first (Settings → Financial Year).'));
        if ($p['status'] === 'closed') throw new RuntimeException(__('Fiscal period for ') . $date . __(' is CLOSED. Posting is not allowed.'));
        return (int)$p['id'];
    }

    /**
     * Post a balanced journal entry. Returns entry id.
     * @param array $lines list of ['account'=>id|system_key,'debit'=>float,'credit'=>float,'customer_id'=>?,'supplier_id'=>?,'memo'=>?]
     * @param array $meta  ['date'=>Y-m-d,'voucher_type'=>str,'reference_type'=>str,'reference_id'=>int,'memo'=>str,'status'=>'posted'|'draft','idem_key'=>?]
     */
    public static function postEntry(array $lines, array $meta): int {
        $date = $meta['date'] ?? today();
        $status = $meta['status'] ?? 'posted';
        $idem = $meta['idem_key'] ?? null;

        // Idempotency: same key → return existing entry
        if ($idem) {
            $existing = db_val('SELECT id FROM journal_entries WHERE idem_key = ?', [$idem]);
            if ($existing) return (int)$existing;
        }

        $periodId = self::periodFor($date);

        // Validate + normalize lines
        $norm = [];
        $sumD = 0.0; $sumC = 0.0;
        foreach ($lines as $ln) {
            $debit = round((float)($ln['debit'] ?? 0), 2);
            $credit = round((float)($ln['credit'] ?? 0), 2);
            if ($debit > 0 && $credit > 0) throw new InvalidArgumentException(__('A journal line cannot have both debit and credit.'));
            if ($debit == 0.0 && $credit == 0.0) continue; // drop empty lines
            $accId = self::resolveAccount($ln['account']);
            $acc = db_one('SELECT id, allow_posting, is_active FROM chart_of_accounts WHERE id = ?', [$accId]);
            if (!$acc) throw new RuntimeException(__('Account id ') . $accId . __(' does not exist.'));
            if (!$acc['is_active']) throw new RuntimeException(__('Account id ') . $accId . __(' is inactive.'));
            if (!$acc['allow_posting']) throw new RuntimeException(__('Account id ') . $accId . __(' is a header account — posting not allowed.'));
            $norm[] = ['account_id' => $accId, 'debit' => $debit, 'credit' => $credit,
                       'customer_id' => $ln['customer_id'] ?? null, 'supplier_id' => $ln['supplier_id'] ?? null,
                       'memo' => $ln['memo'] ?? null];
            $sumD += $debit; $sumC += $credit;
        }
        if (count($norm) < 2) throw new InvalidArgumentException(__('A journal entry needs at least two lines.'));
        if (abs($sumD - $sumC) > 0.009)
            throw new RuntimeException(sprintf(__('Unbalanced journal: debits %.2f ≠ credits %.2f'), $sumD, $sumC));

        $type = $meta['voucher_type'] ?? 'JV';

        return Database::transaction(function () use ($norm, $meta, $date, $periodId, $status, $idem, $type) {
            $entryNo = self::nextVoucherLocked($type, $date);
            $entryId = db_insert('journal_entries', [
                'entry_no' => $entryNo,
                'date' => $date,
                'fiscal_period_id' => $periodId,
                'voucher_type' => $type,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'memo' => $meta['memo'] ?? null,
                'status' => $status,
                'idem_key' => $idem,
                'created_by' => $_SESSION['user_id'] ?? null,
                'posted_by' => $status === 'posted' ? ($_SESSION['user_id'] ?? null) : null,
                'posted_at' => $status === 'posted' ? now_local() : null,
                'created_at' => now_local(),
            ]);
            $q = db()->prepare('INSERT INTO journal_lines (entry_id, account_id, debit, credit, customer_id, supplier_id, memo) VALUES (?,?,?,?,?,?,?)');
            foreach ($norm as $ln) {
                $q->execute([$entryId, $ln['account_id'], $ln['debit'], $ln['credit'], $ln['customer_id'], $ln['supplier_id'], $ln['memo']]);
            }
            return $entryId;
        });
    }

    /** Upsert-locked voucher sequence. Works on MySQL; on SQLite falls back to plain upsert. */
    private static function nextVoucherLocked(string $type, string $date): string {
        $fy = db_one('SELECT id FROM fiscal_years WHERE start_date <= ? AND end_date >= ? LIMIT 1', [$date, $date]);
        if (!$fy) throw new RuntimeException(__('No fiscal year covers ') . $date);
        $fyId = (int)$fy['id'];
        $isSqlite = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if ($isSqlite) {
            db_exec('INSERT OR IGNORE INTO voucher_sequences (fiscal_year_id, voucher_type, current_no) VALUES (?,?,0)', [$fyId, $type]);
            db_exec('UPDATE voucher_sequences SET current_no = current_no + 1 WHERE fiscal_year_id = ? AND voucher_type = ?', [$fyId, $type]);
        } else {
            db_exec('INSERT INTO voucher_sequences (fiscal_year_id, voucher_type, current_no) VALUES (?,?,0)
                     ON DUPLICATE KEY UPDATE current_no = current_no + 1', [$fyId, $type]);
            // first insert should be numbered 1
            db_exec('UPDATE voucher_sequences SET current_no = 1 WHERE fiscal_year_id = ? AND voucher_type = ? AND current_no = 0', [$fyId, $type]);
        }
        $no = (int)db_val('SELECT current_no FROM voucher_sequences WHERE fiscal_year_id = ? AND voucher_type = ?', [$fyId, $type]);
        return $type . '-' . str_pad((string)$no, 5, '0', STR_PAD_LEFT);
    }

    /** Reverse a posted entry. Returns new reversal entry id. Idempotent per entry+date. */
    public static function reverseEntry(int $entryId, string $reason, ?string $date = null): int {
        $entry = db_one('SELECT * FROM journal_entries WHERE id = ?', [$entryId]);
        if (!$entry) throw new RuntimeException(__('Journal entry not found'));
        if ($entry['status'] !== 'posted') throw new RuntimeException(__('Only posted entries can be reversed.'));
        $date = $date ?? today();
        self::periodFor($date); // reversal date must be open

        return Database::transaction(function () use ($entry, $entryId, $reason, $date) {
            $already = db_val('SELECT id FROM journal_entries WHERE reversal_of = ? LIMIT 1', [$entryId]);
            if ($already) return (int)$already;
            $lines = db_all('SELECT account_id, debit, credit, customer_id, supplier_id FROM journal_lines WHERE entry_id = ?', [$entryId]);
            $rid = self::postEntry(array_map(fn($l) => [
                'account' => (int)$l['account_id'],
                'debit' => (float)$l['credit'], 'credit' => (float)$l['debit'],
                'customer_id' => $l['customer_id'], 'supplier_id' => $l['supplier_id'],
                'memo' => 'Reversal: ' . $reason,
            ], $lines), [
                'date' => $date, 'voucher_type' => $entry['voucher_type'],
                'reference_type' => 'reversal', 'reference_id' => $entryId,
                'memo' => 'Reversal of ' . $entry['entry_no'] . ' — ' . $reason,
            ]);
            db_exec("UPDATE journal_entries SET status = 'reversed' WHERE id = ?", [$entryId]);
            // mark the reversal entry with reversal_of link
            db_exec('UPDATE journal_entries SET reversal_of = ? WHERE id = ?', [$entryId, $rid]);
            audit('reverse', 'journal_entries', $entryId, "Reversed by entry #$rid — $reason");
            return $rid;
        });
    }

    /* ---------- Financial statements ---------- */

    public static function accountBalances(?string $from = null, ?string $to = null): array {
        $where = "e.status IN ('posted','reversed')";
        $params = [];
        if ($from) { $where .= ' AND e.date >= ?'; $params[] = $from; }
        if ($to)   { $where .= ' AND e.date <= ?'; $params[] = $to; }
        $sql = "SELECT a.id, a.code, a.name, a.type,
                       SUM(l.debit) AS debits, SUM(l.credit) AS credits,
                       SUM(l.debit) - SUM(l.credit) AS net
                FROM journal_lines l
                JOIN journal_entries e ON e.id = l.entry_id
                JOIN chart_of_accounts a ON a.id = l.account_id
                WHERE $where
                GROUP BY a.id, a.code, a.name, a.type
                ORDER BY a.code";
        $rows = db_all($sql, $params);
        foreach ($rows as &$r) { $r['debits'] = (float)$r['debits']; $r['credits'] = (float)$r['credits']; $r['net'] = (float)$r['net']; }
        return $rows;
    }

    public static function trialBalance(?string $from = null, ?string $to = null): array {
        $rows = self::accountBalances($from, $to);
        $out = [];
        $td = 0.0; $tc = 0.0;
        foreach ($rows as $r) {
            $debit = $r['net'] >= 0 ? $r['net'] : 0;
            $credit = $r['net'] < 0 ? -$r['net'] : 0;
            $td += $debit; $tc += $credit;
            $out[] = ['code' => $r['code'], 'name' => $r['name'], 'type' => $r['type'], 'debit' => round($debit, 2), 'credit' => round($credit, 2)];
        }
        return ['rows' => $out, 'total_debit' => round($td, 2), 'total_credit' => round($tc, 2), 'balanced' => abs($td - $tc) < 0.01];
    }

    public static function profitAndLoss(string $from, string $to): array {
        $rows = self::accountBalances($from, $to);
        $income = []; $expense = []; $ti = 0.0; $te = 0.0;
        foreach ($rows as $r) {
            if ($r['type'] === 'income') { $amt = -$r['net']; $income[] = ['name' => $r['name'], 'code' => $r['code'], 'amount' => round($amt, 2)]; $ti += $amt; }
            elseif ($r['type'] === 'expense') { $amt = $r['net']; $expense[] = ['name' => $r['name'], 'code' => $r['code'], 'amount' => round($amt, 2)]; $te += $amt; }
        }
        return ['income' => $income, 'expense' => $expense, 'total_income' => round($ti, 2), 'total_expense' => round($te, 2),
                'net_profit' => round($ti - $te, 2), 'from' => $from, 'to' => $to];
    }

    public static function balanceSheet(string $asOf): array {
        $rows = self::accountBalances(null, $asOf);
        $assets = []; $liabilities = []; $equity = [];
        $ta = 0.0; $tl = 0.0; $teq = 0.0; $netIncome = 0.0;
        foreach ($rows as $r) {
            if ($r['type'] === 'asset') { $assets[] = ['code' => $r['code'], 'name' => $r['name'], 'amount' => round($r['net'], 2)]; $ta += $r['net']; }
            elseif ($r['type'] === 'liability') { $liabilities[] = ['code' => $r['code'], 'name' => $r['name'], 'amount' => round(-$r['net'], 2)]; $tl += -$r['net']; }
            elseif ($r['type'] === 'equity') { $equity[] = ['code' => $r['code'], 'name' => $r['name'], 'amount' => round(-$r['net'], 2)]; $teq += -$r['net']; }
            elseif ($r['type'] === 'income') { $netIncome += -$r['net']; }
            elseif ($r['type'] === 'expense') { $netIncome -= $r['net']; }
        }
        $equity[] = ['code' => '—', 'name' => 'Current Period Net Profit / (Loss)', 'amount' => round($netIncome, 2)];
        $teq += $netIncome;
        return ['assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity,
                'total_assets' => round($ta, 2), 'total_liabilities' => round($tl, 2), 'total_equity' => round($teq, 2),
                'balanced' => abs($ta - ($tl + $teq)) < 0.01, 'as_of' => $asOf];
    }

    /** Direct-method-ish cash flow: movements on cash/bank accounts grouped by counterpart nature. */
    public static function cashFlow(string $from, string $to): array {
        $sql = "SELECT e.id, e.date, e.entry_no, e.memo,
                       SUM(CASE WHEN a.system_key IN ('CASH','BANK') OR a.code LIKE '112%' THEN l.debit - l.credit ELSE 0 END) AS cash_net
                FROM journal_entries e
                JOIN journal_lines l ON l.entry_id = e.id
                JOIN chart_of_accounts a ON a.id = l.account_id
                WHERE e.status IN ('posted','reversed') AND e.date BETWEEN ? AND ?
                GROUP BY e.id";
        $entries = db_all($sql, [$from, $to]);
        $flow = 0.0;
        foreach ($entries as $e2) $flow += (float)$e2['cash_net'];
        $opening = (float)db_val("SELECT COALESCE(SUM(l.debit - l.credit),0) FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id JOIN chart_of_accounts a ON a.id=l.account_id WHERE a.system_key IN ('CASH','BANK') AND e.date < ?", [$from]);
        return ['from' => $from, 'to' => $to, 'net_movement' => round($flow, 2),
                'opening_cash' => round($opening, 2), 'closing_cash' => round($opening + $flow, 2),
                'entries' => $entries,
                'note' => 'Simplified direct-method summary from cash/bank ledger movements. Use Cash Book / Bank Book for voucher-level detail.'];
    }

    /** Party (customer/supplier) running balance from posted lines. */
    public static function partyLedger(string $partyType, int $partyId, ?string $from = null, ?string $to = null): array {
        $col = $partyType === 'customer' ? 'customer_id' : 'supplier_id';
        $sql = "SELECT e.date, e.entry_no, e.voucher_type, COALESCE(l.memo, e.memo) AS memo,
                       l.debit, l.credit
                FROM journal_lines l
                JOIN journal_entries e ON e.id = l.entry_id
                WHERE e.status IN ('posted','reversed') AND l.$col = ?";
        $p = [$partyId];
        if ($from) { $sql .= ' AND e.date >= ?'; $p[] = $from; }
        if ($to)   { $sql .= ' AND e.date <= ?'; $p[] = $to; }
        $sql .= ' ORDER BY e.date, e.id, l.id';
        $rows = db_all($sql, $p);
        $run = 0.0;
        foreach ($rows as &$r) { $r['debit'] = (float)$r['debit']; $r['credit'] = (float)$r['credit']; $run += $r['debit'] - $r['credit']; $r['balance'] = round($run, 2); }
        return $rows;
    }

    public static function customerBalance(int $customerId): float {
        return (float)db_val("SELECT COALESCE(SUM(l.debit) - SUM(l.credit), 0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id WHERE e.status IN ('posted','reversed') AND l.customer_id = ?", [$customerId]);
    }

    public static function supplierBalance(int $supplierId): float {
        return (float)db_val("SELECT COALESCE(SUM(l.credit) - SUM(l.debit), 0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id WHERE e.status IN ('posted','reversed') AND l.supplier_id = ?", [$supplierId]);
    }

    /** Close a fiscal year: transfer income/expense balances to retained earnings, close periods. */
    public static function closeFiscalYear(int $fyId): array {
        $fy = db_one('SELECT * FROM fiscal_years WHERE id = ?', [$fyId]);
        if (!$fy) throw new RuntimeException(__('Fiscal year not found'));
        if ($fy['is_closed']) throw new RuntimeException(__('Fiscal year already closed.'));
        $from = $fy['start_date']; $to = $fy['end_date'];

        return Database::transaction(function () use ($fy, $from, $to, $fyId) {
            $rows = self::accountBalances($from, $to);
            $incomeLines = []; $expenseLines = [];
            $netPL = 0.0;
            foreach ($rows as $r) {
                if ($r['type'] === 'income' && abs($r['net']) > 0.004) { $incomeLines[] = ['account' => (int)$r['id'], 'debit' => round(-$r['net'], 2)]; $netPL += -$r['net']; }
                elseif ($r['type'] === 'expense' && abs($r['net']) > 0.004) { $expenseLines[] = ['account' => (int)$r['id'], 'credit' => round($r['net'], 2)]; $netPL -= $r['net']; }
            }
            $reId = self::accountId('RE');
            $closing = [];
            if ($incomeLines) {
                $lines = $incomeLines;
                $lines[] = ['account' => $reId, 'credit' => round(array_sum(array_column($incomeLines, 'debit')), 2)];
                $closing[] = self::postEntry($lines, ['date' => $to, 'voucher_type' => 'CL', 'reference_type' => 'fy_close', 'reference_id' => $fyId, 'memo' => 'Closing entry — transfer income to retained earnings']);
            }
            if ($expenseLines) {
                $lines = $expenseLines;
                $lines[] = ['account' => $reId, 'debit' => round(array_sum(array_column($expenseLines, 'credit')), 2)];
                $closing[] = self::postEntry($lines, ['date' => $to, 'voucher_type' => 'CL', 'reference_type' => 'fy_close', 'reference_id' => $fyId, 'memo' => 'Closing entry — transfer expenses to retained earnings']);
            }
            db_exec('UPDATE fiscal_periods SET status = \'closed\' WHERE fiscal_year_id = ?', [$fyId]);
            db_exec('UPDATE fiscal_years SET is_closed = 1 WHERE id = ?', [$fyId]);
            audit('fy_close', 'fiscal_years', $fyId, 'Fiscal year closed; closing entries: ' . implode(',', $closing));
            return $closing;
        });
    }
}
