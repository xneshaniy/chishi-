<?php
final class SalesmenController {
    public static function index(): void {
        $q = inp('q');
        $provinceId = intv(inp('province_id')) ?: null;
        $showInactive = isset($_GET['show_inactive']);
        $where = $showInactive ? 'COALESCE(s.is_archived, 0) = 0' : 's.is_active = 1 AND COALESCE(s.is_archived, 0) = 0';
        $params = [];
        if ($q !== '') { $where .= " AND (code LIKE ? OR name LIKE ? OR phone LIKE ? OR cnic LIKE ? OR area LIKE ?)"; $params = array_fill(0, 5, "%$q%"); }
        if ($provinceId) { $where .= " AND province_id = ?"; $params[] = $provinceId; }
        $rows = db_all("SELECT s.*, w.name AS warehouse,
            (SELECT COUNT(*) FROM customers c WHERE c.salesman_id = s.id AND c.is_active = 1) AS assigned_customers,
            (SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE salesman_id = s.id AND status = 'posted' AND date >= ?) AS month_sales,
            (SELECT name FROM pk_provinces WHERE id = s.province_id) AS province_name,
            (SELECT name FROM pk_cities WHERE id = s.city_id) AS city_name,
            (SELECT name FROM areas WHERE id = s.area_id) AS area_name
            FROM salesmen s LEFT JOIN warehouses w ON w.id = s.warehouse_id WHERE $where ORDER BY s.name", array_merge([date('Y-m-01')], $params));
        foreach ($rows as &$r) {
            $loc = $r['area_name'] ?: ($r['city_name'] ?: null);
            if (!empty($r['province_name'])) $loc = trim($loc . ', ' . $r['province_name'], ', ');
            $r['location_text'] = $loc ?: ($r['area'] ?: '');
        }
        unset($r);
        view('salesmen/index', ['rows' => $rows, 'q' => $q, 'provinceId' => $provinceId, 'showInactive' => $showInactive, 'title' => 'Salesmen']);
    }

    public static function newSalesman(): void { self::save(null); }
    public static function editSalesman(): void { self::save(intv(inp('id'))); }
    public static function salesmanToggle(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        profile_toggle_active('salesmen', $id);
        $back = trim((string)inp('back', ''));
        if ($back !== '' && str_starts_with($back, 'salesmen/')) { redirect($back); }
        redirect('salesmen/' . $id . '/profile');
    }

    private static function save(?int $id): void {
        $salesman = $id ? db_one('SELECT * FROM salesmen WHERE id = ?', [$id]) : null;
        if ($id && !$salesman) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $provinceId = intv(inp('province_id')) ?: null;
            $cityId = intv(inp('city_id')) ?: null;
            $areaText = inp('area');
            $areaId = find_or_create_area($provinceId, $cityId, $areaText);
            $salaryFrequency = in_array(inp('salary_frequency'), ['monthly', 'weekly'], true) ? inp('salary_frequency') : 'monthly';
            $canSetFinancial = is_owner_or_admin();
            $data = [
                'code' => strtoupper(inp('code')), 'name' => inp('name'), 'phone' => inp('phone'),
                'cnic' => inp('cnic'), 'address' => inp('address'), 'area' => $areaText,
                'province_id' => $provinceId, 'city_id' => $cityId, 'area_id' => $areaId,
                'commission_percent' => round(num(inp('commission_percent')), 2),
                'salary' => round(num(inp('salary')), 2),
                'salary_frequency' => $salaryFrequency,
                'join_date' => inp('join_date') ?: null,
                'vehicle_type' => inp('vehicle_type'),
                'vehicle_reg' => inp('vehicle_reg'),
                'vehicle_note' => inp('vehicle_note'),
                // On create this is set from the creator's role below; on edit it reflects the checkbox (Owner/Admin only) — managers cannot toggle it
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            $isManagerRegistration = !$id && !is_owner_or_admin() && in_array('manager', Auth::roleCodes(), true);
            if (!$id) {
                // Owner/Admin-created salesmen are Active immediately; manager registrations stay pending/inactive until Owner/Admin approves
                $data['is_active'] = $isManagerRegistration ? 0 : 1;
            }
            if (!$canSetFinancial && $id) {
                // Manager editing an existing salesman: financial figures stay locked to what the Owner/Admin set
                $data['commission_percent'] = (float)$salesman['commission_percent'];
                $data['salary'] = (float)$salesman['salary'];
                $data['salary_frequency'] = $salesman['salary_frequency'] ?? 'monthly';
                // Manager cannot activate/deactivate an existing profile either
                $data['is_active'] = (int)$salesman['is_active'];
            }
            $errors = [];
            if ($data['code'] === '' || $data['name'] === '') $errors[] = 'Code and name are required.';
            if (db_val('SELECT 1 FROM salesmen WHERE code = ? AND id != ?', [$data['code'], $id ?? 0])) $errors[] = 'Salesman code already exists.';
            if (!$id && !$canSetFinancial && $data['cnic'] !== '' && db_val('SELECT 1 FROM salesmen WHERE cnic = ?', [$data['cnic']])) {
                $errors[] = 'A salesman with this CNIC already exists.';
            }
            if ($errors) { flash('error', implode(' ', $errors)); redirect($id ? 'salesmen/' . $id . '/edit' : 'salesmen/new'); }
            $sid = $id;
            Database::transaction(function () use ($data, $id, $salesman, &$sid, $isManagerRegistration) {
                if ($id) {
                    db_exec('UPDATE salesmen SET code=?, name=?, phone=?, cnic=?, address=?, area=?, province_id=?, city_id=?, area_id=?, commission_percent=?, salary=?, salary_frequency=?, join_date=?, vehicle_type=?, vehicle_reg=?, vehicle_note=?, is_active=? WHERE id=?',
                        array_merge(array_values($data), [$id]));
                    $sid = (int)$id;
                    audit('update', 'salesmen', $id, 'Updated ' . $data['code']);
                } else {
                    $newId = db_insert('salesmen', $data + ['created_at' => now_local()]);
                    if (!$isManagerRegistration) {
                        // Owner/Admin-created sale stock location created immediately; manager registrations get it on approval
                        $branchId = (int)db_val("SELECT id FROM branches WHERE is_main = 1 LIMIT 1");
                        $whCode = 'WH-' . $data['code'];
                        $existing = db_val('SELECT id FROM warehouses WHERE code = ?', [$whCode]);
                        if (!$existing) {
                            $existing = db_insert('warehouses', ['branch_id' => $branchId ?: null, 'code' => $whCode, 'name' => $data['name'] . ' — Sales Stock', 'type' => 'salesman', 'is_default' => 0, 'is_active' => 1, 'created_at' => now_local()]);
                        }
                        db_exec('UPDATE salesmen SET warehouse_id = ? WHERE id = ?', [$existing, $newId]);
                    }
                    $sid = $newId;
                    audit('create', 'salesmen', $newId, 'Created ' . $data['code'] . ($isManagerRegistration ? ' (pending approval)' : ' with sales stock warehouse'));
                }
            });
            // photo + CNIC pictures (served via authenticated route, never public)
            $errs = self::handleUploads((int)$sid);
            foreach ($errs as $e) error_log('salesman upload: ' . $e);
            if ($errs) foreach ($errs as $e) flash('error', __('Profile photo could not be saved. Use JPG, JPEG, PNG or WebP up to 2 MB.'));
            if (!$id && $isManagerRegistration) {
                try {
                    Approvals::create('registration', 'salesmen', (int)$sid, (float)($data['salary'] ?? 0),
                        'New salesman registration — ' . $data['name'] . ' (' . $data['code'] . ')',
                        (int)($_SESSION['user_id'] ?? 0));
                } catch (Throwable $e) { error_log('registration approval request: ' . $e->getMessage()); }
                flash('success', __('Registration submitted — pending Owner/Admin approval. The profile will become active and a sales-stock warehouse will be created upon approval.'));
            } else {
                flash('success', $id ? __('Salesman updated.') : __('Salesman created with a dedicated sales-stock warehouse.'));
            }
            redirect('salesmen');
        }
        $suggestedCode = 'SM-' . str_pad((string)((int)db_val('SELECT MAX(id) FROM salesmen') + 1), 3, '0', STR_PAD_LEFT);
        view('salesmen/form', ['salesman' => $salesman, 'suggestedCode' => $suggestedCode, 'title' => $salesman ? 'Edit Salesman' : 'New Salesman']);
    }

    /* ============ Salesman profile — upload helpers ============ */

    /** Validate + store an uploaded image/document; returns stored relative path or null. */
    private static function storeUpload(array $file, array $kinds, int $maxBytes, ?int $salesmanId): ?string {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > $maxBytes) return null;
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif', 'pdf' => 'pdf'];
        $ext = $allowed[$ext] ?? null;
        if ($ext === null || !isset($kinds[$ext])) return null;
        if ($ext !== 'pdf') { $info = @getimagesize($file['tmp_name']); if (!$info) return null; }
        $id = (int)$salesmanId;
        $dir = dirname(__DIR__, 2) . '/storage/uploads/salesmen/' . $id;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return null;
        $name = 'sm_' . $id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return null;
        return 'salesmen/' . $id . '/' . $name;
    }

    private static function safeUnlink(?string $rel): void {
        if (!$rel) return;
        $path = dirname(__DIR__, 2) . '/storage/uploads/' . $rel;
        if (is_file($path)) @unlink($path);
    }

    private static function mimeFor(string $filename): string {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf'];
        return $map[$ext] ?? 'application/octet-stream';
    }

    private static function handleUploads(int $salesmanId): array {
        $id = (int)$salesmanId;
        $errors = [];
        // profile photo
        if (!empty($_FILES['photo']['name'])) {
            $path = PhotoUpload::store($_FILES['photo'], 'salesmen', $id);
            if ($path) {
                $old = db_val('SELECT photo_path FROM salesmen WHERE id = ?', [$id]);
                db_exec('UPDATE salesmen SET photo_path = ? WHERE id = ?', [$path, $id]);
                self::safeUnlink($old);
            } else {
                $errors[] = PhotoUpload::lastError() ?: 'photo upload failed';
            }
        }
        // CNIC front/back
        foreach (['cnic_front' => 'cnic_front_path', 'cnic_back' => 'cnic_back_path'] as $field => $column) {
            if (!empty($_FILES[$field]['name'])) {
                $path = self::storeUpload($_FILES[$field], ['jpg' => true, 'png' => true, 'webp' => true, 'pdf' => true], 5 * 1024 * 1024, $id);
                if ($path) {
                    $old = db_val("SELECT $column FROM salesmen WHERE id = ?", [$id]);
                    db_exec("UPDATE salesmen SET $column = ? WHERE id = ?", [$path, $id]);
                    self::safeUnlink($old);
                }
            }
        }
        return $errors;
    }

    /* ============ Salesman profile — file serving (authenticated) ============ */

    public static function photoFile(): void {
        $id = intv(inp('id'));
        $path = $id ? db_val('SELECT photo_path FROM salesmen WHERE id = ?', [(int)$id]) : null;
        PhotoUpload::stream($path, 'salesman.jpg');
    }

    /* ============ Inline change-photo (from the salesman profile page) ============ */

    public static function photo(): void {
        if (!Auth::can('salesmen.edit')) { http_response_code(403); exit('Forbidden'); }
        require_post(); csrf_verify();
        $id = (int)inp('id');
        if ($id <= 0 || !db_val('SELECT 1 FROM salesmen WHERE id = ?', [$id])) { http_response_code(404); exit('Not found'); }
        if (empty($_FILES['photo']['name'])) {
            flash('error', __('Choose JPG, JPEG, PNG or WebP up to 2 MB.'));
            redirect('salesmen/' . $id . '/profile');
        }
        $path = PhotoUpload::store($_FILES['photo'], 'salesmen', $id);
        if (!$path) {
            error_log('salesman photo: ' . (PhotoUpload::lastError() ?: '?'));
            flash('error', __('Profile photo could not be saved. Use JPG, JPEG, PNG or WebP up to 2 MB.'));
        } else {
            $old = db_val('SELECT photo_path FROM salesmen WHERE id = ?', [$id]);
            db_exec('UPDATE salesmen SET photo_path = ? WHERE id = ?', [$path, $id]);
            self::safeUnlink($old);
            audit('update', 'salesmen', $id, 'Updated salesman photo');
            flash('success', __('Salesman updated.'));
        }
        redirect('salesmen/' . $id . '/profile');
    }

    /** Stream salesman images (photo / cnic front / cnic back) — column whitelist, permission-gated. */
    public static function salesmanFile(): void {
        $id = intv(inp('id'));
        $col = preg_replace('/[^a-z_]/', '', (string)inp('col'));
        $allowed = ['photo_path' => 'profile', 'cnic_front_path' => 'cnic-front', 'cnic_back_path' => 'cnic-back'];
        if (!$id || !isset($allowed[$col])) { http_response_code(404); exit('Not found'); }
        $path = db_val("SELECT $col FROM salesmen WHERE id = ?", [(int)$id]);
        self::streamFile($path, 'image/jpeg', $allowed[$col] . '.jpg');
    }

    public static function downloadDocument(): void {
        $id = intv(inp('id')); $doc = intv(inp('doc'));
        $row = ($id && $doc) ? db_one('SELECT * FROM salesman_documents WHERE id = ? AND salesman_id = ?', [(int)$doc, (int)$id]) : null;
        if (!$row) { http_response_code(404); exit('Not found'); }
        self::streamFile($row['file_path'], $row['mime'] ?: self::mimeFor($row['original_name']), $row['original_name']);
    }

    private static function streamFile(?string $rel, string $defaultMime, string $downloadName): void {
        if (!$rel) { http_response_code(404); exit('Not found'); }
        $path = dirname(__DIR__, 2) . '/storage/uploads/' . $rel;
        if (!is_file($path)) { http_response_code(404); exit('File not found'); }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = in_array($ext, ['jpg','jpeg','png','webp','gif','pdf'], true) ? self::mimeFor($rel) : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . str_replace(['"', '\\'], ['_', '_'], $downloadName) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public static function uploadDocument(): void {
        if (!Auth::can('salesmen.edit')) { http_response_code(403); exit('Forbidden'); }
        require_post(); csrf_verify();
        $id = (int)inp('id');
        if (!$id || !db_val('SELECT 1 FROM salesmen WHERE id = ?', [$id])) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        self::handleUploads($id);
        // any other documents in this post
        if (!empty($_FILES['documents']['name']) && is_array($_FILES['documents']['name'])) {
            $n = count($_FILES['documents']['name']);
            for ($i = 0; $i < $n; $i++) {
                $file = ['name' => $_FILES['documents']['name'][$i], 'tmp_name' => $_FILES['documents']['tmp_name'][$i], 'error' => $_FILES['documents']['error'][$i], 'size' => $_FILES['documents']['size'][$i]];
                $path = self::storeUpload($file, ['jpg' => true, 'png' => true, 'webp' => true, 'gif' => true, 'pdf' => true], 5 * 1024 * 1024, $id);
                if ($path) {
                    db_insert('salesman_documents', ['salesman_id' => $id, 'doc_type' => 'other', 'original_name' => basename((string)$file['name']), 'file_path' => $path, 'mime' => self::mimeFor($file['name']), 'size_bytes' => (int)$file['size'], 'note' => inp('documents_note') ?: null, 'uploaded_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local()]);
                }
            }
        }
        audit('update', 'salesmen', $id, 'Updated salesman documents');
        flash('success', __('Documents uploaded.'));
        redirect('salesmen/' . $id . '/profile');
    }

    public static function deleteDocument(): void {
        if (!Auth::can('salesmen.edit')) { http_response_code(403); exit('Forbidden'); }
        require_post(); csrf_verify();
        $id = (int)inp('id'); $doc = (int)inp('doc');
        $row = db_one('SELECT * FROM salesman_documents WHERE id = ? AND salesman_id = ?', [$doc, $id]);
        if ($row) {
            self::safeUnlink($row['file_path']);
            db_exec('DELETE FROM salesman_documents WHERE id = ?', [$doc]);
            audit('delete', 'salesman_documents', $doc, 'Deleted document ' . $row['original_name']);
            flash('success', __('Document deleted.'));
        }
        redirect('salesmen/' . $id . '/profile');
    }

    /* ============ Salesman profile page ============ */

    public static function profile(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { http_response_code(404); view('error/404', ['title' => 'Page not found']); return; }
        $province = $sm['province_id'] ? db_val('SELECT name FROM pk_provinces WHERE id = ?', [(int)$sm['province_id']]) : null;
        $city = $sm['city_id'] ? db_val('SELECT name FROM pk_cities WHERE id = ?', [(int)$sm['city_id']]) : null;
        $area = $sm['area_id'] ? db_val('SELECT name FROM areas WHERE id = ?', [(int)$sm['area_id']]) : ($sm['area'] ?: null);
        $assignedCustomers = (int)db_val('SELECT COUNT(*) FROM customers WHERE salesman_id = ? AND is_active = 1', [$id]);
        $documents = db_all('SELECT * FROM salesman_documents WHERE salesman_id = ? ORDER BY id DESC', [$id]);
        $expenseTotal = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_expenses WHERE salesman_id = ? AND status = 'posted'", [$id]);
        // summary tiles (same source of truth as the account page)
        $goods = db_one("SELECT COALESCE(SUM(sii.paid_qty),0) AS issued_paid, COALESCE(SUM(sii.bonus_qty),0) AS issued_bonus, COALESCE(SUM(sii.qty),0) AS issued_units, COALESCE(SUM(sii.line_payable),0) AS issued_value FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE i.salesman_id = ? AND i.status <> 'reversed' AND i.status <> 'pending_approval'", [$id]);
        $stockRow = db_one("SELECT COALESCE(SUM(qty),0) AS qty FROM stock_balances WHERE warehouse_id = ?", [(int)$sm['warehouse_id']]);
        $goodsPayable = (float)db_val("SELECT COALESCE(SUM(debit) - SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ?", [$id]);
        view('salesmen/profile', [
            'sm' => $sm, 'id' => (int)$sm['id'], 'province' => $province, 'city' => $city, 'area' => $area,
            'assignedCustomers' => $assignedCustomers, 'documents' => $documents,
            'goods' => $goods, 'stockRow' => $stockRow, 'goodsPayable' => $goodsPayable,
            'expenseTotal' => $expenseTotal,
            'title' => 'Salesman Profile — ' . $sm['name'],
        ]);
    }

    /* ============ Salesman statement (print, date-filtered) ============ */

    public static function statement(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { http_response_code(404); view('error/404', ['title' => 'Page not found']); return; }
        $from = inp('from', date('Y-m-01'));
        $to = inp('to', today());
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = today();

        $issues = db_all("SELECT i.date, i.issue_no, i.issue_type, i.status, SUM(sii.paid_qty) AS paid_qty, SUM(sii.bonus_qty) AS bonus_qty, SUM(sii.qty) AS qty, SUM(sii.line_payable) AS payable FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id WHERE i.salesman_id = ? AND i.status <> 'reversed' AND i.date BETWEEN ? AND ? GROUP BY i.id ORDER BY i.date, i.id", [$id, $from, $to]);
        $returns = db_all("SELECT i.date, i.issue_no, SUM(sii.qty) AS qty FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id WHERE i.salesman_id = ? AND i.issue_type = 'salesman_return' AND i.status <> 'reversed' AND i.date BETWEEN ? AND ? GROUP BY i.id ORDER BY i.date, i.id", [$id, $from, $to]);
        $settlements = db_all("SELECT date, sales_total, collected_total, cash_counted, variance FROM salesman_settlements WHERE salesman_id = ? AND date BETWEEN ? AND ? ORDER BY date", [$id, $from, $to]);
        $payments = db_all("SELECT date, doc_no, credit, notes FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'payment' AND date BETWEEN ? AND ? ORDER BY date", [$id, $from, $to]);
        $expenses = db_all("SELECT date, expense_type, amount, description FROM salesman_expenses WHERE salesman_id = ? AND status = 'posted' AND date BETWEEN ? AND ? ORDER BY date", [$id, $from, $to]);
        $salary = db_all("SELECT date, period, amount, status, method FROM salesman_salary_payments WHERE salesman_id = ? AND date BETWEEN ? AND ? ORDER BY date", [$id, $from, $to]);
        $advances = db_all("SELECT date, amount, reason, status, method FROM salesman_salary_advances WHERE salesman_id = ? AND date BETWEEN ? AND ? ORDER BY date", [$id, $from, $to]);
        $stockRow = db_one("SELECT COALESCE(SUM(qty),0) AS qty, COALESCE(SUM(qty * avg_cost),0) AS value FROM stock_balances WHERE warehouse_id = ?", [(int)$sm['warehouse_id']]);
        $goodsPayable = (float)db_val("SELECT COALESCE(SUM(debit) - SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ?", [$id]);
        // opening balance (sum of all debit-credit before 'from')
        $opening = (float)db_val("SELECT COALESCE(SUM(debit) - SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ? AND date < ?", [$id, $from]);
        view('salesmen/statement', [
            'sm' => $sm, 'from' => $from, 'to' => $to,
            'issues' => $issues, 'returns' => $returns, 'settlements' => $settlements,
            'payments' => $payments, 'expenses' => $expenses, 'salary' => $salary, 'advances' => $advances,
            'stockRow' => $stockRow, 'goodsPayable' => $goodsPayable, 'opening' => $opening,
            'title' => 'Salesman Statement — ' . $sm['name'],
        ]);
    }

    /** Issue goods to a salesman — transfer (stock only) or credit issue at issue rate (payable + optional bonus). */
    /** Live available stock for the Goods Issue form popup (JSON, gated to salesmen.settle). Never changes accounting. */
    public static function productStock(): void {
        header('Content-Type: application/json; charset=utf-8');
        $pid = intv(inp('product_id'));
        $wh = intv(inp('warehouse_id'));
        if (!$pid || !$wh) { echo json_encode(['ok' => false, 'error' => 'product_id and warehouse_id are required.']); return; }
        $row = db_one("SELECT p.sku, p.name, u.short AS unit_short,
                COALESCE((SELECT qty FROM stock_balances WHERE product_id = p.id AND warehouse_id = ?), 0) AS available
                FROM products p LEFT JOIN units u ON u.id = p.unit_id WHERE p.id = ?", [$wh, $pid]);
        $whName = (string)db_val('SELECT name FROM warehouses WHERE id = ?', [$wh]);
        if (!$row) { echo json_encode(['ok' => false, 'error' => 'Unknown product.']); return; }
        $elsewhere = db_all("SELECT w.code, w.name, b.qty FROM stock_balances b JOIN warehouses w ON w.id = b.warehouse_id WHERE b.product_id = ? AND b.warehouse_id <> ? AND b.qty > 0 ORDER BY b.qty DESC LIMIT 5", [$pid, $wh]);
        $elsewhere = array_map(fn($e) => ['code' => $e['code'], 'name' => $e['name'], 'qty' => round((float)$e['qty'], 3)], $elsewhere);
        echo json_encode(['ok' => true, 'available' => round((float)$row['available'], 3),
            'sku' => $row['sku'], 'name' => $row['name'], 'unit' => $row['unit_short'] ?: null, 'warehouse' => $whName,
            'elsewhere' => $elsewhere]);
    }

    /** Standalone Goods Returns picker — same UX as a one-tap entry into the per-salesman return form. */
    public static function returnsPicker(): void {
        if (!Auth::can('salesmen.view')) { http_response_code(403); exit('Forbidden'); }
        $salesmen = db_all("SELECT id, code, name, area, cnic, photo_path FROM salesmen WHERE is_active = 1 ORDER BY name");
        view('salesmen/returns_picker', ['salesmen' => $salesmen, 'title' => __('Goods Returns')]);
    }

    public static function issueForm(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        // Historical Goods Issue / Stock Received listing. Issuing is now done via New Sales Invoice;
        // this endpoint is read-only. Export runs BEFORE any output is buffered so CSV headers land first.
        $from = inp('from', date('Y-m-01')); if ($from === '') $from = date('Y-m-01'); $to = inp('to', today()); $pid = intv(inp('product_id'));
        $where = ["i.salesman_id = ?", "i.status <> 'reversed'", "i.issue_type <> 'salesman_return'", "i.date BETWEEN ? AND ?"];
        $params = [(int)$sm['id'], $from, $to];
        if ($pid > 0) { $where[] = 'sii.product_id = ?'; $params[] = $pid; }
        $rows = db_all("SELECT i.id, i.date, i.issue_no, i.issue_type, i.status, i.total_payable, sii.product_id, p.name AS product, p.sku, sii.paid_qty, sii.bonus_qty, sii.qty AS total_qty, sii.issue_rate, sii.line_payable
                FROM salesman_issues i
                JOIN salesman_issue_items sii ON sii.issue_id = i.id
                JOIN products p ON p.id = sii.product_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY i.date DESC, i.id DESC, sii.id", $params);
        if (($_GET['export'] ?? '') === 'csv') {
            $filename = 'goods_issued_' . $sm['code'] . '_' . date('Ymd') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Reference', 'Product', 'Regular (Packets)', 'Bonus (Packets)', 'Total (Packets)', 'Issue Rate', 'Payable']);
            foreach ($rows as $r) fputcsv($out, [$r['date'], $r['issue_no'], $r['sku'] . ' - ' . $r['product'], $r['paid_qty'], $r['bonus_qty'], $r['total_qty'], $r['issue_rate'], $r['line_payable']]);
            fclose($out);
            exit;
        }
        view('salesmen/issue', ['sm' => $sm, 'rows' => $rows, 'from' => $from, 'to' => $to, 'product_id' => $pid]);
    }

    /** Printable stock issue receipt with paid/bonus breakdown — authorized users only. */
    public static function issueView(): void {
        $id = intv(inp('id'));
        $issueId = intv(inp('issue_id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $issue = db_one("SELECT i.*, w.name AS warehouse, u.full_name AS issuer, u2.full_name AS approver FROM salesman_issues i JOIN warehouses w ON w.id = i.from_warehouse_id LEFT JOIN users u ON u.id = i.created_by LEFT JOIN users u2 ON u2.id = i.approved_by WHERE i.id = ? AND i.salesman_id = ?", [$issueId, $id]);
        if (!$issue) { flash('error', __('Issue not found.')); redirect('salesmen/' . $id . '/issue'); }
        $items = db_all("SELECT ii.*, p.sku, p.name, u.short AS unit_short FROM salesman_issue_items ii JOIN products p ON p.id = ii.product_id LEFT JOIN units u ON u.id = p.unit_id WHERE ii.issue_id = ? ORDER BY ii.id", [$issueId]);
        $totPaid = 0.0; $totBonus = 0.0; $totUnits = 0.0; $totPayable = 0.0; $totBonusCost = 0.0;
        foreach ($items as $it) { $totPaid += (float)$it['paid_qty']; $totBonus += (float)$it['bonus_qty']; $totUnits += (float)$it['qty']; $totPayable += (float)$it['line_payable']; $totBonusCost += (float)$it['bonus_cost']; }
        view('salesmen/issue_view', ['sm' => $sm, 'issue' => $issue, 'items' => $items,
            'totPaid' => $totPaid, 'totBonus' => $totBonus, 'totUnits' => $totUnits, 'totPayable' => $totPayable, 'totBonusCost' => $totBonusCost,
            'thermal' => inp('thermal') === '1',
            'title' => 'Issue Receipt ' . $issue['issue_no']]);
    }

    /** Salesman credit ledger - running balance of what the salesman owes the company. */
    public static function ledger(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $entries = db_all('SELECT * FROM salesman_ledger WHERE salesman_id = ? ORDER BY id', [$id]);
        $balance = (float)db_val('SELECT COALESCE(balance,0) FROM salesman_ledger WHERE salesman_id = ? ORDER BY id DESC LIMIT 1', [$id]);
        $issues = db_all("SELECT i.*, w.name AS warehouse FROM salesman_issues i JOIN warehouses w ON w.id = i.from_warehouse_id WHERE i.salesman_id = ? ORDER BY i.id DESC LIMIT 20", [$id]);
        view('salesmen/ledger', ['sm' => $sm, 'id' => $id, 'entries' => $entries, 'balance' => $balance, 'issues' => $issues, 'title' => 'Ledger — ' . $sm['name']]);
    }

    /** Salesman pays money to the company against his ledger balance (no approval needed — money in). */
    public static function payToCompany(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        $amount = round(num(inp('amount')), 2);
        $accountId = intv(inp('account_id'));
        if (!$sm || $amount <= 0 || !$accountId) { flash('error', __('Amount and account required.')); redirect('salesmen/' . $id . '/ledger'); }
        $balance = (float)db_val('SELECT COALESCE(balance,0) FROM salesman_ledger WHERE salesman_id = ? ORDER BY id DESC LIMIT 1', [$id]);
        if ($amount > $balance + 0.009) { flash('error', __('Amount exceeds outstanding balance (') . money($balance) . ').'); redirect('salesmen/' . $id . '/ledger'); }
        $gl = Posting::accountGl($accountId);
        Database::transaction(function () use ($sm, $amount, $accountId, $id, $gl, $balance) {
            $journalId = Accounting::postEntry([
                ['account' => $gl, 'debit' => $amount, 'memo' => 'Salesman repayment — ' . $sm['name']],
                ['account' => 'SM_RECEIVABLE', 'credit' => $amount, 'memo' => 'Salesman ledger payment'],
            ], [
                'date' => today(), 'voucher_type' => 'SR', 'reference_type' => 'salesman_repayment',
                'reference_id' => $id, 'memo' => 'Salesman repayment — ' . $sm['name'],
                'idem_key' => 'salesman_repay:' . $id . ':' . bin2hex(random_bytes(5)),
            ]);
            db_insert('salesman_ledger', ['salesman_id' => $id, 'date' => today(), 'doc_type' => 'payment', 'doc_id' => null, 'doc_no' => db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]), 'debit' => 0, 'credit' => $amount, 'balance' => round($balance - $amount, 2), 'notes' => inp('notes') ?: 'Cash/bank deposit', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
            audit('payment', 'salesman_ledger', $id, "Salesman paid Rs $amount to company");
            try { Notifications::notifySalesmanPayment($id, $amount, db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]) ?: 'SR-' . $journalId); } catch (Throwable $e) { error_log('notify salesman payment: ' . $e->getMessage()); }
        });
        flash('success', __('Payment received and posted — Dr Cash/Bank, Cr Salesman Receivable; ledger updated.'));
        redirect('salesmen/' . $id . '/ledger');
    }

    public static function settle(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $date = inp('date', today());
        $monthStart = date('Y-m-01', strtotime($date));
        $myCustomers = db_all('SELECT id FROM customers WHERE salesman_id = ?', [$id]);
        $custIds = array_column($myCustomers, 'id');
        $salesToday = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE salesman_id = ? AND status='posted' AND date = ?", [$id, $date]);
        $salesMonth = (float)db_val("SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE salesman_id = ? AND status='posted' AND date >= ?", [$id, $monthStart]);
        $cashCollected = 0.0;
        if ($custIds) {
            $in = implode(',', array_fill(0, count($custIds), '?'));
            $cashCollected = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM receipts WHERE customer_id IN ($in) AND date = ? AND EXISTS (SELECT 1 FROM cash_bank_accounts a WHERE a.id = receipts.account_id AND a.type = 'cash')", array_merge($custIds, [$date]));
        }
        $stock = $sm['warehouse_id'] ? self::salesStock((int)$sm['warehouse_id']) : [];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $counted = round(num(inp('cash_counted')), 2);
            Database::transaction(function () use ($id, $date, $salesToday, $cashCollected, $counted) {
                $existing = db_one('SELECT id FROM salesman_settlements WHERE salesman_id = ? AND date = ?', [$id, $date]);
                $data = ['sales_total' => $salesToday, 'collected_total' => $cashCollected, 'cash_counted' => $counted,
                         'expected_cash' => $cashCollected, 'variance' => round($counted - $cashCollected, 2),
                         'notes' => inp('notes'), 'created_by' => $_SESSION['user_id'] ?? null];
                if ($existing) db_exec('UPDATE salesman_settlements SET sales_total=?, collected_total=?, cash_counted=?, expected_cash=?, variance=?, notes=? WHERE id = ?', array_merge(array_values($data), [$existing['id']]));
                else db_insert('salesman_settlements', $data + ['salesman_id' => $id, 'date' => $date, 'created_at' => now_local()]);
            });
            audit('settle', 'salesman_settlements', $id, "Settled $date");
            flash('success', __('Settlement saved. Variance = counted − expected cash.'));
            redirect('salesmen/' . $id . '/settle', ['date' => $date]);
        }
        $settlements = db_all('SELECT * FROM salesman_settlements WHERE salesman_id = ? ORDER BY date DESC LIMIT 15', [$id]);
        $issues = db_all("SELECT i.*, (SELECT COALESCE(SUM(paid_qty),0) FROM salesman_issue_items WHERE issue_id = i.id) AS paid_qty, (SELECT COALESCE(SUM(bonus_qty),0) FROM salesman_issue_items WHERE issue_id = i.id) AS bonus_qty FROM salesman_issues i WHERE i.salesman_id = ? ORDER BY i.date DESC, i.id DESC LIMIT 12", [$id]);
        view('salesmen/settle', ['sm' => $sm, 'date' => $date, 'salesToday' => $salesToday, 'salesMonth' => $salesMonth, 'cashCollected' => $cashCollected, 'stock' => $stock, 'settlements' => $settlements, 'issues' => $issues, 'title' => 'Settle - ' . $sm['name']]);
    }

    public static function bonuses(): void {
        $rows = db_all("SELECT b.*, s.name AS salesman FROM bonuses b JOIN salesmen s ON s.id = b.salesman_id ORDER BY b.id DESC LIMIT 100");
        view('salesmen/bonuses', ['rows' => $rows, 'title' => 'Commission & Bonuses']);
    }

    public static function bonusNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $smId = intv(inp('salesman_id')); $amount = round(num(inp('amount')), 2);
            if (!$smId || $amount <= 0) { flash('error', __('Salesman and amount are required.')); redirect('salesmen/bonuses/new'); }
            db_insert('bonuses', ['bonus_no' => 'BON-' . date('YmdHis'), 'salesman_id' => $smId, 'period_month' => inp('period_month', date('Y-m')), 'amount' => $amount, 'reason' => inp('reason'), 'status' => 'pending', 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local()]);
            audit('create', 'bonuses', null, "Bonus Rs $amount for salesman $smId");
            flash('success', __('Bonus recorded (pending). Pay it via Expenses → category “Sales Commission” to post the journal.'));
            redirect('salesmen/bonuses');
        }
        $isSqlite = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $monthExpr = $isSqlite ? "strftime('%Y-%m', i.date) = ?" : "DATE_FORMAT(i.date, '%Y-%m') = ?";
        $commissions = db_all("SELECT s.id, s.name, s.commission_percent, COALESCE(SUM(i.total),0) AS month_sales, ROUND(COALESCE(SUM(i.total),0) * s.commission_percent / 100, 2) AS commission
            FROM salesmen s LEFT JOIN sales_invoices i ON i.salesman_id = s.id AND i.status='posted' AND $monthExpr
            WHERE s.is_active = 1 GROUP BY s.id ORDER BY s.name", [date('Y-m')]);
        view('salesmen/bonus_new', ['salesmen' => db_all('SELECT id, name FROM salesmen WHERE is_active = 1 ORDER BY name'), 'commissions' => $commissions, 'title' => 'New Bonus / Commission']);
    }

    /** Salesman returns unsold goods to the warehouse (from van stock). */
    /** Bonus goods tab: free units given to the salesman; reduces stock but NOT payable. */
    public static function bonusFor(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { http_response_code(404); view('error/404', ['title' => 'Page not found']); return; }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $fromWh = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1") ?: (int)db_val('SELECT id FROM warehouses WHERE is_default = 1 LIMIT 1');
            $productId = intv(inp('product_id'));
            $qty = round(num(inp('bonus_qty')), 3);
            $reason = mb_substr(trim(inp('reason')), 0, 255);
            $schemeId = intv(inp('scheme_id')) ?: null;
            $date = inp('date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : today();
            if (!$productId || $qty <= 0) { flash('error', __('Product and bonus quantity are required.')); redirect('salesmen/' . $id . '/bonus'); }
            if (!$fromWh) { flash('error', __('No source warehouse (WH-MAIN) found.')); redirect('salesmen/' . $id . '/bonus'); }
            $kind = db_val('SELECT kind FROM products WHERE id = ?', [$productId]);
            if ($kind !== 'finished') { flash('error', __('Only finished products can be given as bonus.')); redirect('salesmen/' . $id . '/bonus'); }
            $scheme = $schemeId ? db_one('SELECT * FROM bonus_schemes WHERE id = ?', [$schemeId]) : null;
            $bonusCost = round($qty * Inventory::avgCost($productId, $fromWh), 2);
            try {
                Database::transaction(function () use ($sm, $productId, $qty, $reason, $scheme, $date, $fromWh, $bonusCost) {
                    $issueNo = 'BON-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
                    $issueId = db_insert('salesman_issues', [
                        'issue_no' => $issueNo, 'salesman_id' => (int)$sm['id'], 'from_warehouse_id' => $fromWh,
                        'date' => $date, 'status' => 'posted', 'issue_type' => 'credit_issue',
                        'total_payable' => 0.0, 'total_bonus_cost' => $bonusCost,
                        'bonus_scheme' => $scheme ? $scheme['name'] : 'manual',
                        'bonus_reason' => $reason ?: 'Bonus goods',
                        'notes' => $reason, 'created_by' => (int)($_SESSION['user_id'] ?? 0), 'created_at' => now_local(),
                    ]);
                    db_insert('salesman_issue_items', ['issue_id' => $issueId, 'product_id' => $productId, 'paid_qty' => 0, 'bonus_qty' => $qty, 'qty' => $qty, 'unit_cost' => Inventory::avgCost($productId, $fromWh), 'issue_rate' => 0, 'line_payable' => 0, 'bonus_cost' => $bonusCost]);
                    Posting::postSalesmanIssue($issueId);
                    $bonusIssueId = $issueId;
                });
                flash('success', __('Bonus goods posted — stock moved, payable unchanged (free goods).'));
                redirect('salesmen/' . $id . '/bonus');
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('salesmen/' . $id . '/bonus'); }
        }

        $bonusLines = db_all("SELECT sii.id AS line_id, sii.issue_id, i.date, i.issue_no, i.bonus_reason, i.bonus_scheme, p.sku, p.name, sii.bonus_qty AS qty, sii.bonus_cost, i.issue_type
            FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id JOIN products p ON p.id = sii.product_id
            WHERE i.salesman_id = ? AND sii.bonus_qty > 0 AND i.issue_type = 'credit_issue' AND i.status <> 'reversed'
            ORDER BY i.date DESC, i.id DESC LIMIT 100", [$id]);
        $totalBonusUnits = (float)db_val("SELECT COALESCE(SUM(sii.bonus_qty),0) FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE i.salesman_id = ? AND sii.bonus_qty > 0 AND i.status <> 'reversed' AND i.issue_type = 'credit_issue'", [$id]);
        $totalBonusCost = (float)db_val("SELECT COALESCE(SUM(sii.bonus_cost),0) FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE i.salesman_id = ? AND sii.bonus_qty > 0 AND i.status <> 'reversed' AND i.issue_type = 'credit_issue'", [$id]);
        view('salesmen/bonus', ['sm' => $sm, 'bonusLines' => $bonusLines, 'totalBonusUnits' => $totalBonusUnits, 'totalBonusCost' => $totalBonusCost,
            'productsArr' => StockController::productOptions(['finished']),
            'schemes' => db_all("SELECT * FROM bonus_schemes WHERE is_active = 1 AND kind IN ('salesman','special','promotion') ORDER BY name"),
            'title' => 'Bonus — ' . $sm['name']]);
    }

    public static function salesmanReturn(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $productIds = $_POST['items']['product_id'] ?? [];
            $paidQty = $_POST['items']['paid_qty'] ?? [];
            $bonusQty = $_POST['items']['bonus_qty'] ?? [];
            $issueRates = $_POST['items']['issue_rate'] ?? [];
            $conditions = $_POST['items']['return_condition'] ?? [];
            $items = [];
            foreach ($productIds as $i => $pid) {
                $pid = (int)$pid; $paid = (float)($paidQty[$i] ?? 0); $bonus = (float)($bonusQty[$i] ?? 0);
                $cond = in_array($conditions[$i] ?? 'good', ['good', 'damaged'], true) ? $conditions[$i] : 'good';
                if ($pid && ($paid + $bonus) > 0) {
                    $items[] = ['product_id' => $pid, 'paid_qty' => $paid, 'bonus_qty' => $bonus, 'qty' => $paid + $bonus, 'issue_rate' => round((float)($issueRates[$i] ?? 0), 2), 'unit_cost' => 0, 'return_condition' => $cond];
                }
            }
            if (!$items) { flash('error', __('Quantities to return required.')); redirect('salesmen/' . $id . '/return'); }
            try {
                Database::transaction(function () use ($sm, $items, $id) {
                    $srcWh = (int)$sm['warehouse_id'];
                    $returnId = db_insert('salesman_issues', [
                        'issue_no' => 'SRT-' . date('YmdHis'), 'salesman_id' => $id, 'from_warehouse_id' => $srcWh,
                        'date' => inp('date', today()), 'status' => 'posted', 'issue_type' => 'salesman_return',
                        'notes' => inp('notes'), 'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                    ]);
                    foreach ($items as $it) {
                        $cost = Inventory::avgCost($it['product_id'], $srcWh);
                        db_insert('salesman_issue_items', ['issue_id' => $returnId, 'product_id' => $it['product_id'], 'paid_qty' => $it['paid_qty'], 'bonus_qty' => $it['bonus_qty'], 'qty' => $it['qty'], 'unit_cost' => $cost, 'issue_rate' => $it['issue_rate'], 'line_payable' => round($it['paid_qty'] * $it['issue_rate'], 2), 'bonus_cost' => 0, 'return_condition' => $it['return_condition']]);
                    }
                    Posting::postSalesmanReturn($returnId);
                });
                flash('success', __('Salesman return posted — stock restocked, his ledger credited.'));
                redirect('salesmen/' . $id . '/ledger');
            } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('salesmen/' . $id . '/return'); }
        }
        // available stock with the salesman (per product) + last issue rates
        $stock = db_all("SELECT p.id, p.sku, p.name, p.issue_rate, b.qty, b.avg_cost FROM stock_balances b JOIN products p ON p.id = b.product_id WHERE b.warehouse_id = ? AND b.qty > 0 ORDER BY p.name", [(int)$sm['warehouse_id']]);
        // return summary + history
        $retSummary = db_one("SELECT COALESCE(SUM(CASE WHEN sii.return_condition = 'damaged' THEN 0 ELSE sii.qty END),0) AS good_qty,
            COALESCE(SUM(CASE WHEN sii.return_condition = 'damaged' THEN sii.qty ELSE 0 END),0) AS damaged_qty,
            COALESCE(SUM(sii.qty),0) AS total_qty,
            COALESCE(SUM(CASE WHEN sii.return_condition = 'damaged' THEN 0 ELSE sii.line_payable END),0) AS good_credit,
            COALESCE(SUM(sii.qty * sii.unit_cost),0) AS good_cost
            FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id
            WHERE i.salesman_id = ? AND i.issue_type = 'salesman_return' AND i.status <> 'reversed'", [$id]);
        $paidOnReturns = (float)db_val("SELECT COALESCE(SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'payment'", [$id]);
        $goodsPayableBalance = (float)db_val("SELECT COALESCE(SUM(debit) - SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ?", [$id]);
        $history = db_all("SELECT i.id, i.date, i.issue_no,
            SUM(sii.qty) AS total_qty,
            SUM(CASE WHEN sii.return_condition = 'damaged' THEN 0 ELSE sii.qty END) AS good_qty,
            SUM(CASE WHEN sii.return_condition = 'damaged' THEN sii.qty ELSE 0 END) AS damaged_qty,
            SUM(CASE WHEN sii.return_condition = 'damaged' THEN 0 ELSE sii.line_payable END) AS credit_amount
            FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id
            WHERE i.salesman_id = ? AND i.issue_type = 'salesman_return' AND i.status <> 'reversed'
            GROUP BY i.id ORDER BY i.date DESC, i.id DESC LIMIT 50", [$id]);
        view('salesmen/return', ['sm' => $sm, 'stock' => $stock, 'retSummary' => $retSummary, 'paidOnReturns' => $paidOnReturns, 'goodsPayableBalance' => $goodsPayableBalance, 'history' => $history, 'title' => 'Return Stock — ' . $sm['name']]);
    }

    public static function salesStock(int $warehouseId): array {
        return db_all("SELECT p.sku, p.name, COALESCE(b.qty,0) AS qty, COALESCE(b.avg_cost,0) AS avg_cost, ROUND(COALESCE(b.qty,0) * COALESCE(b.avg_cost,0), 2) AS value
            FROM products p LEFT JOIN stock_balances b ON b.product_id = p.id AND b.warehouse_id = ? WHERE b.qty > 0 ORDER BY p.name", [$warehouseId]);
    }

    /** One simple salesman page: Goods Issued → Money Collection → Goods Return.
     *  All posting goes through the existing recovery payment and stock-return engines. */
    public static function account(): void {
        $id = intv(inp('id'));
        $switchId = intv(inp('switch_id'));
        if ($switchId > 0 && $switchId !== $id) {
            redirect('salesmen/' . $switchId . '/account');
        }
        $sm = $id ? db_one('SELECT * FROM salesmen WHERE id = ?', [$id]) : null;
        if (!$sm) { http_response_code(404); view('error/404', ['title' => 'Page not found']); return; }
        $back = 'salesmen/' . $id . '/account';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Auth::can('salesmen.settle')) { http_response_code(403); exit('Forbidden'); }
            require_post(); csrf_verify();
            if (inp('recovery_action') === 'return') self::recordRecoveryReturn($sm, $back);
            elseif (inp('recovery_action') === 'issue') self::recordGoodsIssue($sm, $back);
            else self::recordPayment($sm, $back);
            return;
        }

        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)inp('from')) ? (string)inp('from') : '';
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)inp('to')) ? (string)inp('to') : '';
        $data = self::recoveryData($id);

        /* Section 1 — one row per product per goods invoice. */
        $issuedLines = []; $productIds = []; $totalIssuedQty = 0.0;
        foreach ($data['invoices'] as $inv) {
            foreach ($inv['items'] as $it) {
                $regular = round((float)$it['paid_qty'], 3);
                $bonus = round((float)$it['bonus_qty'], 3);
                $lineQty = isset($it['qty']) ? round((float)$it['qty'], 3) : round($regular + $bonus, 3);
                $productIds[(int)$it['product_id']] = true;
                $totalIssuedQty = round($totalIssuedQty + $lineQty, 3);
                $line = $it + [
                    'date' => (string)$inv['date'], 'issue_no' => (string)$inv['issue_no'],
                    'regular_qty' => $regular, 'bonus_qty' => $bonus, 'line_qty' => $lineQty,
                    'rate' => round((float)$it['issue_rate'], 2), 'payable' => round((float)$it['line_payable'], 2),
                ];
                if ((!$from || $line['date'] >= $from) && (!$to || $line['date'] <= $to)) $issuedLines[] = $line;
            }
        }
        $issuedLines = array_values($issuedLines);

        /* Section 2 — group the existing recovery history into one row per money collection. */
        $events = self::recoveryHistory($id, ['from' => '', 'to' => '', 'invoice_id' => 0, 'method' => 'all']);
        $collections = self::accountCollections($events['rows']);
        if ($from || $to) {
            $collections = array_values(array_filter($collections, function ($c) use ($from, $to) {
                return (!$from || $c['date'] >= $from) && (!$to || $c['date'] <= $to);
            }));
        }

        /* Section 3 — goods returns, with the original rate and accepted/damaged status. */
        $returnArgs = [$id]; $returnDateSql = '';
        if ($from) { $returnDateSql .= ' AND i.date >= ?'; $returnArgs[] = $from; }
        if ($to)   { $returnDateSql .= ' AND i.date <= ?'; $returnArgs[] = $to; }
        $returnRows = db_all("SELECT i.id, i.date, i.issue_no, i.notes, i.related_issue_id,
                    si.issue_no AS invoice_no, p.sku, p.name,
                    sii.qty, sii.issue_rate, sii.line_payable, COALESCE(sii.return_condition, 'good') AS return_condition
                FROM salesman_issues i
                JOIN salesman_issue_items sii ON sii.issue_id = i.id
                LEFT JOIN products p ON p.id = sii.product_id
                LEFT JOIN salesman_issues si ON si.id = i.related_issue_id
                WHERE i.salesman_id = ? AND i.issue_type = 'salesman_return' AND i.status <> 'reversed'" . $returnDateSql . "
                ORDER BY i.date DESC, i.id DESC, sii.id", $returnArgs);

        $vanStock = db_all("SELECT p.id, p.sku, p.name, p.issue_rate, COALESCE(b.qty,0) AS qty
            FROM products p LEFT JOIN stock_balances b ON b.product_id = p.id AND b.warehouse_id = ?
            WHERE p.kind = 'finished' AND p.is_active = 1 AND COALESCE(b.qty,0) > 0 ORDER BY p.name", [(int)($sm['warehouse_id'] ?? 0)]);
        $rateMap = [];
        foreach ($data['invoices'] as $inv) {
            foreach ($inv['items'] as $it) {
                $rate = (float)$it['issue_rate'];
                if ($rate <= 0 && (float)$it['paid_qty'] > 0) $rate = round((float)$it['line_payable'] / (float)$it['paid_qty'], 2);
                $rateMap[(int)$inv['id']][(int)$it['product_id']] = round($rate, 2);
            }
        }

        $salesmen = db_all("SELECT id, code, name, area, phone FROM salesmen
            WHERE is_active = 1 AND COALESCE(is_archived,0) = 0 ORDER BY name, code");
        if (!array_filter($salesmen, fn($s) => (int)$s['id'] === $id)) {
            $salesmen[] = ['id' => $id, 'code' => $sm['code'], 'name' => $sm['name'], 'area' => $sm['area'], 'phone' => $sm['phone']];
        }

        $fromWh = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1") ?: (int)db_val('SELECT id FROM warehouses WHERE is_default = 1 LIMIT 1');
        $mainProducts = db_all("SELECT p.id, p.sku, p.name, p.issue_rate, COALESCE(b.qty,0) AS available_qty
            FROM products p
            LEFT JOIN stock_balances b ON b.product_id = p.id AND b.warehouse_id = ?
            WHERE p.kind = 'finished' AND p.is_active = 1
            ORDER BY p.name", [$fromWh]);

        view('salesmen/account', $data + [
            'sm' => $sm, 'id' => $id, 'tab' => 'account',
            'filters' => ['from' => $from, 'to' => $to],
            'salesmen' => $salesmen,
            'issuedLines' => $issuedLines, 'productsIssued' => count($productIds), 'totalIssuedQty' => $totalIssuedQty,
            'collections' => $collections, 'returnRows' => $returnRows,
            'vanStock' => $vanStock, 'rateMap' => $rateMap,
            'mainProducts' => $mainProducts,
            'cashAccounts' => db_all("SELECT id, name FROM cash_bank_accounts WHERE is_active = 1 AND type = 'cash' ORDER BY name"),
            'bankAccounts' => db_all("SELECT id, name FROM cash_bank_accounts WHERE is_active = 1 AND type = 'bank' ORDER BY name"),
            'canRecord' => Auth::can('salesmen.settle'),
            'currentUserName' => trim((string)((Auth::user()['full_name'] ?? null) ?: ($_SESSION['username'] ?? ''))),
            'idemKey' => 'ACC-' . bin2hex(random_bytes(12)),
            'title' => 'Salesman Account — ' . $sm['name'],
        ]);
    }

    /** Collapse allocation-split recovery events into one row per physical money collection. */
    private static function accountCollections(array $events): array {
        $byKey = [];
        foreach ($events as $e) {
            if (($e['type'] ?? '') !== 'payment') continue;
            $key = !empty($e['payment_id']) ? 'p' . (int)$e['payment_id'] : 'l' . (int)($e['source_id'] ?? 0);
            if (!isset($byKey[$key])) {
                $byKey[$key] = [
                    'key' => $key, 'date' => (string)$e['date'], 'doc_no' => (string)($e['doc_no'] ?? ''),
                    'method' => (string)($e['method'] ?? ''), 'reference' => (string)($e['reference'] ?? ''),
                    'notes' => (string)($e['notes'] ?? ''), 'cash' => 0.0, 'bank' => 0.0, 'balance' => 0.0,
                ];
            }
            $byKey[$key]['cash'] = round($byKey[$key]['cash'] + (float)($e['cash'] ?? 0), 2);
            $byKey[$key]['bank'] = round($byKey[$key]['bank'] + (float)($e['bank'] ?? 0), 2);
            $byKey[$key]['balance'] = round((float)($e['balance'] ?? 0), 2);
        }
        $rows = array_values($byKey);
        usort($rows, function ($a, $b) {
            $c = strcmp($a['date'], $b['date']);
            return $c !== 0 ? $c : strcmp($a['key'], $b['key']);
        });
        foreach ($rows as &$r) $r['total'] = round($r['cash'] + $r['bank'], 2);
        unset($r);
        return $rows;
    }

    /** Salary pay + advances for one salesman. Owner/Admin post directly; others via approval. */
    public static function salary(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $isAdmin = is_owner_or_admin();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $action = inp('action', 'pay');
            $accountId = intv(inp('account_id'));
            $amount = round(num(inp('amount')), 2);
            $notes = inp('notes');
            $method = in_array(inp('method'), ['cash', 'bank'], true) ? inp('method') : 'cash';
            $period = inp('period') ?: date('Y-m');
            if (!$accountId || $amount <= 0) { flash('error', __('Amount and payment account are required.')); redirect('salesmen/' . $id . '/salary'); }
            try {
                Database::transaction(function () use ($id, $amount, $accountId, $method, $notes, $action, $isAdmin, $period) {
                    if ($action === 'advance') {
                        $newId = db_insert('salesman_salary_advances', [
                            'salesman_id' => $id, 'date' => today(), 'amount' => $amount,
                            'reason' => $notes ?: 'Advance', 'account_id' => $accountId, 'method' => $method,
                            'status' => 'pending', 'requested_by' => (int)($_SESSION['user_id'] ?? 0), 'created_at' => now_local(),
                        ]);
                        if ($isAdmin) {
                            // Post the advance inline as the owner
                            db_exec("UPDATE salesman_salary_advances SET status = 'approved' WHERE id = ?", [$newId]);
                            Posting::postSalesmanAdvance((int)$newId);
                        } else {
                            Approvals::create('advance', 'salesman_salary_advances', (int)$newId, $amount, 'Advance for ' . db_val('SELECT name FROM salesmen WHERE id = ?', [$id]), (int)($_SESSION['user_id'] ?? 0));
                        }
                        return;
                    }
                    $newId = db_insert('salesman_salary_payments', [
                        'salesman_id' => $id, 'date' => today(), 'amount' => $amount,
                        'account_id' => $accountId, 'method' => $method, 'period' => $period,
                        'notes' => $notes ?: null,
                        'status' => 'pending', 'requested_by' => (int)($_SESSION['user_id'] ?? 0), 'created_at' => now_local(),
                    ]);
                    if ($isAdmin) {
                        Posting::postSalesmanSalary((int)$newId);
                    } else {
                        Approvals::create('payroll', 'salesman_salary_payments', (int)$newId, $amount, 'Salesman salary — ' . $period, (int)($_SESSION['user_id'] ?? 0));
                    }
                });
                flash('success', $action === 'advance' ? __('Advance processed.') : __('Salary payment processed.'));
            } catch (Throwable $e) { flash('error', __('Payment failed: ') . $e->getMessage()); }
            redirect('salesmen/' . $id . '/salary');
        }

        $monthly = (float)$sm['salary'];
        $accrued = 0.0;
        if (!empty($sm['join_date']) && $monthly > 0) {
            $join = new DateTimeImmutable($sm['join_date']);
            $now = new DateTimeImmutable('first day of this month');
            $months = max(0, ((int)$now->format('Y') - (int)$join->format('Y')) * 12 + ((int)$now->format('n') - (int)$join->format('n')) + 1);
            $accrued = $months * $monthly;
        }
        $paid = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_salary_payments WHERE salesman_id = ? AND status = 'paid'", [$id]);
        $remaining = max(0, $accrued - $paid);
        $advancesOut = (float)db_val("SELECT COALESCE(SUM(amount - deducted),0) FROM salesman_salary_advances WHERE salesman_id = ? AND status = 'paid' AND amount > deducted", [$id]);
        $salaryPayments = db_all("SELECT * FROM salesman_salary_payments WHERE salesman_id = ? ORDER BY date DESC, id DESC LIMIT 20", [$id]);
        $advances = db_all("SELECT * FROM salesman_salary_advances WHERE salesman_id = ? ORDER BY date DESC, id DESC LIMIT 20", [$id]);
        view('salesmen/salary', ['sm' => $sm, 'monthly' => $monthly, 'accrued' => $accrued, 'paid' => $paid, 'remaining' => $remaining, 'advancesOut' => $advancesOut, 'util' => max(0, $remaining - $advancesOut), 'salaryPayments' => $salaryPayments, 'advances' => $advances, 'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'title' => 'Salary — ' . $sm['name']]);
    }

    /** Salesman salary advances tab — request/give advance, track outstanding balance, view vouchers. */
    public static function advances(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $isAdmin = is_owner_or_admin();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $accountId = intv(inp('account_id'));
            $amount = round(num(inp('amount')), 2);
            $notes = trim(inp('notes'));
            $method = in_array(inp('method'), ['cash', 'bank'], true) ? inp('method') : 'cash';
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('date')) ? inp('date') : today();
            if (!$accountId || $amount <= 0) {
                flash('error', __('Amount and payment account are required.'));
                redirect('salesmen/' . $id . '/advances');
            }
            try {
                Database::transaction(function () use ($id, $amount, $accountId, $method, $notes, $date, $isAdmin) {
                    $newId = db_insert('salesman_salary_advances', [
                        'salesman_id' => $id,
                        'date' => $date,
                        'amount' => $amount,
                        'reason' => $notes ?: 'Advance',
                        'account_id' => $accountId,
                        'method' => $method,
                        'status' => 'pending',
                        'requested_by' => (int)($_SESSION['user_id'] ?? 0),
                        'created_at' => now_local(),
                    ]);
                    if ($isAdmin) {
                        db_exec("UPDATE salesman_salary_advances SET status = 'approved' WHERE id = ?", [$newId]);
                        Posting::postSalesmanAdvance((int)$newId);
                    } else {
                        Approvals::create('advance', 'salesman_salary_advances', (int)$newId, $amount, 'Advance for ' . db_val('SELECT name FROM salesmen WHERE id = ?', [$id]), (int)($_SESSION['user_id'] ?? 0));
                    }
                });
                flash('success', $isAdmin ? __('Advance disbursed and posted successfully.') : __('Advance request submitted for approval.'));
            } catch (Throwable $e) {
                flash('error', __('Failed to process advance: ') . $e->getMessage());
            }
            redirect('salesmen/' . $id . '/advances');
        }

        $p = ['id' => $id, 'person_type' => 'salesman'];
        $stats = AdvancesController::advanceStats($p);
        $monthly = (float)($sm['salary'] ?? 0);
        $accounts = db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name");
        $advances = db_all("SELECT a.*, cba.name AS account_name
                            FROM salesman_salary_advances a
                            LEFT JOIN cash_bank_accounts cba ON cba.id = a.account_id
                            WHERE a.salesman_id = ?
                            ORDER BY a.date DESC, a.id DESC", [$id]);

        view('salesmen/advances', [
            'sm' => $sm,
            'id' => $id,
            'stats' => $stats,
            'monthly' => $monthly,
            'accounts' => $accounts,
            'advances' => $advances,
            'title' => __('Advances') . ' — ' . $sm['name'],
        ]);
    }

    /** Add an expense to a salesman account (diesel|vehicle|food|hotel|repair|other). */
    public static function expense(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $isAdmin = is_owner_or_admin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $accountId = intv(inp('account_id')) ?: null;
            $amount = round(num(inp('amount')), 2);
            $type = in_array(inp('expense_type'), ['vehicle','food','other'], true) ? inp('expense_type') : 'other';
            $desc = inp('description');
            $notes = inp('notes');
            $paymentType = in_array(inp('payment_type'), ['cash','bank'], true) ? inp('payment_type') : 'cash';
            $paidFrom = in_array(inp('paid_from'), ['company', 'collection'], true) ? inp('paid_from') : 'company';
            $expenseDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('date')) ? inp('date') : today();
            if ($paidFrom === 'collection') $accountId = null;
            if ($paidFrom === 'company' && !$accountId) { flash('error', __('Choose the company account that paid.')); redirect('salesmen/' . $id . '/expense'); }
            if ($amount <= 0 || $desc === '') { flash('error', __('Description and amount are required.')); redirect('salesmen/' . $id . '/expense'); }
            try {
                // attach receipt upload if provided
                $attachment = null;
                if (!empty($_FILES['receipt']['tmp_name']) && is_uploaded_file($_FILES['receipt']['tmp_name'])) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($_FILES['receipt']['tmp_name']);
                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];
                    if (isset($allowed[$mime]) && $_FILES['receipt']['size'] <= 5 * 1024 * 1024) {
                        $dir = dirname(__DIR__, 2) . '/storage/uploads/salesmen/' . $id;
                        if (!is_dir($dir)) mkdir($dir, 0750, true);
                        $attachment = 'uploads/salesmen/' . $id . '/receipt-' . time() . '-' . random_int(1000, 9999) . '.' . $allowed[$mime];
                        move_uploaded_file($_FILES['receipt']['tmp_name'], dirname(__DIR__, 2) . '/storage/' . $attachment);
                    }
                }
                Database::transaction(function () use ($id, $amount, $type, $desc, $paymentType, $accountId, $notes, $attachment, $isAdmin, $paidFrom, $expenseDate) {
                    $newId = db_insert('salesman_expenses', [
                        'salesman_id' => $id, 'date' => $expenseDate, 'expense_type' => $type,
                        'amount' => $amount, 'description' => $desc, 'payment_type' => $paymentType,
                        'account_id' => $accountId, 'paid_from' => $paidFrom, 'attachment' => $attachment, 'notes' => $notes ?: null,
                        'status' => $isAdmin ? 'approved' : 'pending', 'requested_by' => (int)($_SESSION['user_id'] ?? 0),
                        'created_at' => now_local(),
                    ]);
                    if ($isAdmin) {
                        Posting::postSalesmanExpense((int)$newId);
                        try { Notifications::notifySalesmanExpense((int)$newId); } catch (Throwable $e) { error_log('notify salesman expense: ' . $e->getMessage()); }
                    } else {
                        Approvals::create('expense', 'salesman_expenses', (int)$newId, $amount, strtoupper($type) . ' expense — ' . ($desc ?: ''), (int)($_SESSION['user_id'] ?? 0));
                    }
                });
                flash('success', $isAdmin ? __('Expense recorded.') : __('Expense submitted for approval.'));
            } catch (Throwable $e) { flash('error', __('Failed: ') . $e->getMessage()); }
            redirect('salesmen/' . $id . '/expense');
        }
        $expenses = db_all("SELECT * FROM salesman_expenses WHERE salesman_id = ? ORDER BY date DESC, id DESC LIMIT 40", [$id]);
        // Summary by 3 spec categories (Vehicle\/Petrol, Food, Other), all-time and this month
        $allTime = db_one("SELECT COALESCE(SUM(CASE WHEN expense_type IN ('diesel','vehicle','repair') THEN amount ELSE 0 END),0) AS vehicle,
            COALESCE(SUM(CASE WHEN expense_type IN ('food','hotel') THEN amount ELSE 0 END),0) AS food,
            COALESCE(SUM(CASE WHEN expense_type = 'other' THEN amount ELSE 0 END),0) AS other,
            COALESCE(SUM(amount),0) AS total
            FROM salesman_expenses WHERE salesman_id = ? AND status = 'posted'", [$id]);
        $monthTime = db_one("SELECT COALESCE(SUM(CASE WHEN expense_type IN ('diesel','vehicle','repair') THEN amount ELSE 0 END),0) AS vehicle,
            COALESCE(SUM(CASE WHEN expense_type IN ('food','hotel') THEN amount ELSE 0 END),0) AS food,
            COALESCE(SUM(CASE WHEN expense_type = 'other' THEN amount ELSE 0 END),0) AS other,
            COALESCE(SUM(amount),0) AS total
            FROM salesman_expenses WHERE salesman_id = ? AND status = 'posted' AND date >= ?", [$id, date('Y-m-01')]);
        view('salesmen/expense', ['sm' => $sm, 'expenses' => $expenses, 'accounts' => db_all("SELECT id, name, type FROM cash_bank_accounts WHERE is_active = 1 ORDER BY type, name"), 'allTime' => $allTime, 'monthTime' => $monthTime, 'title' => 'Expenses — ' . $sm['name']]);
    }

    /** Comprehensive settlement: payable, collections, salary, expenses, advances, remaining. */
    public static function settlement(): void {
        $id = intv(inp('id'));
        $sm = db_one('SELECT * FROM salesmen WHERE id = ?', [$id]);
        if (!$sm) { flash('error', __('Salesman not found.')); redirect('salesmen'); }
        $goodsPayable = (float)db_val("SELECT COALESCE(SUM(debit) - SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ?", [$id]);
        $collections = (float)db_val("SELECT COALESCE(SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'payment'", [$id]);
        $monthlySalary = (float)$sm['salary'];
        $salaryAccrued = 0.0;
        if (!empty($sm['join_date']) && $monthlySalary > 0) {
            $join = new DateTimeImmutable($sm['join_date']);
            $now = new DateTimeImmutable('first day of this month');
            $months = max(0, ((int)$now->format('Y') - (int)$join->format('Y')) * 12 + ((int)$now->format('n') - (int)$join->format('n')) + 1);
            $salaryAccrued = $months * $monthlySalary;
        }
        $salaryPaid = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_salary_payments WHERE salesman_id = ? AND status = 'paid'", [$id]);
        $salaryRemaining = max(0, $salaryAccrued - $salaryPaid);
        $hisab = self::hisabStats((int)$id);
        $expensesPosted = (float)$hisab['company_expenses'];   // company-paid expenses (informational; P&L, not salesman debt)
        $advancesOut = (float)db_val("SELECT COALESCE(SUM(amount - deducted),0) FROM salesman_salary_advances WHERE salesman_id = ? AND status = 'paid' AND amount > deducted", [$id]);
        $salaryStats = PeopleDirectory::salaryStats(
            ['person_type' => 'salesman', 'id' => (int)$sm['id'], 'join_date' => $sm['join_date'], 'salary_rate' => (float)$sm['salary'], 'salary_type' => 'monthly'],
            null
        );
        // Combined summary (informational only): goods payable + advances owed to company − salary remaining.
        // Collection-paid expenses are deducted once inside the Hisab panel; company-paid expenses are P&L, not debt.
        $net = $goodsPayable + $advancesOut - $salaryRemaining;
        view('salesmen/settlement', ['sm' => $sm, 'goodsPayable' => $goodsPayable, 'collections' => $collections,
            'salaryRemaining' => $salaryRemaining, 'expensesPosted' => $expensesPosted, 'advancesOut' => $advancesOut, 'net' => $net,
            'hisab' => $hisab, 'salaryStats' => $salaryStats,
            'title' => 'Settlement — ' . $sm['name']]);
    }

    /* ============ Salesman collections (money collected from customers — van-level, no GL until handover) ============ */

    public static function collections(): void {
        $id = intv(inp('id'));
        $sm = $id ? db_one('SELECT * FROM salesmen WHERE id = ?', [$id]) : null;
        if (!$sm) { http_response_code(404); exit('Salesman not found'); }
        $customers = db_all("SELECT c.id, c.code, c.name FROM customers c WHERE c.is_active = 1 AND (c.salesman_id = ? OR c.id IN (SELECT customer_id FROM salesman_customers WHERE salesman_id = ?)) ORDER BY c.name", [(int)$id, (int)$id]);
        $bankAccounts = db_all("SELECT id, name FROM cash_bank_accounts WHERE is_active = 1 AND type = 'bank' ORDER BY name");
        $cashAccounts = db_all("SELECT id, name FROM cash_bank_accounts WHERE is_active = 1 AND type = 'cash' ORDER BY name");

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (!Auth::can('salesmen.settle')) { http_response_code(403); exit('Forbidden'); }
            self::recordCollection($sm);
            return;
        }

        $rows = db_all(
            "SELECT col.*, COALESCE(c.name, col.contact_name) AS customer_name, c.code AS customer_code, ca.name AS account_name
               FROM salesman_collections col
               LEFT JOIN customers c ON c.id = col.customer_id
               LEFT JOIN cash_bank_accounts ca ON ca.id = col.account_id
              WHERE col.salesman_id = ? ORDER BY col.date DESC, col.id DESC LIMIT 100",
            [(int)$id]
        );
        $totals = db_one(
            "SELECT COALESCE(SUM(amount),0) AS total_collected, COALESCE(SUM(cash_amount),0) AS cash_total, COALESCE(SUM(bank_amount),0) AS bank_total
               FROM salesman_collections WHERE salesman_id = ?",
            [(int)$id]
        );
        view('salesmen/collections', ['sm' => $sm, 'customers' => $customers, 'cashAccounts' => $cashAccounts, 'bankAccounts' => $bankAccounts, 'rows' => $rows, 'totals' => $totals, 'tab' => 'collections', 'idemKey' => bin2hex(random_bytes(12)), 'title' => 'Collections — ' . $sm['name']]);
    }

    private static function recordCollection(array $sm): void {
        $id = (int)$sm['id'];
        $date = inp('date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : today();
        $customerId = intv(inp('customer_id'));
        $contactName = trim((string)inp('contact_name', ''));
        $amount = round(num(inp('amount')), 2);
        $cash = round(num(inp('cash_amount')), 2);
        $bank = round(num(inp('bank_amount')), 2);
        $accountId = intv(inp('account_id')) ?: null;
        $reference = trim(inp('reference'));
        $notes = trim(inp('notes'));
        $idem = trim(inp('idem_key'));

        if (!$customerId && $contactName === '') { flash('error', __('Choose the customer, or enter who paid (person / shop name).')); redirect('salesmen/' . $id . '/collections'); }
        if ($amount <= 0) { flash('error', __('Amount must be positive.')); redirect('salesmen/' . $id . '/collections'); }
        if (abs($cash + $bank - $amount) > 0.009) { flash('error', __('Cash + bank must equal the amount.')); redirect('salesmen/' . $id . '/collections'); }
        if ($bank > 0 && !$accountId) { flash('error', __('Choose the bank account for the bank portion.')); redirect('salesmen/' . $id . '/collections'); }
        if ($idem !== '' && idempotency_seen('salesmen/' . $id . '/collections', $idem, (int)($_SESSION['user_id'] ?? 0))) { flash('info', __('Duplicate submission detected.')); redirect('salesmen/' . $id . '/collections'); }

        try {
            $collectionNo = 'SC-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
            db_insert('salesman_collections', ['collection_no' => $collectionNo, 'salesman_id' => $id, 'customer_id' => $customerId > 0 ? $customerId : null, 'contact_name' => $contactName !== '' ? $contactName : null, 'date' => $date, 'amount' => $amount, 'cash_amount' => $cash, 'bank_amount' => $bank, 'account_id' => $accountId ?: null, 'reference' => $reference ?: null, 'notes' => $notes ?: null, 'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local()]);
            audit('create', 'salesman_collections', null, 'Collection Rs ' . number_format($amount, 2) . ' from ' . ($customerId > 0 ? 'customer ' . $customerId : ($contactName !== '' ? $contactName : 'unnamed')));
            flash('success', __('Collection recorded — hand over to company via the Payments tab.'));
        } catch (Throwable $e) {
            error_log('salesman collection: ' . $e->getMessage());
            flash('error', $e->getMessage());
        }
        redirect('salesmen/' . $id . '/collections');
    }

    /* ============ Payments / Collection (money in against van goods payable) ============ */

    /** Totals for the goods payable panel. Salary/expenses are deliberately excluded. */
    private static function goodsPaymentStats(int $smId): array {
        $issued = (float)db_val("SELECT COALESCE(SUM(sii.line_payable),0) FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE i.salesman_id = ? AND i.issue_type <> 'salesman_return' AND i.status <> 'reversed' AND i.status <> 'pending_approval'", [$smId]);
        $returnCredit = (float)db_val("SELECT COALESCE(SUM(sii.line_payable),0) FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE i.salesman_id = ? AND i.issue_type = 'salesman_return' AND i.status <> 'reversed' AND sii.return_condition = 'good'", [$smId]);
        // New split payments recorded in salesman_payments
        $cashNew = (float)db_val("SELECT COALESCE(SUM(cash_amount),0) FROM salesman_payments WHERE salesman_id = ? AND status = 'posted'", [$smId]);
        $bankNew = (float)db_val("SELECT COALESCE(SUM(bank_amount),0) FROM salesman_payments WHERE salesman_id = ? AND status = 'posted'", [$smId]);
        // Legacy single-account payments (payToCompany) — recover cash vs bank via the posted journal lines
        $legacyCash = (float)db_val("SELECT COALESCE(SUM(jl.debit),0) FROM journal_lines jl JOIN journal_entries je ON je.id = jl.entry_id JOIN cash_bank_accounts cb ON cb.id = jl.account_id WHERE je.reference_type = 'salesman_repayment' AND je.reference_id = ? AND cb.type = 'cash' AND jl.debit > 0", [$smId]);
        $legacyBank = (float)db_val("SELECT COALESCE(SUM(jl.debit),0) FROM journal_lines jl JOIN journal_entries je ON je.id = jl.entry_id JOIN cash_bank_accounts cb ON cb.id = jl.account_id WHERE je.reference_type = 'salesman_repayment' AND je.reference_id = ? AND cb.type = 'bank' AND jl.debit > 0", [$smId]);
        $cash = round($cashNew + $legacyCash, 2);
        $bank = round($bankNew + $legacyBank, 2);
        $totalReceived = round($cash + $bank, 2);
        $remaining = round($issued - $returnCredit - $totalReceived, 2);
        return ['issued' => $issued, 'returnCredit' => $returnCredit, 'cashReceived' => $cash, 'bankReceived' => $bank, 'totalReceived' => $totalReceived, 'remaining' => $remaining];
    }

    /* ============ Recovery / Payment Collection (inside the salesman profile) ============ */

    /** All posted goods invoices for a salesman, with payment / return credits allocated to each.
     *  Explicit allocations always win; older unallocated (legacy) payments and returns are then
     *  spread oldest-invoice-first so pre-feature history is shown without being rewritten. */
    private static function recoveryData(int $smId): array {
        $stats = self::goodsPaymentStats($smId);
        $adjCredit = (float)db_val("SELECT COALESCE(SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'adjustment'", [$smId]);
        $adjDebit  = (float)db_val("SELECT COALESCE(SUM(debit),0)  FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'adjustment'", [$smId]);

        $headers = db_all("SELECT i.id, i.issue_no, i.date, i.status, i.notes, i.total_payable
            FROM salesman_issues i
            WHERE i.salesman_id = ? AND i.issue_type = 'credit_issue' AND i.status = 'posted'
            ORDER BY i.date, i.id", [$smId]);
        $invoices = [];
        if ($headers) {
            $ids = array_map('intval', array_column($headers, 'id'));
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $itemRows = db_all("SELECT sii.issue_id, sii.product_id, sii.paid_qty, sii.bonus_qty, sii.qty, sii.issue_rate,
                    sii.line_payable, p.sku, p.name
                FROM salesman_issue_items sii JOIN products p ON p.id = sii.product_id
                WHERE sii.issue_id IN ($ph) ORDER BY sii.id", $ids);
            $byIssue = [];
            foreach ($itemRows as $it) { $byIssue[(int)$it['issue_id']][] = $it; }
            foreach ($headers as $h) {
                $lines = $byIssue[(int)$h['id']] ?? [];
                $amount = 0.0;
                foreach ($lines as $it) $amount += (float)$it['line_payable'];
                $amount = round($amount, 2);
                if ($amount <= 0 && (float)$h['total_payable'] <= 0) continue; // bonus-only / non-payable issue
                if ($amount <= 0) $amount = round((float)$h['total_payable'], 2);
                $invoices[] = [
                    'id' => (int)$h['id'], 'issue_no' => (string)$h['issue_no'], 'date' => (string)$h['date'],
                    'status' => (string)$h['status'], 'notes' => (string)($h['notes'] ?? ''),
                    'amount' => $amount, 'items' => $lines,
                    'paid' => 0.0, 'returned' => 0.0, 'recovered' => 0.0, 'remaining' => 0.0,
                ];
            }
        }

        // Explicit payment allocations recorded from the Recovery tab.
        $explicit = [];
        foreach (db_all("SELECT issue_id, SUM(amount) AS amt FROM salesman_payment_allocations WHERE salesman_id = ? GROUP BY issue_id", [$smId]) as $a) {
            $explicit[(int)$a['issue_id']] = round((float)$a['amt'], 2);
        }
        // Goods returns explicitly linked to an invoice at the time they were received.
        $linked = [];
        foreach (db_all("SELECT i.related_issue_id, SUM(sii.line_payable) AS amt
                FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id
                WHERE i.salesman_id = ? AND i.issue_type = 'salesman_return' AND i.status <> 'reversed'
                  AND i.related_issue_id IS NOT NULL AND COALESCE(sii.return_condition, 'good') = 'good'
                GROUP BY i.related_issue_id", [$smId]) as $r) {
            $linked[(int)$r['related_issue_id']] = round((float)$r['amt'], 2);
        }

        $appliedExplicit = 0.0; $appliedLinked = 0.0;
        foreach ($invoices as &$inv) {
            $p = $explicit[$inv['id']] ?? 0.0;
            $r = $linked[$inv['id']] ?? 0.0;
            $inv['paid'] = round($p, 2);
            $inv['returned'] = round($r, 2);
            $appliedExplicit = round($appliedExplicit + $p, 2);
            $appliedLinked = round($appliedLinked + $r, 2);
        }
        unset($inv);

        // Anything recorded before allocations existed (or unlinked) is spread oldest-first.
        $pools = [
            'paid' => max(0, round($stats['totalReceived'] - $appliedExplicit, 2)),
            'returned' => max(0, round($stats['returnCredit'] - $appliedLinked, 2)),
        ];
        foreach ($pools as $key => $amount) {
            $left = $amount;
            if ($left <= 0) continue;
            foreach ($invoices as &$inv) {
                if ($left <= 0.009) break;
                $room = round($inv['amount'] - $inv['paid'] - $inv['returned'], 2);
                if ($room <= 0.009) continue;
                $take = round(min($room, $left), 2);
                $inv[$key] = round($inv[$key] + $take, 2);
                $left = round($left - $take, 2);
            }
            unset($inv);
            $pools[$key] = $left; // genuine credit above today's invoices (on account / advance)
        }

        $totalInvoiced = 0.0; $pending = 0;
        foreach ($invoices as &$inv) {
            $inv['recovered'] = round($inv['paid'] + $inv['returned'], 2);
            $inv['remaining'] = round($inv['amount'] - $inv['recovered'], 2);
            $totalInvoiced = round($totalInvoiced + $inv['amount'], 2);
            if ($inv['remaining'] > 0.009) $pending++;
        }
        unset($inv);

        $outstanding = round($totalInvoiced - $stats['returnCredit'] - $stats['cashReceived'] - $stats['bankReceived'] - $adjCredit + $adjDebit, 2);
        $totalRecovered = round($stats['cashReceived'] + $stats['bankReceived'] + $stats['returnCredit'] + $adjCredit - $adjDebit, 2);

        return [
            'stats' => $stats, 'invoices' => $invoices,
            'total_invoiced' => $totalInvoiced, 'total_paid' => round(array_sum(array_column($invoices, 'paid')), 2),
            'total_return_credit' => round(array_sum(array_column($invoices, 'returned')), 2),
            'cash_received' => $stats['cashReceived'], 'bank_received' => $stats['bankReceived'],
            'return_credit' => $stats['returnCredit'], 'adjustment_credit' => $adjCredit, 'adjustment_debit' => $adjDebit,
            'total_recovered' => $totalRecovered, 'outstanding' => $outstanding, 'pending_invoices' => $pending,
            'on_account_payment' => $pools['paid'], 'on_account_return' => $pools['returned'],
        ];
    }

    /** Allocate one payment across invoices. Manual amounts are honoured first; the rest goes oldest-first. */
    private static function allocatePaymentAmount(array $invoices, float $amount, array $manual): array {
        $remaining = [];
        $labels = [];
        foreach ($invoices as $inv) {
            $remaining[(int)$inv['id']] = max(0, round((float)$inv['remaining'], 2));
            $labels[(int)$inv['id']] = (string)$inv['issue_no'];
        }
        $alloc = []; $used = 0.0;
        foreach ($manual as $rawId => $rawAmount) {
            $iid = (int)$rawId; $amt = round((float)$rawAmount, 2);
            if ($amt <= 0) continue;
            if (!isset($remaining[$iid])) return ['error' => 'Allocation references an unknown invoice.'];
            if ($amt > $remaining[$iid] + 0.009) {
                return ['error' => 'Allocation for ' . $labels[$iid] . ' exceeds its outstanding amount (' . money($remaining[$iid]) . ').'];
            }
            $alloc[$iid] = round(($alloc[$iid] ?? 0) + $amt, 2);
            $remaining[$iid] = round($remaining[$iid] - $amt, 2);
            $used = round($used + $amt, 2);
        }
        if ($used > $amount + 0.009) return ['error' => 'Manual allocations exceed the payment received.'];

        $left = round($amount - $used, 2);
        foreach ($invoices as $inv) {
            if ($left <= 0.009) break;
            $iid = (int)$inv['id'];
            $room = $remaining[$iid] ?? 0;
            if ($room <= 0.009) continue;
            $take = round(min($room, $left), 2);
            $alloc[$iid] = round(($alloc[$iid] ?? 0) + $take, 2);
            $remaining[$iid] = round($room - $take, 2);
            $left = round($left - $take, 2);
        }
        return ['allocations' => $alloc, 'unallocated' => $left];
    }

    /** Chronological money/stock movement history for the recovery tab and its printable statement. */
    private static function recoveryHistory(int $smId, array $filters): array {
        $data = self::recoveryData($smId);
        $events = [];
        foreach ($data['invoices'] as $inv) {
            $events[] = [
                'date' => $inv['date'], 'seq' => 0, 'source_id' => $inv['id'], 'type' => 'invoice',
                'doc_no' => $inv['issue_no'], 'invoice_no' => $inv['issue_no'], 'invoice_id' => $inv['id'],
                'goods' => $inv['amount'], 'cash' => 0.0, 'bank' => 0.0, 'return_credit' => 0.0,
                'adjustment' => 0.0, 'method' => '', 'reference' => '', 'notes' => $inv['notes'], 'payment_id' => null,
            ];
        }

        $payRows = db_all("SELECT p.*, ca.name AS cash_account, ba.name AS bank_account
            FROM salesman_payments p
            LEFT JOIN cash_bank_accounts ca ON ca.id = p.cash_account_id
            LEFT JOIN cash_bank_accounts ba ON ba.id = p.bank_account_id
            WHERE p.salesman_id = ? AND p.status = 'posted'", [$smId]);
        $allocationByPayment = [];
        foreach (db_all("SELECT spa.payment_id, spa.issue_id, spa.amount, i.issue_no
                FROM salesman_payment_allocations spa JOIN salesman_issues i ON i.id = spa.issue_id
                WHERE spa.salesman_id = ? ORDER BY spa.payment_id, i.date, i.id", [$smId]) as $al) {
            $allocationByPayment[(int)$al['payment_id']][] = $al;
        }
        $invoiceLabels = [];
        foreach ($data['invoices'] as $inv) $invoiceLabels[(int)$inv['id']] = $inv['issue_no'];

        foreach ($payRows as $p) {
            $cash = round((float)$p['cash_amount'], 2);
            $bank = round((float)$p['bank_amount'], 2);
            $total = round((float)$p['total_amount'], 2);
            $method = (string)$p['payment_method'];
            $ref = trim((string)($p['bank_reference'] ?? ''));
            $notes = trim((string)($p['notes'] ?? ''));
            $als = $allocationByPayment[(int)$p['id']] ?? [];
            if ($als) {
                foreach ($als as $al) {
                    $share = $total > 0 ? round((float)$al['amount'] / $total, 6) : 0;
                    $events[] = [
                        'date' => $p['date'], 'seq' => 1, 'source_id' => (int)$p['id'], 'type' => 'payment',
                        'doc_no' => (string)($p['receipt_no'] ?: ('SP-' . $p['id'])),
                        'invoice_no' => (string)$al['issue_no'], 'invoice_id' => (int)$al['issue_id'],
                        'goods' => 0.0, 'cash' => round($cash * $share, 2), 'bank' => round($bank * $share, 2),
                        'return_credit' => 0.0, 'adjustment' => 0.0, 'method' => $method,
                        'reference' => $ref, 'notes' => $notes, 'payment_id' => (int)$p['id'],
                    ];
                }
            } else {
                $events[] = [
                    'date' => $p['date'], 'seq' => 1, 'source_id' => (int)$p['id'], 'type' => 'payment',
                    'doc_no' => (string)($p['receipt_no'] ?: ('SP-' . $p['id'])), 'invoice_no' => '', 'invoice_id' => 0,
                    'goods' => 0.0, 'cash' => $cash, 'bank' => $bank, 'return_credit' => 0.0,
                    'adjustment' => 0.0, 'method' => $method, 'reference' => $ref, 'notes' => $notes,
                    'payment_id' => (int)$p['id'],
                ];
            }
        }

        // Legacy repayments recorded before the split-payment table existed (ledger-only, doc_id NULL).
        foreach (db_all("SELECT sl.*, je.entry_no,
                    COALESCE((SELECT SUM(jl.debit) FROM journal_lines jl JOIN cash_bank_accounts cb ON cb.id = jl.account_id
                        WHERE jl.entry_id = je.id AND cb.type = 'cash' AND jl.debit > 0), 0) AS legacy_cash,
                    COALESCE((SELECT SUM(jl.debit) FROM journal_lines jl JOIN cash_bank_accounts cb ON cb.id = jl.account_id
                        WHERE jl.entry_id = je.id AND cb.type = 'bank' AND jl.debit > 0), 0) AS legacy_bank
                FROM salesman_ledger sl
                LEFT JOIN journal_entries je ON je.entry_no = sl.doc_no AND je.reference_type = 'salesman_repayment'
                WHERE sl.salesman_id = ? AND sl.doc_type = 'payment' AND sl.doc_id IS NULL", [$smId]) as $lp) {
            $c = round((float)$lp['legacy_cash'], 2);
            $b = round((float)$lp['legacy_bank'], 2);
            $events[] = [
                'date' => $lp['date'], 'seq' => 1, 'source_id' => (int)$lp['id'], 'type' => 'payment',
                'doc_no' => (string)($lp['doc_no'] ?: ('SR-' . $lp['id'])), 'invoice_no' => '', 'invoice_id' => 0,
                'goods' => 0.0, 'cash' => $c, 'bank' => $b, 'return_credit' => 0.0, 'adjustment' => 0.0,
                'method' => ($c > 0 && $b > 0) ? 'split' : ($b > 0 ? 'bank' : 'cash'),
                'reference' => '', 'notes' => (string)($lp['notes'] ?? ''), 'payment_id' => null,
            ];
        }

        // Goods returns — credited separately from cash, never counted as a payment.
        foreach (db_all("SELECT i.id, i.issue_no, i.date, i.related_issue_id, i.notes,
                    COALESCE(SUM(sii.line_payable),0) AS credit,
                    COALESCE(SUM(CASE WHEN COALESCE(sii.return_condition,'good') = 'damaged' THEN sii.qty ELSE 0 END),0) AS damaged_qty,
                    COALESCE(SUM(CASE WHEN COALESCE(sii.return_condition,'good') = 'damaged' THEN 0 ELSE sii.qty END),0) AS good_qty
                FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id
                WHERE i.salesman_id = ? AND i.issue_type = 'salesman_return' AND i.status <> 'reversed'
                GROUP BY i.id", [$smId]) as $ret) {
            $credit = round((float)$ret['credit'], 2);
            $rel = (int)($ret['related_issue_id'] ?? 0);
            $events[] = [
                'date' => $ret['date'], 'seq' => 2, 'source_id' => (int)$ret['id'], 'type' => 'return',
                'doc_no' => (string)$ret['issue_no'], 'invoice_no' => $rel ? (string)($invoiceLabels[$rel] ?? ('#' . $rel)) : '',
                'invoice_id' => $rel, 'goods' => 0.0, 'cash' => 0.0, 'bank' => 0.0, 'return_credit' => $credit,
                'adjustment' => 0.0, 'method' => 'return',
                'reference' => ((float)$ret['damaged_qty'] > 0 ? 'damaged ' . qty($ret['damaged_qty']) . ' Packet(s)' : ''),
                'notes' => (string)($ret['notes'] ?? '') . ((float)$ret['good_qty'] > 0 ? ' · ' . qty($ret['good_qty']) . ' Packet(s)' : ''),
                'payment_id' => null,
            ];
        }

        foreach (db_all("SELECT * FROM salesman_ledger WHERE salesman_id = ? AND doc_type = 'adjustment'", [$smId]) as $ad) {
            $events[] = [
                'date' => $ad['date'], 'seq' => 3, 'source_id' => (int)$ad['id'], 'type' => 'adjustment',
                'doc_no' => (string)($ad['doc_no'] ?: ('ADJ-' . $ad['id'])), 'invoice_no' => '', 'invoice_id' => 0,
                'goods' => 0.0, 'cash' => 0.0, 'bank' => 0.0, 'return_credit' => 0.0,
                'adjustment' => round((float)$ad['credit'] - (float)$ad['debit'], 2),
                'method' => 'adjustment', 'reference' => '', 'notes' => (string)($ad['notes'] ?? ''), 'payment_id' => null,
            ];
        }

        usort($events, function ($a, $b) {
            $c = strcmp($a['date'], $b['date']);
            if ($c !== 0) return $c;
            $c = $a['seq'] <=> $b['seq'];
            return $c !== 0 ? $c : ((int)$a['source_id'] <=> (int)$b['source_id']);
        });

        $from = $filters['from'] ?? '';
        $to = $filters['to'] ?? '';
        $invoiceFilter = (int)($filters['invoice_id'] ?? 0);
        $method = (string)($filters['method'] ?? 'all');
        $running = 0.0; $opening = 0.0; $closing = 0.0; $rows = [];

        foreach ($events as $e) {
            $delta = $e['goods'] - $e['cash'] - $e['bank'] - $e['return_credit'] + $e['adjustment'];
            $running = round($running + $delta, 2);
            if ($from && $e['date'] < $from) $opening = $running;
            $closing = $running;
            $e['total_recovered'] = round($e['cash'] + $e['bank'] + $e['return_credit'], 2);
            $e['balance'] = $running;

            if ($from && $e['date'] < $from) continue;
            if ($to && $e['date'] > $to) continue;
            if ($invoiceFilter && (int)$e['invoice_id'] !== $invoiceFilter) continue;
            if ($method !== 'all') {
                if ($method === 'return') {
                    if ($e['type'] !== 'invoice' && $e['type'] !== 'return') continue;
                } elseif ($method === 'adjustment') {
                    if ($e['type'] !== 'invoice' && $e['type'] !== 'adjustment') continue;
                } elseif (!in_array($method, ['cash', 'bank', 'split'], true)) {
                    // unknown method — show everything
                } elseif ($e['type'] === 'payment' && $e['method'] !== $method) {
                    continue;
                } elseif ($e['type'] !== 'invoice' && $e['type'] !== 'payment') {
                    continue;
                }
            }
            $rows[] = $e;
        }
        if ($method !== 'all' && !$rows) $opening = 0.0;

        return ['rows' => $rows, 'opening' => $opening, 'closing' => $closing, 'all_events' => count($events)];
    }

    /** Backward-compatible URL — the old Payments tab is now the Recovery / Payment Collection tab. */
    public static function payments(): void {
        redirect('salesmen/' . intv(inp('id')) . '/recovery');
    }

    /** One tab: dashboard, all invoices, new recovery entry, goods return, history and reports. */
    public static function recovery(): void {
        $id = intv(inp('id'));
        $sm = $id ? db_one('SELECT * FROM salesmen WHERE id = ?', [$id]) : null;
        if (!$sm) { http_response_code(404); view('error/404', ['title' => 'Page not found']); return; }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Auth::can('salesmen.settle')) { http_response_code(403); exit('Forbidden'); }
            require_post(); csrf_verify();
            if (inp('recovery_action') === 'return') self::recordRecoveryReturn($sm);
            else self::recordPayment($sm);
            return;
        }
        $data = self::recoveryData((int)$id);
        $filters = [
            'from' => inp('from'), 'to' => inp('to'),
            'invoice_id' => intv(inp('invoice_id')), 'method' => inp('method', 'all'),
        ];
        if (!in_array($filters['method'], ['all', 'cash', 'bank', 'split', 'return', 'adjustment'], true)) $filters['method'] = 'all';
        $history = self::recoveryHistory((int)$id, $filters);
        $payments = db_all("SELECT p.*, ca.name AS cash_account, ba.name AS bank_account,
                 u.full_name AS received_full_name, u.username AS received_username
            FROM salesman_payments p
            LEFT JOIN cash_bank_accounts ca ON ca.id = p.cash_account_id
            LEFT JOIN cash_bank_accounts ba ON ba.id = p.bank_account_id
            LEFT JOIN users u ON u.id = p.received_by
            WHERE p.salesman_id = ? ORDER BY p.date DESC, p.id DESC LIMIT 200", [(int)$id]);
        $returns = db_all("SELECT i.id, i.date, i.issue_no, i.notes, i.related_issue_id,
                 si.issue_no AS invoice_no,
                 COALESCE(SUM(CASE WHEN COALESCE(sii.return_condition,'good') = 'damaged' THEN 0 ELSE sii.qty END),0) AS good_qty,
                 COALESCE(SUM(CASE WHEN COALESCE(sii.return_condition,'good') = 'damaged' THEN sii.qty ELSE 0 END),0) AS damaged_qty,
                 COALESCE(SUM(CASE WHEN COALESCE(sii.return_condition,'good') = 'damaged' THEN 0 ELSE sii.line_payable END),0) AS credit
            FROM salesman_issues i
            LEFT JOIN salesman_issues si ON si.id = i.related_issue_id
            JOIN salesman_issue_items sii ON sii.issue_id = i.id
            WHERE i.salesman_id = ? AND i.issue_type = 'salesman_return' AND i.status <> 'reversed'
            GROUP BY i.id ORDER BY i.date DESC, i.id DESC LIMIT 100", [(int)$id]);
        $vanStock = db_all("SELECT p.id, p.sku, p.name, p.issue_rate, COALESCE(b.qty,0) AS qty
            FROM products p LEFT JOIN stock_balances b ON b.product_id = p.id AND b.warehouse_id = ?
            WHERE p.kind = 'finished' AND p.is_active = 1 AND COALESCE(b.qty,0) > 0 ORDER BY p.name", [(int)($sm['warehouse_id'] ?? 0)]);
        $rateMap = [];
        foreach ($data['invoices'] as $inv) {
            foreach ($inv['items'] as $it) {
                $rate = (float)$it['issue_rate'];
                if ($rate <= 0 && (float)$it['paid_qty'] > 0) $rate = round((float)$it['line_payable'] / (float)$it['paid_qty'], 2);
                $rateMap[(int)$inv['id']][(int)$it['product_id']] = round($rate, 2);
            }
        }
        view('salesmen/recovery', $data + [
            'sm' => $sm, 'id' => (int)$id, 'tab' => 'recovery', 'filters' => $filters, 'history' => $history,
            'payments' => $payments, 'returns' => $returns, 'vanStock' => $vanStock, 'rateMap' => $rateMap,
            'cashAccounts' => db_all("SELECT id, name FROM cash_bank_accounts WHERE is_active = 1 AND type = 'cash' ORDER BY name"),
            'bankAccounts' => db_all("SELECT id, name FROM cash_bank_accounts WHERE is_active = 1 AND type = 'bank' ORDER BY name"),
            'currentUserName' => trim((string)((Auth::user()['full_name'] ?? null) ?: ($_SESSION['username'] ?? ''))),
            'idemKey' => 'REC-' . bin2hex(random_bytes(12)),
            'title' => 'Recovery / Payment Collection — ' . $sm['name'],
        ]);
    }

    /** Printable salesman recovery statement. */
    public static function recoveryStatement(): void {
        $id = intv(inp('id'));
        $sm = $id ? db_one('SELECT * FROM salesmen WHERE id = ?', [$id]) : null;
        if (!$sm) { http_response_code(404); view('error/404', ['title' => 'Page not found']); return; }
        $from = inp('from', date('Y-m-01'));
        $to = inp('to', today());
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = today();
        $invoiceId = intv(inp('invoice_id'));
        $data = self::recoveryData((int)$id);
        $history = self::recoveryHistory((int)$id, ['from' => $from, 'to' => $to, 'invoice_id' => $invoiceId, 'method' => 'all']);
        view('salesmen/recovery_statement', $data + [
            'sm' => $sm, 'id' => (int)$id, 'from' => $from, 'to' => $to, 'invoice_id' => $invoiceId,
            'history' => $history, 'title' => 'Recovery Statement — ' . $sm['name'],
        ]);
    }

    /** Original issue rate for a product on an invoice (fallback: latest issue, then product master). */
    private static function originalIssueRate(int $smId, int $productId, int $issueId): float {
        if ($issueId > 0) {
            $row = db_one("SELECT sii.issue_rate, sii.paid_qty, sii.line_payable
                FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id
                WHERE i.id = ? AND i.salesman_id = ? AND i.issue_type = 'credit_issue' AND i.status = 'posted'
                  AND sii.product_id = ? ORDER BY sii.id DESC LIMIT 1", [$issueId, $smId, $productId]);
            if ($row) {
                $rate = round((float)$row['issue_rate'], 2);
                if ($rate <= 0 && (float)$row['paid_qty'] > 0) $rate = round((float)$row['line_payable'] / (float)$row['paid_qty'], 2);
                return max(0, $rate);
            }
            return -1.0; // invoice selected but product not on it
        }
        $rate = (float)db_val("SELECT sii.issue_rate
            FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id
            WHERE i.salesman_id = ? AND i.issue_type = 'credit_issue' AND i.status = 'posted'
              AND sii.product_id = ? AND sii.issue_rate > 0
            ORDER BY i.date DESC, i.id DESC, sii.id DESC LIMIT 1", [$smId, $productId]);
        if ($rate <= 0) $rate = (float)db_val('SELECT issue_rate FROM products WHERE id = ?', [$productId]);
        return round($rate, 2);
    }

    /** Goods Returned from inside the Recovery tab. Posts stock + ledger through the existing return engine. */
    private static function recordRecoveryReturn(array $sm, string $back = ''): void {
        $id = (int)$sm['id'];
        if ($back === '') $back = 'salesmen/' . $id . '/recovery';
        $date = inp('date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : today();
        $productId = intv(inp('product_id'));
        $qty = round(num(inp('qty')), 3);
        $relatedId = intv(inp('related_issue_id'));
        $condition = inp('return_condition') === 'damaged' ? 'damaged' : 'good';
        $reference = trim(inp('reference'));
        $notes = trim(inp('notes'));
        $vanId = (int)($sm['warehouse_id'] ?? 0);

        if (!$productId || $qty <= 0) { flash('error', __('Select a product and enter the returned quantity in Packets.')); redirect($back); }
        if (db_val('SELECT kind FROM products WHERE id = ?', [$productId]) !== 'finished') {
            flash('error', __('Only finished goods can be returned to Main Warehouse stock.')); redirect($back);
        }
        if (!$vanId) { flash('error', __('Salesman has no van stock location.')); redirect($back); }
        $available = (float)db_val('SELECT COALESCE(qty,0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$productId, $vanId]);
        if ($qty > $available + 1e-6) {
            flash('error', __('Return quantity exceeds van stock (') . qty($available) . ' ' . __('Packets') . ').'); redirect($back);
        }
        $rate = self::originalIssueRate($id, $productId, $relatedId);
        if ($rate < 0) { flash('error', __('The selected invoice does not contain this product.')); redirect($back); }
        if ($condition === 'good' && $rate <= 0) { flash('error', __('No applicable issue rate found for this product.')); redirect($back); }
        $credit = $condition === 'good' ? round($qty * $rate, 2) : 0.0;
        $note = trim(($reference !== '' ? 'Ref: ' . $reference : '') . ($notes !== '' ? (($reference !== '' ? ' — ' : '') . $notes) : ''));

        try {
            $returnId = 0;
            Database::transaction(function () use ($id, $vanId, $date, $productId, $qty, $rate, $credit, $condition, $note, $relatedId, &$returnId) {
                $returnId = db_insert('salesman_issues', [
                    'issue_no' => 'SRT-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)),
                    'salesman_id' => $id, 'from_warehouse_id' => $vanId, 'date' => $date,
                    'status' => 'posted', 'issue_type' => 'salesman_return',
                    'total_payable' => 0.0, 'notes' => $note ?: null,
                    'related_issue_id' => $relatedId > 0 ? $relatedId : null,
                    'created_by' => $_SESSION['user_id'] ?? null, 'created_at' => now_local(),
                ]);
                $cost = Inventory::avgCost($productId, $vanId);
                db_insert('salesman_issue_items', [
                    'issue_id' => $returnId, 'product_id' => $productId,
                    'qty' => $qty, 'paid_qty' => $qty, 'bonus_qty' => 0,
                    'unit_cost' => $cost, 'issue_rate' => $rate, 'line_payable' => $credit,
                    'bonus_cost' => 0, 'return_condition' => $condition,
                ]);
                Posting::postSalesmanReturn($returnId);
                audit('create', 'salesman_issues', $returnId, 'Recovery goods return ' . qty($qty) . ' Packets, credit ' . money($credit) . ' (' . $condition . ')');
            });
            flash('success', $condition === 'good'
                ? sprintf(__('Goods return posted — %s Packets credited Rs %s at the original issue rate; saleable stock returned to Main Warehouse.'), qty($qty), money($credit, false))
                : sprintf(__('Damaged return recorded separately — %s Packets, no credit and no saleable stock.'), qty($qty)));
        } catch (Throwable $e) {
            error_log('recovery goods return: ' . $e->getMessage());
            flash('error', __('Goods return could not be posted: ') . $e->getMessage());
        }
        redirect($back);
    }

    /** Direct Goods Issue (regular + bonus) from inside the Salesman Account page. */
    private static function recordGoodsIssue(array $sm, string $back = ''): void {
        $id = (int)$sm['id'];
        if ($back === '') $back = 'salesmen/' . $id . '/account';
        $date = inp('date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : today();
        $productId = intv(inp('product_id'));
        $paidQty = round(num(inp('paid_qty')), 3);
        $bonusQty = round(num(inp('bonus_qty')), 3);
        $totalQty = round($paidQty + $bonusQty, 3);
        $issueRate = round(num(inp('issue_rate')), 2);
        $reference = trim(inp('reference'));
        $notes = trim(inp('notes'));
        $vanId = (int)($sm['warehouse_id'] ?? 0);

        if (!$productId || $totalQty <= 0) {
            flash('error', __('Select a product and enter regular or bonus quantity in Packets.'));
            redirect($back);
        }
        if ($paidQty > 0 && $issueRate <= 0) {
            flash('error', __('Enter a valid issue rate for the regular quantity.'));
            redirect($back);
        }
        if (!$vanId) {
            flash('error', __('Salesman has no van stock location assigned.'));
            redirect($back);
        }
        $fromWh = (int)db_val("SELECT id FROM warehouses WHERE code = 'WH-MAIN' LIMIT 1") ?: (int)db_val('SELECT id FROM warehouses WHERE is_default = 1 LIMIT 1');
        if (!$fromWh) {
            flash('error', __('Main Warehouse (WH-MAIN) not found.'));
            redirect($back);
        }
        $available = (float)db_val('SELECT COALESCE(qty,0) FROM stock_balances WHERE product_id = ? AND warehouse_id = ?', [$productId, $fromWh]);
        if ($totalQty > $available + 1e-6) {
            flash('error', sprintf(__('Requested quantity (%s Packets) exceeds Main Warehouse stock (%s Packets).'), qty($totalQty), qty($available)));
            redirect($back);
        }

        $linePayable = round($paidQty * $issueRate, 2);
        $note = trim(($reference !== '' ? 'Ref: ' . $reference : '') . ($notes !== '' ? (($reference !== '' ? ' — ' : '') . $notes) : ''));

        try {
            $issueId = 0;
            Database::transaction(function () use ($sm, $id, $fromWh, $vanId, $date, $productId, $paidQty, $bonusQty, $totalQty, $issueRate, $linePayable, $note, &$issueId) {
                $issueNo = 'SI-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
                $cost = Inventory::avgCost($productId, $fromWh);
                $bonusCost = round($bonusQty * $cost, 2);

                $issueId = db_insert('salesman_issues', [
                    'issue_no' => $issueNo,
                    'salesman_id' => $id,
                    'from_warehouse_id' => $fromWh,
                    'date' => $date,
                    'status' => 'posted',
                    'issue_type' => 'credit_issue',
                    'total_payable' => $linePayable,
                    'total_bonus_cost' => $bonusCost,
                    'bonus_scheme' => $bonusQty > 0 ? 'Direct Issue' : null,
                    'bonus_reason' => $bonusQty > 0 ? ($note ?: 'Bonus goods') : null,
                    'notes' => $note ?: null,
                    'created_by' => (int)($_SESSION['user_id'] ?? 0),
                    'created_at' => now_local(),
                ]);

                db_insert('salesman_issue_items', [
                    'issue_id' => $issueId,
                    'product_id' => $productId,
                    'paid_qty' => $paidQty,
                    'bonus_qty' => $bonusQty,
                    'qty' => $totalQty,
                    'unit_cost' => $cost,
                    'issue_rate' => $issueRate,
                    'line_payable' => $linePayable,
                    'bonus_cost' => $bonusCost,
                ]);

                Posting::postSalesmanIssue((int)$issueId);
                audit('create', 'salesman_issues', $issueId, "Goods issued: $totalQty Packets (paid $paidQty @ Rs $issueRate, bonus $bonusQty), payable Rs $linePayable");
            });

            flash('success', sprintf(__('Goods successfully issued — %s Packets transferred to %s (Payable: Rs %s, Bonus: %s Packets).'), qty($totalQty), e($sm['name']), money($linePayable, false), qty($bonusQty)));
        } catch (Throwable $e) {
            error_log('direct goods issue: ' . $e->getMessage());
            flash('error', __('Goods issue failed: ') . $e->getMessage());
        }
        redirect($back);
    }

    private static function recordPayment(array $sm, string $back = ''): void {
        $id = (int)$sm['id'];
        if ($back === '') $back = 'salesmen/' . $id . '/recovery';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $date = inp('date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : today();
        $total = round(num(inp('total_amount')), 2);
        $cash = round(num(inp('cash_amount')), 2);
        $bank = round(num(inp('bank_amount')), 2);
        $cashAcct = intv(inp('cash_account_id'));
        $bankAcct = intv(inp('bank_account_id'));
        $bankRef = trim(inp('bank_reference'));
        $notes = trim(inp('notes'));
        $idem = trim(inp('idem_key'));

        $errors = [];
        if ($total <= 0) $errors[] = 'Total payment must be positive.';
        if ($cash < 0 || $bank < 0) $errors[] = 'Amounts cannot be negative.';
        if (abs($cash + $bank - $total) > 0.009) $errors[] = 'Cash amount + bank amount must equal the total payment amount (Rs ' . number_format($total, 2) . ').';
        if ($cash <= 0 && $bank <= 0) $errors[] = 'Enter at least a cash amount or a bank amount.';
        if ($cash > 0) {
            if (!$cashAcct) { $cashAcct = (int)db_val("SELECT id FROM cash_bank_accounts WHERE is_active = 1 AND type = 'cash' ORDER BY id LIMIT 1"); }
            if (!$cashAcct || !db_val("SELECT 1 FROM cash_bank_accounts WHERE id = ? AND is_active = 1", [$cashAcct])) $errors[] = 'Select the cash account for the cash portion.';
        }
        if ($bank > 0) {
            if (!$bankAcct || !db_val("SELECT 1 FROM cash_bank_accounts WHERE id = ? AND is_active = 1 AND type = 'bank'", [$bankAcct])) $errors[] = 'Select the bank account for the bank portion.';
            if ($bankRef === '') $errors[] = 'Enter the bank transaction / reference number.';
        }
        if ($errors) { flash('error', implode(' ', $errors)); redirect($back); }

        // Invoice allocation: manual where supplied, otherwise oldest invoice first. Leftovers stay on account.
        $mode = inp('allocation_mode') === 'manual' ? 'manual' : 'auto';
        $manual = [];
        if ($mode === 'manual' && is_array($_POST['allocations'] ?? null)) {
            foreach ($_POST['allocations'] as $iid => $amt) $manual[(int)$iid] = num($amt);
        }
        $recovery = self::recoveryData($id);
        $allocation = self::allocatePaymentAmount($recovery['invoices'], $total, $manual);
        if (isset($allocation['error'])) { flash('error', $allocation['error']); redirect($back); }

        if ($idem !== '' && idempotency_seen($back, $idem, $userId)) { flash('info', __('Duplicate submission detected — the payment was already recorded.')); redirect($back); }

        $method = ($cash > 0 && $bank > 0) ? 'split' : ($bank > 0 ? 'bank' : 'cash');
        try {
            Database::transaction(function () use ($sm, $id, $date, $total, $cash, $bank, $cashAcct, $bankAcct, $bankRef, $notes, $method, $userId, $idem, $allocation, &$pid) {
                $pid = db_insert('salesman_payments', ['salesman_id' => $id, 'date' => $date, 'total_amount' => $total, 'cash_amount' => $cash, 'bank_amount' => $bank, 'cash_account_id' => $cashAcct ?: null, 'bank_account_id' => $bankAcct ?: null, 'bank_reference' => $bankRef ?: null, 'payment_method' => $method, 'notes' => $notes ?: null, 'received_by' => $userId ?: null, 'idem_key' => $idem !== '' ? $idem : bin2hex(random_bytes(12)), 'status' => 'posted', 'created_by' => $userId ?: null, 'created_at' => now_local()]);
                $lines = [];
                if ($cash > 0) $lines[] = ['account' => Posting::accountGl($cashAcct), 'debit' => $cash, 'memo' => 'Salesman payment cash — ' . $sm['name']];
                if ($bank > 0) $lines[] = ['account' => Posting::accountGl($bankAcct), 'debit' => $bank, 'memo' => 'Salesman payment bank — ' . $sm['name']];
                $lines[] = ['account' => 'SM_RECEIVABLE', 'credit' => $total, 'memo' => 'Salesman payment — ' . $sm['name']];
                $journalId = Accounting::postEntry($lines, ['date' => $date, 'voucher_type' => 'SP', 'reference_type' => 'salesman_payment', 'reference_id' => $pid, 'memo' => 'Salesman goods payment — ' . $sm['name'], 'idem_key' => 'salesman_payment:' . $pid]);
                $entryNo = db_val('SELECT entry_no FROM journal_entries WHERE id = ?', [$journalId]);
                $prev = (float)db_val('SELECT COALESCE(SUM(debit) - SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ?', [$id]);
                db_insert('salesman_ledger', ['salesman_id' => $id, 'date' => $date, 'doc_type' => 'payment', 'doc_id' => $pid, 'doc_no' => $entryNo, 'debit' => 0, 'credit' => $total, 'balance' => round($prev - $total, 2), 'notes' => ($notes ?: 'Goods payment') . ' (' . strtoupper($method) . ')', 'created_by' => $userId ?: null, 'created_at' => now_local()]);
                db_exec('UPDATE salesman_payments SET journal_entry_id = ?, receipt_no = ? WHERE id = ?', [$journalId, $entryNo, $pid]);
                foreach ($allocation['allocations'] as $issueId => $amount) {
                    if ($amount <= 0) continue;
                    db_insert('salesman_payment_allocations', [
                        'payment_id' => $pid, 'issue_id' => (int)$issueId, 'salesman_id' => $id,
                        'amount' => round($amount, 2), 'created_at' => now_local(),
                    ]);
                }
                audit('payment', 'salesman_payments', $pid, 'Salesman goods payment Rs ' . number_format($total, 2) . ' (cash ' . number_format($cash, 2) . ' / bank ' . number_format($bank, 2) . '); allocated to ' . count($allocation['allocations']) . ' invoice(s)');
                try { Notifications::notifySalesmanPayment($id, $total, $entryNo ?: ('SP-' . $pid)); } catch (Throwable $e) { error_log('notify salesman payment: ' . $e->getMessage()); }
            });
            $extra = $allocation['unallocated'] > 0.009 ? ' ' . sprintf(__('Rs %s kept on account (no outstanding invoice).'), money($allocation['unallocated'], false)) : '';
            flash('success', sprintf(__('Payment received and posted — receipt %s generated, allocated across %d invoice(s).'), db_val('SELECT receipt_no FROM salesman_payments WHERE id = ?', [$pid]), count($allocation['allocations'])) . $extra);
        } catch (Throwable $e) {
            error_log('salesman payment: ' . $e->getMessage());
            flash('error', __('Payment could not be posted: ') . $e->getMessage());
        }
        redirect($back);
    }

    /** Printable payment receipt for one salesman payment. */
    public static function paymentPrint(): void {
        $id = intv(inp('id')); $pid = intv(inp('pid'));
        $p = $id && $pid ? db_one("SELECT p.*, s.name AS salesman_name, s.code AS salesman_code, s.phone AS salesman_phone, u.full_name AS received_full_name, u.username AS received_username, ca.name AS cash_account, ba.name AS bank_account FROM salesman_payments p JOIN salesmen s ON s.id = p.salesman_id LEFT JOIN users u ON u.id = p.received_by LEFT JOIN cash_bank_accounts ca ON ca.id = p.cash_account_id LEFT JOIN cash_bank_accounts ba ON ba.id = p.bank_account_id WHERE p.id = ? AND p.salesman_id = ? AND p.status = 'posted'", [$pid, $id]) : null;
        if (!$p) { http_response_code(404); view('error/404', ['title' => 'Receipt not found']); return; }
        $allocations = db_all("SELECT i.issue_no, i.date, spa.amount
            FROM salesman_payment_allocations spa JOIN salesman_issues i ON i.id = spa.issue_id
            WHERE spa.payment_id = ? ORDER BY i.date, i.id", [$pid]);
        view('salesmen/payment_print', ['p' => $p, 'allocations' => $allocations, 'title' => 'Recovery Receipt ' . ($p['receipt_no'] ?: $p['id'])]);
    }

    /** Collection + handover + expense stats for the Hisab page.
     *  Only expenses paid out of customer collections deduct from what the salesman holds (booked Dr expense / Cr SM_RECEIVABLE).
     *  Company-paid expenses are P&L company money — they never affect the salesman's settlement. */
    private static function hisabStats(int $smId): array {
        $collected = (float)db_val('SELECT COALESCE(SUM(amount),0) FROM salesman_collections WHERE salesman_id = ?', [$smId]);
        $cashCollected = (float)db_val('SELECT COALESCE(SUM(cash_amount),0) FROM salesman_collections WHERE salesman_id = ?', [$smId]);
        $bankCollected = (float)db_val('SELECT COALESCE(SUM(bank_amount),0) FROM salesman_collections WHERE salesman_id = ?', [$smId]);
        $handedCash = (float)db_val("SELECT COALESCE(SUM(cash_amount),0) FROM salesman_payments WHERE salesman_id = ? AND status = 'posted'", [$smId]);
        $handedBank = (float)db_val("SELECT COALESCE(SUM(bank_amount),0) FROM salesman_payments WHERE salesman_id = ? AND status = 'posted'", [$smId]);
        $approvedExpenses = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_expenses WHERE salesman_id = ? AND status = 'posted' AND COALESCE(paid_from, 'company') = 'collection'", [$smId]);
        $companyExpenses = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_expenses WHERE salesman_id = ? AND status = 'posted' AND COALESCE(paid_from, 'company') = 'company'", [$smId]);
        $outstanding = round($collected - $handedCash - $handedBank - $approvedExpenses, 2);
        return ['collected' => $collected, 'cash_collected' => $cashCollected, 'bank_collected' => $bankCollected,
            'handed_cash' => $handedCash, 'handed_bank' => $handedBank, 'handed_total' => round($handedCash + $handedBank, 2),
            'approved_expenses' => $approvedExpenses, 'company_expenses' => $companyExpenses, 'outstanding' => $outstanding];
    }

    /* ============ Daily Settlement (day cockpit — snapshot on sign-off, no duplicate money posting) ============ */

    /** All numbers for the salesman + date window — computed live, never typed. */
    private static function dayWindow(int $id, string $date): array {
        $issue = db_one("SELECT COUNT(i.id) AS cnt,
            COALESCE(SUM(sii.qty),0) AS units,
            COALESCE(SUM(sii.line_payable),0) AS value,
            COALESCE(SUM(sii.bonus_qty),0) AS bonus_qty,
            COALESCE(SUM(sii.bonus_cost),0) AS bonus_value
            FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id
            WHERE i.salesman_id = ? AND i.date = ? AND i.issue_type <> 'salesman_return' AND i.status = 'posted'", [$id, $date]);
        $ret = db_one("SELECT COUNT(DISTINCT i.id) AS cnt,
            COALESCE(SUM(CASE WHEN sii.return_condition = 'damaged' THEN 0 ELSE sii.qty END),0) AS good_units,
            COALESCE(SUM(CASE WHEN sii.return_condition = 'damaged' THEN sii.qty ELSE 0 END),0) AS damaged_units,
            COALESCE(SUM(CASE WHEN sii.return_condition = 'damaged' THEN 0 ELSE sii.line_payable END),0) AS value
            FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id
            WHERE i.salesman_id = ? AND i.date = ? AND i.issue_type = 'salesman_return' AND i.status = 'posted'", [$id, $date]);
        $coll = db_one("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total, COALESCE(SUM(cash_amount),0) AS cash, COALESCE(SUM(bank_amount),0) AS bank
            FROM salesman_collections WHERE salesman_id = ? AND date = ?", [$id, $date]);
        $han = db_one("SELECT COALESCE(SUM(cash_amount),0) AS cash, COALESCE(SUM(bank_amount),0) AS bank
            FROM salesman_payments WHERE salesman_id = ? AND date = ? AND status = 'posted'", [$id, $date]);
        $expC = db_one("SELECT COALESCE(SUM(CASE WHEN expense_type IN ('diesel','vehicle','repair') THEN amount ELSE 0 END),0) AS vehicle,
            COALESCE(SUM(CASE WHEN expense_type IN ('food','hotel') THEN amount ELSE 0 END),0) AS food,
            COALESCE(SUM(CASE WHEN expense_type = 'other' THEN amount ELSE 0 END),0) AS other,
            COALESCE(SUM(amount),0) AS total
            FROM salesman_expenses WHERE salesman_id = ? AND date = ? AND status = 'posted' AND COALESCE(paid_from,'company') = 'collection'", [$id, $date]);
        $companyExp = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_expenses WHERE salesman_id = ? AND date = ? AND status = 'posted' AND COALESCE(paid_from,'company') = 'company'", [$id, $date]);
        $pending = (int)db_val("SELECT COUNT(*) FROM salesman_issues WHERE salesman_id = ? AND status = 'pending_approval'", [$id])
                 + (int)db_val("SELECT COUNT(*) FROM salesman_expenses WHERE salesman_id = ? AND status = 'pending'", [$id]);
        $outstanding = (float)self::hisabStats($id)['outstanding'];
        $activity = ((float)$issue['units'] + (float)$ret['good_units'] + (float)$coll['total'] + (float)$han['cash'] + (float)$han['bank'] + (float)$expC['total']) > 0;
        if ($outstanding > 0.004) $status = 'outstanding';
        elseif ($pending > 0) $status = 'pending';
        elseif ($activity && (float)$coll['total'] > (float)$han['cash'] + (float)$han['bank'] + (float)$expC['total'] + 0.009) $status = 'partial';
        elseif ($activity) $status = 'settled';
        else $status = 'idle';
        return [
            'issueCnt' => (int)$issue['cnt'], 'issueUnits' => (float)$issue['units'], 'issueValue' => (float)$issue['value'],
            'issueBonusQty' => (float)$issue['bonus_qty'], 'issueBonusValue' => (float)$issue['bonus_value'],
            'retCnt' => (int)$ret['cnt'], 'retGoodUnits' => (float)$ret['good_units'], 'retDamagedUnits' => (float)$ret['damaged_units'], 'retValue' => (float)$ret['value'],
            'collCnt' => (int)$coll['cnt'], 'collTotal' => (float)$coll['total'], 'collCash' => (float)$coll['cash'], 'collBank' => (float)$coll['bank'],
            'handCash' => (float)$han['cash'], 'handBank' => (float)$han['bank'],
            'expVehicle' => (float)$expC['vehicle'], 'expFood' => (float)$expC['food'], 'expOther' => (float)$expC['other'], 'expCollTotal' => (float)$expC['total'],
            'expCompany' => $companyExp,
            'pending' => $pending, 'outstanding' => $outstanding, 'status' => $status,
        ];
    }

    public static function daily(): void {
        $id = intv(inp('id'));
        $sm = $id ? db_one('SELECT * FROM salesmen WHERE id = ?', [$id]) : null;
        if (!$sm) { http_response_code(404); exit('Salesman not found'); }
        $date = inp('date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : today();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (!Auth::can('salesmen.settle')) { http_response_code(403); exit('Forbidden'); }
            $summary = self::dayWindow($id, $date);
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $counted = round(num(inp('cash_counted')), 2);
            $expectedCash = round($summary['handCash'], 2);
            $todays = (int)db_val('SELECT COUNT(*) FROM salesman_settlements WHERE salesman_id = ? AND date = ?', [$id, $date]);
            $reference = 'DS-' . date('ymd', strtotime($date)) . '-' . str_pad((string)($todays + 1), 2, '0', STR_PAD_LEFT);
            try {
                $sid = (int)db_insert('salesman_settlements', [
                    'salesman_id' => $id, 'date' => $date,
                    'sales_total' => round($summary['issueValue'], 2), 'collected_total' => round($summary['collTotal'], 2),
                    'cash_counted' => $counted, 'expected_cash' => $expectedCash, 'variance' => round($counted - $expectedCash, 2),
                    'issued_value' => round($summary['issueValue'], 2), 'additional_issued' => round($summary['issueValue'], 2),
                    'returns_value' => round($summary['retValue'], 2), 'collections_total' => round($summary['collTotal'], 2),
                    'cash_received' => round($summary['handCash'], 2), 'bank_received' => round($summary['handBank'], 2),
                    'expenses_total' => round($summary['expCollTotal'], 2), 'remaining_payable' => round($summary['outstanding'], 2),
                    'reference' => $reference, 'notes' => inp('notes') ?: null, 'received_by' => $userId ?: null,
                    'created_by' => $userId ?: null, 'created_at' => now_local(),
                ]);
                audit('settle', 'salesman_settlements', $sid, "Daily settlement $reference for salesman $id on $date");
                try { Notifications::notifySalesmanSettlement($sid, $reference); } catch (Throwable $e) { error_log('notify settlement: ' . $e->getMessage()); }
                redirect('salesmen/' . $id . '/daily/print/' . $sid);
            } catch (Throwable $e) {
                error_log('daily settlement save: ' . $e->getMessage());
                flash('error', $e->getMessage());
                redirect('salesmen/' . $id . '/daily?date=' . $date);
            }
        }

        $summary = self::dayWindow($id, $date);
        $issues = db_all("SELECT i.id, i.issue_no, i.created_at, i.status,
            COALESCE(SUM(sii.qty),0) AS units, COALESCE(SUM(sii.line_payable),0) AS value, COALESCE(SUM(sii.bonus_qty),0) AS bonus_qty
            FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id
            WHERE i.salesman_id = ? AND i.date = ? AND i.issue_type <> 'salesman_return' GROUP BY i.id ORDER BY i.id DESC", [$id, $date]);
        $returnsList = db_all("SELECT i.id, i.issue_no, i.created_at,
            COALESCE(SUM(sii.qty),0) AS units,
            COALESCE(SUM(CASE WHEN sii.return_condition = 'damaged' THEN 0 ELSE sii.line_payable END),0) AS value
            FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id
            WHERE i.salesman_id = ? AND i.date = ? AND i.issue_type = 'salesman_return' GROUP BY i.id ORDER BY i.id DESC", [$id, $date]);
        $collections = db_all("SELECT c.*, COALESCE(cu.name, c.contact_name) AS customer_name, ca.name AS account_name FROM salesman_collections c LEFT JOIN customers cu ON cu.id = c.customer_id LEFT JOIN cash_bank_accounts ca ON ca.id = c.account_id WHERE c.salesman_id = ? AND c.date = ? ORDER BY c.id DESC", [$id, $date]);
        $payments = db_all("SELECT p.*, COALESCE(ca.name, ba.name) AS account_name FROM salesman_payments p LEFT JOIN cash_bank_accounts ca ON ca.id = p.cash_account_id LEFT JOIN cash_bank_accounts ba ON ba.id = p.bank_account_id WHERE p.salesman_id = ? AND p.date = ? AND p.status = 'posted' ORDER BY p.id DESC", [$id, $date]);
        $expenses = db_all("SELECT e.*, u.full_name AS requested_by_name FROM salesman_expenses e LEFT JOIN users u ON u.id = e.requested_by WHERE e.salesman_id = ? AND e.date = ? AND e.status = 'posted' ORDER BY e.id DESC", [$id, $date]);
        $prevSettlements = db_all("SELECT s.*, u.full_name AS received_full_name, u.username FROM salesman_settlements s LEFT JOIN users u ON u.id = s.received_by WHERE s.salesman_id = ? ORDER BY s.id DESC LIMIT 15", [$id]);
        view('salesmen/daily', ['sm' => $sm, 'date' => $date, 'summary' => $summary,
            'issues' => $issues, 'returnsList' => $returnsList, 'collections' => $collections, 'payments' => $payments, 'expenses' => $expenses,
            'prevSettlements' => $prevSettlements, 'tab' => 'daily', 'title' => 'Daily Settlement — ' . $sm['name']]);
    }

    public static function dailyPrint(): void {
        $id = intv(inp('id'));
        $sid = intv(inp('sid'));
        $sm = $id ? db_one('SELECT * FROM salesmen WHERE id = ?', [$id]) : null;
        $st = $sid ? db_one('SELECT s.*, u.full_name AS received_full_name, u.username AS received_username FROM salesman_settlements s LEFT JOIN users u ON u.id = s.received_by WHERE s.id = ? AND s.salesman_id = ?', [$sid, $id]) : null;
        if (!$sm || !$st) { http_response_code(404); exit('Settlement not found'); }
        $d = $st['date'];
        $details = [
            'issues' => db_all("SELECT i.issue_no, COALESCE(SUM(sii.qty),0) AS units, COALESCE(SUM(sii.line_payable),0) AS value FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id WHERE i.salesman_id = ? AND i.date = ? AND i.issue_type <> 'salesman_return' GROUP BY i.id", [$id, $d]),
            'collections' => db_all("SELECT c.collection_no, COALESCE(cu.name, c.contact_name, '—') AS customer, c.amount, c.cash_amount, c.bank_amount FROM salesman_collections c LEFT JOIN customers cu ON cu.id = c.customer_id WHERE c.salesman_id = ? AND c.date = ?", [$id, $d]),
            'payments' => db_all("SELECT p.receipt_no, p.cash_amount, p.bank_amount, p.total_amount FROM salesman_payments p WHERE p.salesman_id = ? AND p.date = ? AND p.status = 'posted'", [$id, $d]),
            'expenses' => db_all("SELECT e.expense_type, e.description, e.amount, COALESCE(e.paid_from,'company') AS paid_from FROM salesman_expenses e WHERE e.salesman_id = ? AND e.date = ? AND e.status = 'posted'", [$id, $d]),
        ];
        view('salesmen/daily_print', ['sm' => $sm, 'st' => $st, 'details' => $details, 'tab' => 'daily', 'title' => 'Settlement ' . ($st['reference'] ?: $st['id'])]);
    }

    /* ============ Complete Overview (live all-time position + timeline + print) ============ */

    private static function overviewStats(int $id): array {
        $hisab = self::hisabStats($id);
        $goodsIssued = (float)db_val("SELECT COALESCE(SUM(sii.line_payable),0) FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE i.salesman_id = ? AND i.issue_type <> 'salesman_return' AND i.status = 'posted'", [$id]);
        $unitsIssued = (float)db_val("SELECT COALESCE(SUM(sii.qty),0) FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE i.salesman_id = ? AND i.issue_type <> 'salesman_return' AND i.status = 'posted'", [$id]);
        $bonusQty = (float)db_val("SELECT COALESCE(SUM(sii.bonus_qty),0) FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE i.salesman_id = ? AND i.issue_type <> 'salesman_return' AND i.status = 'posted'", [$id]);
        $bonusValue = (float)db_val("SELECT COALESCE(SUM(sii.bonus_cost),0) FROM salesman_issue_items sii JOIN salesman_issues i ON i.id = sii.issue_id WHERE i.salesman_id = ? AND i.issue_type <> 'salesman_return' AND i.status = 'posted'", [$id]);
        $returns = db_one("SELECT COUNT(DISTINCT i.id) AS cnt,
            COALESCE(SUM(CASE WHEN sii.return_condition = 'damaged' THEN 0 ELSE sii.qty END),0) AS good_units,
            COALESCE(SUM(CASE WHEN sii.return_condition = 'damaged' THEN sii.qty ELSE 0 END),0) AS damaged_units,
            COALESCE(SUM(CASE WHEN sii.return_condition = 'damaged' THEN 0 ELSE sii.line_payable END),0) AS value
            FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id WHERE i.salesman_id = ? AND i.issue_type = 'salesman_return' AND i.status = 'posted'", [$id]);
        $goodsPayable = (float)db_val('SELECT COALESCE(SUM(debit) - SUM(credit),0) FROM salesman_ledger WHERE salesman_id = ?', [$id]);
        $sm = db_one('SELECT warehouse_id FROM salesmen WHERE id = ?', [$id]);
        $stockUnits = 0.0; $stockValue = 0.0;
        if ($sm && $sm['warehouse_id']) {
            $sb = db_one('SELECT COALESCE(SUM(b.qty),0) AS q, COALESCE(SUM(b.qty * b.avg_cost),0) AS v FROM stock_balances b WHERE b.warehouse_id = ?', [(int)$sm['warehouse_id']]);
            $stockUnits = (float)$sb['q']; $stockValue = (float)$sb['v'];
        }
        $handedCash = (float)db_val("SELECT COALESCE(SUM(cash_amount),0) FROM salesman_payments WHERE salesman_id = ? AND status = 'posted'", [$id]);
        $handedBank = (float)db_val("SELECT COALESCE(SUM(bank_amount),0) FROM salesman_payments WHERE salesman_id = ? AND status = 'posted'", [$id]);
        $exp = db_one("SELECT COALESCE(SUM(CASE WHEN expense_type IN ('diesel','vehicle','repair') THEN amount ELSE 0 END),0) AS vehicle,
            COALESCE(SUM(CASE WHEN expense_type IN ('food','hotel') THEN amount ELSE 0 END),0) AS food,
            COALESCE(SUM(CASE WHEN expense_type = 'other' THEN amount ELSE 0 END),0) AS other,
            COALESCE(SUM(amount),0) AS total FROM salesman_expenses WHERE salesman_id = ? AND status = 'posted'", [$id]);
        $salaryRow = db_one('SELECT join_date, salary FROM salesmen WHERE id = ?', [$id]);
        $salaryStats = PeopleDirectory::salaryStats(['person_type' => 'salesman', 'id' => (int)$id, 'join_date' => $salaryRow['join_date'] ?? null, 'salary_rate' => (float)($salaryRow['salary'] ?? 0), 'salary_type' => 'monthly'], null);
        $adv = db_one("SELECT COALESCE(SUM(amount),0) AS given, COALESCE(SUM(deducted),0) AS recovered, COALESCE(SUM(CASE WHEN amount > deducted THEN amount - deducted ELSE 0 END),0) AS outstanding FROM salesman_salary_advances WHERE salesman_id = ?", [$id]);
        // Claims context (his customers) + supplier claim goods ledger (company)
        $custIds = array_column(db_all("SELECT DISTINCT c.id FROM customers c WHERE c.salesman_id = ? UNION SELECT customer_id FROM salesman_customers WHERE salesman_id = ?", [$id, $id]), 'id');
        $custClaims = ['total' => 0, 'amount' => 0.0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
        if ($custIds) {
            $in = implode(',', array_fill(0, count($custIds), '?'));
            $cc = db_one("SELECT COUNT(*) AS total, COALESCE(SUM(amount),0) AS amount,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
                FROM claims WHERE customer_id IN ($in)", $custIds);
            $custClaims = ['total' => (int)$cc['total'], 'amount' => (float)$cc['amount'], 'approved' => (int)$cc['approved'], 'pending' => (int)$cc['pending'], 'rejected' => (int)$cc['rejected']];
        }
        $cg = db_one("SELECT COUNT(*) AS total, COALESCE(SUM(good_qty),0) AS good_qty, COALESCE(SUM(damaged_qty),0) AS damaged_qty, COALESCE(SUM(rejected_qty),0) AS rejected_qty,
            COALESCE(SUM(claim_amount),0) AS claim_amount, COALESCE(SUM(cash_paid),0) AS cash_paid,
            SUM(CASE WHEN status = 'approved' OR status = 'posted' THEN 1 ELSE 0 END) AS approved
            FROM claim_goods", []);
        $pending = (int)db_val("SELECT COUNT(*) FROM salesman_issues WHERE salesman_id = ? AND status = 'pending_approval'", [$id])
                 + (int)db_val("SELECT COUNT(*) FROM salesman_expenses WHERE salesman_id = ? AND status = 'pending'", [$id]);
        // All-time outstanding = hisab (collected − handed − collection-paid expenses) — identical to the ledger balance in this model. Never subtract handovers twice.
        $outstanding = (float)($hisab['outstanding'] ?? 0);
        return ['hisab' => $hisab, 'goodsIssued' => $goodsIssued, 'unitsIssued' => $unitsIssued, 'bonusQty' => $bonusQty, 'bonusValue' => $bonusValue,
            'returnsCnt' => (int)$returns['cnt'], 'retGoodUnits' => (float)$returns['good_units'], 'retDamagedUnits' => (float)$returns['damaged_units'], 'retValue' => (float)$returns['value'],
            'stockUnits' => $stockUnits, 'stockValue' => $stockValue, 'goodsPayable' => (float)$goodsPayable,
            'handCash' => $handedCash, 'handBank' => $handedBank, 'handTotal' => round($handedCash + $handedBank, 2),
            'expVehicle' => (float)$exp['vehicle'], 'expFood' => (float)$exp['food'], 'expOther' => (float)$exp['other'], 'expTotal' => (float)$exp['total'],
            'salaryEarned' => (float)$salaryStats['earned'], 'salaryPaid' => (float)$salaryStats['paid'], 'salaryRemaining' => (float)$salaryStats['remaining'],
            'advGiven' => (float)$adv['given'], 'advRecovered' => (float)$adv['recovered'], 'advOutstanding' => (float)$adv['outstanding'],
            'custClaims' => $custClaims, 'supplierCG' => ['total' => (int)$cg['total'], 'good_qty' => (float)$cg['good_qty'], 'damaged_qty' => (float)$cg['damaged_qty'], 'rejected_qty' => (float)$cg['rejected_qty'], 'claim_amount' => (float)$cg['claim_amount'], 'cash_paid' => (float)$cg['cash_paid'], 'approved' => (int)$cg['approved']],
            'pending' => $pending, 'outstanding' => $outstanding];
    }

    private static function overviewTimeline(int $id, string $from, string $to): array {
        $events = [];
        $push = function (?string $ts, string $date, string $type, string $ref, string $descr, float $issue = 0, float $ret = 0, float $cash = 0, float $bank = 0, float $expense = 0, ?float $balance = null) use (&$events) {
            $events[] = ['ts' => $ts ?: $date, 'date' => $date, 'type' => $type, 'ref' => $ref, 'descr' => $descr,
                'issue' => $issue, 'returns' => $ret, 'cash' => $cash, 'bank' => $bank, 'expense' => $expense, 'balance' => $balance];
        };
        foreach (db_all("SELECT i.id, i.issue_no, i.date, i.created_at, i.status, i.issue_type, COALESCE(SUM(sii.line_payable),0) AS value, COALESCE(SUM(sii.qty),0) AS units
            FROM salesman_issues i JOIN salesman_issue_items sii ON sii.issue_id = i.id
            WHERE i.salesman_id = ? AND i.date BETWEEN ? AND ? GROUP BY i.id ORDER BY i.id DESC", [$id, $from, $to]) as $r) {
            $isReturn = $r['issue_type'] === 'salesman_return';
            $balance = (float)db_val("SELECT balance FROM salesman_ledger WHERE doc_type = ? AND doc_id = ? ORDER BY id DESC LIMIT 1", [$isReturn ? 'return' : 'issue', (int)$r['id']]);
            $pending = $r['status'] === 'pending_approval';
            $push($r['created_at'], $r['date'], $isReturn ? 'Goods Return' : 'Goods Issue', ($r['issue_no'] ?: ('#' . $r['id'])),
                ($pending ? 'PENDING APPROVAL — ' : '') . ($isReturn ? 'Goods returned to warehouse' : 'Goods issued to salesman'),
                $isReturn ? 0 : (float)$r['value'], $isReturn ? (float)$r['value'] : 0, 0, 0, 0, $balance);
        }
        foreach (db_all("SELECT c.id, c.collection_no, c.date, c.created_at, c.amount, c.cash_amount, c.bank_amount, COALESCE(cu.name, c.contact_name, 'Walk-in') AS customer
            FROM salesman_collections c LEFT JOIN customers cu ON cu.id = c.customer_id WHERE c.salesman_id = ? AND c.date BETWEEN ? AND ? ORDER BY c.id DESC", [$id, $from, $to]) as $r) {
            $push($r['created_at'], $r['date'], 'Collection', $r['collection_no'], 'Customer collection — ' . $r['customer'], 0, 0, (float)$r['cash_amount'], (float)$r['bank_amount'], 0, null);
        }
        foreach (db_all("SELECT p.id, p.receipt_no, p.date, p.created_at, p.cash_amount, p.bank_amount, p.total_amount
            FROM salesman_payments p WHERE p.salesman_id = ? AND p.date BETWEEN ? AND ? AND p.status = 'posted' ORDER BY p.id DESC", [$id, $from, $to]) as $r) {
            $balance = (float)db_val("SELECT balance FROM salesman_ledger WHERE doc_type = 'payment' AND doc_id = ? ORDER BY id DESC LIMIT 1", [(int)$r['id']]);
            $push($r['created_at'], $r['date'], 'Handover', $r['receipt_no'] ?: ('SP-' . $r['id']), 'Handed over to company', 0, 0, (float)$r['cash_amount'], (float)$r['bank_amount'], 0, $balance);
        }
        foreach (db_all("SELECT e.id, e.date, e.created_at, e.expense_type, e.amount, e.description, COALESCE(e.paid_from,'company') AS paid_from
            FROM salesman_expenses e WHERE e.salesman_id = ? AND e.date BETWEEN ? AND ? AND e.status = 'posted' ORDER BY e.id DESC", [$id, $from, $to]) as $r) {
            $balance = strtolower($r['paid_from']) === 'collection' ? (float)db_val("SELECT balance FROM salesman_ledger WHERE doc_type = 'expense' AND doc_id = ? ORDER BY id DESC LIMIT 1", [(int)$r['id']]) : null;
            $push($r['created_at'], $r['date'], 'Expense', strtoupper($r['expense_type']), ucfirst((string)$r['paid_from']) . '-paid: ' . $r['description'], 0, 0, 0, 0, (float)$r['amount'], $balance);
        }
        foreach (db_all("SELECT p.id, p.date, p.created_at, p.amount FROM salesman_salary_payments p WHERE p.salesman_id = ? AND p.date BETWEEN ? AND ? AND p.status = 'paid' ORDER BY p.id DESC", [$id, $from, $to]) as $r) {
            $push($r['created_at'], $r['date'], 'Salary', 'SP-' . $r['id'], 'Salary payment', 0, 0, (float)$r['amount'], 0, 0, null);
        }
        foreach (db_all("SELECT a.id, a.date, a.created_at, a.amount FROM salesman_salary_advances a WHERE a.salesman_id = ? AND a.date BETWEEN ? AND ? AND a.status = 'paid' ORDER BY a.id DESC", [$id, $from, $to]) as $r) {
            $push($r['created_at'], $r['date'], 'Advance', 'ADV-' . $r['id'], 'Salary advance disbursed', 0, 0, (float)$r['amount'], 0, 0, null);
        }
        foreach (db_all("SELECT s.id, s.reference, s.date, s.created_at, s.remaining_payable FROM salesman_settlements s WHERE s.salesman_id = ? AND s.date BETWEEN ? AND ? ORDER BY s.id DESC", [$id, $from, $to]) as $r) {
            $push($r['created_at'], $r['date'], 'Settlement', $r['reference'] ?: ('DS-' . $r['id']), 'Daily settlement signed off — remaining Rs ' . number_format((float)$r['remaining_payable'], 2), 0, 0, 0, 0, 0, null);
        }
        usort($events, fn($a, $b) => strcmp((string)$b['ts'], (string)$a['ts']));
        return $events;
    }

    public static function overview(): void {
        $id = intv(inp('id'));
        $sm = $id ? db_one('SELECT * FROM salesmen WHERE id = ?', [$id]) : null;
        if (!$sm) { http_response_code(404); exit('Salesman not found'); }
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('from', date('Y-m-01', strtotime('-1 month')))) ? inp('from', date('Y-m-01', strtotime('-1 month'))) : date('Y-m-01', strtotime('-1 month'));
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('to', today())) ? inp('to', today()) : today();
        if ($from > $to) [$from, $to] = [$to, $from];
        $typeMap = ['issue' => 'Goods Issue', 'return' => 'Goods Return', 'collection' => 'Collection', 'handover' => 'Handover', 'expense' => 'Expense', 'salary' => 'Salary', 'advance' => 'Advance', 'settlement' => 'Settlement'];
        $type = in_array(inp('type'), array_merge(['all'], array_keys($typeMap)), true) ? inp('type', 'all') : 'all';
        $stats = self::overviewStats($id);
        $events = self::overviewTimeline($id, $from, $to);
        if ($type !== 'all') { $label = $typeMap[$type]; $events = array_values(array_filter($events, fn($e) => $e['type'] === $label)); }
        $summary = ['issue' => 0, 'returns' => 0, 'cash' => 0, 'bank' => 0, 'expense' => 0];
        foreach ($events as $e) { $summary['issue'] += $e['issue']; $summary['returns'] += $e['returns']; $summary['cash'] += $e['cash']; $summary['bank'] += $e['bank']; $summary['expense'] += $e['expense']; }
        view('salesmen/overview', ['sm' => $sm, 'stats' => $stats, 'events' => $events, 'from' => $from, 'to' => $to, 'type' => $type, 'summary' => $summary, 'tab' => 'overview', 'title' => 'Complete Overview — ' . $sm['name']]);
    }

    public static function overviewPrint(): void {
        $id = intv(inp('id'));
        $sm = $id ? db_one('SELECT * FROM salesmen WHERE id = ?', [$id]) : null;
        if (!$sm) { http_response_code(404); exit('Salesman not found'); }
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('from', date('Y-m-01', strtotime('-3 month')))) ? inp('from', date('Y-m-01', strtotime('-3 month'))) : date('Y-m-01', strtotime('-3 month'));
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('to', today())) ? inp('to', today()) : today();
        if ($from > $to) [$from, $to] = [$to, $from];
        $stats = self::overviewStats($id);
        $events = self::overviewTimeline($id, $from, $to);
        view('salesmen/overview_print', ['sm' => $sm, 'stats' => $stats, 'events' => $events, 'from' => $from, 'to' => $to, 'tab' => 'overview', 'title' => 'Statement — ' . $sm['name']]);
    }

    /* ============ Salesman claims (reuses existing claims tables, no duplication) ============ */

    public static function claims(): void {
        $id = intv(inp('id'));
        $sm = $id ? db_one('SELECT * FROM salesmen WHERE id = ?', [$id]) : null;
        if (!$sm) { http_response_code(404); exit('Salesman not found'); }

        // Customer claims associated with this salesman's customers (existing claims table)
        $custIds = array_column(db_all("SELECT DISTINCT c.id FROM customers c WHERE c.salesman_id = ? UNION SELECT customer_id FROM salesman_customers WHERE salesman_id = ?", [(int)$id, (int)$id]), 'id');
        $custClaims = [];
        if ($custIds) {
            $in = implode(',', array_fill(0, count($custIds), '?'));
            $custClaims = db_all(
                "SELECT cl.*, c.name AS customer, c.code AS customer_code FROM claims cl JOIN customers c ON c.id = cl.customer_id WHERE cl.customer_id IN ($in) ORDER BY cl.date DESC, cl.id DESC",
                $custIds
            );
        }

        // Supplier claim goods (company-wide) with line items merged
        $flat = [];
        foreach (db_all("SELECT cg.*, s.name AS supplier_name FROM claim_goods cg JOIN suppliers s ON s.id = cg.supplier_id WHERE cg.status <> 'cancelled' ORDER BY cg.date DESC, cg.id DESC") as $cg) {
            $items = db_all("SELECT p.sku, p.name, cgi.good_qty, cgi.damaged_qty, cgi.rejected_qty, cgi.line_claim FROM claim_goods_items cgi JOIN products p ON p.id = cgi.product_id WHERE cgi.claim_goods_id = ? ORDER BY p.name", [(int)$cg['id']]);
            foreach ($items as $it) {
                $flat[] = $it + [
                    'date' => $cg['date'],
                    'claim_no' => $cg['claim_no'],
                    'supplier' => $cg['supplier_name'],
                    'status' => $cg['status'],
                    'payment_type' => $cg['payment_type'],
                    'cg_id' => (int)$cg['id'],
                ];
            }
        }

        $tab = 'claims';
        view('salesmen/claims', [
            'sm' => $sm,
            'tab' => $tab,
            'custClaims' => $custClaims,
            'supplierClaims' => $flat,
            'title' => 'Claims — ' . $sm['name'],
        ]);
    }
}
