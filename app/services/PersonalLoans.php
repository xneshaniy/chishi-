<?php
/**
 * Personal loans — independent, private record-keeping for money borrowed by/lent to people by the owner.
 * ZERO coupling to company accounting: no journals, no stock, no sales/purchases, no cash/bank balances.
 *  - Remaining = original − SUM(repayments) — recomputed live; original is never overwritten.
 *  - Status is DERIVED on read: cancelled > fully_paid > overdue > partially_paid > pending.
 *  - Repayment amount may never exceed the remaining balance.
 *  - One active row per person is NOT enforced — a person may hold many concurrent loans.
 *  - Exactly-once repayment rows per user submit; idempotency guard at the controller via POST-then-redirect.
 *  - Every create/edit/repay/cancel is audited with who/when/what.
 */
final class PersonalLoans {

    public static function validDate(string $d): bool {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && strtotime($d) !== false;
    }

    /** Live status for a loan row (requires original_amount + expected/repaid) */
    public static function statusOf(array $loan): string {
        $status = (string)($loan['status'] ?? 'pending');
        if ($status === 'cancelled') return 'cancelled';
        $expected = $loan['expected_date'] ?? null;
        $remaining = (float)($loan['remaining_amount'] ?? 0);
        if ($remaining <= 0.004) return 'paid';
        if ($expected && self::validDate($expected) && strtotime($expected) < strtotime(date('Y-m-d'))) return 'overdue';
        $repaid = (float)($loan['total_repaid'] ?? 0);
        if ($repaid > 0.004) return 'partial';
        return 'pending';
    }

    /** Next loan number: PL-00001 */
    private static function nextLoanNo(): string {
        $max = (int)db_val("SELECT MAX(CAST(SUBSTR(loan_no, 4) AS INTEGER)) FROM personal_loans WHERE loan_no LIKE 'PL-%'");
        return 'PL-' . str_pad((string)($max + 1), 5, '0', STR_PAD_LEFT);
    }

    /** List loans enriched with repaid/remaining/status. Optional filter: all|pending|partial|paid|overdue|cancelled. */
    public static function listLoans(string $filter = 'all', string $q = '', ?string $from = null, ?string $to = null, string $dateType = 'loan_date'): array {
        $where = [];
        $params = [];
        if ($from && self::validDate($from)) {
            $col = $dateType === 'expected_date' ? 'pl.expected_date' : 'pl.loan_date';
            $where[] = "$col >= ?";
            $params[] = $from;
        }
        if ($to && self::validDate($to)) {
            $col = $dateType === 'expected_date' ? 'pl.expected_date' : 'pl.loan_date';
            $where[] = "$col <= ?";
            $params[] = $to;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = db_all("SELECT pl.*,
                a.name AS account_name,
                a.type AS account_type,
                COALESCE((SELECT SUM(r.amount) FROM personal_loan_repayments r WHERE r.loan_id = pl.id),0) AS total_repaid,
                (SELECT COUNT(*) FROM personal_loan_repayments r WHERE r.loan_id = pl.id) AS repayment_count
            FROM personal_loans pl
            LEFT JOIN cash_bank_accounts a ON a.id = pl.account_id
            $whereSql
            ORDER BY pl.loan_date DESC, pl.id DESC", $params);
        $q = mb_strtolower(trim($q));
        $out = [];
        foreach ($rows as $r) {
            $r['original_amount'] = (float)$r['original_amount'];
            $r['total_repaid'] = (float)$r['total_repaid'];
            $r['remaining_amount'] = round(max(0, $r['original_amount'] - $r['total_repaid']), 2);
            $r['status'] = self::statusOf($r);
            if ($filter !== 'all' && $r['status'] !== $filter) continue;
            if ($q !== '') {
                $hay = mb_strtolower(($r['person_name'] ?? '') . ' ' . ($r['phone'] ?? '') . ' ' . ($r['loan_no'] ?? '') . ' ' . ($r['description'] ?? ''));
                if (strpos($hay, $q) === false) continue;
            }
            $out[] = $r;
        }
        return $out;
    }

    public static function find(int $id): ?array {
        $r = db_one("SELECT pl.*,
                a.name AS account_name,
                a.type AS account_type,
                COALESCE((SELECT SUM(r.amount) FROM personal_loan_repayments r WHERE r.loan_id = pl.id),0) AS total_repaid,
                (SELECT COUNT(*) FROM personal_loan_repayments r WHERE r.loan_id = pl.id) AS repayment_count
            FROM personal_loans pl
            LEFT JOIN cash_bank_accounts a ON a.id = pl.account_id
            WHERE pl.id = ?", [$id]);
        if (!$r) return null;
        $r['original_amount'] = (float)$r['original_amount'];
        $r['total_repaid'] = (float)$r['total_repaid'];
        $r['remaining_amount'] = round(max(0, $r['original_amount'] - $r['total_repaid']), 2);
        $r['status'] = self::statusOf($r);
        $r['repayments'] = db_all("SELECT r.*, a.name AS account_name, a.type AS account_type, u.full_name AS recorded_by_name, u.username AS recorded_by_username
            FROM personal_loan_repayments r
            LEFT JOIN cash_bank_accounts a ON a.id = r.account_id
            LEFT JOIN users u ON u.id = r.recorded_by
            WHERE r.loan_id = ?
            ORDER BY r.repayment_date DESC, r.id DESC", [$id]);
        $r['created_by_name'] = $r['created_by'] ? (string)db_val('SELECT COALESCE(full_name, username) FROM users WHERE id = ?', [(int)$r['created_by']]) : null;
        return $r;
    }

    public static function createLoan(array $data, int $userId): int {
        $amount = round((float)($data['original_amount'] ?? 0), 2);
        if ($amount <= 0.004) throw new RuntimeException(__('Loan amount must be positive.'));
        $name = trim((string)($data['person_name'] ?? ''));
        if ($name === '') throw new RuntimeException(__('Person name is required.'));
        $loanDate = self::validDate($data['loan_date'] ?? '') ? $data['loan_date'] : today();
        $expected = self::validDate($data['expected_date'] ?? '') ? $data['expected_date'] : null;
        $accountId = !empty($data['account_id']) ? (int)$data['account_id'] : null;
        $loanNo = self::nextLoanNo();
        $id = db_insert('personal_loans', [
            'loan_no' => $loanNo, 'person_name' => $name, 'phone' => trim((string)($data['phone'] ?? '')) ?: null,
            'loan_date' => $loanDate, 'original_amount' => $amount, 'expected_date' => $expected,
            'description' => trim((string)($data['description'] ?? '')) ?: null, 'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'account_id' => $accountId,
            'status' => 'pending', 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => now_local(),
        ]);
        if ($accountId) {
            $payNo = 'PAY-PL-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
            try {
                db_insert('payments', [
                    'payment_no' => $payNo,
                    'party_type' => 'other',
                    'party_id' => $id,
                    'account_id' => $accountId,
                    'date' => $loanDate,
                    'amount' => $amount,
                    'reference' => 'Dasti / Loan ' . $loanNo,
                    'notes' => 'Loan given to ' . $name . ($expected ? ' (Return due: ' . $expected . ')' : ''),
                    'status' => 'posted',
                    'created_by' => $userId,
                    'created_at' => now_local(),
                ]);
            } catch (Throwable $pe) {
                error_log('Personal loan payment insert error: ' . $pe->getMessage());
            }
        }
        audit('create', 'personal_loans', $id, sprintf('Loan %s of Rs %s to %s (expected %s)', $loanNo, number_format($amount, 2), $name, $expected ?: '—'));
        return (int)$id;
    }

    public static function updateLoan(int $id, array $data, int $userId): void {
        $loan = db_one('SELECT * FROM personal_loans WHERE id = ?', [$id]);
        if (!$loan) throw new RuntimeException(__('Loan not found.'));
        if ($loan['status'] === 'cancelled') throw new RuntimeException(__('Cancelled loans cannot be edited.'));
        $totalRepaid = (float)db_val('SELECT COALESCE(SUM(amount),0) FROM personal_loan_repayments WHERE loan_id = ?', [$id]);
        $name = trim((string)($data['person_name'] ?? ''));
        if ($name === '') throw new RuntimeException(__('Person name is required.'));
        $amount = round((float)($data['original_amount'] ?? 0), 2);
        if ($amount <= 0.004) throw new RuntimeException(__('Loan amount must be positive.'));
        if ($totalRepaid - $amount > 0.004) {
            throw new RuntimeException(sprintf(__('Original loan cannot be less than the total already repaid (Rs %s).'), number_format($totalRepaid, 2)));
        }
        $loanDate = self::validDate($data['loan_date'] ?? '') ? $data['loan_date'] : $loan['loan_date'];
        $expected = self::validDate($data['expected_date'] ?? '') ? $data['expected_date'] : null;
        $accountId = !empty($data['account_id']) ? (int)$data['account_id'] : $loan['account_id'];
        db_exec('UPDATE personal_loans SET person_name=?, phone=?, loan_date=?, original_amount=?, expected_date=?, account_id=?, description=?, notes=?, updated_by=? WHERE id=?',
            [$name, trim((string)($data['phone'] ?? '')) ?: null, $loanDate, $amount, $expected, $accountId,
             trim((string)($data['description'] ?? '')) ?: null, trim((string)($data['notes'] ?? '')) ?: null, $userId, $id]);
        audit('update', 'personal_loans', $id, 'Updated — amount Rs ' . number_format($amount, 2));
    }

    /** Record a repayment. Amount capped at remaining; negative not allowed. */
    public static function addRepayment(int $loanId, string $date, float $amount, string $note, int $userId, ?int $accountId = null): void {
        if (!self::validDate($date)) throw new RuntimeException(__('Invalid repayment date.'));
        $amount = round($amount, 2);
        if ($amount <= 0.004) throw new RuntimeException(__('Repayment amount must be positive.'));
        Database::transaction(function () use ($loanId, $date, $amount, $note, $userId, $accountId) {
            $loan = db_one('SELECT * FROM personal_loans WHERE id = ?', [$loanId]);
            if (!$loan) throw new RuntimeException(__('Loan not found.'));
            if ($loan['status'] === 'cancelled') throw new RuntimeException(__('Cancelled loans cannot receive repayments.'));
            $totalRepaid = (float)db_val('SELECT COALESCE(SUM(amount),0) FROM personal_loan_repayments WHERE loan_id = ?', [$loanId]);
            $remaining = round((float)$loan['original_amount'] - $totalRepaid, 2);
            if ($amount - $remaining > 0.004) {
                throw new RuntimeException(sprintf(__('Amount exceeds the remaining balance (Rs %s).'), number_format($remaining, 2)));
            }
            $rid = db_insert('personal_loan_repayments', [
                'loan_id' => $loanId, 'repayment_date' => $date, 'amount' => $amount, 'account_id' => $accountId,
                'note' => trim($note) ?: null, 'recorded_by' => $userId, 'created_at' => now_local()
            ]);
            // status housekeeping (purely advisory)
            $newRemaining = round($remaining - $amount, 2);
            $newStatus = $newRemaining <= 0.004 ? 'paid' : 'partial';
            db_exec('UPDATE personal_loans SET status = ?, updated_by = ? WHERE id = ?', [$newStatus, $userId, $loanId]);
            if ($accountId) {
                $rctNo = 'RCT-PL-' . str_pad((string)$rid, 5, '0', STR_PAD_LEFT);
                try {
                    db_insert('receipts', [
                        'receipt_no' => $rctNo,
                        'party_type' => 'other',
                        'customer_id' => null,
                        'contact_name' => $loan['person_name'],
                        'account_id' => $accountId,
                        'date' => $date,
                        'amount' => $amount,
                        'reference' => 'Loan Return ' . $loan['loan_no'],
                        'notes' => 'Loan / Dasti return from ' . $loan['person_name'] . ($note ? ' — ' . $note : ''),
                        'created_by' => $userId,
                        'created_at' => now_local(),
                    ]);
                } catch (Throwable $re) {
                    error_log('Personal loan receipt insert error: ' . $re->getMessage());
                }
            }
            audit('repay', 'personal_loan_repayments', $rid, sprintf('Repayment Rs %s on loan %s — remaining Rs %s', number_format($amount, 2), $loan['loan_no'], number_format($newRemaining, 2)));
        });
    }

    /** Cancel a loan (never a hard delete — history stays). Reason required. */
    public static function cancelLoan(int $id, int $userId, string $reason): void {
        $reason = trim($reason);
        if ($reason === '') throw new RuntimeException(__('A reason is required to cancel.'));
        $loan = db_one('SELECT * FROM personal_loans WHERE id = ?', [$id]);
        if (!$loan) throw new RuntimeException(__('Loan not found.'));
        if ($loan['status'] === 'cancelled') throw new RuntimeException(__('Already cancelled.'));
        $repaid = (float)db_val('SELECT COALESCE(SUM(amount),0) FROM personal_loan_repayments WHERE loan_id = ?', [$id]);
        $newNote = trim(((string)($loan['notes'] ?? '')) . ' — cancelled by user ' . $userId . ': ' . $reason);
        db_exec("UPDATE personal_loans SET status = 'cancelled', notes = ?, updated_by = ? WHERE id = ?", [$newNote, $userId, $id]);
        audit('cancel', 'personal_loans', $id, sprintf('Cancelled loan %s — Rs %s repaid Rs %s — %s', $loan['loan_no'], number_format((float)$loan['original_amount'], 2), number_format($repaid, 2), $reason));
    }

    /** Dashboard rollups. */
    public static function summary(): array {
        $rows = self::listLoans('all');
        $t = ['outstanding' => 0.0, 'original' => 0.0, 'repaid' => 0.0, 'people_outstanding' => 0, 'overdue' => 0, 'count' => count($rows)];
        $people = [];
        foreach ($rows as $r) {
            if ($r['status'] === 'cancelled') continue;
            $t['original'] += $r['original_amount'];
            $t['repaid'] += $r['total_repaid'];
            $t['outstanding'] += $r['remaining_amount'];
            if ($r['remaining_amount'] > 0.004) $people[mb_strtolower($r['person_name'])] = true;
            if ($r['status'] === 'overdue') $t['overdue']++;
        }
        $t['people_outstanding'] = count($people);
        return $t;
    }
}
