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
            throw new RuntimeException('An approval decision already exists for this document.');
        }
        $id = db_insert('approval_requests', [
            'request_type' => $type, 'reference_type' => $referenceType, 'reference_id' => $referenceId,
            'amount' => round($amount, 2), 'reason' => $reason, 'requested_by' => $userId,
            'requested_at' => now_local(), 'status' => 'pending', 'created_at' => now_local(),
        ]);
        try { Notifications::notifyApprovalRequested($id); } catch (Throwable $e) { error_log('notify approval request: ' . $e->getMessage()); }
        audit('request', 'approval_requests', $id, strtoupper($type) . " requested — Rs " . number_format($amount, 2));
        return $id;
    }

    public static function pendingCount(): int {
        return (int)db_val("SELECT COUNT(*) FROM approval_requests WHERE status = 'pending'");
    }

    /** Approve or reject a request. Returns [action, referenceId, journalOrMessage]. */
    public static function decide(int $id, bool $approve, string $note, int $deciderId): array {
        return Database::transaction(function () use ($id, $approve, $note, $deciderId) {
            $req = db_one('SELECT * FROM approval_requests WHERE id = ?', [$id]);
            if (!$req) throw new RuntimeException('Approval request not found');
            if ($req['status'] !== 'pending') throw new RuntimeException('Already decided (' . $req['status'] . ').');
            if ((int)$req['requested_by'] === $deciderId) {
                throw new RuntimeException('You cannot approve your own request.');
            }
            if (!$approve && trim($note) === '') throw new RuntimeException('A rejection reason is required.');

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
            case 'payments': case 'expenses': case 'payroll': case 'bank_transfers':
                db_exec("UPDATE {$req['reference_type']} SET status = 'rejected' WHERE id = ?", [(int)$req['reference_id']]); break;
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
        }
    }

    /** Post the source document. Each handler is idempotent + status-guarded — never posts twice. */
    private static function dispatchPost(array $req, int $deciderId) {
        switch ($req['reference_type']) {
            case 'payments':        Posting::postPayment((int)$req['reference_id']); return 'payment posted';
            case 'bank_transfers':  Posting::postBankTransfer((int)$req['reference_id']); return 'transfer posted';
            case 'expenses':        Posting::postExpense((int)$req['reference_id']); return 'expense posted';
            case 'payroll':         $p = db_one('SELECT account_id FROM payroll WHERE id = ?', [(int)$req['reference_id']]); Posting::postPayroll((int)$req['reference_id'], (int)$p['account_id']); return 'salary posted';
            case 'payroll_payments': Posting::postPayrollPayment((int)$req['reference_id']); return 'salary payment posted';
            case 'employee_advances': Posting::postSalaryAdvance((int)$req['reference_id']); return 'advance disbursed';
            case 'employee_loans':  Posting::postEmployeeLoan((int)$req['reference_id']); return 'loan disbursed';
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
            case 'factory_issues':      Posting::postFactoryIssue((int)$req['reference_id']); return 'factory consumption posted';
            default: throw new RuntimeException('Unknown approval reference type: ' . $req['reference_type']);
        }
    }
}
