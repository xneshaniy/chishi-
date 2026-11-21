<?php
/**
 * Gifts — money (routed through existing Expense approval) and goods (FG stock deduction ledger).
 * Manager can CREATE gift records only with gifts.create permission (granted per decision by Owner/Admin).
 * Only Owner/Admin may approve. Money gifts become expenses and follow the exact same approval flow.
 */
final class GiftsController {
    public static function index(): void {
        if (!Auth::can('gifts.view')) { http_response_code(403); exit('Forbidden'); }
        $canMoney = is_owner_or_admin();
        $status = in_array(inp('status', 'all'), ['all', 'pending', 'posted', 'rejected'], true) ? inp('status') : 'all';
        $type   = in_array(inp('type', 'all'),  ['all', 'money', 'goods'], true) ? inp('type') : 'all';
        $q      = trim(inp('q'));
        $where = []; $params = [];
        if ($status !== 'all') { $where[] = 'g.status = ?'; $params[] = $status; }
        if ($type   !== 'all') { $where[] = 'g.gift_type = ?'; $params[] = $type; }
        if ($q !== '')          { $where[] = '(g.recipient_name LIKE ? OR g.gift_no LIKE ? OR g.reason LIKE ?)'; $like = "%$q%"; array_push($params, $like, $like, $like); }
        $rows = db_all("SELECT g.*, p.name AS product_name, p.sku FROM gifts g LEFT JOIN products p ON p.id = g.product_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY g.id DESC", $params);
        $summary = Gifts::summary(date('Y-01-01'), today());
        $canCreate = Auth::can('gifts.create');
        view('gifts/index', ['rows' => $rows, 'summary' => $summary, 'status' => $status, 'type' => $type, 'q' => $q, 'canCreate' => $canCreate, 'title' => __('Gifts')]);
    }

    public static function newMoney(): void {
        if (!Auth::can('gifts.create')) { http_response_code(403); exit('Forbidden'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            try {
                $giftId = Gifts::createMoneyGift($_POST, (int)($_SESSION['user_id'] ?? 0));
                flash('success', __('Money gift recorded — pending Owner/Admin approval.'));
                redirect('gifts/' . $giftId);
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('gifts/new-money'); }
        }
        $accounts = db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
        view('gifts/new_money', ['accounts' => $accounts, 'title' => __('New Money Gift')]);
    }

    public static function newGoods(): void {
        if (!Auth::can('gifts.create')) { http_response_code(403); exit('Forbidden'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            try {
                $giftId = Gifts::createGoodsGift($_POST, (int)($_SESSION['user_id'] ?? 0));
                flash('success', __('Goods gift recorded — stock deducted only after Owner/Admin approval.'));
                redirect('gifts/' . $giftId);
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('gifts/new-goods'); }
        }
        $products = Gifts::fgProductOptions();
        view('gifts/new_goods', ['products' => $products, 'title' => __('New Goods Gift')]);
    }

    public static function show(): void {
        if (!Auth::can('gifts.view')) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        $gift = db_one("SELECT g.*, p.name AS product_name, p.sku, e.expense_no AS expense_no, u.username AS recorded_by_username FROM gifts g LEFT JOIN products p ON p.id = g.product_id LEFT JOIN expenses e ON e.id = g.expense_id LEFT JOIN users u ON u.id = g.recorded_by WHERE g.id = ?", [$id]);
        if (!$gift) { http_response_code(404); exit(__('Gift not found.')); }
        $auditRows = db_all("SELECT * FROM audit_logs WHERE entity = 'gifts' AND entity_id = ? ORDER BY id", [(int)$id]);
        view('gifts/show', ['gift' => $gift, 'auditRows' => $auditRows, 'title' => __('Gift') . ' ' . ($gift['gift_no'] ?? ('#' . $id))]);
    }

    /** Cancel a pending gift — only Owner/Admin; stock never moved at cancel-time (stock is untouched until approval). */
    public static function cancelGift(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        try {
            $g = db_one('SELECT * FROM gifts WHERE id = ?', [$id]);
            if (!$g) throw new RuntimeException(__('Gift not found.'));
            if ($g['status'] !== 'pending') throw new RuntimeException(__('Only pending gifts can be cancelled.'));
            db_exec("UPDATE gifts SET status = 'cancelled' WHERE id = ?", [$id]);
            if (!empty($g['expense_id'])) db_exec("UPDATE expenses SET status = 'rejected' WHERE id = ? AND status = 'pending'", [(int)$g['expense_id']]);
            audit('cancel', 'gifts', $id, $g['gift_no'] . ' cancelled');
            flash('success', __('Gift cancelled.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('gifts');
    }
}
