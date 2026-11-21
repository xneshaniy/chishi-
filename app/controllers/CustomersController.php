<?php
final class CustomersController {
    public static function index(): void {
        $q = inp('q');
        $where = ['COALESCE(c.is_archived, 0) = 0']; $params = [];
        if ($q !== '') { $where[] = '(c.code LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR c.area LIKE ?)'; $params = array_fill(0, 4, "%$q%"); }
        if (is_salesman_only()) {
            $my = my_salesman_id() ?: 0;
            $where[] = '(c.salesman_id = ? OR c.id IN (SELECT customer_id FROM salesman_customers WHERE salesman_id = ?))';
            $params[] = $my; $params[] = $my;
        }
        $sql = "SELECT c.*, s.name AS salesman,
                       (SELECT COALESCE(SUM(l.debit - l.credit),0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id WHERE e.status IN ('posted','reversed') AND l.customer_id = c.id) AS gl_balance
                FROM customers c LEFT JOIN salesmen s ON s.id = c.salesman_id WHERE " . implode(' AND ', $where) . ' ORDER BY c.name';
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        view('customers/index', ['p' => $p, 'q' => $q, 'title' => 'Customers']);
    }

    public static function newCustomer(): void { self::save(null); }
    public static function editCustomer(): void { self::save(intv(inp('id'))); }

    private static function save(?int $id): void {
        $customer = $id ? db_one('SELECT * FROM customers WHERE id = ?', [$id]) : null;
        if ($id && !$customer) { flash('error', __('Customer not found.')); redirect('customers'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $data = [
                'code' => strtoupper(inp('code')), 'name' => inp('name'), 'phone' => inp('phone'), 'alt_phone' => inp('alt_phone'),
                'address' => inp('address'), 'area' => inp('area'), 'city' => inp('city'), 'ntn' => inp('ntn'),
                'credit_limit' => round(num(inp('credit_limit')), 2),
                'opening_balance' => round(num(inp('opening_balance')), 2),
                'opening_type' => in_array(inp('opening_type'), ['debit', 'credit'], true) ? inp('opening_type') : 'debit',
                'salesman_id' => intv(inp('salesman_id')) ?: null,
                // New customers are Active by default; existing records toggle only via the edit form (Owner/Admin)
                'is_active' => $id ? (isset($_POST['is_active']) ? 1 : 0) : 1,
            ];
            $errors = [];
            if ($data['code'] === '' || $data['name'] === '') $errors[] = 'Code and name are required.';
            if (db_val('SELECT 1 FROM customers WHERE code = ? AND id != ?', [$data['code'], $id ?? 0])) $errors[] = 'Customer code already exists.';
            if ($errors) { flash('error', implode(' ', $errors)); redirect($id ? 'customers/' . $id . '/edit' : 'customers/new'); }
            if ($id) {
                db_exec('UPDATE customers SET code=?, name=?, phone=?, alt_phone=?, address=?, area=?, city=?, ntn=?, credit_limit=?, opening_balance=?, opening_type=?, salesman_id=?, is_active=? WHERE id=?',
                    array_merge(array_values($data), [$id]));
                audit('update', 'customers', $id, 'Updated ' . $data['code']);
                flash('success', __('Customer updated.'));
            } else {
                $newId = db_insert('customers', $data + ['created_at' => now_local()]);
                if ($data['salesman_id']) {
                    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
                    db_exec($driver === 'sqlite' ? 'INSERT OR IGNORE INTO salesman_customers (salesman_id, customer_id) VALUES (?,?)' : 'INSERT IGNORE INTO salesman_customers (salesman_id, customer_id) VALUES (?,?)', [$data['salesman_id'], $newId]);
                }
                audit('create', 'customers', $newId, 'Created ' . $data['code']);
                flash('success', __('Customer created.'));
            }
            redirect('customers');
        }
        $suggestedCode = 'CUST-' . str_pad((string)((int)db_val('SELECT MAX(id) FROM customers') + 1), 4, '0', STR_PAD_LEFT);
        view('customers/form', ['customer' => $customer, 'suggestedCode' => $suggestedCode,
            'salesmen' => db_all('SELECT id, name FROM salesmen WHERE is_active = 1 ORDER BY name'),
            'title' => $customer ? 'Edit Customer' : 'New Customer']);
    }

    public static function statement(): void {
        $id = intv(inp('id'));
        $customer = db_one('SELECT * FROM customers WHERE id = ?', [$id]);
        if (!$customer) { flash('error', __('Customer not found.')); redirect('customers'); }
        if (is_salesman_only()) {
            $my = my_salesman_id() ?: 0;
            $assigned = db_val('SELECT 1 FROM salesman_customers WHERE salesman_id = ? AND customer_id = ?', [$my, $id]) || (int)$customer['salesman_id'] === $my;
            if (!$assigned) { http_response_code(403); exit('You can only view your assigned customers.'); }
        }
        $from = inp('from') ?: null; $to = inp('to') ?: null;
        $lines = Accounting::partyLedger('customer', $id, $from, $to);
        $openingSigned = $customer['opening_type'] === 'debit' ? (float)$customer['opening_balance'] : -(float)$customer['opening_balance'];
        // When a period filter is used, the "opening" row is the balance before the period starts
        $prior = 0.0;
        if ($from) {
            $prior = (float)db_val("SELECT COALESCE(SUM(l.debit - l.credit),0) FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id WHERE e.status IN ('posted','reversed') AND l.customer_id = ? AND e.date < ?", [$id, $from]);
        }
        $periodOpening = $openingSigned + $prior;
        $gl = Accounting::customerBalance($id);
        $aging = Reporting::aging('customer', $id);
        view('customers/statement', ['customer' => $customer, 'lines' => $lines, 'openingSigned' => $openingSigned, 'periodOpening' => $periodOpening,
            'gl' => $gl, 'aging' => $aging, 'from' => $from, 'to' => $to,
            'title' => 'Customer Statement — ' . $customer['name']]);
    }
}
