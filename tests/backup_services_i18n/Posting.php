<?php
/**
 * Transaction posting — bridges business documents to journals and stock.
 * Every function is idempotent and runs inside one DB transaction.
 */
final class Posting {

    /** KG quantity for a purchase line (uses stored qty_kg, falls back to converting via the line's unit). */
    private static function purchaseKgQty(array $it): float {
        if (isset($it['qty_kg']) && $it['qty_kg'] !== null && $it['qty_kg'] !== '') return round((float)$it['qty_kg'], 3);
        return UnitConverter::toKg((float)$it['qty'], (string)($it['unit_short'] ?? 'KG'));
    }

    /** Unit cost per KG for a purchase line (purchase price is entered per original unit). */
    private static function purchaseKgCost(array $it): float {
        $f = UnitConverter::kgFactor((string)($it['unit_short'] ?? 'KG'));
        return $f > 0 ? round((float)$it['unit_cost'] / $f, 4) : (float)$it['unit_cost'];
    }

    /* ============ PURCHASE ============ */

    /** Post a new purchase invoice (stock in + journal). Called right after inserting header/items. */

    /** Post a new purchase invoice (stock in + journal). Called right after inserting header/items. */
    public static function postPurchaseInvoice(int $invoiceId): void {
        $inv = db_one('SELECT * FROM purchase_invoices WHERE id = ?', [$invoiceId]);
        if (!$inv || !empty($inv['journal_entry_id'])) return; // exactly once
        if (!in_array($inv['status'], ['posted', 'pending_approval'], true)) return;
        // Approvals flow posts a pending invoice exactly once — flip then post
        db_exec("UPDATE purchase_invoices SET status = 'posted' WHERE id = ? AND status = 'pending_approval'", [$invoiceId]);
        // refresh cached row for downstream logic
        $inv = db_one('SELECT * FROM purchase_invoices WHERE id = ?', [$invoiceId]);
        $items = db_all('SELECT * FROM purchase_items WHERE invoice_id = ?', [$invoiceId]);
        if (!$items) throw new RuntimeException('Purchase invoice has no items.');

        $totalCost = 0.0; $totalTax = 0.0; $totals = 0.0;
        foreach ($items as $it) {
            $net = round((float)$it['qty'] * (float)$it['unit_cost'] - (float)$it['discount'], 2);
            $totalCost += $net; $totalTax += (float)$it['tax_amount']; $totals += $net + (float)$it['tax_amount'];
        }

        Database::transaction(function () use ($inv, $items, $invoiceId, $totalCost, $totalTax, $totals) {
            // Stock in at KG base (raw materials) — unit cost per KG so stock valuation is always per KG
            foreach ($items as $it) {
                Inventory::receive((int)$it['product_id'], (int)$inv['warehouse_id'], self::purchaseKgQty($it),
                    self::purchaseKgCost($it), 'purchase', 'purchase_invoice', $invoiceId);
            }
            $payAccount = $inv['payment_type'] === 'cash' ? self::defaultAccountGl('cash') : null;
            $lines = [];
            $lines[] = ['account' => 'INVENTORY', 'debit' => $totalCost, 'memo' => 'Goods received — ' . $inv['invoice_no']];
            if ($totalTax > 0) $lines[] = ['account' => 'TAX_IN', 'debit' => $totalTax, 'memo' => 'Input sales tax'];
            if ($payAccount) {
                $lines[] = ['account' => $payAccount, 'credit' => $totals, 'memo' => 'Cash purchase payment'];
            } else {
                $lines[] = ['account' => 'AP', 'credit' => $totals, 'supplier_id' => (int)$inv['supplier_id'], 'memo' => 'Credit purchase — ' . $inv['invoice_no']];
            }
            $journalId = Accounting::postEntry($lines, [
                'date' => $inv['date'], 'voucher_type' => 'PI', 'reference_type' => 'purchase_invoice',
                'reference_id' => $invoiceId, 'memo' => 'Purchase invoice',
                'idem_key' => 'purchase_invoice:' . $invoiceId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec('UPDATE purchase_invoices SET journal_entry_id = ?, invoice_no = ? WHERE id = ?', [$journalId, $entryNo, $invoiceId]);
            if (!empty($inv['po_id'])) db_exec("UPDATE purchase_orders SET status = 'received' WHERE id = ? AND status IN ('approved','draft','pending_approval')", [(int)$inv['po_id']]);
            audit('post', 'purchase_invoices', $invoiceId, 'Purchase posted; journal entry #' . $journalId);
        });
        try { Notifications::notifyPurchase($invoiceId); Notifications::checkLowStock(); } catch (Throwable $e) { error_log('notify purchase: ' . $e->getMessage()); }
    }

    /** Reverse a posted purchase invoice: roll back stock (if still on hand) and reverse journal. */
    public static function reversePurchaseInvoice(int $invoiceId, string $reason): void {
        $inv = db_one('SELECT * FROM purchase_invoices WHERE id = ?', [$invoiceId]);
        if (!$inv) throw new RuntimeException('Purchase invoice not found');
        if ($inv['status'] === 'reversed') return;
        Database::transaction(function () use ($inv, $invoiceId, $reason) {
            $items = db_all('SELECT * FROM purchase_items WHERE invoice_id = ?', [$invoiceId]);
            foreach ($items as $it) {
                Inventory::issue((int)$it['product_id'], (int)$inv['warehouse_id'], self::purchaseKgQty($it),
                    'purchase_reversal', 'purchase_invoice', $invoiceId, null, null,
                    'Reversal of purchase ' . $inv['invoice_no']);
            }
            if ($inv['journal_entry_id']) Accounting::reverseEntry((int)$inv['journal_entry_id'], $reason ?? 'Purchase reversal');
            db_exec("UPDATE purchase_invoices SET status = 'reversed' WHERE id = ?", [$invoiceId]);
            audit('reverse', 'purchase_invoices', $invoiceId, $reason ?? '');
        });
    }

    /* ============ FACTORY CONSUMPTION (RM issued to production, no double-deduct) ============ */

    /** Post a factory consumption issue — exactly once; deducts KG from RM stock at WA/FIFO cost. */
    public static function postFactoryIssue(int $issueId): void {
        $issue = db_one('SELECT * FROM factory_issues WHERE id = ?', [$issueId]);
        if (!$issue) throw new RuntimeException('Consumption issue not found.');
        if ($issue['status'] === 'posted') return; // exactly once
        if (!in_array($issue['status'], ['pending', 'approved'], true)) {
            throw new RuntimeException('Consumption issue cannot be posted from status ' . $issue['status'] . '.');
        }
        $items = db_all('SELECT * FROM factory_issue_items WHERE issue_id = ?', [$issueId]);
        if (!$items) throw new RuntimeException('No materials on this consumption issue.');
        Database::transaction(function () use ($issue, $items, $issueId) {
            foreach ($items as $it) {
                $unitCost = Inventory::issue((int)$it['product_id'], (int)$it['warehouse_id'], (float)$it['qty_kg'],
                    'factory_consumption', 'factory_issue', $issueId, null, null, 'Consumed for batch ' . ($issue['batch_no'] ?? '-'));
                $total = round($unitCost * (float)$it['qty_kg'], 2);
                db_exec('UPDATE factory_issue_items SET unit_cost = ?, total_cost = ? WHERE id = ?', [$unitCost, $total, $it['id']]);
            }
            db_exec("UPDATE factory_issues SET status = 'posted' WHERE id = ?", [$issueId]);
            audit('post', 'factory_issues', $issueId, 'Consumed ' . count($items) . ' material line(s) to batch ' . ($issue['batch_no'] ?? '-'));
        });
    }

    /** Cancel a consumption issue — posted issues return stock; pending/rejected ones just cancel. */
    public static function reverseFactoryIssue(int $issueId, string $reason): void {
        $issue = db_one('SELECT * FROM factory_issues WHERE id = ?', [$issueId]);
        if (!$issue || $issue['status'] === 'cancelled') return;
        if (!in_array($issue['status'], ['posted', 'pending', 'approved', 'rejected'], true)) {
            throw new RuntimeException('Consumption issue cannot be cancelled from status ' . $issue['status']);
        }
        Database::transaction(function () use ($issue, $issueId, $reason) {
            if ($issue['status'] === 'posted') {
                foreach (db_all('SELECT * FROM factory_issue_items WHERE issue_id = ?', [$issueId]) as $it) {
                    Inventory::receive((int)$it['product_id'], (int)$it['warehouse_id'], (float)$it['qty_kg'], (float)$it['unit_cost'],
                        'factory_consumption_reversal', 'factory_issue', $issueId, null, null, 'Consumption reversed: ' . $reason);
                }
            }
            db_exec("UPDATE factory_issues SET status = 'cancelled' WHERE id = ?", [$issueId]);
            audit('cancel', 'factory_issues', $issueId, $reason ?: 'cancelled');
        });
    }

    /* ============ SALES ============ */

    /** Post a new sales invoice (stock out at cost + journal + COGS). */
    public static function postSalesInvoice(int $invoiceId): void {
        $inv = db_one('SELECT * FROM sales_invoices WHERE id = ?', [$invoiceId]);
        if (!$inv || !empty($inv['journal_entry_id'])) return; // exactly once
        if ($inv['status'] === 'reversed') return;
        // Approvals flow posts a pending invoice exactly once — flip then post
        db_exec("UPDATE sales_invoices SET status = 'posted' WHERE id = ? AND status = 'pending'", [$invoiceId]);
        $items = db_all('SELECT * FROM sales_invoice_items WHERE invoice_id = ?', [$invoiceId]);
        if (!$items) throw new RuntimeException('Sales invoice has no items.');

        Database::transaction(function () use ($inv, $items, $invoiceId) {
            $normalCost = 0.0; $bonusCost = 0.0;
            // Deduct stock at cost; capture actual cost per line
            $q = db()->prepare('UPDATE sales_invoice_items SET cost_price = ?, cost_total = ? WHERE id = ?');
            foreach ($items as &$it) {
                $unitCost = Inventory::issue((int)$it['product_id'], (int)$inv['warehouse_id'], (float)$it['qty'],
                    'sale', 'sales_invoice', $invoiceId);
                $lineCost = round($unitCost * (float)$it['qty'], 2);
                $q->execute([$unitCost, $lineCost, $it['id']]);
                if ((int)$it['is_bonus'] === 1) $bonusCost += $lineCost; else $normalCost += $lineCost;
            }
            unset($it);

            $total = (float)$inv['total'];
            $netBeforeTax = round((float)$inv['subtotal'] - (float)$inv['discount'], 2);
            $tax = (float)$inv['tax'];
            $totalCost = $normalCost + $bonusCost;

            $lines = [];
            if ($inv['payment_type'] === 'cash') {
                $lines[] = ['account' => self::defaultAccountGl('cash'), 'debit' => $total, 'memo' => 'Cash sale — ' . $inv['invoice_no']];
            } else {
                $lines[] = ['account' => 'AR', 'debit' => $total, 'customer_id' => (int)$inv['customer_id'], 'memo' => 'Credit sale — ' . $inv['invoice_no']];
            }
            $lines[] = ['account' => 'SALES', 'credit' => $netBeforeTax, 'memo' => 'Sales — ' . $inv['invoice_no']];
            if ($tax > 0) $lines[] = ['account' => 'TAX_OUT', 'credit' => $tax, 'memo' => 'Sales tax'];
            // COGS / inventory
            if ($normalCost > 0) $lines[] = ['account' => 'COGS', 'debit' => $normalCost, 'memo' => 'COGS — ' . $inv['invoice_no']];
            if ($bonusCost > 0)  $lines[] = ['account' => 'PROMO', 'debit' => $bonusCost, 'memo' => 'Free/bonus goods at cost'];
            if ($totalCost > 0)  $lines[] = ['account' => 'INVENTORY', 'credit' => $totalCost, 'memo' => 'Inventory issued — ' . $inv['invoice_no']];

            $journalId = Accounting::postEntry($lines, [
                'date' => $inv['date'], 'voucher_type' => 'SI', 'reference_type' => 'sales_invoice',
                'reference_id' => $invoiceId, 'memo' => 'Sales invoice',
                'idem_key' => 'sales_invoice:' . $invoiceId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec('UPDATE sales_invoices SET journal_entry_id = ?, cost_total = ?, invoice_no = ? WHERE id = ?', [$journalId, $totalCost, $entryNo, $invoiceId]);
            audit('post', 'sales_invoices', $invoiceId, 'Sale posted; journal entry #' . $journalId . '; cost ' . $totalCost);
        });
        try { Notifications::notifySale($invoiceId); Notifications::checkLowStock(); } catch (Throwable $e) { error_log('notify sale: ' . $e->getMessage()); }
    }

    public static function reverseSalesInvoice(int $invoiceId, string $reason): void {
        $inv = db_one('SELECT * FROM sales_invoices WHERE id = ?', [$invoiceId]);
        if (!$inv) throw new RuntimeException('Sales invoice not found');
        if ($inv['status'] === 'reversed') return;
        Database::transaction(function () use ($inv, $invoiceId, $reason) {
            $items = db_all('SELECT * FROM sales_invoice_items WHERE invoice_id = ?', [$invoiceId]);
            foreach ($items as $it) {
                Inventory::receive((int)$it['product_id'], (int)$inv['warehouse_id'], (float)$it['qty'],
                    (float)($it['cost_price'] ?: 0), 'sale_reversal', 'sales_invoice', $invoiceId,
                    null, null, 'Reversal of sale ' . $inv['invoice_no']);
            }
            if ($inv['journal_entry_id']) Accounting::reverseEntry((int)$inv['journal_entry_id'], $reason ?: 'Sales invoice reversal');
            db_exec("UPDATE sales_invoices SET status = 'reversed' WHERE id = ?", [$invoiceId]);
            audit('reverse', 'sales_invoices', $invoiceId, $reason ?? '');
        });
    }

    /* ============ SALES RETURN ============ */

    public static function postSalesReturn(int $returnId): void {
        $ret = db_one('SELECT * FROM sales_returns WHERE id = ?', [$returnId]);
        if (!$ret || $ret['status'] !== 'posted' || !empty($ret['journal_entry_id'])) return;
        $items = db_all('SELECT * FROM sales_return_items WHERE return_id = ?', [$returnId]);
        if (!$items) throw new RuntimeException('Sales return has no items.');
        $inv = db_one('SELECT * FROM sales_invoices WHERE id = ?', [(int)$ret['sales_invoice_id']]);

        Database::transaction(function () use ($ret, $items, $returnId, $inv) {
            $netReturned = 0.0; $taxReturned = 0.0; $costReturned = 0.0;
            foreach ($items as $it) {
                $line = db_one('SELECT * FROM sales_invoice_items WHERE invoice_id = ? AND product_id = ? ORDER BY id LIMIT 1', [$ret['sales_invoice_id'], $it['product_id']]);
                $qtyRatio = ($line && (float)$line['qty'] > 0) ? min(1, (float)$it['qty'] / (float)$line['qty']) : 1;
                $netReturned += $line ? round((float)$line['net_before_tax'] * $qtyRatio, 2) : round((float)$it['line_total'], 2);
                $taxReturned += $line ? round((float)$line['tax_amount'] * $qtyRatio, 2) : 0.0;
                $costReturned += round((float)$it['cost_price'] * (float)$it['qty'], 2);
                // Restock at returned cost
                Inventory::receive((int)$it['product_id'], (int)$inv['warehouse_id'], (float)$it['qty'],
                    (float)$it['cost_price'], 'sale_return', 'sales_return', $returnId);
            }
            $total = $netReturned + $taxReturned;
            $lines = [
                ['account' => 'SALES_RETURNS', 'debit' => $netReturned, 'memo' => 'Sales return ' . $ret['return_no']],
            ];
            if ($taxReturned > 0) $lines[] = ['account' => 'TAX_OUT', 'debit' => $taxReturned, 'memo' => 'Output tax on return'];
            $lines[] = ['account' => 'AR', 'credit' => $total, 'customer_id' => (int)$ret['customer_id'], 'memo' => 'Return — ' . $ret['return_no']];
            if ($costReturned > 0) {
                $lines[] = ['account' => 'INVENTORY', 'debit' => $costReturned, 'memo' => 'Restocked'];
                $lines[] = ['account' => 'COGS', 'credit' => $costReturned, 'memo' => 'COGS reversal'];
            }
            $journalId = Accounting::postEntry($lines, [
                'date' => $ret['date'], 'voucher_type' => 'SR', 'reference_type' => 'sales_return',
                'reference_id' => $returnId, 'memo' => 'Sales return',
                'idem_key' => 'sales_return:' . $returnId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec('UPDATE sales_returns SET journal_entry_id = ?, total = ?, return_no = ? WHERE id = ?', [$journalId, $total, $entryNo, $returnId]);
            audit('post', 'sales_returns', $returnId, 'Return posted; journal #' . $journalId);
        });
    }

    public static function reverseSalesReturn(int $returnId, string $reason): void {
        $ret = db_one('SELECT * FROM sales_returns WHERE id = ?', [$returnId]);
        if (!$ret) throw new RuntimeException('Sales return not found');
        if ($ret['status'] === 'reversed') return;
        $inv = db_one('SELECT * FROM sales_invoices WHERE id = ?', [(int)$ret['sales_invoice_id']]);
        Database::transaction(function () use ($ret, $inv, $returnId, $reason) {
            $items = db_all('SELECT * FROM sales_return_items WHERE return_id = ?', [$returnId]);
            foreach ($items as $it) {
                Inventory::issue((int)$it['product_id'], (int)$inv['warehouse_id'], (float)$it['qty'],
                    'sale_return_reversal', 'sales_return', $returnId, null, null, 'Reversal of return ' . $ret['return_no']);
            }
            if ($ret['journal_entry_id']) Accounting::reverseEntry((int)$ret['journal_entry_id'], $reason ?: 'Sales return reversal');
            db_exec("UPDATE sales_returns SET status = 'reversed' WHERE id = ?", [$returnId]);
            audit('reverse', 'sales_returns', $returnId, $reason ?? '');
        });
    }

    /* ============ RECEIPTS & PAYMENTS ============ */

    public static function postReceipt(int $receiptId): void {
        $r = db_one('SELECT * FROM receipts WHERE id = ?', [$receiptId]);
        if (!$r) throw new RuntimeException('Receipt not found');
        if ($r['journal_entry_id']) return; // already posted
        $gl = self::accountGl((int)$r['account_id']);
        Database::transaction(function () use ($r, $receiptId, $gl) {
            $journalId = Accounting::postEntry([
                ['account' => $gl, 'debit' => (float)$r['amount'], 'memo' => 'Receipt'],
                ['account' => 'AR', 'credit' => (float)$r['amount'], 'customer_id' => $r['customer_id'] ? (int)$r['customer_id'] : null, 'memo' => 'Against receivable'],
            ], [
                'date' => $r['date'], 'voucher_type' => 'RC', 'reference_type' => 'receipt',
                'reference_id' => $receiptId, 'memo' => 'Receipt',
                'idem_key' => 'receipt:' . $receiptId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec('UPDATE receipts SET journal_entry_id = ?, receipt_no = ? WHERE id = ?', [$journalId, $entryNo, $receiptId]);
            audit('post', 'receipts', $receiptId, 'Receipt posted; journal #' . $journalId);
        });
        try { Notifications::notifyReceipt($receiptId); } catch (Throwable $e) { error_log('notify receipt: ' . $e->getMessage()); }
    }

    /** FIFO-allocate a supplier payment across open credit purchase invoices (oldest first); drives Paid/Unpaid/Partial status. All-or-nothing within the caller's transaction; legacy DBs without the columns skip cleanly. */
    public static function allocateSupplierPayment(int $supplierId, float $amount): void {
        if ($supplierId <= 0 || $amount <= 0) return;
        // Probe for the payment-status columns first — legacy installs may not have them yet
        try { db_val('SELECT payment_status FROM purchase_invoices LIMIT 0'); }
        catch (Throwable $e) { error_log('allocateSupplierPayment skipped (legacy schema): ' . $e->getMessage()); return; }
        $remaining = round($amount, 2);
        $open = db_all("SELECT id, total, COALESCE(amount_paid, 0) AS amount_paid FROM purchase_invoices WHERE supplier_id = ? AND payment_type = 'credit' AND status IN ('posted','pending_approval') AND COALESCE(payment_status, 'unpaid') != 'paid' ORDER BY date ASC, id ASC", [$supplierId]);
        foreach ($open as $inv) {
            if ($remaining <= 0) break;
            $due = round((float)$inv['total'] - (float)$inv['amount_paid'], 2);
            if ($due <= 0) continue;
            $apply = min($due, $remaining);
            $newPaid = round((float)$inv['amount_paid'] + $apply, 2);
            $status = $newPaid + 0.005 >= (float)$inv['total'] ? 'paid' : 'partial';
            db_exec("UPDATE purchase_invoices SET amount_paid = ?, payment_status = ? WHERE id = ?", [$newPaid, $status, (int)$inv['id']]);
            $remaining = round($remaining - $apply, 2);
        }
    }

    public static function postPayment(int $paymentId): void {
        $p = db_one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
        if (!$p) throw new RuntimeException('Payment not found');
        if (!empty($p['journal_entry_id']) || $p['status'] === 'posted') { db_exec("UPDATE payments SET status = 'posted' WHERE id = ? AND status = 'pending'", [$paymentId]); return; }
        $gl = self::accountGl((int)$p['account_id']);
        Database::transaction(function () use ($p, $paymentId, $gl) {
            if ($p['party_type'] === 'supplier') {
                $dr = ['account' => 'AP', 'debit' => (float)$p['amount'], 'supplier_id' => $p['party_id'] ? (int)$p['party_id'] : null, 'memo' => 'Supplier payment ' . $p['payment_no']];
            } elseif ($p['party_type'] === 'owner') {
                $dr = ['account' => 'DRAWINGS', 'debit' => (float)$p['amount'], 'memo' => 'Owner personal drawing'];
            } else {
                throw new InvalidArgumentException('Unsupported payment party_type: ' . $p['party_type']);
            }
            $journalId = Accounting::postEntry([
                $dr,
                ['account' => $gl, 'credit' => (float)$p['amount'], 'memo' => 'Payment'],
            ], [
                'date' => $p['date'], 'voucher_type' => 'PY', 'reference_type' => 'payment',
                'reference_id' => $paymentId, 'memo' => 'Payment',
                'idem_key' => 'payment:' . $paymentId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec("UPDATE payments SET journal_entry_id = ?, payment_no = ?, status = 'posted' WHERE id = ?", [$journalId, $entryNo, $paymentId]);
            if ($p['party_type'] === 'supplier') self::allocateSupplierPayment((int)($p['party_id'] ?? 0), (float)$p['amount']);
            audit('post', 'payments', $paymentId, 'Payment posted; journal #' . $journalId);
        });
    }

    public static function postExpense(int $expenseId): void {
        $x = db_one('SELECT * FROM expenses WHERE id = ?', [$expenseId]);
        if (!$x) throw new RuntimeException('Expense not found');
        if ($x['journal_entry_id'] || $x['status'] === 'reversed') return;
        $cat = db_one('SELECT * FROM expense_categories WHERE id = ?', [(int)$x['category_id']]);
        if (!$cat) throw new RuntimeException('Expense category not found');

        Database::transaction(function () use ($x, $cat, $expenseId) {
            if ($x['payment_type'] === 'payable') {
                $cr = ['account' => 'AP', 'credit' => (float)$x['amount'], 'memo' => 'Expense on credit — ' . $x['payee']];
            } else {
                $gl = self::accountGl((int)$x['account_id']);
                $cr = ['account' => $gl, 'credit' => (float)$x['amount'], 'memo' => 'Expense paid — ' . $x['expense_no']];
            }
            $journalId = Accounting::postEntry([
                ['account' => (int)$cat['gl_account_id'], 'debit' => (float)$x['amount'], 'memo' => $x['description'] ?: $cat['name']],
                $cr,
            ], [
                'date' => $x['date'], 'voucher_type' => 'EXP', 'reference_type' => 'expense',
                'reference_id' => $expenseId, 'memo' => 'Expense — ' . ($x['description'] ?? $cat['name']),
                'idem_key' => 'expense:' . $expenseId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec("UPDATE expenses SET journal_entry_id = ?, status = 'posted', expense_no = ? WHERE id = ?", [$journalId, $entryNo, $expenseId]);
            audit('post', 'expenses', $expenseId, 'Expense posted; journal #' . $journalId);
        });
    }

    public static function reverseExpense(int $expenseId, string $reason): void {
        $x = db_one('SELECT * FROM expenses WHERE id = ?', [$expenseId]);
        if (!$x) throw new RuntimeException('Expense not found');
        if ($x['status'] === 'reversed') return;
        if ($x['journal_entry_id']) Accounting::reverseEntry((int)$x['journal_entry_id'], $reason ?: 'Expense reversal');
        db_exec("UPDATE expenses SET status = 'reversed' WHERE id = ?", [$expenseId]);
        audit('reverse', 'expenses', $expenseId, $reason ?? '');
    }

    /* ============ FACTORY MAINTENANCE & REPAIRS ============ */

    /** On Owner/Admin approval: create the expense row (Factory Maintenance & Repairs category) and post it exactly once. */
    public static function postMaintenance(int $recordId): void {
        $m = db_one('SELECT * FROM maintenance_records WHERE id = ?', [$recordId]);
        if (!$m) throw new RuntimeException('Maintenance record not found');
        if ($m['status'] === 'reversed' || $m['status'] === 'rejected') return;
        if (!empty($m['expense_id']) || $m['status'] === 'posted') return; // exactly once
        Database::transaction(function () use ($m, $recordId) {
            $cat = db_one("SELECT id FROM expense_categories WHERE name = 'Factory Maintenance & Repairs' LIMIT 1");
            if (!$cat) throw new RuntimeException('Expense category "Factory Maintenance & Repairs" is not configured.');
            $paymentType = $m['payment_type'] === 'credit' ? 'payable' : ($m['payment_type'] === 'bank' ? 'bank' : 'cash');
            $expenseId = db_insert('expenses', [
                'expense_no' => 'TMP-' . bin2hex(random_bytes(6)),
                'category_id' => (int)$cat['id'],
                'date' => $m['work_date'] ?: $m['problem_date'],
                'amount' => (float)$m['total_cost'],
                'payee' => $m['vendor'] ?: null,
                'description' => mb_substr('Maintenance ' . $m['record_no'] . ' — ' . $m['machine_name'] . ' — ' . preg_replace('/\s+/', ' ', (string)$m['problem']), 0, 250),
                'payment_type' => $paymentType,
                'account_id' => $m['account_id'] ? (int)$m['account_id'] : null,
                'status' => 'pending',
                'created_by' => $_SESSION['user_id'] ?? null,
                'created_at' => now_local(),
            ]);
            self::postExpense((int)$expenseId); // posts the journal exactly once (idem key expense:<id>)
            db_exec("UPDATE maintenance_records SET expense_id = ?, status = 'posted', approved_by = ?, approved_at = ? WHERE id = ?",
                [(int)$expenseId, (int)($_SESSION['user_id'] ?? 0), now_local(), $recordId]);
            audit('post', 'maintenance_records', $recordId, 'Maintenance posted; expense #' . $expenseId);
        });
    }

    public static function reverseMaintenance(int $recordId, string $reason): void {
        $m = db_one('SELECT * FROM maintenance_records WHERE id = ?', [$recordId]);
        if (!$m) throw new RuntimeException('Maintenance record not found');
        if ($m['status'] === 'reversed') return;
        if (!empty($m['expense_id'])) self::reverseExpense((int)$m['expense_id'], $reason ?: 'Maintenance reversal');
        db_exec("UPDATE maintenance_records SET status = 'reversed' WHERE id = ?", [$recordId]);
        audit('reverse', 'maintenance_records', $recordId, $reason ?? '');
    }

    /* ============ BANK TRANSFERS ============ */

    public static function postBankTransfer(int $transferId): void {
        $t = db_one('SELECT * FROM bank_transfers WHERE id = ?', [$transferId]);
        if (!$t) throw new RuntimeException('Transfer not found');
        if (!empty($t['journal_entry_id']) || $t['status'] === 'posted') { db_exec("UPDATE bank_transfers SET status = 'posted' WHERE id = ? AND status = 'pending'", [$transferId]); return; }
        $fromGl = self::accountGl((int)$t['from_account_id']);
        $toGl = self::accountGl((int)$t['to_account_id']);
        Database::transaction(function () use ($t, $transferId, $fromGl, $toGl) {
            $journalId = Accounting::postEntry([
                ['account' => $toGl, 'debit' => (float)$t['amount'], 'memo' => 'Transfer in'],
                ['account' => $fromGl, 'credit' => (float)$t['amount'], 'memo' => 'Transfer out'],
            ], [
                'date' => $t['date'], 'voucher_type' => 'TRF', 'reference_type' => 'bank_transfer',
                'reference_id' => $transferId, 'memo' => 'Bank/cash transfer',
                'idem_key' => 'bank_transfer:' . $transferId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec("UPDATE bank_transfers SET journal_entry_id = ?, transfer_no = ?, status = 'posted' WHERE id = ?", [$journalId, $entryNo, $transferId]);
            audit('post', 'bank_transfers', $transferId, 'Transfer posted; journal #' . $journalId);
        });
    }

    /* ============ PAYROLL ============ */

    public static function postPayroll(int $payrollId, int $accountId): void {
        $p = db_one('SELECT * FROM payroll WHERE id = ?', [$payrollId]);
        if (!$p) throw new RuntimeException('Payroll record not found');
        if ($p['status'] === 'paid' && !empty($p['journal_entry_id'])) return; // exactly once
        $accountToUse = $accountId > 0 ? $accountId : (int)($p['account_id'] ?? 0);
        if (!$accountToUse) throw new RuntimeException('Payout account missing on payroll record.');
        $emp = db_one('SELECT name FROM employees WHERE id = ?', [(int)$p['employee_id']]);
        $cat = db_one("SELECT gl_account_id FROM expense_categories WHERE name = 'Salaries & Wages' LIMIT 1");
        $salGl = $cat ? (int)$cat['gl_account_id'] : 0;
        if (!$salGl) throw new RuntimeException('Salaries & Wages expense account not configured.');
        $net = (float)$p['net_pay'];
        $gl = self::accountGl($accountToUse);
        Database::transaction(function () use ($p, $payrollId, $salGl, $gl, $net, $emp) {
            $journalId = Accounting::postEntry([
                ['account' => $salGl, 'debit' => $net, 'memo' => 'Salary — ' . ($emp['name'] ?? '')],
                ['account' => $gl, 'credit' => $net, 'memo' => 'Salary payment'],
            ], [
                'date' => today(), 'voucher_type' => 'PAY', 'reference_type' => 'payroll',
                'reference_id' => $payrollId, 'memo' => 'Payroll payment — ' . ($emp['name'] ?? ''),
                'idem_key' => 'payroll:' . $payrollId,
            ]);
            db_exec("UPDATE payroll SET status = 'paid', amount_paid = net_pay, journal_entry_id = ? WHERE id = ?", [$journalId, $payrollId]);
            audit('post', 'payroll', $payrollId, 'Salary paid; journal #' . $journalId);
        });
    }

    /* ============ PARTIAL / FULL SALARY PAYMENTS (approval-gated) ============ */

    /**
     * Post one approved salary payment (full or partial). Dr Salaries & Wages, Cr the chosen cash/bank.
     * payroll.amount_paid accumulates; status flips to 'partial' until the payslip is fully settled.
     * Exactly-once: payment row flips pending→paid inside the same transaction; journal carries an idem key.
     */
    public static function postPayrollPayment(int $paymentId): void {
        $pp = db_one('SELECT * FROM payroll_payments WHERE id = ?', [$paymentId]);
        if (!$pp) throw new RuntimeException('Salary payment not found.');
        if ($pp['status'] === 'paid' && !empty($pp['journal_entry_id'])) return; // exactly once
        if ($pp['status'] !== 'pending') throw new RuntimeException('Salary payment cannot be posted from status ' . $pp['status'] . '.');
        $p = db_one('SELECT * FROM payroll WHERE id = ?', [(int)$pp['payroll_id']]);
        if (!$p) throw new RuntimeException('Payroll record not found.');
        if (!in_array($p['status'], ['draft', 'partial'], true)) {
            throw new RuntimeException('Payroll is ' . $p['status'] . ' — nothing to pay. Reverse it first if needed.');
        }
        $net = (float)$p['net_pay'];
        $paidBefore = (float)$p['amount_paid'];
        $remaining = round(max(0, $net - $paidBefore), 2);
        $amount = round((float)$pp['amount'], 2);
        if ($amount <= 0) throw new RuntimeException('Payment amount must be positive.');
        if ($amount > $remaining + 0.005) {
            throw new RuntimeException('Payment (Rs ' . number_format($amount, 2) . ') exceeds remaining payable (Rs ' . number_format($remaining, 2) . '). Reject and re-request with the correct amount.');
        }
        $accountId = (int)$pp['account_id'];
        $accountToUse = $accountId > 0 ? $accountId : (int)($p['account_id'] ?? 0);
        if (!$accountToUse) throw new RuntimeException('Payout account missing on this payment.');
        $emp = db_one('SELECT name FROM employees WHERE id = ?', [(int)$p['employee_id']]);
        $cat = db_one("SELECT gl_account_id FROM expense_categories WHERE name = 'Salaries & Wages' LIMIT 1");
        $salGl = $cat ? (int)$cat['gl_account_id'] : 0;
        if (!$salGl) throw new RuntimeException('Salaries & Wages expense account not configured.');
        $gl = self::accountGl($accountToUse);
        $newPaid = round($paidBefore + $amount, 2);
        $fullyPaid = $newPaid >= $net - 0.005;
        Database::transaction(function () use ($pp, $p, $paymentId, $salGl, $gl, $amount, $emp, $newPaid, $fullyPaid, $net) {
            $journalId = Accounting::postEntry([
                ['account' => $salGl, 'debit' => $amount, 'memo' => 'Salary — ' . ($emp['name'] ?? '') . ' (' . $p['period_start'] . ' → ' . $p['period_end'] . ')'],
                ['account' => $gl, 'credit' => $amount, 'memo' => 'Salary payment'],
            ], [
                'date' => today(), 'voucher_type' => 'PAY', 'reference_type' => 'payroll_payment',
                'reference_id' => $paymentId, 'memo' => 'Salary payment — ' . ($emp['name'] ?? ''),
                'idem_key' => 'payroll_payment:' . $paymentId,
            ]);
            db_exec("UPDATE payroll_payments SET status = 'paid', journal_entry_id = ? WHERE id = ?", [$journalId, $paymentId]);
            db_exec("UPDATE payroll SET amount_paid = ?, status = ?, account_id = ? WHERE id = ?", [$newPaid, $fullyPaid ? 'paid' : 'partial', (int)($pp['account_id']), (int)$p['id']]);
            audit('post', 'payroll_payments', $paymentId, 'Salary payment posted; journal #' . $journalId . ' · ' . ($fullyPaid ? 'fully paid' : 'partial — Rs ' . number_format($net - $newPaid, 2) . ' remaining'));
        });
    }

    /* ============ SALARY ADVANCES (approval-gated) ============ */

    /** Disburse an approved advance: Dr Employee Advances (asset), Cr chosen cash/bank account. */
    public static function postSalaryAdvance(int $advanceId): void {
        $a = db_one('SELECT * FROM employee_advances WHERE id = ?', [$advanceId]);
        if (!$a) throw new RuntimeException('Advance not found');
        if ($a['status'] === 'disbursed' || !empty($a['journal_entry_id'])) return; // exactly once
        if (!in_array($a['status'], ['pending', 'approved'], true)) throw new RuntimeException('Advance cannot be disbursed from status ' . $a['status'] . '.');
        $accountId = (int)($a['account_id'] ?: 0);
        if (!$accountId) throw new RuntimeException('No payout account was captured with this advance — reject it and re-request with an account selected.');
        $gl = self::accountGl($accountId);
        Database::transaction(function () use ($a, $advanceId, $gl) {
            $emp = db_one('SELECT name FROM employees WHERE id = ?', [(int)$a['employee_id']]);
            $journalId = Accounting::postEntry([
                ['account' => 'EMP_ADV', 'debit' => (float)$a['amount'], 'memo' => 'Salary advance — ' . ($emp['name'] ?? '')],
                ['account' => $gl, 'credit' => (float)$a['amount'], 'memo' => 'Advance disbursed'],
            ], [
                'date' => today(), 'voucher_type' => 'ADV', 'reference_type' => 'salary_advance',
                'reference_id' => $advanceId, 'memo' => 'Salary advance — ' . ($emp['name'] ?? ''),
                'idem_key' => 'salary_advance:' . $advanceId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec("UPDATE employee_advances SET status = 'disbursed', journal_entry_id = ?, approved_at = COALESCE(approved_at, ?) WHERE id = ?", [$journalId, now_local(), $advanceId]);
            audit('post', 'employee_advances', $advanceId, 'Advance disbursed; journal ' . $entryNo);
        });
    }

    /* ============ SALESMAN SALARY ADVANCE ============ */

    /** Disburse an approved salesman salary: Dr Salesman Salary (expense), Cr cash/bank. */
    public static function postSalesmanSalary(int $paymentId): void {
        $p = db_one('SELECT sp.*, s.name AS sm_name FROM salesman_salary_payments sp JOIN salesmen s ON s.id = sp.salesman_id WHERE sp.id = ?', [$paymentId]);
        if (!$p) throw new RuntimeException('Salary payment not found.');
        if ($p['status'] === 'paid' && !empty($p['journal_entry_id'])) return; // exactly once
        if (!in_array($p['status'], ['pending', 'approved'], true)) throw new RuntimeException('Salary can be paid from status ' . $p['status'] . ' only.');
        $accountId = (int)$p['account_id'];
        if (!$accountId) throw new RuntimeException('No payout account captured for this payment.');
        $gl = self::accountGl($accountId);
        $cat = db_one("SELECT gl_account_id FROM expense_categories WHERE name = 'Salesman Salary' LIMIT 1");
        $salGl = $cat ? (int)$cat['gl_account_id'] : 0;
        if (!$salGl) throw new RuntimeException('Salesman Salary expense category not configured.');
        Database::transaction(function () use ($p, $paymentId, $salGl, $gl) {
            $journalId = Accounting::postEntry([
                ['account' => $salGl, 'debit' => (float)$p['amount'], 'memo' => 'Salesman salary — ' . $p['sm_name'] . ($p['period'] ? ' (' . $p['period'] . ')' : '')],
                ['account' => $gl, 'credit' => (float)$p['amount'], 'memo' => 'Salary paid — ' . $p['sm_name']],
            ], [
                'date' => $p['date'], 'voucher_type' => 'SMS', 'reference_type' => 'salesman_salary',
                'reference_id' => $paymentId, 'memo' => 'Salesman salary — ' . $p['sm_name'],
                'idem_key' => 'salesman_salary:' . $paymentId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec("UPDATE salesman_salary_payments SET status = 'paid', journal_entry_id = ? WHERE id = ?", [$journalId, $paymentId]);
            audit('post', 'salesman_salary_payments', $paymentId, 'Salary paid; journal ' . $entryNo);
        });
    }

    /** Disburse an approved salesman advance: Dr Employee Advances (asset), Cr cash/bank. */
    public static function postSalesmanAdvance(int $advanceId): void {
        $a = db_one('SELECT a.*, s.name AS sm_name FROM salesman_salary_advances a JOIN salesmen s ON s.id = a.salesman_id WHERE a.id = ?', [$advanceId]);
        if (!$a) throw new RuntimeException('Advance not found.');
        if ($a['status'] === 'paid' && !empty($a['journal_entry_id'])) return; // exactly once
        if (!in_array($a['status'], ['pending', 'approved'], true)) throw new RuntimeException('Advance can be paid from status ' . $a['status'] . ' only.');
        $accountId = (int)$a['account_id'];
        if (!$accountId) throw new RuntimeException('No payout account captured for this advance.');
        $gl = self::accountGl($accountId);
        Database::transaction(function () use ($a, $advanceId, $gl) {
            $journalId = Accounting::postEntry([
                ['account' => 'EMP_ADV', 'debit' => (float)$a['amount'], 'memo' => 'Salesman salary advance — ' . $a['sm_name']],
                ['account' => $gl, 'credit' => (float)$a['amount'], 'memo' => 'Advance disbursed — ' . $a['sm_name']],
            ], [
                'date' => $a['date'], 'voucher_type' => 'ADV', 'reference_type' => 'salesman_advance',
                'reference_id' => $advanceId, 'memo' => 'Salesman salary advance — ' . $a['sm_name'],
                'idem_key' => 'salesman_advance:' . $advanceId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec("UPDATE salesman_salary_advances SET status = 'paid', journal_entry_id = ?, approved_at = COALESCE(approved_at, ?) WHERE id = ?", [$journalId, now_local(), $advanceId]);
            audit('post', 'salesman_salary_advances', $advanceId, 'Advance disbursed; journal ' . $entryNo);
        });
    }

    /** Post a salesman business expense: Dr Salesman Expenses, Cr cash/bank. */
    public static function postSalesmanExpense(int $expenseId): void {
        $e = db_one('SELECT e.*, s.name AS sm_name FROM salesman_expenses e JOIN salesmen s ON s.id = e.salesman_id WHERE e.id = ?', [$expenseId]);
        if (!$e) throw new RuntimeException('Expense not found.');
        if ($e['status'] === 'posted' && !empty($e['journal_entry_id'])) return; // exactly once
        if (!in_array($e['status'], ['pending', 'approved'], true)) throw new RuntimeException('Expense can be posted from status ' . $e['status'] . ' only.');
        $accountId = (int)$e['account_id'];
        if (!$accountId) throw new RuntimeException('No payment account captured for this expense.');
        $gl = self::accountGl($accountId);
        $cat = db_one("SELECT gl_account_id FROM expense_categories WHERE name = 'Salesman Expenses' LIMIT 1");
        $expGl = $cat ? (int)$cat['gl_account_id'] : 0;
        if (!$expGl) throw new RuntimeException('Salesman Expenses category not configured.');
        Database::transaction(function () use ($e, $expenseId, $expGl, $gl) {
            $journalId = Accounting::postEntry([
                ['account' => $expGl, 'debit' => (float)$e['amount'], 'memo' => strtoupper($e['expense_type']) . ' expense — ' . $e['sm_name'] . ' — ' . $e['description']],
                ['account' => $gl, 'credit' => (float)$e['amount'], 'memo' => 'Expense paid — ' . $e['sm_name']],
            ], [
                'date' => $e['date'], 'voucher_type' => 'SMX', 'reference_type' => 'salesman_expense',
                'reference_id' => $expenseId, 'memo' => 'Salesman expense — ' . $e['description'],
                'idem_key' => 'salesman_expense:' . $expenseId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec("UPDATE salesman_expenses SET status = 'posted', journal_entry_id = ?, approved_at = COALESCE(approved_at, ?) WHERE id = ?", [$journalId, now_local(), $expenseId]);
            audit('post', 'salesman_expenses', $expenseId, 'Expense posted; journal ' . $entryNo);
        });
    }

    /* ============ EMPLOYEE LOANS (approval-gated) ============ */

    /** Disburse an approved loan: Dr Employee Loans (asset), Cr chosen cash/bank account; create installment schedule. */
    public static function postEmployeeLoan(int $loanId): void {
        $loan = db_one('SELECT * FROM employee_loans WHERE id = ?', [$loanId]);
        if (!$loan) throw new RuntimeException('Loan not found');
        if ($loan['status'] === 'active' && !empty($loan['journal_entry_id'])) return; // exactly once
        if ($loan['status'] !== 'active' && $loan['status'] !== 'pending') throw new RuntimeException('Loan cannot be disbursed from status ' . $loan['status'] . '.');
        $accountId = (int)($loan['account_id'] ?: 0);
        if (!$accountId) throw new RuntimeException('No payout account captured for this loan.');
        $gl = self::accountGl($accountId);
        Database::transaction(function () use ($loan, $loanId, $gl) {
            $emp = db_one('SELECT name FROM employees WHERE id = ?', [(int)$loan['employee_id']]);
            $journalId = Accounting::postEntry([
                ['account' => 'EMP_LOAN', 'debit' => (float)$loan['amount'], 'memo' => 'Employee loan — ' . ($emp['name'] ?? '')],
                ['account' => $gl, 'credit' => (float)$loan['amount'], 'memo' => 'Loan disbursed'],
            ], [
                'date' => today(), 'voucher_type' => 'LN', 'reference_type' => 'employee_loan',
                'reference_id' => $loanId, 'memo' => 'Employee loan — ' . ($loan['loan_no'] ?? ''),
                'idem_key' => 'employee_loan:' . $loanId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec("UPDATE employee_loans SET status = 'active', journal_entry_id = ?, outstanding = amount, approved_at = COALESCE(approved_at, ?) WHERE id = ?", [$journalId, now_local(), $loanId]);
            // Build installment schedule
            $n = max(1, (int)$loan['total_installments']);
            $amt = round((float)$loan['installment_amount'], 2);
            $weekly = $loan['installment_type'] === 'weekly';
            for ($i = 1; $i <= $n; $i++) {
                $due = $weekly ? date('Y-m-d', strtotime($loan['date'] . " + $i week")) : date('Y-m-d', strtotime($loan['date'] . " + $i month"));
                // Final installment takes the remainder so installments never over-recover
                $thisAmt = ($i === $n) ? round((float)$loan['amount'] - $amt * ($n - 1), 2) : $amt;
                if ($thisAmt <= 0) $thisAmt = $amt;
                db_insert('loan_installments', ['loan_id' => $loanId, 'installment_no' => $i, 'due_date' => $due, 'amount' => round($thisAmt, 2), 'status' => 'due']);
            }
            audit('post', 'employee_loans', $loanId, 'Loan disbursed; journal ' . $entryNo);
        });
    }

    /* ============ SALESMAN ISSUE / CREDIT ISSUE ============ */

    /**
     * Post a salesman issue of ANY type: moves stock (paid + bonus) to the van,
     * for credit_issue also posts the journal + salesman ledger (payable at issue rate, bonus at zero charge).
     * Exactly-once via: status flip + stock-movement marker + journal guard.
     */
    public static function postSalesmanIssue(int $issueId): void {
        $issue = db_one('SELECT * FROM salesman_issues WHERE id = ?', [$issueId]);
        if (!$issue) throw new RuntimeException('Salesman issue not found');
        if ($issue['status'] === 'reversed') throw new RuntimeException('Issue was rejected/reversed.');
        if ($issue['status'] === 'pending_approval') throw new RuntimeException('Issue is awaiting Owner/Admin approval — approve it in the Admin Console first.');
        if ((int)db_val("SELECT COUNT(*) FROM stock_movements WHERE ref_type = 'salesman_issue' AND ref_id = ?", [$issueId]) > 0) return; // exactly once
        $type = $issue['issue_type'];

        $items = db_all('SELECT * FROM salesman_issue_items WHERE issue_id = ?', [$issueId]);
        if (!$items) throw new RuntimeException('Issue has no items.');
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [(int)$issue['salesman_id']]);
        $destWh = (int)($sm['warehouse_id'] ?: 0);
        if (!$destWh) throw new RuntimeException('Salesman has no van warehouse.');

        Database::transaction(function () use ($issue, $items, $issueId, $destWh, $sm, $type) {
            // Flip to posted inside the same transaction so a failed post can never leave a half-posted record
            db_exec("UPDATE salesman_issues SET status = 'posted' WHERE id = ? AND status != 'posted'", [$issueId]);
            $payable = 0.0; $bonusCost = 0.0; $paidCost = 0.0;
            $q = db()->prepare('UPDATE salesman_issue_items SET bonus_cost = ? WHERE id = ?');
            foreach ($items as $it) {
                $paid = (float)$it['paid_qty']; $bonus = (float)$it['bonus_qty'];
                $totalQty = $paid + $bonus;
                if ($totalQty <= 0) throw new RuntimeException('Issue lines must have paid or bonus quantity.');
                $issueRate = (float)$it['issue_rate'];
                $linePayable = round($paid * $issueRate, 2);
                $payable += $linePayable;
                // Stock out of source warehouse — bonus carried at zero issue value but full cost
                $avgCost = Inventory::avgCost((int)$it['product_id'], (int)$issue['from_warehouse_id']);
                // Move paid portion
                if ($paid > 0) {
                    Inventory::transfer((int)$it['product_id'], (int)$issue['from_warehouse_id'], $destWh, $paid, 'salesman_issue_out', 'salesman_issue_in', 'salesman_issue', $issueId);
                    $paidCost += round($avgCost * $paid, 2);
                }
                if ($bonus > 0) {
                    Inventory::transfer((int)$it['product_id'], (int)$issue['from_warehouse_id'], $destWh, $bonus, 'salesman_issue_out', 'salesman_issue_in', 'salesman_issue', $issueId);
                    $bonusCost += round($avgCost * $bonus, 2);
                }
                $q->execute([round($avgCost * $bonus, 2), $it['id']]);
                db_exec('UPDATE salesman_issue_items SET line_payable = ? WHERE id = ?', [$linePayable, $it['id']]);
            }
            db_exec('UPDATE salesman_issues SET total_payable = ?, total_bonus_cost = ? WHERE id = ?', [$type === 'credit_issue' ? $payable : 0.0, $bonusCost, $issueId]);

            if ($type === 'credit_issue' && $payable > 0) {
                $lines = [
                    ['account' => 'SM_RECEIVABLE', 'debit' => $payable, 'memo' => 'Credit issue to salesman — ' . $sm['name']],
                    ['account' => 'SALES', 'credit' => $payable, 'memo' => 'Issue at issue-rate — ' . $sm['name']],
                ];
                if ($paidCost > 0) { $lines[] = ['account' => 'COGS', 'debit' => $paidCost]; $lines[] = ['account' => 'INVENTORY', 'credit' => $paidCost]; }
                if ($bonusCost > 0) { $lines[] = ['account' => 'PROMO', 'debit' => $bonusCost, 'memo' => 'Bonus goods at cost — ' . $sm['name']]; $lines[] = ['account' => 'INVENTORY', 'credit' => $bonusCost, 'memo' => 'Bonus goods issued']; }
                $journalId = Accounting::postEntry($lines, [
                    'date' => $issue['date'], 'voucher_type' => 'CI', 'reference_type' => 'salesman_issue',
                    'reference_id' => $issueId, 'memo' => 'Credit issue — ' . $sm['name'],
                    'idem_key' => 'salesman_issue:' . $issueId,
                ]);
                db_exec('UPDATE salesman_issues SET journal_entry_id = ? WHERE id = ?', [$journalId, $issueId]);
                // Salesman ledger (running balance)
                $prev = (float)db_val('SELECT balance FROM salesman_ledger WHERE salesman_id = ? ORDER BY id DESC LIMIT 1', [(int)$sm['id']]);
                $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
                db_insert('salesman_ledger', ['salesman_id' => (int)$sm['id'], 'date' => $issue['date'], 'doc_type' => 'issue', 'doc_id' => $issueId, 'doc_no' => $entryNo, 'debit' => $payable, 'credit' => 0, 'balance' => round($prev + $payable, 2), 'notes' => 'Credit issue', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
            } elseif ($type === 'transfer') {
                // Transfer: stock only, no journal, no payable
                db_exec("UPDATE salesman_issues SET issue_type = 'transfer' WHERE id = ?", [$issueId]);
            }
            audit('post', 'salesman_issues', $issueId, "Issue posted — payable " . number_format($payable, 2) . ", bonus cost " . number_format($bonusCost, 2));
        });
    }

    /* ============ Salesman returns to warehouse ============ */

    /**
     * Salesman returns unsold stock to the warehouse.
     * Stock received at current avg cost; ledger credited at the recorded ISSUE RATE for paid units returned
     * (bonus units returned free of charge); journal mirrors customer returns:
     *   Dr Sales Returns / Cr Salesman Receivable (paid portion)
     *   Dr Inventory / Cr COGS (paid cost);  Dr Inventory / Cr PROMO (bonus cost)
     */
    public static function postSalesmanReturn(int $returnId): void {
        $ret = db_one('SELECT * FROM salesman_issues WHERE id = ?', [$returnId]);
        if (!$ret || $ret['issue_type'] !== 'salesman_return') throw new RuntimeException('Return not found');
        if (!empty($ret['journal_entry_id'])) return; // exactly once
        if ((int)db_val("SELECT COUNT(*) FROM stock_movements WHERE ref_type = 'salesman_return' AND ref_id = ?", [$returnId]) > 0) return;
        $items = db_all('SELECT * FROM salesman_issue_items WHERE issue_id = ?', [$returnId]);
        if (!$items) throw new RuntimeException('Return has no items.');
        $sm = db_one('SELECT id, name, warehouse_id FROM salesmen WHERE id = ?', [(int)$ret['salesman_id']]);
        $whId = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1");
        if (!$whId) { $whId = (int)db_val('SELECT id FROM warehouses WHERE is_default = 1 LIMIT 1'); }

        Database::transaction(function () use ($ret, $items, $returnId, $sm, $whId) {
            $payableReturn = 0.0; $paidCost = 0.0; $bonusCost = 0.0;
            $srcWh = (int)$sm['warehouse_id'];
            foreach ($items as $it) {
                $paid = (float)$it['paid_qty']; $bonus = (float)$it['bonus_qty'];
                if ($paid + $bonus <= 0) continue;
                $cost = Inventory::avgCost((int)$it['product_id'], $srcWh);
                $unitRate = (float)$it['issue_rate'];
                Inventory::receive((int)$it['product_id'], $whId, $paid + $bonus, $cost, 'salesman_return', 'salesman_return', $returnId, null, null, 'Returned by salesman');
                // Deduct from van warehouse with the correct movement types
                if ($paid > 0 || $bonus > 0) {
                    Inventory::issue((int)$it['product_id'], $srcWh, $paid + $bonus, 'salesman_issue_out', 'salesman_return', $returnId, null, null, 'Return to main warehouse');
                }
                $payableReturn += round($paid * $unitRate, 2);
                $paidCost += round($paid * $cost, 2);
                $bonusCost += round($bonus * $cost, 2);
            }
            $lines = [];
            if ($payableReturn > 0) {
                $lines[] = ['account' => 'SALES_RETURNS', 'debit' => $payableReturn, 'memo' => 'Salesman return'];
                $lines[] = ['account' => 'SM_RECEIVABLE', 'credit' => $payableReturn, 'memo' => 'Return — reduce salesman balance'];
            }
            if ($paidCost > 0) { $lines[] = ['account' => 'INVENTORY', 'debit' => $paidCost, 'memo' => 'Return restocked']; $lines[] = ['account' => 'COGS', 'credit' => $paidCost, 'memo' => 'COGS reversal']; }
            if ($bonusCost > 0) { $lines[] = ['account' => 'INVENTORY', 'debit' => $bonusCost, 'memo' => 'Bonus restocked']; $lines[] = ['account' => 'PROMO', 'credit' => $bonusCost, 'memo' => 'Bonus promo reversed']; }
            $journalId = null;
            if ($lines) {
                $journalId = Accounting::postEntry($lines, [
                    'date' => $ret['date'], 'voucher_type' => 'SR', 'reference_type' => 'salesman_return',
                    'reference_id' => $returnId, 'memo' => 'Salesman return — ' . ($sm['name'] ?? ''),
                    'idem_key' => 'salesman_return:' . $returnId,
                ]);
                db_exec('UPDATE salesman_issues SET journal_entry_id = ? WHERE id = ?', [$journalId, $returnId]);
            }
            if ($payableReturn > 0) {
                $prev = (float)db_val('SELECT balance FROM salesman_ledger WHERE salesman_id = ? ORDER BY id DESC LIMIT 1', [(int)$ret['salesman_id']]);
                db_insert('salesman_ledger', ['salesman_id' => (int)$ret['salesman_id'], 'date' => $ret['date'], 'doc_type' => 'return', 'doc_id' => $returnId, 'doc_no' => $journalId ? db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]) : null, 'debit' => 0, 'credit' => $payableReturn, 'balance' => round($prev - $payableReturn, 2), 'notes' => 'Salesman stock return', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
            }
            audit('post', 'salesman_returns', $returnId, "Salesman return — payable reversed " . number_format($payableReturn, 2));
        });
    }

    /* ============ CLAIM GOODS (supplier claim receipts — accepted only enters stock) ============ */

    /** Post a claim goods receipt (exactly once). Accepted qty only → stock at claim terms; journal posts AP or Cash. */
    public static function postClaimGoods(int $claimId): void {
        $cg = db_one('SELECT * FROM claim_goods WHERE id = ?', [$claimId]);
        if (!$cg) throw new RuntimeException('Claim not found');
        if ($cg['status'] === 'reversed' || $cg['status'] === 'rejected') return;
        if (!empty($cg['journal_entry_id']) || $cg['status'] === 'posted') return; // exactly once
        $items = db_all('SELECT * FROM claim_goods_items WHERE claim_goods_id = ?', [$claimId]);
        if (!$items) throw new RuntimeException('Claim has no items.');
        $whId = (int)$cg['warehouse_id'];

        Database::transaction(function () use ($cg, $items, $claimId, $whId) {
            $claimTotal = 0.0;
            foreach ($items as $it) {
                $good = (float)$it['good_qty'];
                if ($good <= 0) continue; // damaged/rejected never touch saleable stock
                $rate = (float)$it['claim_rate'];
                $line = round($good * $rate, 2);
                Inventory::receive((int)$it['product_id'], $whId, $good, $rate, 'claim_goods', 'claim_goods', $claimId, null, null, 'Claim goods — accepted only');
                $claimTotal += $line;
            }
            $claimTotal = round($claimTotal, 2);
            $lines = [
                ['account' => 'INVENTORY', 'debit' => $claimTotal, 'memo' => 'Claim goods — accepted only — ' . $cg['claim_no']],
            ];
            $cashPaid = 0.0;
            if ($cg['payment_type'] === 'cash' && !empty($cg['pay_account_id'])) {
                $gl = self::accountGl((int)$cg['pay_account_id']);
                $lines[] = ['account' => $gl, 'credit' => $claimTotal, 'memo' => 'Claim paid in cash'];
                $cashPaid = $claimTotal;
            } else {
                $lines[] = ['account' => 'AP', 'credit' => $claimTotal, 'supplier_id' => (int)$cg['supplier_id'], 'memo' => 'Claim payable — ' . $cg['claim_no']];
            }
            $journalId = Accounting::postEntry($lines, [
                'date' => $cg['date'], 'voucher_type' => 'CG', 'reference_type' => 'claim_goods',
                'reference_id' => $claimId, 'memo' => 'Claim goods — ' . $cg['claim_no'],
                'idem_key' => 'claim_goods:' . $claimId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec("UPDATE claim_goods SET journal_entry_id = ?, claim_amount = ?, cash_paid = ?, status = 'posted' WHERE id = ?", [$journalId, $claimTotal, $cashPaid, $claimId]);
            audit('post', 'claim_goods', $claimId, "Claim posted; accepted Rs $claimTotal; journal $entryNo");
        });
        try { Notifications::notifyClaimGoods($claimId); } catch (Throwable $e) { error_log('notify claim goods: ' . $e->getMessage()); }
    }

    /** Approval-gated claim payment (partial or full) — posts exactly once: Dr AP, Cr Cash/Bank. */
    public static function postClaimPayment(int $paymentId): void {
        $p = db_one('SELECT * FROM claim_goods_payments WHERE id = ?', [$paymentId]);
        if (!$p) return;
        if ($p['status'] === 'posted' || !empty($p['journal_entry_id'])) return;
        $cl = db_one('SELECT * FROM claim_goods WHERE id = ?', [(int)$p['claim_goods_id']]);
        if (!$cl) throw new RuntimeException('Parent claim not found');
        $gl = self::accountGl((int)$p['account_id']);
        Database::transaction(function () use ($p, $cl, $paymentId, $gl) {
            $journalId = Accounting::postEntry([
                ['account' => 'AP', 'debit' => (float)$p['amount'], 'supplier_id' => (int)$cl['supplier_id'], 'memo' => 'Claim payment — ' . $cl['claim_no']],
                ['account' => $gl, 'credit' => (float)$p['amount'], 'memo' => 'Claim payment'],
            ], [
                'date' => $p['date'], 'voucher_type' => 'CP', 'reference_type' => 'claim_payment',
                'reference_id' => $paymentId, 'memo' => 'Claim payment — ' . $cl['claim_no'],
                'idem_key' => 'claim_payment:' . $paymentId,
            ]);
            $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
            db_exec("UPDATE claim_goods_payments SET payment_no = ?, status = 'posted', journal_entry_id = ? WHERE id = ?", [$entryNo, $journalId, $paymentId]);
            db_exec('UPDATE claim_goods SET cash_paid = cash_paid + ? WHERE id = ?', [(float)$p['amount'], (int)$p['claim_goods_id']]);
            audit('post', 'claim_goods_payments', $paymentId, "Claim payment; journal $entryNo");
        });
        try { Notifications::notifyClaimPayment($paymentId); } catch (Throwable $e) { error_log('notify claim payment: ' . $e->getMessage()); }
    }

    /* ============ PURCHASE ORDERS (approval-gated manager requests) ============ */

    /** Flip a pending purchase order to approved — exactly once. */
    public static function approvePurchaseOrder(int $poId): void {
        $po = db_one('SELECT * FROM purchase_orders WHERE id = ?', [$poId]);
        if (!$po) throw new RuntimeException('Purchase order not found');
        if ($po['status'] === 'approved') return; // exactly once
        if ($po['status'] !== 'pending_approval' && $po['status'] !== 'draft') {
            throw new RuntimeException('PO cannot be approved from status ' . $po['status'] . '.');
        }
        db_exec("UPDATE purchase_orders SET status = 'approved' WHERE id = ? AND status IN ('pending_approval','draft')", [$poId]);
        audit('approve', 'purchase_orders', $poId, 'Purchase order approved via approvals console');
    }

    /* ============ Generic helpers ============ */

    /** GL account id linked to a cash/bank account (falls back to system CASH/BANK defaults). */
    public static function accountGl(int $cashBankAccountId): int {
        $r = db_one('SELECT gl_account_id, type FROM cash_bank_accounts WHERE id = ?', [$cashBankAccountId]);
        if (!$r) throw new RuntimeException('Cash/bank account not found');
        if ($r['gl_account_id']) return (int)$r['gl_account_id'];
        return Accounting::accountId($r['type'] === 'bank' ? 'BANK' : 'CASH');
    }

    public static function defaultAccountGl(string $type): int {
        $r = db_val("SELECT id FROM cash_bank_accounts WHERE type = ? AND is_active = 1 ORDER BY id LIMIT 1", [$type]);
        return $r ? self::accountGl((int)$r) : Accounting::accountId($type === 'bank' ? 'BANK' : 'CASH');
    }
}
