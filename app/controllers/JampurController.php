<?php
/**
 * Jampur Grinding — tobacco/raw material sent to Jampur for grinding.
 * Tracking is a shadow ledger in grinding_* tables; no new warehouse system.
 * Send: issues from WH-MAIN (grinding_out). Grind: records output + loss + charges (no stock move).
 * Return: receives back into WH-MAIN (grinding_return). Losses are always explicit, never silent.
 */
final class JampurController {

    private static function whMainId(): int {
        return (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1");
    }

    private static function unitFactor(string $unit): float {
        $u = strtolower($unit);
        if ($u === 'ton') return (float)setting('rm_unit_ton', 1000);
        if ($u === 'man') return (float)setting('rm_unit_man', 40);
        return 1.0; // kg
    }

    /** All sends with computed remaining/processed/returned/loss for summary. */
    private static function sendsData(?string $productId = null): array {
        $sql = "SELECT s.*, p.name AS product, p.sku,
            COALESCE(r.qty_received, 0) AS processed_kg,
            COALESCE(r.loss, 0) AS loss_kg,
            COALESCE(ret.qty_returned, 0) AS returned_kg,
            COALESCE(ret.qty_returned, 0) AS returned
            FROM grinding_sends s
            JOIN products p ON p.id = s.product_id
            LEFT JOIN (SELECT send_id, SUM(qty_received_kg) AS qty_received, SUM(loss_kg) AS loss FROM grinding_records GROUP BY send_id) r ON r.send_id = s.id
            LEFT JOIN (SELECT send_id, SUM(qty_kg) AS qty_returned FROM grinding_returns GROUP BY send_id) ret ON ret.send_id = s.id";
        $params = [];
        if ($productId) { $sql .= ' WHERE s.product_id = ?'; $params[] = (int)$productId; }
        $sql .= ' ORDER BY s.id DESC';
        return db_all($sql, $params);
    }

    public static function index(): void {
        $sends = self::sendsData();
        $sent = array_sum(array_column($sends, 'qty_kg'));
        $processed = array_sum(array_column($sends, 'processed_kg'));
        $loss = array_sum(array_column($sends, 'loss_kg'));
        $returned = array_sum(array_column($sends, 'returned_kg'));
        $remaining = max(0, $sent - $returned - $loss);
        $pendingGrinding = max(0, $sent - $processed);
        $pendingCharges = (float)db_val("SELECT COALESCE(SUM(charges),0) FROM grinding_records WHERE charges_status = 'pending'");
        $pendingCount = (int)db_val("SELECT COUNT(*) FROM grinding_sends s LEFT JOIN grinding_records r ON r.send_id = s.id WHERE r.id IS NULL");
        $recentGrinding = db_all("SELECT gr.*, s.send_no, s.party_name, p.name AS product FROM grinding_records gr JOIN grinding_sends s ON s.id = gr.send_id JOIN products p ON p.id = s.product_id ORDER BY gr.grind_date DESC, gr.id DESC LIMIT 5");
        $recentReturns = db_all("SELECT g.*, s.send_no, p.name AS product FROM grinding_returns g JOIN grinding_sends s ON s.id = g.send_id JOIN products p ON p.id = s.product_id ORDER BY g.return_date DESC, g.id DESC LIMIT 5");
        $canTransfer = Auth::can('inventory.transfer');
        view('jampur/index', [
            'sends' => $sends, 'sent' => $sent, 'processed' => $processed, 'loss' => $loss,
            'returned' => $returned, 'remaining' => $remaining, 'pendingGrinding' => $pendingGrinding,
            'pendingCharges' => $pendingCharges, 'pendingSends' => $pendingCount,
            'recentGrinding' => $recentGrinding, 'recentReturns' => $recentReturns,
            'canTransfer' => $canTransfer, 'title' => 'Jampur Grinding',
        ]);
    }

    /* ============ Send material for grinding ============ */

    public static function send(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (!Auth::can('inventory.transfer')) { http_response_code(403); exit('Forbidden'); }
            $date = inp('date', today());
            $productId = intv(inp('product_id'));
            $qty = num(inp('qty'));
            $unit = strtolower(inp('unit', 'kg'));
            $party = trim(inp('party_name'));
            $ratePerKg = inp('charges_per_kg') !== '' ? num(inp('charges_per_kg')) : null;
            $vehicle = trim(inp('vehicle'));
            $notes = trim(inp('notes'));

            $err = [];
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $err[] = __('Date is required.');
            if (!$productId) $err[] = __('Select a material.');
            if ($qty <= 0) $err[] = __('Quantity must be positive.');
            if ($party === '') $err[] = __('Grinding party / factory name is required.');
            $kind = db_val('SELECT kind FROM products WHERE id = ?', [$productId]);
            if (!$kind || !in_array($kind, ['raw', 'packaging'], true)) $err[] = __('Only raw materials and packaging can be sent for grinding.');
            $qtyKg = round($qty * self::unitFactor($unit), 3);
            if ($err) { flash('error', implode(' ', $err)); redirect('jampur/send'); }

            try {
                Database::transaction(function () use ($date, $productId, $qtyKg, $party, $ratePerKg, $vehicle, $notes) {
                    $maxId = (int)db_val('SELECT COALESCE(MAX(id),0) FROM grinding_sends');
                    $sendNo = 'JMP-' . str_pad((string)($maxId + 1), 4, '0', STR_PAD_LEFT);
                    $sendId = (int)db_insert('grinding_sends', [
                        'send_no' => $sendNo, 'date' => $date, 'product_id' => $productId, 'qty_kg' => $qtyKg,
                        'unit_cost' => 0, 'party_name' => $party, 'charges_per_kg' => $ratePerKg,
                        'vehicle' => $vehicle ?: null, 'notes' => $notes ?: null,
                        'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local(),
                    ]);
                    $unitCost = Inventory::issue($productId, self::whMainId(), $qtyKg, 'grinding_out', 'grinding_send', $sendId, null, null, 'Sent to Jampur for grinding - ' . $party);
                    db_exec('UPDATE grinding_sends SET unit_cost = ? WHERE id = ?', [$unitCost, $sendId]);
                });
                audit('create', 'grinding_sends', null, 'Material sent to Jampur (' . number_format($qtyKg, 3) . ' KG)');
                flash('success', __('Material sent to Jampur for grinding. Ahmedpur stock deducted.'));
            } catch (Throwable $e) {
                error_log('jampur send: ' . $e->getMessage());
                flash('error', __('Could not send material: ') . $e->getMessage());
            }
            redirect('jampur');
        }
        $products = db_all("SELECT p.id, p.sku, p.name, p.kind, COALESCE(sb.qty,0) AS on_hand, u.short AS unit
            FROM products p LEFT JOIN stock_balances sb ON sb.product_id = p.id AND sb.warehouse_id = ?
            LEFT JOIN units u ON u.id = p.unit_id
            WHERE p.is_active = 1 AND p.kind IN ('raw','packaging') ORDER BY p.name", [self::whMainId()]);
        view('jampur/send', ['products' => $products, 'title' => 'Send Material for Grinding']);
    }

    /* ============ Record grinding ============ */

    public static function grind(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (!Auth::can('inventory.transfer')) { http_response_code(403); exit('Forbidden'); }
            $sendId = intv(inp('send_id'));
            $grindDate = inp('grind_date', today());
            $qtyReceived = num(inp('qty_received_kg'));
            $loss = num(inp('loss_kg'));
            $charges = num(inp('charges'));
            $notes = trim(inp('notes'));

            $err = [];
            $send = $sendId ? db_one('SELECT * FROM grinding_sends WHERE id = ?', [$sendId]) : null;
            if (!$send) $err[] = __('Select a Jampur grinding record.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $grindDate)) $err[] = __('Grinding date is required.');
            if ($qtyReceived <= 0) $err[] = __('Quantity received after grinding must be positive.');
            if ($loss < 0) $err[] = __('Loss cannot be negative.');
            if ($send) {
                $already = (float)db_val('SELECT COALESCE(SUM(qty_received_kg + loss_kg),0) FROM grinding_records WHERE send_id = ?', [$sendId]);
                if ($already + $qtyReceived + $loss - (float)$send['qty_kg'] > 0.0009) $err[] = __('Received + loss cannot exceed the quantity sent.');
            }
            if ($err) { flash('error', implode(' ', $err)); redirect('jampur/grind'); }

            try {
                db_insert('grinding_records', ['send_id' => $sendId, 'grind_date' => $grindDate, 'qty_received_kg' => $qtyReceived, 'loss_kg' => $loss, 'charges' => $charges, 'charges_status' => 'pending', 'notes' => $notes ?: null, 'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local()]);
                audit('create', 'grinding_records', null, 'Grinding recorded for ' . $send['send_no']);
                flash('success', __('Grinding recorded.'));
            } catch (Throwable $e) {
                error_log('jampur grind: ' . $e->getMessage());
                flash('error', __('Could not record grinding: ') . $e->getMessage());
            }
            redirect('jampur');
        }
        $sends = self::sendsData();
        view('jampur/grind', ['sends' => $sends, 'title' => 'Record Grinding']);
    }

    /* ============ Material returned to Ahmedpur ============ */

    public static function returns(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (!Auth::can('inventory.transfer')) { http_response_code(403); exit('Forbidden'); }
            $sendId = intv(inp('send_id'));
            $returnDate = inp('return_date', today());
            $qty = num(inp('qty_kg'));
            $notes = trim(inp('notes'));

            $err = [];
            $send = $sendId ? db_one('SELECT * FROM grinding_sends WHERE id = ?', [$sendId]) : null;
            if (!$send) $err[] = __('Select a Jampur grinding record.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnDate)) $err[] = __('Return date is required.');
            if ($qty <= 0) $err[] = __('Return quantity must be positive.');
            if ($send) {
                $processed = (float)db_val('SELECT COALESCE(SUM(qty_received_kg),0) FROM grinding_records WHERE send_id = ?', [$sendId]);
                $alreadyReturned = (float)db_val('SELECT COALESCE(SUM(qty_kg),0) FROM grinding_returns WHERE send_id = ?', [$sendId]);
                if ($alreadyReturned + $qty - $processed > 0.0009) $err[] = __('Cannot return more than the quantity processed at Jampur.');
            }
            if ($err) { flash('error', implode(' ', $err)); redirect('jampur/returns'); }

            try {
                Database::transaction(function () use ($send, $returnDate, $qty, $notes) {
                    $retId = (int)db_insert('grinding_returns', ['send_id' => (int)$send['id'], 'return_date' => $returnDate, 'qty_kg' => $qty, 'unit_cost' => (float)$send['unit_cost'], 'notes' => $notes ?: null, 'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local()]);
                    Inventory::receive((int)$send['product_id'], self::whMainId(), $qty, (float)$send['unit_cost'], 'grinding_return', 'grinding_return', $retId, null, null, 'Return from Jampur for grinding - ' . $send['send_no']);
                });
                audit('create', 'grinding_returns', null, 'Material returned from Jampur to Ahmedpur (' . number_format($qty, 3) . ' KG)');
                flash('success', __('Return recorded. Ahmedpur stock updated.'));
            } catch (Throwable $e) {
                error_log('jampur return: ' . $e->getMessage());
                flash('error', __('Could not record return: ') . $e->getMessage());
            }
            redirect('jampur');
        }
        $sends = self::sendsData();
        $returns = db_all("SELECT g.*, s.send_no, s.party_name, p.name AS product, p.sku FROM grinding_returns g JOIN grinding_sends s ON s.id = g.send_id JOIN products p ON p.id = s.product_id ORDER BY g.id DESC LIMIT 50");
        view('jampur/returns', ['sends' => $sends, 'returns' => $returns, 'title' => 'Material Returned to Ahmedpur']);
    }

    /* ============ Grinding charges — mark paid/unpaid ============ */

    public static function toggleCharges(): void {
        if (!Auth::can('inventory.transfer')) { http_response_code(403); exit('Forbidden'); }
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $rec = $id ? db_one('SELECT * FROM grinding_records WHERE id = ?', [$id]) : null;
        if ($rec) {
            $newStatus = $rec['charges_status'] === 'paid' ? 'pending' : 'paid';
            db_exec('UPDATE grinding_records SET charges_status = ? WHERE id = ?', [$newStatus, $id]);
            audit('update', 'grinding_records', $id, 'Grinding charges marked ' . $newStatus);
            flash('success', __('Charges status updated.'));
        }
        redirect('jampur/report');
    }

    /* ============ Reports (date / material filter, print) ============ */

    public static function report(): void {
        $from = inp('from', date('Y-m-01'));
        $to = inp('to', today());
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = today();
        $productId = inp('product_id') !== '' ? (int)inp('product_id') : null;

        $sendWhere = 'WHERE s.date BETWEEN ? AND ?';
        $sendParams = [$from, $to];
        if ($productId) { $sendWhere .= ' AND s.product_id = ?'; $sendParams[] = $productId; }

        $sent = db_all("SELECT s.*, p.name AS product, p.sku FROM grinding_sends s JOIN products p ON p.id = s.product_id $sendWhere ORDER BY s.date, s.id", $sendParams);
        $grindWhere = 'WHERE gr.grind_date BETWEEN ? AND ?';
        $grindParams = [$from, $to];
        if ($productId) { $grindWhere .= ' AND s.product_id = ?'; $grindParams[] = $productId; }
        $ground = db_all("SELECT gr.*, s.send_no, s.party_name, p.name AS product, p.sku FROM grinding_records gr JOIN grinding_sends s ON s.id = gr.send_id JOIN products p ON p.id = s.product_id $grindWhere ORDER BY gr.grind_date, gr.id", $grindParams);
        $retWhere = 'WHERE g.return_date BETWEEN ? AND ?';
        $retParams = [$from, $to];
        if ($productId) { $retWhere .= ' AND s.product_id = ?'; $retParams[] = $productId; }
        $returns = db_all("SELECT g.*, s.send_no, p.name AS product, p.sku FROM grinding_returns g JOIN grinding_sends s ON s.id = g.send_id JOIN products p ON p.id = s.product_id $retWhere ORDER BY g.return_date, g.id", $retParams);

        $allSends = self::sendsData($productId);
        $remaining = []; $totSent = 0; $totProcessed = 0; $totLoss = 0; $totReturned = 0;
        foreach ($allSends as $s) {
            $totSent += (float)$s['qty_kg'];
            $totProcessed += (float)$s['processed_kg'];
            $totLoss += (float)$s['loss_kg'];
            $totReturned += (float)$s['returned_kg'];
            $sRemain = max(0, (float)$s['qty_kg'] - (float)$s['returned_kg'] - (float)$s['loss_kg']);
            if ($sRemain > 0.0009 || (float)$s['processed_kg'] < (float)$s['qty_kg'] - 0.0009) $remaining[] = array_merge($s, ['remaining_kg' => $sRemain]);
        }
        $pendingCharges = (float)db_val("SELECT COALESCE(SUM(charges),0) FROM grinding_records WHERE charges_status = 'pending'");
        $paidCharges = (float)db_val("SELECT COALESCE(SUM(charges),0) FROM grinding_records WHERE charges_status = 'paid'");
        $chargesList = db_all("SELECT gr.*, s.send_no, p.name AS product, p.sku FROM grinding_records gr JOIN grinding_sends s ON s.id = gr.send_id JOIN products p ON p.id = s.product_id WHERE gr.charges > 0 ORDER BY gr.grind_date DESC, gr.id DESC");

        $products = db_all("SELECT id, sku, name FROM products WHERE is_active = 1 AND kind IN ('raw','packaging') ORDER BY name");

        $history = [];
        foreach ($sent as $s) $history[] = ['date' => $s['date'], 'type' => 'SEND', 'ref' => $s['send_no'], 'material' => $s['product'], 'qty' => $s['qty_kg'], 'detail' => $s['party_name'], 'id' => $s['id']];
        foreach ($ground as $g) $history[] = ['date' => $g['grind_date'], 'type' => 'GRIND', 'ref' => $g['send_no'], 'material' => $g['product'], 'qty' => $g['qty_received_kg'], 'detail' => __('Loss') . ' ' . number_format($g['loss_kg'], 3), 'id' => $g['id']];
        foreach ($returns as $g) $history[] = ['date' => $g['return_date'], 'type' => 'RETURN', 'ref' => $g['send_no'], 'material' => $g['product'], 'qty' => $g['qty_kg'], 'detail' => '', 'id' => $g['id']];
        usort($history, fn($a, $b) => strcmp($a['date'] . $a['type'], $b['date'] . $b['type']));

        view('jampur/report', [
            'sent' => $sent, 'ground' => $ground, 'returns' => $returns, 'remaining' => $remaining,
            'from' => $from, 'to' => $to, 'productId' => $productId, 'products' => $products,
            'totSent' => $totSent, 'totProcessed' => $totProcessed, 'totLoss' => $totLoss, 'totReturned' => $totReturned,
            'pendingCharges' => $pendingCharges, 'paidCharges' => $paidCharges, 'chargesList' => $chargesList,
            'history' => $history, 'title' => 'Jampur Reports',
        ]);
    }
}
