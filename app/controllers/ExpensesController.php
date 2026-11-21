<?php
final class ExpensesController {
    public static function index(): void {
        $q = inp('q'); $cat = intv(inp('category_id')); $status = inp('status'); $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $where = ['e.date BETWEEN ? AND ?']; $params = [$from, $to];
        if ($q !== '') { $where[] = '(e.payee LIKE ? OR e.description LIKE ? OR e.expense_no LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($cat) { $where[] = 'e.category_id = ?'; $params[] = $cat; }
        if ($status !== '') { $where[] = 'e.status = ?'; $params[] = $status; }
        $sql = "SELECT e.*, c.name AS category FROM expenses e JOIN expense_categories c ON c.id = e.category_id WHERE " . implode(' AND ', $where) . " ORDER BY e.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        $sum = db_val("SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE " . implode(' AND ', $where), $params);
        if (($_GET['export'] ?? '') === 'csv') {
            $rows = db_all($sql, $params);
            StockController::sendCsv('expenses.csv', ['No','Date','Category','Payee','Description','Payment','Status','Amount'],
                array_map(fn($r) => [$r['expense_no'], $r['date'], $r['category'], $r['payee'], $r['description'], $r['payment_type'], $r['status'], $r['amount']], $rows));
        }
        view('expenses/index', ['p' => $p, 'q' => $q, 'cat' => $cat, 'status' => $status, 'from' => $from, 'to' => $to, 'sum' => $sum,
            'categories' => db_all('SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name'), 'title' => 'Expenses']);
    }

    public static function newExpense(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $categoryId = intv(inp('category_id'));
            $amount = round(num(inp('amount')), 2);
            $paymentType = in_array(inp('payment_type'), ['cash', 'bank', 'payable'], true) ? inp('payment_type') : 'cash';
            $accountId = in_array($paymentType, ['cash', 'bank'], true) ? intv(inp('account_id')) : null;
            if (!$categoryId || $amount <= 0) { flash('error', __('Category and a positive amount are required.')); redirect('expenses/new'); }
            if ($paymentType !== 'payable' && !$accountId) { flash('error', __('Select the cash/bank account paid from (or choose “On credit/payable”).')); redirect('expenses/new'); }
            if (idempotency_seen('expenses/new', inp('idem_key'), (int)($_SESSION['user_id'] ?? 0))) {
                flash('info', __('Duplicate submission detected.')); redirect('expenses');
            }
            // Optional receipt/photo upload
            $attachment = null;
            if (!empty($_FILES['attachment']['name'])) {
                $f = $_FILES['attachment'];
                $maxBytes = 5 * 1024 * 1024;
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($f['tmp_name']);
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];
                if ($f['size'] > $maxBytes) { flash('error', __('Attachment too large (max 5 MB).')); redirect('expenses/new'); }
                if (!isset($allowed[$mime])) { flash('error', __('Attachment must be a JPG/PNG/WebP/GIF image or a PDF.')); redirect('expenses/new'); }
                $dir = dirname(__DIR__, 2) . '/storage/uploads';
                if (!is_dir($dir)) mkdir($dir, 0775, true);
                $attachment = 'exp_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $attachment)) { flash('error', __('Could not save the attachment.')); redirect('expenses/new'); }
            }
            Database::transaction(function () use ($categoryId, $amount, $paymentType, $accountId, $attachment) {
                $id = db_insert('expenses', ['expense_no' => 'TMP-' . bin2hex(random_bytes(6)), 'category_id' => $categoryId, 'date' => inp('date', today()), 'amount' => $amount, 'payee' => inp('payee'), 'description' => inp('description'), 'payment_type' => $paymentType, 'account_id' => $accountId, 'status' => 'pending', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                    'person_name' => trim(inp('person_name')) ?: null, 'qty' => num(inp('qty')) > 0 ? round(num(inp('qty')), 3) : null, 'notes' => trim(inp('notes')) ?: null, 'attachment' => $attachment]);
                $catName = db_val('SELECT name FROM expense_categories WHERE id = ?', [$categoryId]);
                Approvals::create('expense', 'expenses', $id, $amount, 'Expense — ' . ($catName ?: 'category') . (inp('payee') ? ' — ' . inp('payee') : ''), (int)($_SESSION['user_id'] ?? 0));
                audit('create', 'expenses', $id, "Expense Rs $amount — pending Admin approval");
            });
            flash('success', __('Expense recorded — Owner/Admin must approve it before it posts to the ledger.'));
            redirect('expenses');
        }
        view('expenses/new', ['categories' => db_all('SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name'),
            'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'title' => 'New Expense']);
    }

    public static function expensePost(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        // Owner/Admin decides — mirrors the Admin Console decide flow exactly once
        if (!Auth::can('approvals.decide')) { flash('error', __('Approvals are decided by Owner/Admin in the Admin Console (/admin/approvals).')); redirect('expenses'); }
        $req = db_one("SELECT id FROM approval_requests WHERE reference_type = 'expenses' AND reference_id = ? AND request_type = 'expense' AND status = 'pending'", [$id]);
        if (!$req) { flash('error', __('No pending approval request found for this expense.')); redirect('expenses'); }
        try {
            Approvals::decide((int)$req['id'], true, 'Approved from expenses list', (int)($_SESSION['user_id'] ?? 0));
            flash('success', __('Expense approved — posted to the ledger exactly once.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('expenses');
    }

    public static function view(): void {
        $id = intv(inp('id'));
        $x = db_one("SELECT e.*, c.name AS category, a.name AS account FROM expenses e JOIN expense_categories c ON c.id = e.category_id LEFT JOIN cash_bank_accounts a ON a.id = e.account_id WHERE e.id = ?", [$id]);
        if (!$x) { http_response_code(404); exit('Expense not found'); }
        view('expenses/view', ['x' => $x, 'thermal' => inp('thermal') === '1', 'title' => 'Expense Voucher ' . $x['expense_no']]);
    }

    public static function expenseReverse(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        try {
            Posting::reverseExpense($id, inp('reason', 'Manual reversal'));
            flash('success', __('Expense reversed — a reversal journal was posted.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('expenses');
    }

    public static function categories(): void {
        $rows = db_all('SELECT c.*, a.code, a.name AS account_name FROM expense_categories c JOIN chart_of_accounts a ON a.id = c.gl_account_id ORDER BY c.name');
        view('expenses/categories', ['rows' => $rows, 'title' => 'Expense Categories']);
    }

    public static function categoryNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $name = inp('name'); $gl = intv(inp('gl_account_id'));
            if ($name === '' || !$gl) { flash('error', __('Name and GL account required.')); redirect('expenses/categories/new'); }
            db_insert('expense_categories', ['name' => $name, 'gl_account_id' => $gl, 'is_active' => 1]);
            audit('create', 'expense_categories', null, $name);
            flash('success', __('Category added.'));
            redirect('expenses/categories');
        }
        view('expenses/category_new', ['accounts' => db_all("SELECT id, code, name FROM chart_of_accounts WHERE allow_posting = 1 AND type IN ('expense','equity') ORDER BY code"), 'title' => 'New Expense Category']);
    }
}
