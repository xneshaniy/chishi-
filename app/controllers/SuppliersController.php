<?php
final class SuppliersController {
    public static function index(): void {
        $q = inp('q');
        $where = ['COALESCE(s.is_archived, 0) = 0']; $params = [];
        if ($q !== '') { $where[] = '(code LIKE ? OR name LIKE ? OR phone LIKE ?)'; $params = ["%$q%", "%$q%", "%$q%"]; }
        $sql = "SELECT s.*, (SELECT COALESCE(SUM(l.credit - l.debit),0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id WHERE e.status IN ('posted','reversed') AND l.supplier_id = s.id) AS gl_balance
                FROM suppliers s WHERE " . implode(' AND ', $where) . ' ORDER BY s.name';
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        view('suppliers/index', ['p' => $p, 'q' => $q, 'title' => 'Suppliers']);
    }

    public static function newSupplier(): void { self::save(null); }
    public static function editSupplier(): void { self::save(intv(inp('id'))); }

    private static function save(?int $id): void {
        $supplier = $id ? db_one('SELECT * FROM suppliers WHERE id = ?', [$id]) : null;
        if ($id && !$supplier) { flash('error', __('Supplier not found.')); redirect('suppliers'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $data = [
                'code' => strtoupper(inp('code')), 'name' => inp('name'), 'phone' => inp('phone'),
                'address' => inp('address'), 'city' => inp('city'), 'ntn' => inp('ntn'),
                'opening_balance' => round(num(inp('opening_balance')), 2),
                'opening_type' => in_array(inp('opening_type'), ['debit', 'credit'], true) ? inp('opening_type') : 'credit',
                // New suppliers are Active by default; existing records toggle only via the edit form (Owner/Admin)
                'is_active' => $id ? (isset($_POST['is_active']) ? 1 : 0) : 1,
            ];
            $errors = [];
            if ($data['code'] === '' || $data['name'] === '') $errors[] = 'Code and name are required.';
            if (db_val('SELECT 1 FROM suppliers WHERE code = ? AND id != ?', [$data['code'], $id ?? 0])) $errors[] = 'Supplier code already exists.';
            if ($errors) { flash('error', implode(' ', $errors)); redirect($id ? 'suppliers/' . $id . '/edit' : 'suppliers/new'); }
            if ($id) {
                db_exec('UPDATE suppliers SET code=?, name=?, phone=?, address=?, city=?, ntn=?, opening_balance=?, opening_type=?, is_active=? WHERE id=?', array_merge(array_values($data), [$id]));
                audit('update', 'suppliers', $id, 'Updated ' . $data['code']);
                flash('success', __('Supplier updated.'));
            } else {
                $newId = db_insert('suppliers', $data + ['created_at' => now_local()]);
                audit('create', 'suppliers', $newId, 'Created ' . $data['code']);
                flash('success', __('Supplier created.'));
            }
            redirect('suppliers');
        }
        $suggestedCode = 'SUP-' . str_pad((string)((int)db_val('SELECT MAX(id) FROM suppliers') + 1), 4, '0', STR_PAD_LEFT);
        view('suppliers/form', ['supplier' => $supplier, 'suggestedCode' => $suggestedCode, 'title' => $supplier ? 'Edit Supplier' : 'New Supplier']);
    }

    public static function statement(): void {
        $id = intv(inp('id'));
        $supplier = db_one('SELECT * FROM suppliers WHERE id = ?', [$id]);
        if (!$supplier) { flash('error', __('Supplier not found.')); redirect('suppliers'); }
        $from = inp('from') ?: null; $to = inp('to') ?: null;
        $lines = Accounting::partyLedger('supplier', $id, $from, $to);
        $openingSigned = $supplier['opening_type'] === 'credit' ? (float)$supplier['opening_balance'] : -(float)$supplier['opening_balance'];
        $prior = 0.0;
        if ($from) {
            $prior = (float)db_val("SELECT COALESCE(SUM(l.credit - l.debit),0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id WHERE e.status IN ('posted','reversed') AND l.supplier_id = ? AND e.date < ?", [$id, $from]);
        }
        $periodOpening = $openingSigned + $prior;
        $gl = Accounting::supplierBalance($id);
        $aging = Reporting::aging('supplier', $id);
        view('suppliers/statement', ['supplier' => $supplier, 'lines' => $lines, 'openingSigned' => $openingSigned, 'periodOpening' => $periodOpening,
            'gl' => $gl, 'aging' => $aging, 'from' => $from, 'to' => $to, 'title' => 'Supplier Statement — ' . $supplier['name']]);
    }
}
