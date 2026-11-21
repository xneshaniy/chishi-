<?php
/**
 * Gifts (money + stock) — owner-funded, tracked, auditable, NOT accounting sales/gifts from customers.
 *  - gift_type 'money': recorded as an expense with the "Gifts" category → existing approval flow posts exactly once.
 *  - gift_type 'goods': deducts finished goods from a warehouse exactly once on Owner/Admin approval.
 *  - Nothing here posts to sales, receivables or cash/bank without Owner/Admin approval.
 */
final class Gifts {

    public const MONEY = 'money';
    public const GOODS = 'goods';

    /** Gift number: GIFT-00001 */
    public static function nextGiftNo(): string {
        $max = (int)db_val("SELECT MAX(CAST(SUBSTR(gift_no, 6) AS INTEGER)) FROM gifts WHERE gift_no LIKE 'GIFT-%'");
        return 'GIFT-' . str_pad((string)($max + 1), 5, '0', STR_PAD_LEFT);
    }

    /** Finished-goods warehouse preference: WH-MAIN if it exists (type central), else first FG warehouse by id. */
    public static function defaultFgWarehouse(): int {
        $id = (int)db_val("SELECT id FROM warehouses WHERE is_active = 1 AND type = 'central' ORDER BY id LIMIT 1");
        return $id ?: (int)db_val("SELECT id FROM warehouses WHERE is_active = 1 ORDER BY id LIMIT 1");
    }

    /** All active finished-goods products with packaging tiers configured. */
    public static function fgProductOptions(): array {
        $products = db_all("SELECT p.id, p.sku, p.name, pk.pack_pieces, pk.bundle_packs, pk.packet_bundles FROM products p LEFT JOIN product_packaging pk ON pk.product_id = p.id WHERE p.is_active = 1 AND p.kind = 'finished' ORDER BY p.name");
        $defaults = UnitConverter::packagingDefaults();
        foreach ($products as &$p) {
            $pp = UnitConverter::productPackaging((int)$p['id']);
            $p['pack_pieces'] = $pp['pack_pieces'];
            $p['bundle_packs'] = $pp['bundle_packs'];
            $p['packet_bundles'] = $pp['packet_bundles'];
        }
        return $products;
    }

    /** Base stock quantity (Packets) for the chosen gift unit. Bundle is the exact 10-Packet equivalent. */
    public static function toPackets(float $qty, string $unit): ?float {
        $u = strtolower(trim($unit));
        if (in_array($u, ['packet', 'packets'], true)) return $qty;
        if (in_array($u, ['bundle', 'bundles'], true)) return $qty * UnitConverter::packetsPerBundle();
        return null;
    }

    /** Available finished-goods stock (Packets) across company warehouses. */
    public static function availablePackets(int $productId): float {
        $wh = (float)db_val("SELECT COALESCE(SUM(qty),0) FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id WHERE sb.product_id = ? AND w.type <> 'salesman'", [$productId]);
        return $wh;
    }

    /** Summary for dashboard */
    public static function summary(?string $from = null, ?string $to = null): array {
        $from = $from ?: date('Y-01-01');
        $to = $to ?: date('Y-m-d');
        $p = [$from, $to];
        $money = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM gifts WHERE gift_type = 'money' AND status IN ('pending','posted') AND gift_date BETWEEN ? AND ?", $p);
        $goods = (float)db_val("SELECT COALESCE(SUM(COALESCE(qty_pieces, qty, 0)),0) FROM gifts WHERE gift_type = 'goods' AND status IN ('pending','posted') AND gift_date BETWEEN ? AND ?", $p);
        $count = (int)db_val("SELECT COUNT(*) FROM gifts WHERE gift_date BETWEEN ? AND ?", $p);
        $pending = (int)db_val("SELECT COUNT(*) FROM gifts WHERE status = 'pending'");
        return ['money' => $money, 'goods_packets' => $goods, 'count' => $count, 'pending' => $pending, 'from' => $from, 'to' => $to];
    }

    /** Persist a money gift — creates an expense via the existing approval pipeline. */
    public static function createMoneyGift(array $data, int $userId): int {
        $catId = (int)db_val("SELECT id FROM expense_categories WHERE name = 'Gifts'");
        if (!$catId) {
            $glId = (int)db_val("SELECT id FROM chart_of_accounts WHERE code = '5780'");
            $catId = (int)db_insert('expense_categories', ['name' => 'Gifts', 'gl_account_id' => $glId ?: (int)db_val("SELECT MIN(id) FROM chart_of_accounts WHERE type='expense'"), 'is_active' => 1]);
        }
        $amount = round((float)$data['amount'], 2);
        if ($amount <= 0.004) throw new RuntimeException(__('Gift amount must be positive.'));
        $recipient = trim((string)($data['recipient_name'] ?? ''));
        if ($recipient === '') throw new RuntimeException(__('Recipient name is required.'));
        $expenseId = (int)db_insert('expenses', [
            'expense_no' => 'TMP-' . bin2hex(random_bytes(6)),
            'category_id' => $catId,
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data['gift_date']) ? $data['gift_date'] : today(),
            'amount' => $amount,
            'payee' => 'Gift — ' . $recipient,
            'description' => trim((string)($data['description'] ?? 'Gift')) ?: 'Gift',
            'payment_type' => in_array($data['payment_method'] ?? 'bank', ['cash', 'bank'], true) ? $data['payment_method'] : 'bank',
            'account_id' => intv($data['account_id'] ?? 0) ?: null,
            'status' => 'pending',
            'created_by' => $userId,
            'created_at' => now_local(),
        ]);
        if (empty($data['account_id'])) {
            // untracked amount tracking only — expense stays payable; still approval-gated
        }
        $giftId = (int)db_insert('gifts', [
            'gift_no' => self::nextGiftNo(),
            'gift_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data['gift_date']) ? $data['gift_date'] : today(),
            'recipient_name' => $recipient,
            'recipient_type' => in_array($data['recipient_type'] ?? 'guest', ['employee','relative','customer','guest','other'], true) ? $data['recipient_type'] : 'guest',
            'gift_type' => self::MONEY,
            'amount' => $amount,
            'reason' => trim((string)($data['occasion'] ?? $data['reason'] ?? '')) ?: null,
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'status' => 'pending',
            'expense_id' => $expenseId,
            'recorded_by' => $userId,
            'created_at' => now_local(),
        ]);
        Approvals::create('gift', 'gifts', $giftId, $amount, 'Money gift — ' . $recipient . ' (ledger via expense #' . $expenseId . ')', $userId);
        return $giftId;
    }

    /** Persist a goods gift — stock NOT deducted yet; approval posts it exactly once. */
    public static function createGoodsGift(array $data, int $userId): int {
        $recipient = trim((string)($data['recipient_name'] ?? ''));
        if ($recipient === '') throw new RuntimeException(__('Recipient name is required.'));
        $productId = intv($data['product_id'] ?? 0);
        $product = db_one("SELECT * FROM products WHERE id = ? AND kind = 'finished' AND is_active = 1", [$productId]);
        if (!$product) throw new RuntimeException(__('Select a finished-goods product.'));
        $qty = (float)($data['qty'] ?? 0);
        if ($qty <= 0.004) throw new RuntimeException(__('Quantity must be positive.'));
        $unit = strtolower(trim((string)($data['qty_unit'] ?? 'packet')));
        $packets = self::toPackets($qty, $unit);
        if ($packets === null) throw new RuntimeException(__('Choose Packet or Bundle for this gift.'));
        $available = self::availablePackets($productId);
        if ($packets - $available > 0.004) {
            throw new RuntimeException(sprintf(__('Insufficient stock: %.3f Packets available, requested %.3f Packets.'), $available, $packets));
        }
        $giftId = (int)db_insert('gifts', [
            'gift_no' => self::nextGiftNo(),
            'gift_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data['gift_date']) ? $data['gift_date'] : today(),
            'recipient_name' => $recipient,
            'recipient_type' => in_array($data['recipient_type'] ?? 'guest', ['employee','relative','customer','guest','other'], true) ? $data['recipient_type'] : 'guest',
            'gift_type' => self::GOODS,
            'product_id' => $productId,
            'qty' => round($qty, 3),
            'qty_unit' => $unit,
            'qty_pieces' => round($packets, 3), // legacy column name — stores the base stock quantity in Packets
            'reason' => trim((string)($data['occasion'] ?? $data['reason'] ?? '')) ?: null,
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'status' => 'pending',
            'recorded_by' => $userId,
            'created_at' => now_local(),
        ]);
        Approvals::create('gift', 'gifts', $giftId, 0.00, 'Goods gift — ' . $recipient . ': ' . $qty . ' ' . $unit . ' × ' . $product['name'], $userId);
        audit('request', 'gifts', $giftId, 'Goods gift requested');
        return $giftId;
    }

    /** Exactly-once post: goods gifts deduct FG stock; money gifts stay routed through expenses approval. */
    public static function post(int $giftId, int $deciderId): void {
        $gift = db_one('SELECT * FROM gifts WHERE id = ?', [$giftId]);
        if (!$gift) throw new RuntimeException(__('Gift not found.'));
        if ($gift['status'] !== 'pending') return; // already posted — never duplicate
        if ($gift['gift_type'] === self::GOODS) {
            $productId = (int)$gift['product_id'];
            $packets = (float)$gift['qty_pieces']; // base stock quantity in Packets (legacy column name)
            if ($packets <= 0) throw new RuntimeException(__('Invalid gift quantity.'));
            // Deduct FIFO from FG (type='central') warehouses — same policy as all FG issues
            $remaining = $packets;
            foreach (db_all("SELECT sb.warehouse_id, sb.qty FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id WHERE sb.product_id = ? AND sb.qty > 0 AND w.type IN ('central','factory','warehouse') ORDER BY CASE w.type WHEN 'central' THEN 0 WHEN 'factory' THEN 1 ELSE 2 END, sb.qty DESC", [$productId]) as $row) {
                if ($remaining <= 0.0009) break;
                $take = min((float)$row['qty'], $remaining);
                Inventory::issue($productId, (int)$row['warehouse_id'], $take, 'gift_goods', 'gifts', $giftId, null, null, 'Gift #' . $giftId . ' — ' . $gift['recipient_name']);
                $remaining -= $take;
            }
            if ($remaining > 0.0009) throw new RuntimeException(__('Insufficient FG stock — gift cannot be issued.'));
            db_exec('UPDATE gifts SET status = ?, approved_by = ? WHERE id = ?', ['posted', $deciderId, $giftId]);
        } else {
            // money gift posting happens via the linked expenses row — nothing to stock here
            db_exec('UPDATE gifts SET status = ?, approved_by = ? WHERE id = ?', ['posted', $deciderId, $giftId]);
        }
    }

    /** Reject: mark rejected; nothing posts, stock stays untouched. */
    public static function markRejected(int $giftId): void {
        db_exec("UPDATE gifts SET status = 'rejected' WHERE id = ? AND status = 'pending'", [$giftId]);
    }

    public static function listGifts(?string $status = null, int $limit = 50): array {
        $where = []; $params = [];
        if ($status && in_array($status, ['pending','posted','rejected'], true)) { $where[] = 'status = ?'; $params[] = $status; }
        $rows = db_all("SELECT g.*, p.name AS product_name, p.sku FROM gifts g LEFT JOIN products p ON p.id = g.product_id " . ($where ? 'WHERE ' . implode(' AND ', $where) . ' ' : '') . " ORDER BY g.id DESC LIMIT " . (int)$limit, $params);
        return $rows;
    }
}
