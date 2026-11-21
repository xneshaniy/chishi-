<?php
final class MaintenanceController {

    /* ============ List / dashboard ============ */
    public static function index(): void {
        $q = inp('q'); $dept = inp('department'); $status = inp('status');
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $where = ['mr.problem_date BETWEEN ? AND ?']; $params = [$from, $to];
        if ($q !== '') { $where[] = '(mr.machine_name LIKE ? OR mr.record_no LIKE ? OR mr.asset_no LIKE ? OR mr.vendor LIKE ? OR mr.problem LIKE ?)'; $like = "%$q%"; array_push($params, $like, $like, $like, $like, $like); }
        if ($dept !== '') { $where[] = 'mr.department = ?'; $params[] = $dept; }
        if ($status !== '') { $where[] = 'mr.status = ?'; $params[] = $status; }
        $sql = "SELECT mr.*, (SELECT COALESCE(SUM(mp.qty * mp.unit_cost),0) FROM maintenance_parts mp WHERE mp.maintenance_id = mr.id) AS parts_sum FROM maintenance_records mr WHERE " . implode(' AND ', $where) . " ORDER BY mr.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        $sum = db_val("SELECT COALESCE(SUM(mr.total_cost),0) FROM maintenance_records mr WHERE " . implode(' AND ', $where), $params);
        $totPending = (int)db_val("SELECT COUNT(*) FROM maintenance_records mr WHERE " . implode(' AND ', $where) . " AND mr.status = 'pending'", $params);
        $totPosts = (float)db_val("SELECT COALESCE(SUM(mr.total_cost),0) FROM maintenance_records mr WHERE " . implode(' AND ', $where) . " AND mr.status = 'posted'", $params);
        if (($_GET['export'] ?? '') === 'csv') {
            $rows = db_all($sql, $params);
            StockController::sendCsv('maintenance.csv', ['No','Date','Machine','Asset No','Department','Problem','Work','Vendor','Parts','Labour','Other','Total','Payment','Status'],
                array_map(fn($r) => [$r['record_no'], $r['problem_date'], $r['machine_name'], $r['asset_no'], $r['department'], mb_substr($r['problem'], 0, 120), mb_substr((string)$r['work_performed'], 0, 120), $r['vendor'], $r['parts_cost'], $r['labour_cost'], $r['other_cost'], $r['total_cost'], $r['payment_type'], $r['status']], $rows));
        }
        view('maintenance/index', ['p' => $p, 'q' => $q, 'dept' => $dept, 'status' => $status, 'from' => $from, 'to' => $to, 'sum' => $sum, 'totPending' => $totPending, 'totPosted' => $totPosts,
            'departments' => db_all("SELECT DISTINCT department FROM maintenance_records WHERE department <> '' ORDER BY department"),
            'title' => 'Factory Maintenance & Repairs']);
    }

    /* ============ Create / record ============ */
    public static function newRecord(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $assetId = intv(inp('asset_id')) ?: null;
            $asset = $assetId ? db_one('SELECT * FROM maintenance_assets WHERE id = ?', [$assetId]) : null;
            $machineInput = trim(inp('machine_name'));
            if ($asset && strcasecmp(trim($asset['name']), $machineInput) !== 0) $asset = null; // user typed a different machine name
            $machine = $machineInput ?: ($asset ? trim($asset['name']) : '');
            $problem = trim(inp('problem'));
            $problemDate = inp('problem_date', today());
            $vendor = trim(inp('vendor'));
            $labour = round(num(inp('labour_cost')), 2);
            $other = round(num(inp('other_cost')), 2);
            $paymentType = in_array(inp('payment_type'), ['cash', 'bank', 'credit'], true) ? inp('payment_type') : 'cash';
            $accountId = $paymentType !== 'credit' ? (intv(inp('account_id')) ?: null) : null;
            $parts = [];
            foreach (($_POST['parts']['name'] ?? []) as $i => $name) {
                $name = trim((string)$name);
                $qty = round(num($_POST['parts']['qty'][$i] ?? 0), 3);
                $cost = round(num($_POST['parts']['unit_cost'][$i] ?? 0), 2);
                if ($name === '' || $qty <= 0) continue;
                $parts[] = ['name' => $name, 'qty' => $qty, 'unit_cost' => $cost, 'line_total' => round($qty * $cost, 2)];
            }
            $partsCost = round(array_sum(array_column($parts, 'line_total')), 2);
            $total = round($partsCost + $labour + $other, 2);
            if ($machine === '' || $problem === '') { flash('error', __('Machine name and problem description are required.')); redirect('maintenance/new'); }
            if ($total < 0) { flash('error', __('Costs cannot be negative.')); redirect('maintenance/new'); }
            if ($paymentType !== 'credit' && !$accountId) { flash('error', __('Select the cash/bank account paid from (or choose "On credit/payable").')); redirect('maintenance/new'); }
            if (idempotency_seen('maintenance/new', inp('idem_key'), (int)($_SESSION['user_id'] ?? 0))) { flash('info', __('Duplicate submission detected.')); redirect('maintenance'); }

            $attachment = null;
            if (!empty($_FILES['attachment']['name'])) {
                $f = $_FILES['attachment'];
                $maxBytes = 5 * 1024 * 1024;
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($f['tmp_name']);
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];
                if ($f['size'] > $maxBytes) { flash('error', __('Attachment too large (max 5 MB).')); redirect('maintenance/new'); }
                if (!isset($allowed[$mime])) { flash('error', __('Attachment must be a JPG/PNG/WebP/GIF image or a PDF.')); redirect('maintenance/new'); }
                $dir = dirname(__DIR__, 2) . '/storage/uploads';
                if (!is_dir($dir)) mkdir($dir, 0775, true);
                $attachment = 'mnt_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $attachment)) { flash('error', __('Could not save the attachment.')); redirect('maintenance/new'); }
            }

            Database::transaction(function () use ($asset, $machine, $problem, $problemDate, $vendor, $labour, $other, $parts, $partsCost, $total, $paymentType, $accountId, $attachment) {
                $seq = (int)db_val('SELECT COALESCE(MAX(id),0) + 1 FROM maintenance_records');
                $recordNo = 'MNT-' . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
                $recordId = db_insert('maintenance_records', [
                    'record_no' => $recordNo,
                    'asset_id' => $asset ? (int)$asset['id'] : null,
                    'machine_name' => $machine,
                    'asset_no' => $asset ? ($asset['asset_no'] ?: null) : (inp('asset_no') ?: null),
                    'department' => trim(inp('department')) ?: ($asset ? $asset['department'] : ''),
                    'location' => trim(inp('location')) ?: ($asset ? $asset['location'] : null),
                    'problem' => $problem,
                    'problem_date' => $problemDate,
                    'work_performed' => trim(inp('work_performed')) ?: null,
                    'work_date' => inp('work_date') ?: null,
                    'vendor' => $vendor ?: null,
                    'labour_cost' => $labour,
                    'other_cost' => $other,
                    'parts_cost' => $partsCost,
                    'total_cost' => $total,
                    'payment_type' => $paymentType,
                    'account_id' => $accountId,
                    'status' => $total > 0 ? 'pending' : 'posted',
                    'attachment' => $attachment,
                    'created_by' => $_SESSION['user_id'] ?? null,
                    'created_at' => now_local(),
                ]);
                foreach ($parts as $p) db_insert('maintenance_parts', ['maintenance_id' => $recordId, 'name' => $p['name'], 'qty' => $p['qty'], 'unit_cost' => $p['unit_cost'], 'line_total' => $p['line_total']]);
                if ($total > 0) {
                    Approvals::create('maintenance', 'maintenance_records', (int)$recordId, $total, 'Maintenance — ' . $machine . ($vendor ? ' — ' . $vendor : ''), (int)($_SESSION['user_id'] ?? 0));
                }
                audit('create', 'maintenance_records', $recordId, $recordNo . ' total Rs ' . number_format($total, 2));
            });
            flash('success', $total > 0 ? __('Maintenance record saved — Owner/Admin must approve it before the expense posts and cash/bank is updated.') : __('Maintenance record saved (no cost — no payment required).'));
            redirect('maintenance');
        }
        view('maintenance/new', [
            'assets' => db_all('SELECT id, name, asset_no, department, location FROM maintenance_assets WHERE is_active = 1 ORDER BY name'),
            'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"),
            'title' => 'New Maintenance Record',
        ]);
    }

    /* ============ View / print ============ */
    public static function view(): void {
        $id = intv(inp('id'));
        $m = db_one('SELECT * FROM maintenance_records WHERE id = ?', [$id]);
        if (!$m) { flash('error', __('Maintenance record not found.')); redirect('maintenance'); }
        $parts = db_all('SELECT * FROM maintenance_parts WHERE maintenance_id = ?', [$id]);
        $assetHistory = !empty($m['asset_id']) ? db_all('SELECT * FROM maintenance_records WHERE asset_id = ? AND id <> ? ORDER BY problem_date DESC, id DESC LIMIT 20', [(int)$m['asset_id'], $id]) : [];
        view('maintenance/view', ['m' => $m, 'parts' => $parts, 'assetHistory' => $assetHistory, 'title' => 'Maintenance ' . $m['record_no']]);
    }

    public static function attachment(): void {
        $id = intv(inp('id'));
        $m = db_one('SELECT attachment, record_no FROM maintenance_records WHERE id = ?', [$id]);
        if (!$m || empty($m['attachment'])) { http_response_code(404); exit('Not found'); }
        $path = dirname(__DIR__, 2) . '/storage/uploads/' . basename((string)$m['attachment']);
        if (!is_file($path)) { http_response_code(404); exit('File not found'); }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf'];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . $m['record_no'] . '.' . $ext . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /* ============ Approve / reject / reverse ============ */
    public static function approve(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        if (!Auth::can('approvals.decide')) { flash('error', __('Approvals are decided by Owner/Admin in the Admin Console (/admin/approvals).')); redirect('maintenance'); }
        $req = db_one("SELECT id FROM approval_requests WHERE reference_type = 'maintenance_records' AND reference_id = ? AND request_type = 'maintenance' AND status = 'pending'", [$id]);
        if (!$req) { flash('error', __('No pending approval request found for this record.')); redirect('maintenance/' . $id); }
        try {
            Approvals::decide((int)$req['id'], true, 'Approved from maintenance module', (int)($_SESSION['user_id'] ?? 0));
            flash('success', __('Maintenance approved — expense posted and cash/bank updated exactly once.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('maintenance/' . $id);
    }

    public static function reverse(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        if (!Auth::can('approvals.decide')) { flash('error', __('Only Owner/Admin can reverse a posted maintenance record.')); redirect('maintenance/' . $id); }
        try {
            Posting::reverseMaintenance($id, inp('reason', 'Manual reversal'));
            flash('success', __('Maintenance reversed — expense reversed and a reversal journal was posted.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('maintenance/' . $id);
    }

    /* ============ Assets ============ */
    public static function assets(): void {
        $q = inp('q');
        $where = ['1=1']; $params = [];
        if ($q !== '') { $where[] = '(a.name LIKE ? OR a.asset_no LIKE ? OR a.department LIKE ? OR a.location LIKE ?)'; $like = "%$q%"; array_push($params, $like, $like, $like, $like); }
        $rows = db_all("SELECT a.*,
            (SELECT COUNT(*) FROM maintenance_records mr WHERE mr.asset_id = a.id) AS jobs,
            (SELECT COALESCE(SUM(mr.total_cost),0) FROM maintenance_records mr WHERE mr.asset_id = a.id) AS repair_cost,
            (SELECT COALESCE(SUM(mr.total_cost),0) FROM maintenance_records mr WHERE mr.asset_id = a.id AND mr.status = 'posted') AS posted_cost
            FROM maintenance_assets a WHERE " . implode(' AND ', $where) . " ORDER BY a.name", $params);
        $totRows = db_one("SELECT COUNT(*) AS assets, COALESCE(SUM(x.repair_cost),0) AS total FROM (SELECT a.id, (SELECT COALESCE(SUM(mr.total_cost),0) FROM maintenance_records mr WHERE mr.asset_id = a.id) AS repair_cost FROM maintenance_assets a WHERE " . implode(' AND ', $where) . ") x", $params);
        view('maintenance/assets', ['rows' => $rows, 'q' => $q, 'totRows' => $totRows, 'title' => 'Maintenance Assets']);
    }

    public static function assetNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $name = trim(inp('name')); $assetNo = trim(inp('asset_no'));
            if ($name === '') { flash('error', __('Asset name is required.')); redirect('maintenance/assets/new'); }
            try {
                db_insert('maintenance_assets', ['name' => $name, 'asset_no' => $assetNo ?: null, 'department' => trim(inp('department')), 'location' => trim(inp('location')) ?: null, 'notes' => trim(inp('notes')) ?: null, 'is_active' => 1, 'created_at' => now_local()]);
                audit('create', 'maintenance_assets', null, $name);
                flash('success', __('Asset added.'));
                redirect('maintenance/assets');
            } catch (PDOException $e) { flash('error', __('Asset number already exists.')); redirect('maintenance/assets/new'); }
        }
        view('maintenance/asset_form', ['asset' => null, 'title' => 'New Maintenance Asset']);
    }

    public static function assetEdit(): void {
        $id = intv(inp('id'));
        $asset = db_one('SELECT * FROM maintenance_assets WHERE id = ?', [$id]);
        if (!$asset) { flash('error', __('Asset not found.')); redirect('maintenance/assets'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $name = trim(inp('name')); $assetNo = trim(inp('asset_no'));
            if ($name === '') { flash('error', __('Asset name is required.')); redirect('maintenance/assets/' . $id . '/edit'); }
            try {
                db_exec('UPDATE maintenance_assets SET name=?, asset_no=?, department=?, location=?, notes=?, is_active=? WHERE id=?', [
                    $name, $assetNo ?: null, trim(inp('department')), trim(inp('location')) ?: null, trim(inp('notes')) ?: null, isset($_POST['is_active']) ? 1 : 0, $id,
                ]);
                audit('update', 'maintenance_assets', $id, $name);
                flash('success', __('Asset updated.'));
                redirect('maintenance/assets');
            } catch (PDOException $e) { flash('error', __('Asset number already exists.')); redirect('maintenance/assets/' . $id . '/edit'); }
        }
        view('maintenance/asset_form', ['asset' => $asset, 'title' => 'Edit Maintenance Asset']);
    }

    /* ============ Reports ============ */
    public static function report(): void {
        $from = inp('from', date('Y-m-01')); $to = inp('to', today());
        $group = in_array(inp('group'), ['day', 'month', 'year', 'machine', 'department', 'vendor', 'asset'], true) ? inp('group') : 'month';
        $dept = inp('department'); $machine = inp('machine');
        $where = ['mr.problem_date BETWEEN ? AND ?']; $params = [$from, $to];
        if ($dept !== '') { $where[] = 'mr.department = ?'; $params[] = $dept; }
        if ($machine !== '') { $where[] = 'mr.machine_name LIKE ?'; $params[] = '%' . $machine . '%'; }
        $rows = db_all("SELECT mr.*, (SELECT COALESCE(SUM(mp.qty * mp.unit_cost),0) FROM maintenance_parts mp WHERE mp.maintenance_id = mr.id) AS parts_sum FROM maintenance_records mr WHERE " . implode(' AND ', $where) . " ORDER BY mr.problem_date DESC, mr.id DESC", $params);
        $groups = [];
        foreach ($rows as $r) {
            $key = match ($group) {
                'day' => (string)$r['problem_date'],
                'month' => substr((string)$r['problem_date'], 0, 7),
                'year' => substr((string)$r['problem_date'], 0, 4),
                'machine' => $r['machine_name'],
                'department' => $r['department'] ?: '(no department)',
                'vendor' => $r['vendor'] ?: '(no vendor)',
                'asset' => $r['asset_no'] ?: $r['machine_name'],
            };
            if (!isset($groups[$key])) $groups[$key] = ['label' => $key, 'count' => 0, 'cost' => 0.0, 'posted' => 0.0];
            $groups[$key]['count']++;
            $groups[$key]['cost'] += (float)$r['total_cost'];
            if ($r['status'] === 'posted') $groups[$key]['posted'] += (float)$r['total_cost'];
        }
        uasort($groups, fn($a, $b) => $b['cost'] <=> $a['cost']);
        $totCount = count($rows); $totCost = array_sum(array_column($rows, 'total_cost')); $totPosted = 0.0; foreach ($rows as $r) if ($r['status'] === 'posted') $totPosted += (float)$r['total_cost'];
        $summary = array_values($groups);
        if (($_GET['export'] ?? '') === 'csv') {
            StockController::sendCsv('maintenance_report.csv', ['Group','Jobs','Total Cost','Posted Cost'], array_map(fn($g) => [$g['label'], $g['count'], $g['cost'], $g['posted']], $summary));
        }
        view('maintenance/report', ['from' => $from, 'to' => $to, 'group' => $group, 'dept' => $dept, 'machine' => $machine, 'rows' => $rows, 'summary' => $summary, 'totCount' => $totCount, 'totCost' => $totCost, 'totPosted' => $totPosted, 'title' => 'Maintenance Report']);
    }
}
