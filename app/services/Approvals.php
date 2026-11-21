<?php
/**
 * Financial approval engine — every outgoing payment requires Owner/Admin approval.
 *
 * Flow: requester acts → document saved as PENDING → approval_request created →
 *       Owner/Admin decides in /admin/approvals →
 *       approved  → dispatch to the matching Posting handler (exactly once)
 *       rejected  → nothing posts; balances unchanged
 * Requester can never approve their own request. All decisions audited with who/when/amount/reason.
 */
final class Approvals {

    public static function create(string $type, string $referenceType, int $referenceId, float $amount, ?string $reason, int $userId): int {
        $existing = db_one('SELECT id, status FROM approval_requests WHERE reference_type = ? AND reference_id = ? AND request_type = ?', [$referenceType, $referenceId, $type]);
        if ($existing) {
            if ($existing['status'] === 'pending') return (int)$existing['id'];   // re-request = no-op
            throw new RuntimeException(__('An approval decision already exists for this document.'));
        }
        $id = db_insert('approval_requests', [
            'request_type' => $type, 'reference_type' => $referenceType, 'reference_id' => $referenceId,
            'amount' => round($amount, 2), 'reason' => $reason, 'requested_by' => $userId,
            'requested_at' => now_local(), 'status' => 'pending', 'created_at' => now_local(),
        ]);
        try { Notifications::notifyApprovalRequested($id); } catch (Throwable $e) { error_log('notify approval request: ' . $e->getMessage()); }
        audit('request', 'approval_requests', $id, approval_label($type) . " requested — Rs " . number_format($amount, 2));
        return $id;
    }

    public static function pendingCount(): int {
        return (int)db_val("SELECT COUNT(*) FROM approval_requests WHERE status = 'pending'");
    }

    /** Approve or reject a request. Returns [action, referenceId, journalOrMessage]. */
    public static function decide(int $id, bool $approve, string $note, int $deciderId): array {
        return Database::transaction(function () use ($id, $approve, $note, $deciderId) {
            $req = db_one('SELECT * FROM approval_requests WHERE id = ?', [$id]);
            if (!$req) throw new RuntimeException(__('Approval request not found'));
            if ($req['status'] !== 'pending') throw new RuntimeException(__('Already decided (') . $req['status'] . ').');
            if ((int)$req['requested_by'] === $deciderId) {
                throw new RuntimeException(__('You cannot approve your own request.'));
            }
            if (!$approve && trim($note) === '') throw new RuntimeException(__('A rejection reason is required.'));

            if (!$approve) {
                db_exec("UPDATE approval_requests SET status = 'rejected', approver_id = ?, decided_at = ?, decision_note = ? WHERE id = ?",
                    [$deciderId, now_local(), $note, $id]);
                audit('reject', 'approval_requests', $id, $note);
                // Rejected request never affects balances — but mark the source doc as rejected so it cannot post later
                self::markSourceRejected($req);
                try { Notifications::notifyApprovalDecided($id, false, $note); } catch (Throwable $e) { error_log('notify decision: ' . $e->getMessage()); }
                return ['rejected', (int)$req['reference_id'], $note];
            }

            // Record who authorised a bonus/discount/below-minimum invoice
            if ($req['reference_type'] === 'sales_invoices') {
                db_exec('UPDATE sales_invoices SET authorized_by = ? WHERE id = ? AND authorized_by IS NULL', [$deciderId, (int)$req['reference_id']]);
            }

            // Stamp approver on source documents that track it
            if ($req['reference_type'] === 'employee_advances') {
                db_exec('UPDATE employee_advances SET approved_by = ?, approved_at = COALESCE(approved_at, ?) WHERE id = ?', [$deciderId, now_local(), (int)$req['reference_id']]);
            }
            if ($req['reference_type'] === 'employee_loans') {
                db_exec('UPDATE employee_loans SET approved_by = ?, approved_at = COALESCE(approved_at, ?) WHERE id = ?', [$deciderId, now_local(), (int)$req['reference_id']]);
            }
            if ($req['reference_type'] === 'employee_bonuses') {
                db_exec('UPDATE employee_bonuses SET approved_by = ?, approved_at = COALESCE(approved_at, ?) WHERE id = ?', [$deciderId, now_local(), (int)$req['reference_id']]);
            }

            // Approve: flip doc + post exactly once
            $result = self::dispatchPost($req, $deciderId);

            db_exec("UPDATE approval_requests SET status = 'approved', approver_id = ?, decided_at = ?, decision_note = ? WHERE id = ?",
                [$deciderId, now_local(), $note ?: null, $id]);
            try { Notifications::notifyApprovalDecided($id, true, $note); } catch (Throwable $e) { error_log('notify decision: ' . $e->getMessage()); }
            audit('approve', 'approval_requests', $id, 'Approved — posted: ' . json_encode($result));
            return ['approved', (int)$req['reference_id'], $result];
        });
    }

    private static function markSourceRejected(array $req): void {
        switch ($req['reference_type']) {
            case 'payments': case 'payroll': case 'bank_transfers':
                db_exec("UPDATE {$req['reference_type']} SET status = 'rejected' WHERE id = ?", [(int)$req['reference_id']]); break;
            case 'expenses':
                db_exec("UPDATE expenses SET status = 'rejected' WHERE id = ?", [(int)$req['reference_id']]);
                // Companion company_expenses row (if any) follows the same decision
                db_exec("UPDATE company_expenses SET status = 'rejected' WHERE linked_expense_id = ? AND status = 'pending'", [(int)$req['reference_id']]);
                break;
            case 'payroll_payments':
                db_exec("UPDATE payroll_payments SET status = 'rejected' WHERE id = ?", [(int)$req['reference_id']]);
                // Payroll row stays draft/partial so the employee can retry — nothing posted.
                $ppRow = db_one('SELECT payroll_id FROM payroll_payments WHERE id = ?', [(int)$req['reference_id']]);
                if ($ppRow) {
                    $pid = (int)$ppRow['payroll_id'];
                    $paid = (float)db_val('SELECT amount_paid FROM payroll WHERE id = ?', [$pid]);
                    db_exec("UPDATE payroll SET status = ? WHERE id = ? AND status <> 'reversed'", [$paid > 0.004 ? 'partial' : 'draft', $pid]);
                }
                break;
            case 'employee_advances': db_exec("UPDATE employee_advances SET status = 'rejected' WHERE id = ?", [(int)$req['reference_id']]); break;
            case 'employee_loans': db_exec("UPDATE employee_loans SET status = 'rejected' WHERE id = ?", [(int)$req['reference_id']]); break;
            case 'employee_bonuses': db_exec("UPDATE employee_bonuses SET status = 'rejected' WHERE id = ? AND status = 'pending'", [(int)$req['reference_id']]); break;
            // employees/salesmen registrations: record was created inactive; rejecting must keep it inactive — nothing to flip.
            case 'sales_invoices': db_exec("UPDATE sales_invoices SET status = 'reversed' WHERE id = ? AND status = 'pending'", [(int)$req['reference_id']]); break;
            case 'salesman_issues': db_exec("UPDATE salesman_issues SET status = 'reversed' WHERE id = ? AND status = 'pending_approval'", [(int)$req['reference_id']]); break;
            case 'claim_goods': db_exec("UPDATE claim_goods SET status = 'rejected' WHERE id = ? AND status = 'pending_approval'", [(int)$req['reference_id']]); break;
            case 'claim_goods_payments': db_exec("UPDATE claim_goods_payments SET status = 'rejected' WHERE id = ? AND status = 'pending'", [(int)$req['reference_id']]); break;
            case 'purchase_orders': db_exec("UPDATE purchase_orders SET status = 'rejected' WHERE id = ? AND status = 'pending_approval'", [(int)$req['reference_id']]); break;
            case 'purchase_invoices': db_exec("UPDATE purchase_invoices SET status = 'rejected' WHERE id = ? AND status = 'pending_approval'", [(int)$req['reference_id']]); break;
            case 'maintenance_records': db_exec("UPDATE maintenance_records SET status = 'rejected' WHERE id = ? AND status = 'pending'", [(int)$req['reference_id']]); break;
            case 'factory_issues':      db_exec("UPDATE factory_issues SET status = 'rejected' WHERE id = ? AND status = 'pending'", [(int)$req['reference_id']]); break;
            case 'salesman_salary_payments': db_exec("UPDATE salesman_salary_payments SET status = 'rejected' WHERE id = ? AND status = 'pending'", [(int)$req['reference_id']]); break;
            case 'salesman_salary_advances': db_exec("UPDATE salesman_salary_advances SET status = 'rejected' WHERE id = ? AND status = 'pending'", [(int)$req['reference_id']]); break;
            case 'salesman_expenses':        db_exec("UPDATE salesman_expenses SET status = 'rejected' WHERE id = ? AND status = 'pending'", [(int)$req['reference_id']]); break;
            case 'other_income':             db_exec("UPDATE other_income SET status = 'rejected' WHERE id = ? AND status = 'pending'", [(int)$req['reference_id']]); break;
            // Gift rejection cascades to its linked expense (money gifts); goods gift never touches stock
            case 'gifts':
                $g = db_one('SELECT expense_id FROM gifts WHERE id = ?', [(int)$req['reference_id']]);
                db_exec("UPDATE gifts SET status = 'rejected' WHERE id = ? AND status = 'pending'", [(int)$req['reference_id']]);
                if ($g && !empty($g['expense_id'])) {
                    db_exec("UPDATE expenses SET status = 'rejected' WHERE id = ? AND status = 'pending'", [(int)$g['expense_id']]);
                }
                break;
        }
    }

    /** Post the source document. Each handler is idempotent + status-guarded — never posts twice. */
    private static function dispatchPost(array $req, int $deciderId) {
        switch ($req['reference_type']) {
            case 'payments':        Posting::postPayment((int)$req['reference_id']); return 'payment posted';
            case 'bank_transfers':  Posting::postBankTransfer((int)$req['reference_id']); return 'transfer posted';
            case 'expenses':        Posting::postExpense((int)$req['reference_id']);
                                   // Companion company_expenses row (if any) follows the same decision
                                   db_exec("UPDATE company_expenses SET status = 'posted' WHERE linked_expense_id = ? AND status = 'pending'", [(int)$req['reference_id']]);
                                   return 'expense posted';
            case 'payroll':         $p = db_one('SELECT account_id FROM payroll WHERE id = ?', [(int)$req['reference_id']]); Posting::postPayroll((int)$req['reference_id'], (int)$p['account_id']); return 'salary posted';
            case 'payroll_payments': Posting::postPayrollPayment((int)$req['reference_id']); return 'salary payment posted';
            case 'employee_advances': Posting::postSalaryAdvance((int)$req['reference_id']); return 'advance disbursed';
            case 'employee_loans':  Posting::postEmployeeLoan((int)$req['reference_id']); return 'loan disbursed';
            case 'employee_bonuses': Posting::postEmployeeBonus((int)$req['reference_id']); return 'bonus posted';
            case 'sales_invoices':  Posting::postSalesInvoice((int)$req['reference_id']); return 'sales invoice posted';
            case 'salesman_issues': db_exec("UPDATE salesman_issues SET status = 'approved' WHERE id = ? AND status = 'pending_approval'", [(int)$req['reference_id']]); Posting::postSalesmanIssue((int)$req['reference_id']); return 'bonus/issue posted';
            case 'claim_goods':      Posting::postClaimGoods((int)$req['reference_id']); return 'claim goods posted';
            case 'claim_goods_payments': Posting::postClaimPayment((int)$req['reference_id']); return 'claim payment posted';
            case 'purchase_orders':  Posting::approvePurchaseOrder((int)$req['reference_id']); return 'purchase order approved';
            case 'purchase_invoices': Posting::postPurchaseInvoice((int)$req['reference_id']); return 'purchase / GRN posted';
            case 'maintenance_records': Posting::postMaintenance((int)$req['reference_id']); return 'maintenance record posted';
            case 'factory_issues':      Posting::postFactoryIssue((int)$req['reference_id']); return 'factory consumption posted';
            case 'salesman_salary_payments': Posting::postSalesmanSalary((int)$req['reference_id']); return 'salesman salary posted';
            case 'salesman_salary_advances': Posting::postSalesmanAdvance((int)$req['reference_id']); return 'salesman advance posted';
            case 'salesman_expenses':        Posting::postSalesmanExpense((int)$req['reference_id']); return 'salesman expense posted';
            case 'other_income':              Posting::postOtherIncome((int)$req['reference_id']); return 'other income posted';
            // Gifts: money gifts ride through the existing expenses approval posting (exactly-once via postExpense);
            // goods gifts deduct FG stock from the dedicated Gifts ledger (no journals, no receivables)
            case 'gifts':
                $g = db_one('SELECT gift_type, expense_id FROM gifts WHERE id = ?', [(int)$req['reference_id']]);
                if (!$g) throw new RuntimeException(__('Gift not found.'));
                if ($g['gift_type'] === 'money' && !empty($g['expense_id'])) {
                    Posting::postExpense((int)$g['expense_id']);
                    db_exec("UPDATE gifts SET status = 'posted', approved_by = ? WHERE id = ? AND status = 'pending'", [$deciderId, (int)$req['reference_id']]);
                    return 'money gift posted via expense';
                }
                Gifts::post((int)$req['reference_id'], $deciderId);
                return 'goods gift posted';
            case 'employees':
                db_exec("UPDATE employees SET is_active = 1 WHERE id = ? AND is_active = 0", [(int)$req['reference_id']]);
                audit('activate', 'employees', (int)$req['reference_id'], 'New employee registration approved by admin ' . $deciderId);
                return 'employee activated';
            case 'salesmen':
                db_exec("UPDATE salesmen SET is_active = 1 WHERE id = ? AND is_active = 0", [(int)$req['reference_id']]);
                // dedicated sales-stock warehouse created at approval time (same as Owner/Admin-created salesmen)
                $row = db_one('SELECT id, code, name, warehouse_id FROM salesmen WHERE id = ?', [(int)$req['reference_id']]);
                if ($row && empty($row['warehouse_id'])) {
                    $branchId = (int)db_val("SELECT id FROM branches WHERE is_main = 1 LIMIT 1");
                    $whCode = 'WH-' . $row['code'];
                    $existing = db_val('SELECT id FROM warehouses WHERE code = ?', [$whCode]);
                    if (!$existing) {
                        $existing = db_insert('warehouses', ['branch_id' => $branchId ?: null, 'code' => $whCode, 'name' => $row['name'] . ' — Sales Stock', 'type' => 'salesman', 'is_default' => 0, 'is_active' => 1, 'created_at' => now_local()]);
                    }
                    db_exec('UPDATE salesmen SET warehouse_id = ? WHERE id = ?', [(int)$existing, (int)$req['reference_id']]);
                }
                audit('activate', 'salesmen', (int)$req['reference_id'], 'New salesman registration approved by admin ' . $deciderId);
                return 'salesman activated';
            case 'factory_issues':      Posting::postFactoryIssue((int)$req['reference_id']); return 'factory consumption posted';
            default: throw new RuntimeException(__('Unknown approval reference type: ') . $req['reference_type']);
        }
    }

    /** Fetch a friendly description of the source document for the Approvals inbox and detail pages. */
    public static function source(string $refType, int $refId): array {
        $d = ['type' => $refType, 'party' => null, 'method' => null, 'account' => null, 'date' => null,
              'amount' => null, 'description' => null, 'rows' => [], 'attachment' => null, 'status' => null,
              'party_kind' => null, 'party_id' => null, 'party_code' => null, 'party_photo' => null];
        $acc = fn($id) => $id ? (string)db_val('SELECT name FROM cash_bank_accounts WHERE id = ?', [(int)$id]) : null;
        switch ($refType) {
            case 'payments':
                $p = db_one('SELECT p.*, s.name AS supplier_name FROM payments p LEFT JOIN suppliers s ON s.id = p.party_id WHERE p.id = ?', [$refId]);
                if ($p) { $d['party'] = $p['supplier_name'] ?: ($p['party_type'] ?? 'owner'); $d['method'] = strtoupper($p['payment_method'] ?? ''); $d['account'] = $acc($p['account_id']); $d['date'] = $p['date']; $d['amount'] = $p['amount']; $d['description'] = $p['notes'] ?? null; $d['status'] = $p['status']; if (($p['party_type'] ?? '') === 'supplier' && $p['party_id']) { $d['party_kind'] = 'supplier'; $d['party_id'] = (int)$p['party_id']; } }
                break;
            case 'bank_transfers':
                $t = db_one('SELECT * FROM bank_transfers WHERE id = ?', [$refId]);
                if ($t) { $d['party'] = 'Transfer'; $d['method'] = 'TRANSFER'; $d['account'] = ($acc($t['from_account_id']) ?: '?') . ' → ' . ($acc($t['to_account_id']) ?: '?'); $d['date'] = $t['date']; $d['amount'] = $t['amount']; $d['description'] = $t['notes'] ?? null; $d['status'] = $t['status']; }
                break;
            case 'expenses': case 'maintenance_records':
                if ($refType === 'expenses') { $x = db_one('SELECT * FROM expenses WHERE id = ?', [$refId]); }
                else { $x = db_one('SELECT * FROM maintenance_records WHERE id = ?', [$refId]); }
                if ($x) { $d['party'] = $x['payee'] ?? ($x['vendor'] ?? null); $d['method'] = strtoupper($x['payment_type'] ?? 'cash'); $d['account'] = $acc($x['account_id']); $d['date'] = $x['date'] ?? ($x['problem_date'] ?? null); $d['amount'] = $x['amount'] ?? ($x['total_cost'] ?? null); $d['description'] = $x['description'] ?? ($x['problem'] ?? ($x['notes'] ?? null)); $d['attachment'] = $x['attachment'] ?? null; $d['status'] = $x['status']; }
                break;
            case 'salesman_expenses':
                $x = db_one('SELECT e.*, s.name AS sm FROM salesman_expenses e JOIN salesmen s ON s.id = e.salesman_id WHERE e.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['sm']; $d['method'] = strtoupper($x['payment_type']) . ' / ' . strtoupper($x['paid_from'] ?? 'company'); $d['account'] = $acc($x['account_id']) ?: 'collections'; $d['date'] = $x['date']; $d['amount'] = $x['amount']; $d['description'] = $x['description']; $d['attachment'] = $x['attachment'] ?? null; $d['status'] = $x['status']; $d['party_kind'] = 'salesman'; $d['party_id'] = (int)$x['salesman_id']; }
                break;
            case 'payroll_payments':
                $x = db_one('SELECT pp.*, e.id AS employee_id, e.name AS emp, p.period_start, p.period_end FROM payroll_payments pp JOIN payroll p ON p.id = pp.payroll_id JOIN employees e ON e.id = p.employee_id WHERE pp.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['emp']; $d['method'] = strtoupper($x['method'] ?? 'cash'); $d['account'] = $acc($x['account_id']); $d['date'] = $x['created_at'] ?? null; $d['amount'] = $x['amount']; $d['description'] = 'Period ' . ($x['period_start'] ?? '') . ' → ' . ($x['period_end'] ?? ''); $d['status'] = $x['status']; $d['party_kind'] = 'employee'; $d['party_id'] = (int)$x['employee_id']; }
                break;
            case 'salesman_salary_payments':
                $x = db_one('SELECT p.*, s.name FROM salesman_salary_payments p JOIN salesmen s ON s.id = p.salesman_id WHERE p.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['name']; $d['method'] = strtoupper($x['method']); $d['account'] = $acc($x['account_id']); $d['date'] = $x['date']; $d['amount'] = $x['amount']; $d['description'] = 'Salary period ' . ($x['period'] ?? '-'); $d['status'] = $x['status']; $d['party_kind'] = 'salesman'; $d['party_id'] = (int)$x['salesman_id']; }
                break;
            case 'employee_advances': case 'salesman_salary_advances':
                $tbl = $refType;
                $x = db_one("SELECT * FROM $tbl WHERE id = ?", [$refId]);
                if ($x) { $d['party'] = $refType === 'employee_advances' ? (string)db_val('SELECT name FROM employees WHERE id = ?', [(int)$x['employee_id']]) : (string)db_val('SELECT name FROM salesmen WHERE id = ?', [(int)$x['salesman_id']]); $d['method'] = strtoupper($x['method'] ?? 'cash'); $d['account'] = $acc($x['account_id']); $d['date'] = $x['date']; $d['amount'] = $x['amount']; $d['description'] = $x['reason'] ?? null; $d['status'] = $x['status']; if ($refType === 'employee_advances') { $d['party_kind'] = 'employee'; $d['party_id'] = (int)$x['employee_id']; } else { $d['party_kind'] = 'salesman'; $d['party_id'] = (int)$x['salesman_id']; } }
                break;
            case 'employee_loans':
                $x = db_one('SELECT l.*, e.name FROM employee_loans l JOIN employees e ON e.id = l.employee_id WHERE l.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['name']; $d['method'] = strtoupper($x['method'] ?? 'cash'); $d['account'] = $acc($x['account_id']); $d['date'] = $x['date']; $d['amount'] = $x['amount']; $d['description'] = $x['reason'] ?? null; $d['status'] = $x['status']; $d['party_kind'] = 'employee'; $d['party_id'] = (int)$x['employee_id']; }
                break;
            case 'employee_bonuses':
                $x = db_one('SELECT b.*, e.name FROM employee_bonuses b JOIN employees e ON e.id = b.employee_id WHERE b.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['name']; $d['method'] = strtoupper($x['method'] ?? ($x['payment_method'] ?? 'cash')); $d['account'] = $acc($x['account_id']); $d['date'] = $x['date']; $d['amount'] = $x['amount']; $d['description'] = $x['reason'] ?? null; $d['status'] = $x['status']; $d['party_kind'] = 'employee'; $d['party_id'] = (int)$x['employee_id']; }
                break;
            case 'other_income':
                $x = db_one('SELECT * FROM other_income WHERE id = ?', [$refId]);
                if ($x) { $d['party'] = 'Other income'; $d['method'] = strtoupper($x['method'] ?? 'cash'); $d['account'] = $acc($x['account_id']); $d['date'] = $x['date']; $d['amount'] = $x['amount']; $d['description'] = $x['description'] ?: ($x['category'] ?? null); $d['status'] = $x['status']; }
                break;
            case 'employees':
                $x = db_one('SELECT * FROM employees WHERE id = ?', [$refId]);
                if ($x) {
                    $d['party'] = $x['name']; $d['method'] = strtoupper($x['payment_method'] ?? 'cash');
                    $d['date'] = $x['created_at'] ?? null; $d['amount'] = (float)($x['salary_rate'] ?? 0);
                    $d['description'] = 'New employee registration — ' . ($x['code'] ?? '');
                    $d['status'] = $x['is_active'] ? 'active' : 'pending';
                    $d['party_kind'] = 'employee'; $d['party_id'] = (int)$x['id'];
                    $d['registration'] = [
                        'code' => $x['code'] ?? null, 'name' => $x['name'] ?? null,
                        'cnic' => $x['cnic'] ?? null, 'phone' => $x['phone'] ?? null,
                        'address' => $x['address'] ?? null, 'designation' => $x['designation'] ?? null,
                        'department' => $x['department'] ?? null, 'join_date' => $x['join_date'] ?? null,
                        'salary_frequency' => $x['salary_frequency'] ?? ($x['salary_type'] ?? 'monthly'),
                        'salary_rate' => (float)($x['salary_rate'] ?? 0), 'payment_method' => $x['payment_method'] ?? 'cash',
                        'kind' => $x['kind'] ?? 'employee',
                    ];
                }
                break;
            case 'salesmen':
                $x = db_one('SELECT * FROM salesmen WHERE id = ?', [$refId]);
                if ($x) {
                    $d['party'] = $x['name']; $d['date'] = $x['created_at'] ?? null; $d['amount'] = (float)($x['salary'] ?? 0);
                    $d['description'] = 'New salesman registration — ' . ($x['code'] ?? '');
                    $d['status'] = $x['is_active'] ? 'active' : 'pending';
                    $d['party_kind'] = 'salesman'; $d['party_id'] = (int)$x['id'];
                    $d['registration'] = [
                        'code' => $x['code'] ?? null, 'name' => $x['name'] ?? null,
                        'cnic' => $x['cnic'] ?? null, 'phone' => $x['phone'] ?? null,
                        'address' => $x['address'] ?? null, 'area' => $x['area'] ?? null,
                        'join_date' => $x['join_date'] ?? null, 'commission_percent' => (float)($x['commission_percent'] ?? 0),
                        'salary_frequency' => $x['salary_frequency'] ?? 'monthly',
                        'salary' => (float)($x['salary'] ?? 0),
                        'vehicle_type' => $x['vehicle_type'] ?? null, 'vehicle_reg' => $x['vehicle_reg'] ?? null,
                    ];
                }
                break;
            case 'salesman_issues':
                $x = db_one('SELECT i.*, s.name FROM salesman_issues i JOIN salesmen s ON s.id = i.salesman_id WHERE i.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['name']; $d['date'] = $x['date']; $d['amount'] = (float)$x['total_payable'] + (float)$x['total_bonus_cost']; $d['description'] = 'Goods issue ' . $x['issue_no'] . ($x['issue_type'] === 'transfer' ? ' (free transfer)' : ' (credit issue)'); $d['status'] = $x['status']; $d['party_kind'] = 'salesman'; $d['party_id'] = (int)$x['salesman_id']; }
                break;
            case 'claim_goods':
                $x = db_one('SELECT cg.*, s.name FROM claim_goods cg JOIN suppliers s ON s.id = cg.supplier_id WHERE cg.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['name']; $d['date'] = $x['date']; $d['amount'] = $x['claim_amount']; $d['description'] = 'Claim goods ' . $x['claim_no']; $d['status'] = $x['status']; $d['party_kind'] = 'supplier'; $d['party_id'] = (int)$x['supplier_id']; }
                break;
            case 'claim_goods_payments':
                $x = db_one('SELECT cgp.*, cg.claim_no, s.name, s.id AS supplier_id FROM claim_goods_payments cgp JOIN claim_goods cg ON cg.id = cgp.claim_goods_id JOIN suppliers s ON s.id = cg.supplier_id WHERE cgp.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['name']; $d['method'] = 'CASH / BANK'; $d['account'] = $acc($x['account_id']); $d['date'] = $x['date']; $d['amount'] = $x['amount']; $d['description'] = 'Claim payment — ' . $x['claim_no']; $d['status'] = $x['status']; $d['party_kind'] = 'supplier'; $d['party_id'] = (int)$x['supplier_id']; }
                break;
            case 'purchase_orders':
                $x = db_one('SELECT po.*, s.name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['name']; $d['date'] = $x['date'] ?? ($x['order_date'] ?? null); $d['amount'] = $x['total']; $d['description'] = 'Purchase order ' . ($x['order_no'] ?? ('#' . $x['id'])); $d['status'] = $x['status']; $d['party_kind'] = 'supplier'; $d['party_id'] = (int)$x['supplier_id']; }
                break;
            case 'purchase_invoices':
                $x = db_one('SELECT p.*, s.name FROM purchase_invoices p JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['name']; $d['date'] = $x['date']; $d['amount'] = $x['total']; $d['description'] = 'Purchase invoice ' . ($x['invoice_no'] ?? ('#' . $x['id'])); $d['status'] = $x['status']; $d['party_kind'] = 'supplier'; $d['party_id'] = (int)$x['supplier_id']; }
                break;
            case 'factory_issues':
                $x = db_one('SELECT * FROM factory_issues WHERE id = ?', [$refId]);
                if ($x) { $d['party'] = 'Factory consumption'; $d['date'] = $x['date']; $d['amount'] = $x['total_cost'] ?? null; $d['description'] = 'Batch ' . ($x['batch_no'] ?? '-'); $d['status'] = $x['status']; }
                break;
            case 'payroll':
                $x = db_one('SELECT p.*, e.name FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['name']; $d['method'] = strtoupper($x['payment_type'] ?? 'cash'); $d['account'] = $acc($x['account_id']); $d['date'] = $x['period_end'] ?? null; $d['amount'] = $x['net_pay']; $d['description'] = 'Salary period ' . $x['period_start'] . ' → ' . $x['period_end']; $d['status'] = $x['status']; $d['party_kind'] = 'employee'; $d['party_id'] = (int)$x['employee_id']; }
                break;
            case 'sales_invoices':
                $x = db_one('SELECT i.*, COALESCE(c.name, \'Walk-in\') AS name FROM sales_invoices i LEFT JOIN customers c ON c.id = i.customer_id WHERE i.id = ?', [$refId]);
                if ($x) { $d['party'] = $x['name']; $d['date'] = $x['date']; $d['amount'] = $x['total']; $d['description'] = 'Sale ' . ($x['invoice_no'] ?? ''); $d['status'] = $x['status']; $d['party_kind'] = 'customer'; $d['party_id'] = (int)$x['customer_id']; }
                break;
        }
        // Enrich party with ID code + saved profile photo (employees/salesmen only) so the
        // Admin console can show a face + name with every approval. Suppliers/customers
        // never carry a photo column, and CNIC / document images are never used as avatars.
        if ($d['party_kind'] && $d['party_id']) {
            if ($d['party_kind'] === 'employee') {
                $p = db_one('SELECT code, name, photo_path FROM employees WHERE id = ?', [$d['party_id']]);
            } elseif ($d['party_kind'] === 'salesman') {
                $p = db_one('SELECT code, name, photo_path FROM salesmen WHERE id = ?', [$d['party_id']]);
            } elseif ($d['party_kind'] === 'supplier') {
                $p = db_one('SELECT code, name FROM suppliers WHERE id = ?', [$d['party_id']]);
            } else { // customers have no code/photo
                $p = null;
            }
            if ($p) {
                $d['party_code'] = $p['code'] ?? null;
                $d['party_photo'] = $p['photo_path'] ?? null;
                $d['party'] = $p['name'] ?? $d['party'];
            }
        }
        return $d;
    }

}
