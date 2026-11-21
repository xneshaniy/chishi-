<?php
/**
 * Factory daily raw-material consumption — KG out of RM stock into production.
 * Multiple materials per batch; exact KG deducted; over-issue blocked; exactly-once posting.
 */
final class ConsumptionController {

    public static function index(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', date('Y-m-t')); $q = inp('q'); $batch = inp('batch');
        $where = ["date(fi.date) BETWEEN ? AND ?"]; $params = [$from, $to];
        if ($q !== '') { $where[] = "(fi.issue_no LIKE ? OR fi.batch_no LIKE ? OR fi.details LIKE ?)"; array_push($params, "%$q%", "%$q%", "%$q%"); }
        if ($batch !== '') { $where[] = 'fi.batch_no = ?'; $params[] = $batch; }
        $sql = "SELECT fi.*, u.username AS issuer, a.username AS approver,
                       COALESCE((SELECT SUM(qty_kg) FROM factory_issue_items WHERE issue_id = fi.id),0) AS qty_kg,
                       COALESCE((SELECT SUM(total_cost) FROM factory_issue_items WHERE issue_id = fi.id),0) AS total_cost,
                       COALESCE((SELECT COUNT(*) FROM factory_issue_items WHERE issue_id = fi.id),0) AS line_count
                FROM factory_issues fi LEFT JOIN users u ON u.id = fi.issued_by LEFT JOIN users a ON a.id = fi.approved_by
                WHERE " . implode(' AND ', $where) . " ORDER BY fi.date DESC, fi.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        $todayTotal = (float)db_val("SELECT COALESCE(SUM(fii.qty_kg),0) FROM factory_issue_items fii JOIN factory_issues fi ON fi.id = fii.issue_id WHERE fi.status = 'posted' AND date(fi.date) = ?", [today()]);
        $todayValue = (float)db_val("SELECT COALESCE(SUM(fii.total_cost),0) FROM factory_issue_items fii JOIN factory_issues fi ON fi.id = fii.issue_id WHERE fi.status = 'posted' AND date(fi.date) = ?", [today()]);
        view('consumption/index', ['p' => $p, 'from' => $from, 'to' => $to, 'q' => $q, 'batch' => $batch,
            'todayTotal' => $todayTotal, 'todayValue' => $todayValue,
            'batches' => db_all("SELECT DISTINCT batch_no FROM factory_issues WHERE batch_no IS NOT NULL AND batch_no <> '' ORDER BY batch_no DESC"),
            'title' => 'Factory Consumption']);
    }

    public static function newIssue(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $date = inp('date', today());
            $batchNo = inp('batch_no');
            $details = inp('details');
            $productionOrderId = intv(inp('production_order_id')) ?: null;
            $warehouseId = intv(inp('warehouse_id'));
            $issuedBy = (int)($_SESSION['user_id'] ?? 0);
            $approvedByInput = intv(inp('approved_by')) ?: null;
            $needsApproval = (bool)setting('rm_consumption_approval', '0');
            $manualApprover = $approvedByInput ?: null;

            $isAdmin = is_owner_or_admin();
            // If this batch is tied to a released production order, surface it (validated below)
            if ($productionOrderId) {
                $linked = db_one("SELECT id, status FROM production_orders WHERE id = ?", [$productionOrderId]);
                if (!$linked || !in_array($linked['status'], ['released', 'draft'], true)) $productionOrderId = null;
            }

            $materials = $_POST['items']['product_id'] ?? [];
            $qtys = $_POST['items']['qty_kg'] ?? [];
            $items = []; $totalKg = 0.0; $errors = [];
            foreach (($qtys ?? []) as $i => $q) {
                $pid = (int)($materials[$i] ?? 0); $qty = (float)$q;
                if (!$pid || $qty <= 0) continue;
                $kind = (string)db_val('SELECT kind FROM products WHERE id = ?', [$pid]);
                if ($kind === 'finished') { $errors[] = 'Finished products cannot be consumed as raw material.'; continue; }
                $wh = $warehouseId ?: (int)db_val("SELECT id FROM warehouses WHERE type IN ('factory','central') AND is_active = 1 ORDER BY CASE type WHEN 'factory' THEN 0 ELSE 1 END LIMIT 1");
                $onHand = (float)db_val('SELECT COALESCE(qty,0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$pid, $wh]);
                if ($qty > $onHand + 0.0009) {
                    $p = db_val('SELECT name FROM products WHERE id = ?', [$pid]);
                    $errors[] = 'Insufficient stock for ' . $p . ' (on hand ' . number_format($onHand, 3) . ' kg).';
                    continue;
                }
                $items[] = ['product_id' => $pid, 'qty_kg' => round($qty, 3), 'warehouse_id' => $wh];
                $totalKg += $qty;
            }
            if (!$items) $errors[] = 'Add at least one raw material with a quantity in KG.';
            if (!$warehouseId) $errors[] = 'Select the RM warehouse to deduct from.';
            if ($batchNo === '') $errors[] = 'Batch number is required.';
            if ($errors) { flash('error', implode(' ', $errors)); redirect('stock/consumption/new'); }

            $issueNo = 'FC-' . date('ymd') . '-' . str_pad((string)((int)db_val('SELECT MAX(id) FROM factory_issues') + 1), 4, '0', STR_PAD_LEFT);
            $totalValue = 0.0;
            try {
                Database::transaction(function () use ($date, $batchNo, $details, $productionOrderId, &$issueId, $issuedBy, $needsApproval, $isAdmin, $manualApprover, $items, $warehouseId, &$totalValue) {
                    $issueId = db_insert('factory_issues', [
                        'issue_no' => 'TMP-' . bin2hex(random_bytes(4)), 'date' => $date, 'batch_no' => $batchNo,
                        'details' => $details, 'production_order_id' => $productionOrderId,
                        'issued_by' => $issuedBy, 'approved_by' => $isAdmin ? $issuedBy : ($manualApprover ?: null),
                        'status' => 'pending', 'notes' => inp('notes') ?: null, 'created_at' => now_local(),
                    ]);
                    foreach ($items as $it) {
                        $onHand = (float)db_val('SELECT COALESCE(qty,0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$it['product_id'], $warehouseId]);
                        db_insert('factory_issue_items', ['issue_id' => $issueId, 'product_id' => $it['product_id'], 'warehouse_id' => $warehouseId, 'qty_kg' => $it['qty_kg'], 'unit_cost' => 0, 'total_cost' => 0]);
                    }
                    // exact-once idempotent number
                    db_exec('UPDATE factory_issues SET issue_no = ? WHERE id = ?', ['FC-' . date('ymd') . '-' . str_pad((string)$issueId, 4, '0', STR_PAD_LEFT), $issueId]);
                });
                // Post now if permitted — otherwise route through approvals
                $canDirectPost = $isAdmin || (!$needsApproval && Auth::can('production.release'));
                if ($canDirectPost) {
                    Posting::postFactoryIssue((int)$issueId);
                    flash('success', 'Factory consumption posted — RM stock deducted (' . number_format($totalKg, 3) . ' kg).');
                } else {
                    $totalEst = 0.0;
                    foreach ($items as $it) $totalEst += (float)db_val('SELECT COALESCE(avg_cost,0) * ? FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$it['qty_kg'], $it['product_id'], $it['warehouse_id']]);
                    Approvals::create('rm_consumption', 'factory_issues', (int)$issueId, round($totalEst, 2), 'Factory consumption batch ' . $batchNo, (int)($_SESSION['user_id'] ?? 0));
                    flash('success', 'Consumption submitted — Owner/Admin must approve it before stock moves.');
                }
                redirect('stock/consumption');
            } catch (Throwable $e) {
                error_log('factory consumption failed: ' . $e->getMessage());
                flash('error', 'Consumption failed: ' . $e->getMessage());
                redirect('stock/consumption/new');
            }
        }
        // GET: materials & orders
        $materials = db_all("SELECT p.id, p.sku, p.name, u.short AS unit_short, COALESCE((SELECT SUM(qty) FROM stock_balances sb WHERE sb.product_id = p.id),0) AS on_hand FROM products p LEFT JOIN units u ON u.id = p.unit_id WHERE p.is_active = 1 AND p.kind IN ('raw','packaging') ORDER BY p.name");
        $warehouses = StockController::warehouseOptions();
        $released = db_all("SELECT id, order_no, batch_no FROM production_orders WHERE status = 'released' ORDER BY id DESC LIMIT 50");
        $approvers = db_all("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name");
        view('consumption/new', ['materials' => $materials, 'warehouses' => $warehouses, 'released' => $released, 'approvers' => $approvers, 'title' => 'Factory Consumption']);
    }

    /** Printable consumption voucher for a posted/pending issue. */
    public static function view(): void {
        $id = intv(inp('id'));
        $issue = db_one("SELECT fi.*, u.username AS issuer, a.username AS approver FROM factory_issues fi LEFT JOIN users u ON u.id = fi.issued_by LEFT JOIN users a ON a.id = fi.approved_by WHERE fi.id = ?", [$id]);
        if (!$issue) { flash('error', __('Consumption issue not found.')); redirect('stock/consumption'); }
        $items = db_all("SELECT fii.*, p.sku, p.name FROM factory_issue_items fii JOIN products p ON p.id = fii.product_id WHERE fii.issue_id = ? ORDER BY fii.id", [$id]);
        view('consumption/view', ['issue' => $issue, 'items' => $items, 'title' => 'Consumption Voucher ' . $issue['issue_no']]);
    }

    /** Owner/Admin cancels (Posts reversal movements back to RM warehouse). */
    public static function cancel(): void {
        require_post(); csrf_verify();
        if (!Auth::can('journal.reverse')) { flash('error', __('Not permitted.')); redirect('stock/consumption'); }
        $id = intv(inp('id'));
        try { Posting::reverseFactoryIssue($id, inp('reason', 'Cancelled by user')); flash('success', 'Consumption issue cancelled — stock returned to RM warehouse if it had posted.'); }
        catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('stock/consumption');
    }
}
