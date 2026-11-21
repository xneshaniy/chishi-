<?php
/**
 * Unified Employees module — one section for all staff (Labour, Manager, Accountant, Cleaning Staff, Office Staff, Other).
 * Old standalone Labour / Staff sections have been merged here; every staff member lives in the single `employees` table.
 * Job type is stored in `designation` (kept free-text; dropdown presets + custom values supported).
 */
final class EmployeesController {

    /* ============ helpers ============ */

    /** Suggested Job Title presets (user can type any custom value too). */
    public static function jobTitleSuggestions(): array {
        return ['Labour', 'Manager', 'Accountant', 'Cleaning Staff', 'Office Staff', 'Other'];
    }

    /** Shared edit form data builder: photo + cnic + cnic back + documents. */
    private static function handleDateUploads(int $id): void {
        self::handleUploads($id);
    }

    /* ============ Employees list (card grid) ============ */

    public static function index(): void {
        $q = trim(inp('q'));
        $title = inp('JobTitle', '');
        $showInactive = isset($_GET['show_inactive']);
        $params = [];
        $where = $showInactive ? 'WHERE COALESCE(is_archived, 0) = 0' : 'WHERE is_active = 1 AND COALESCE(is_archived, 0) = 0';
        if ($q !== '') {
            $where .= " AND (code LIKE ? OR name LIKE ? OR cnic LIKE ? OR designation LIKE ? OR department LIKE ?)";
            $like = '%' . $q . '%';
            $params = array_fill(0, 5, $like);
        }
        if ($title !== '') { $where .= ' AND designation = ?'; $params[] = $title; }
        $employees = db_all("SELECT id, code, name, designation, department, phone, photo_path, join_date, is_active FROM employees $where ORDER BY is_active DESC, code", $params);
        $titles = db_all("SELECT DISTINCT designation FROM employees WHERE designation IS NOT NULL AND designation <> '' ORDER BY designation");
        $jobTitles = array_column($titles, 'designation');
        $totalCount = (int)db_val('SELECT COUNT(*) FROM employees WHERE is_active = 1');
        $counts = db_all("SELECT designation, COUNT(*) AS n FROM employees WHERE is_active = 1 AND designation IS NOT NULL AND designation <> '' GROUP BY designation ORDER BY n DESC");
        view('employees/index', ['employees' => $employees, 'q' => $q, 'active' => $totalCount, 'jobTitle' => $title, 'jobTitles' => $jobTitles, 'counts' => $counts, 'showInactive' => $showInactive, 'title' => 'Employees']);
    }

    /* ============ Add / edit employee (with photo + CNIC + docs) ============ */

    /** Owner/Admin toggles a profile between Active and Inactive (pending registration approvals are honoured exactly-once). */
    public static function toggleActive(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        profile_toggle_active('employees', $id);
        redirect('employees/' . $id);
    }

    public static function employeeForm(): void {
        $id = (inp('id') !== '' && (int)inp('id') > 0) ? (int)inp('id') : null;
        $employee = null;
        if ($id) { $employee = db_one('SELECT * FROM employees WHERE id = ?', [$id]); }
        $isEdit = (bool)$employee;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $desigValue = inp('designation');
            if ($desigValue === 'Other' || $desigValue === '') {
                $desigValue = trim(inp('designation_custom'));
            }
            // Salary frequency is required for every person (Monthly | Weekly, stored separately per person)
            $salaryFrequency = in_array(inp('salary_frequency'), ['monthly', 'weekly'], true) ? inp('salary_frequency') : 'monthly';
            // Owner/Admin can always set salary; anyone adding a NEW person sets it too (it goes through registration approval).
            // Only salary edits on existing profiles are Owner/Admin-only.
            $canSetSalary = is_owner_or_admin() || !$isEdit;
            $salaryRateRaw = inp('salary_rate');
            $data = [
                'name' => inp('name'),
                'cnic' => preg_replace('/\s+/', '', inp('cnic')),
                'phone' => inp('phone'),
                'address' => inp('address'),
                'designation' => $desigValue,
                'department' => inp('department'),
                'join_date' => inp('join_date') ?: null,
                'salary_frequency' => $salaryFrequency,
                'salary_type' => $salaryFrequency, // payroll engine keying (monthly|weekly supported natively)
                'salary_rate' => ($canSetSalary || !$isEdit) ? round((float)num($salaryRateRaw), 2) : (float)$employee['salary_rate'],
                'payment_method' => in_array(inp('payment_method'), ['cash', 'bank'], true) ? inp('payment_method') : (($isEdit && inp('payment_method') === '') ? $employee['payment_method'] : 'cash'),
            ];
            if (!$canSetSalary && $isEdit) {
                // Manager editing an existing profile: salary figures stay locked to what the Owner/Admin set
                $data['salary_rate'] = (float)$employee['salary_rate'];
                $data['salary_frequency'] = $employee['salary_frequency'] ?? ($employee['salary_type'] ?? 'monthly');
                $data['salary_type'] = $data['salary_frequency'];
            }
            if ($data['name'] === '') { flash('error', __('Employee name is required.')); redirect($isEdit ? 'employees/' . $employee['id'] . '/edit' : 'employees/new'); }

            if ($isEdit) {
                if (!Auth::can('employees.edit')) { http_response_code(403); exit('Forbidden'); }
                db_exec('UPDATE employees SET name=?, cnic=?, phone=?, address=?, designation=?, department=?, join_date=?, salary_type=?, salary_frequency=?, salary_rate=?, payment_method=? WHERE id=?',
                    [$data['name'], $data['cnic'] ?: null, $data['phone'] ?: null, $data['address'] ?: null, $data['designation'] ?: null, $data['department'] ?: null, $data['join_date'], $data['salary_type'], $data['salary_frequency'], $data['salary_rate'], $data['payment_method'], (int)$employee['id']]);
                $empId = (int)$employee['id'];
                flash('success', __('Employee saved.'));
            } else {
                if (!Auth::can('employees.create')) { http_response_code(403); exit('Forbidden'); }
                $code = self::nextEmployeeCode();
                $roleCodes = Auth::roleCodes();
                $isManagerRegistration = !is_owner_or_admin() && in_array('manager', $roleCodes, true);
                if ($isManagerRegistration && $data['cnic'] !== '') {
                    $dup = db_one("SELECT id, code, name FROM employees WHERE cnic = ?", [$data['cnic']]);
                    if ($dup) { flash('error', __('An employee with this CNIC already exists (#') . $dup['code'] . ' — ' . $dup['name'] . ').'); redirect('employees/new'); }
                }
                $empId = (int)db_insert('employees', $data + ['code' => $code, 'is_active' => $isManagerRegistration ? 0 : 1, 'created_at' => now_local()]);
                if ($isManagerRegistration) {
                    try {
                        Approvals::create('registration', 'employees', $empId, (float)($data['salary_rate'] ?? 0),
                            'New employee registration — ' . $data['name'] . ' (' . $code . ')',
                            (int)($_SESSION['user_id'] ?? 0));
                    } catch (Throwable $e) { error_log('registration approval request: ' . $e->getMessage()); }
                    audit('request', 'employees', $empId, 'New employee registration pending approval (by manager)');
                    flash('success', sprintf(__('Employee added. Employee ID: %s. Owner/Admin approval is required before the profile becomes active.'), $code));
                } else {
                    try { Notifications::notifyEmployeeAdded($empId, (string)$data['name']); } catch (Throwable $e) { error_log('notify employee added: ' . $e->getMessage()); }
                    flash('success', sprintf(__('Employee added. Employee ID: %s'), $code));
                }
            }

            // handle uploads (photo + CNIC front/back + documents)
            $errs = self::handleUploads($empId);
            foreach ($errs as $e) error_log('employee upload: ' . $e);
            if ($errs) foreach ($errs as $e) flash('error', __('File could not be saved. Use JPG, JPEG, PNG or WebP up to 2 MB.'));
            audit('save', 'employees', (int)$empId, ($isEdit ? 'Updated' : 'Created') . ' employee');
            redirect('employees/' . $empId);
        }

        $title = $isEdit ? __('Edit Employee') : __('Add Employee');
        view('employees/form', ['employee' => $employee, 'suggestedCode' => self::nextEmployeeCode(), 'title' => $title]);
    }

    private static function nextEmployeeCode(): string {
        $maxId = (int)db_val('SELECT COALESCE(MAX(id), 0) FROM employees');
        $code = 'EMP-' . str_pad((string)($maxId + 1), 4, '0', STR_PAD_LEFT);
        $tries = 0;
        while (db_val('SELECT 1 FROM employees WHERE code = ?', [$code])) {
            $maxId += 1000;
            $code = 'EMP-' . str_pad((string)($maxId + 1), 4, '0', STR_PAD_LEFT);
            if (++$tries > 5) break;
        }
        return $code;
    }

    private static function storeUpload(array $file, array $kinds, int $maxBytes, string $subdir, int $employeeId): ?string {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > $maxBytes) return null;
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif', 'pdf' => 'pdf'];
        $ext = $allowed[$ext] ?? null;
        if ($ext === null || !isset($kinds[$ext])) return null;
        if ($ext !== 'pdf') { $info = @getimagesize($file['tmp_name']); if (!$info) return null; }
        $id = (int)$employeeId;
        $dir = dirname(__DIR__, 2) . '/storage/uploads/employees/' . $id;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return null;
        $name = 'emp_' . $id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return null;
        return 'employees/' . $id . '/' . $name;
    }

    /** Handle photo + CNIC + documents uploads. Returns list of errors. */
    private static function handleUploads(int $employeeId): array {
        $id = (int)$employeeId;
        $errors = [];
        if (!empty($_FILES['photo']['name'])) {
            $path = PhotoUpload::store($_FILES['photo'], 'employees', $id);
            if ($path) {
                $old = db_val('SELECT photo_path FROM employees WHERE id = ?', [$id]);
                db_exec('UPDATE employees SET photo_path = ? WHERE id = ?', [$path, $id]);
                self::safeUnlink($old);
            } else {
                $errors[] = PhotoUpload::lastError() ?: 'photo upload failed';
            }
        }
        foreach (['cnic_front' => 'cnic_front_path', 'cnic_back' => 'cnic_back_path'] as $field => $column) {
            if (!empty($_FILES[$field]['name'])) {
                $path = self::storeUpload($_FILES[$field], ['jpg' => true, 'png' => true, 'webp' => true, 'pdf' => true], 5 * 1024 * 1024, $field, $id);
                if ($path) {
                    $old = db_val("SELECT $column FROM employees WHERE id = ?", [$id]);
                    db_exec("UPDATE employees SET $column = ? WHERE id = ?", [$path, $id]);
                    self::safeUnlink($old);
                }
            }
        }
        if (!empty($_FILES['documents']['name']) && is_array($_FILES['documents']['name'])) {
            $n = count($_FILES['documents']['name']);
            for ($i = 0; $i < $n; $i++) {
                $file = ['name' => $_FILES['documents']['name'][$i], 'tmp_name' => $_FILES['documents']['tmp_name'][$i], 'error' => $_FILES['documents']['error'][$i], 'size' => $_FILES['documents']['size'][$i]];
                $path = self::storeUpload($file, ['jpg' => true, 'png' => true, 'webp' => true, 'gif' => true, 'pdf' => true], 5 * 1024 * 1024, 'documents', $id);
                if ($path) {
                    db_insert('employee_documents', ['employee_id' => $id, 'doc_type' => 'other', 'original_name' => basename((string)$file['name']), 'file_path' => $path, 'mime' => self::mimeFor($file['name']), 'size_bytes' => (int)$file['size'], 'note' => inp('documents_note') ?: null, 'uploaded_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local()]);
                }
            }
        }
        return $errors;
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

    /* ============ Photo streaming (authenticated) ============ */

    public static function photoFile(): void {
        $id = intv(inp('id'));
        $path = $id ? db_val('SELECT photo_path FROM employees WHERE id = ?', [(int)$id]) : null;
        PhotoUpload::stream($path, 'employee.jpg');
    }

    public static function employeeFile(): void {
        $id = intv(inp('id'));
        $col = preg_replace('/[^a-z_]/', '', (string)inp('col'));
        $allowed = ['photo_path' => 'profile', 'cnic_front_path' => 'cnic-front', 'cnic_back_path' => 'cnic-back'];
        if (!$id || !isset($allowed[$col])) { http_response_code(404); exit('Not found'); }
        $path = db_val("SELECT $col FROM employees WHERE id = ?", [(int)$id]);
        self::streamFile($path, 'image/jpeg', $allowed[$col] . '.jpg');
    }

    public static function downloadDocument(): void {
        $id = intv(inp('id')); $doc = intv(inp('doc'));
        $row = ($id && $doc) ? db_one('SELECT * FROM employee_documents WHERE id = ? AND employee_id = ?', [(int)$doc, (int)$id]) : null;
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

    /* ============ Inline change-photo (from profile) ============ */

    public static function photo(): void {
        if (!Auth::can('employees.edit')) { http_response_code(403); exit('Forbidden'); }
        require_post(); csrf_verify();
        $id = (int)inp('id');
        if ($id <= 0 || !db_val('SELECT 1 FROM employees WHERE id = ?', [$id])) { http_response_code(404); exit('Not found'); }
        if (empty($_FILES['photo']['name'])) {
            flash('error', __('Choose JPG, JPEG, PNG or WebP up to 2 MB.'));
            redirect('employees/' . $id);
        }
        $path = PhotoUpload::store($_FILES['photo'], 'employees', $id);
        if (!$path) {
            error_log('employee photo: ' . (PhotoUpload::lastError() ?: '?'));
            flash('error', __('Profile photo could not be saved. Use JPG, JPEG, PNG or WebP up to 2 MB.'));
        } else {
            $old = db_val('SELECT photo_path FROM employees WHERE id = ?', [$id]);
            db_exec('UPDATE employees SET photo_path = ? WHERE id = ?', [$path, $id]);
            self::safeUnlink($old);
            audit('update', 'employees', $id, 'Updated employee photo');
            flash('success', __('Employee updated.'));
        }
        redirect('employees/' . $id);
    }

    public static function uploadDocument(): void {
        if (!Auth::can('employees.edit')) { http_response_code(403); exit('Forbidden'); }
        require_post(); csrf_verify();
        $id = (int)inp('id');
        if (!$id || !db_val('SELECT 1 FROM employees WHERE id = ?', [$id])) { flash('error', __('Employee not found.')); redirect('employees'); }
        self::handleUploads($id);
        audit('update', 'employees', $id, 'Added employee documents');
        flash('success', __('Documents uploaded.'));
        redirect('employees/' . $id);
    }

    public static function deleteDocument(): void {
        if (!Auth::can('employees.edit')) { http_response_code(403); exit('Forbidden'); }
        require_post(); csrf_verify();
        $id = (int)inp('id'); $doc = (int)inp('doc');
        $row = db_one('SELECT * FROM employee_documents WHERE id = ? AND employee_id = ?', [$doc, $id]);
        if ($row) {
            self::safeUnlink($row['file_path']);
            db_exec('DELETE FROM employee_documents WHERE id = ?', [$doc]);
            audit('delete', 'employee_documents', $doc, 'Deleted document ' . $row['original_name']);
            flash('success', __('Document deleted.'));
        }
        redirect('employees/' . $id);
    }

    /* ============ Employee profile (with 8 tabs, selected via ?tab= query param) ============ */

    public static function employeeProfile(): void {
        $id = intv(inp('id'));
        $employee = $id ? db_one('SELECT * FROM employees WHERE id = ?', [(int)$id]) : null;
        if (!$employee) { http_response_code(404); view('error/404', ['title' => 'Page not found']); return; }
        $tab = inp('tab', 'profile');
        $tabs = ['profile' => true, 'salary' => true, 'attendance' => true, 'advances' => true, 'bonus' => true, 'deductions' => true, 'history' => true, 'ledger' => true];
        if (!isset($tabs[$tab])) $tab = 'profile';
        $documents = db_all('SELECT * FROM employee_documents WHERE employee_id = ? ORDER BY id DESC', [(int)$id]);
        $attTotals = StaffAttendance::employeeTotals((int)$id);
        // Salary data
        $monthly = (float)$employee['salary_rate'];
        $salaryAccrued = 0.0;
        if (!empty($employee['join_date']) && (float)$employee['salary_rate'] > 0) {
            $join = new DateTimeImmutable($employee['join_date']);
            $now = new DateTimeImmutable('first day of this month');
            $months = max(0, ((int)$now->format('Y') - (int)$join->format('Y')) * 12 + ((int)$now->format('n') - (int)$join->format('n')) + 1);
            $salaryAccrued = $months * $monthly;
        }
        $salaryPaid = (float)db_val("SELECT COALESCE(SUM(amount_paid),0) FROM payroll WHERE employee_id = ? AND status <> 'reversed'", [(int)$id]);
        $salaryRemaining = max(0, round($salaryAccrued - $salaryPaid, 2));
        $advancesOut = (float)db_val("SELECT COALESCE(SUM(CASE WHEN amount > deducted THEN amount - deducted ELSE 0 END),0) FROM employee_advances WHERE employee_id = ? AND status = 'disbursed'", [(int)$id]);
        $advancesTotal = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM employee_advances WHERE employee_id = ? AND status = 'disbursed'", [(int)$id]);
        $deductions = db_all('SELECT * FROM employee_deductions WHERE employee_id = ? ORDER BY date DESC LIMIT 20', [(int)$id]);
        $deductionsTotal = (float)db_val('SELECT COALESCE(SUM(amount),0) FROM employee_deductions WHERE employee_id = ?', [(int)$id]);
        $bonusRows = db_all('SELECT * FROM employee_bonuses WHERE employee_id = ? ORDER BY date DESC, id DESC LIMIT 20', [(int)$id]);
        $bonusTotal = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM employee_bonuses WHERE employee_id = ? AND status IN ('posted','pending')", [(int)$id]);
        $recentPayslips = db_all("SELECT * FROM payroll WHERE employee_id = ? ORDER BY period_start DESC LIMIT 6", [(int)$id]);
        $recentAttendance = db_all('SELECT * FROM staff_attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 14', [(int)$id]);
        $salaryChanges = db_all('SELECT * FROM salary_changes WHERE employee_id = ? ORDER BY effective_date DESC, id DESC LIMIT 5', [(int)$id]);
        // Salary payable ledger: payslip accruals (credit) vs salary paid (debit), running balance
        $salaryRows = [];
        foreach (db_all("SELECT period_start, period_end, net_pay, amount_paid FROM payroll WHERE employee_id = ? AND status <> 'reversed' ORDER BY period_start ASC LIMIT 200", [(int)$id]) as $r) {
            $salaryRows[] = ['date' => $r['period_end'], 'desc_text' => 'Salary accrued — ' . date_display($r['period_start']) . ' → ' . date_display($r['period_end']), 'credit' => (float)$r['net_pay'], 'debit' => 0.0, 'kind' => 'accrual'];
        }
        foreach (db_all("SELECT pp.date, pp.amount, p.period_start, p.period_end, cb.name AS account FROM payroll_payments pp JOIN payroll p ON p.id = pp.payroll_id LEFT JOIN cash_bank_accounts cb ON cb.id = pp.account_id WHERE p.employee_id = ? AND pp.status = 'paid' ORDER BY pp.date ASC, pp.id ASC LIMIT 200", [(int)$id]) as $r) {
            $salaryRows[] = ['date' => $r['date'], 'desc_text' => 'Salary paid — ' . date_display($r['period_start']) . ' → ' . date_display($r['period_end']) . ($r['account'] ? ' · ' . $r['account'] : ''), 'credit' => 0.0, 'debit' => (float)$r['amount'], 'kind' => 'payment'];
        }
        // fallback: legacy payroll rows with amount_paid but no recorded payroll_payments rows
        $paidSum = (float)db_val("SELECT COALESCE(SUM(pp.amount),0) FROM payroll_payments pp JOIN payroll p ON p.id = pp.payroll_id WHERE p.employee_id = ? AND pp.status = 'paid'", [(int)$id]);
        $legacyPaid = (float)db_val("SELECT COALESCE(SUM(amount_paid),0) FROM payroll WHERE employee_id = ? AND status <> 'reversed'", [(int)$id]) - $paidSum;
        $bal = 0.0;
        usort($salaryRows, function($a, $b) { $c = strcmp($a['date'], $b['date']); return $c !== 0 ? $c : (($a['kind'] === 'accrual' ? 0 : 1) <=> ($b['kind'] === 'accrual' ? 0 : 1)); });
        $salaryLedger = [];
        foreach ($salaryRows as $r) {
            if ($r['credit'] > 0) { $bal = round($bal + $r['credit'], 2); } else { $bal = round($bal - $r['debit'], 2); }
            $r['balance'] = $bal;
            $salaryLedger[] = $r;
        }
        if ($legacyPaid > 0.009) {
            // Legacy totals: fold into the oldest accrual line's paid column so the statement still balances
            $salaryLedger[] = ['date' => 'legacy', 'desc_text' => 'Salary paid — legacy payslip records', 'credit' => 0.0, 'debit' => round($legacyPaid, 2), 'balance' => round($bal - $legacyPaid, 2), 'kind' => 'legacy'];
        }
        $salaryLedger = array_reverse($salaryLedger);

        // Advance receivable ledger: advances disbursed (debit) vs recoveries taken from salary (credit), running balance
        $advanceRows = [];
        foreach (db_all("SELECT a.id, a.date, a.amount, a.notes, cb.name AS account FROM employee_advances a LEFT JOIN cash_bank_accounts cb ON cb.id = a.account_id WHERE a.employee_id = ? AND a.status = 'disbursed' ORDER BY a.date ASC, a.id ASC LIMIT 200", [(int)$id]) as $r) {
            $advanceRows[] = ['date' => $r['date'], 'desc_text' => 'Advance #' . (int)$r['id'] . ($r['notes'] ? ' — ' . $r['notes'] : '') . ($r['account'] ? ' · ' . $r['account'] : ''), 'debit' => (float)$r['amount'], 'credit' => 0.0, 'kind' => 'advance'];
        }
        foreach (db_all("SELECT p.period_end, pd.amount, p.period_start FROM payroll_deductions pd JOIN payroll p ON p.id = pd.payroll_id WHERE p.employee_id = ? AND pd.source_type = 'advance' ORDER BY p.period_end ASC, pd.id ASC LIMIT 200", [(int)$id]) as $r) {
            $advanceRows[] = ['date' => $r['period_end'], 'desc_text' => 'Advance recovered from salary — ' . date_display($r['period_start']) . ' → ' . date_display($r['period_end']), 'debit' => 0.0, 'credit' => (float)$r['amount'], 'kind' => 'recovery'];
        }
        usort($advanceRows, fn($a, $b) => strcmp($a['date'], $b['date']));
        $bal2 = 0.0;
        $advanceLedger = [];
        foreach ($advanceRows as $r) {
            $bal2 = round($bal2 + ($r['debit'] - $r['credit']), 2);
            $r['balance'] = $bal2;
            $advanceLedger[] = $r;
        }
        $advanceLedger = array_reverse($advanceLedger);

        // Bonuses & deductions (informational — not netted into either ledger)
        $awardsLedger = [];
        foreach (db_all("SELECT b.date, b.amount, b.reason, b.payment_method, cb.name AS account FROM employee_bonuses b LEFT JOIN cash_bank_accounts cb ON cb.id = b.account_id WHERE b.employee_id = ? AND b.status = 'posted' ORDER BY b.date DESC, b.id DESC LIMIT 40", [(int)$id]) as $r) {
            $awardsLedger[] = ['date' => $r['date'], 'desc_text' => 'Bonus — ' . $r['reason'] . ($r['account'] ? ' · ' . $r['account'] : ''), 'amount' => (float)$r['amount'], 'kind' => 'Bonus'];
        }
        foreach (db_all("SELECT date, reason, amount FROM employee_deductions WHERE employee_id = ? ORDER BY date DESC, id DESC LIMIT 40", [(int)$id]) as $r) {
            $awardsLedger[] = ['date' => $r['date'], 'desc_text' => 'Deduction — ' . $r['reason'], 'amount' => (float)$r['amount'], 'kind' => 'Deduction'];
        }
        usort($awardsLedger, fn($a, $b) => strcmp($b['date'], $a['date']));
        $workHistory = db_all("SELECT id, 'role' AS kind, designation AS detail, join_date AS effective_from, NULL AS effective_to FROM employees WHERE id = ?", [(int)$id]);

        view('employees/profile', [
            'employee' => $employee,
            'tab' => $tab,
            'documents' => $documents,
            'att' => $attTotals,
            'monthly' => $monthly,
            'salaryAccrued' => $salaryAccrued,
            'salaryPaid' => $salaryPaid,
            'salaryRemaining' => $salaryRemaining,
            'advancesTotal' => $advancesTotal,
            'advancesOut' => $advancesOut,
            'deductions' => $deductions,
            'deductionsTotal' => $deductionsTotal,
            'bonusRows' => $bonusRows,
            'bonusTotal' => $bonusTotal,
            'recentPayslips' => $recentPayslips,
            'recentAttendance' => $recentAttendance,
            'salaryChanges' => $salaryChanges,
            'salaryLedger' => $salaryLedger,
            'advanceLedger' => $advanceLedger,
            'awardsLedger' => $awardsLedger,
            'workHistory' => $workHistory,
            'title' => $employee['name'],
        ]);
    }

    /* ============ Mark attendance for one employee (used from profile) ============ */

    public static function attendanceMarkFor(): void {
        if (!Auth::can('attendance.mark')) { http_response_code(403); exit('Forbidden'); }
        require_post(); csrf_verify();
        $employeeId = intv(inp('employee_id'));
        $date = inp('date', today());
        $status = strtolower(inp('status'));
        $note = trim(inp('note'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = today();
        if (!in_array($status, ['present', 'absent', 'half_day'], true)) { flash('error', __('Attendance must be Present, Absent or Half Day.')); redirect('employees/' . $employeeId, ['tab' => 'attendance']); }
        [$ok] = StaffAttendance::mark($employeeId, $date, $status, (int)($_SESSION['user_id'] ?? 0), $note);
        flash($ok ? 'success' : 'error', $ok ? __('Attendance saved.') : __('Attendance could not be saved.'));
        redirect('employees/' . $employeeId, ['tab' => 'attendance']);
    }

    /* ============ Salary: set/update with effective date (shared with Labour) ============ */

    public static function setSalary(): void {
        // Salary changes are Owner/Admin-only — Managers cannot set or change salary figures
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden — only Owner/Admin can set salaries.'); }
        require_post(); csrf_verify();
        $employeeId = intv(inp('employee_id'));
        $employee = $employeeId ? db_one('SELECT * FROM employees WHERE id = ?', [$employeeId]) : null;
        if (!$employee) { flash('error', __('Employee not found.')); redirect('employees'); }
        $salaryType = in_array(inp('salary_type'), ['monthly', 'weekly'], true) ? inp('salary_type') : 'monthly';
        $rate = round((float)num(inp('salary_rate')), 2);
        $effective = inp('effective_date', today());
        $note = inp('note');
        if ($rate <= 0) { flash('error', __('Rate must be positive.')); redirect('employees/' . $employeeId, ['tab' => 'salary']); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective)) $effective = today();
        Database::transaction(function () use ($employeeId, $salaryType, $rate, $effective, $note, $employee) {
            db_exec('UPDATE employees SET salary_type = ?, salary_frequency = ?, salary_rate = ? WHERE id = ?', [$salaryType, $salaryType, $rate, $employeeId]);
            db_insert('salary_changes', ['employee_id' => $employeeId, 'salary_type' => $salaryType, 'old_rate' => (float)$employee['salary_rate'], 'new_rate' => $rate, 'effective_date' => $effective, 'note' => $note ?: null, 'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local()]);
        });
        audit('update', 'employees', $employeeId, 'Salary set to ' . number_format($rate, 2) . ' (' . $salaryType . ')');
        flash('success', __('Salary saved.'));
        redirect('employees/' . $employeeId, ['tab' => 'salary']);
    }

    /* ============ Deductions ============ */

    public static function addDeduction(): void {
        if (!Auth::can('employees.edit')) { http_response_code(403); exit('Forbidden'); }
        require_post(); csrf_verify();
        $employeeId = intv(inp('employee_id'));
        $amount = round((float)num(inp('amount')), 2);
        $reason = trim(inp('reason'));
        if (!$employeeId || $amount <= 0 || $reason === '') { flash('error', __('Reason and amount are required.')); redirect('employees'); }
        db_insert('employee_deductions', ['employee_id' => $employeeId, 'date' => inp('date', today()), 'reason' => $reason, 'amount' => $amount, 'approved_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local()]);
        audit('create', 'employee_deductions', null, 'Deduction Rs ' . number_format($amount, 2) . ' for employee ' . $employeeId);
        flash('success', __('Deduction recorded.'));
        redirect('employees/' . $employeeId, ['tab' => 'deductions']);
    }

    /* ============ Bonus (approval-gated) ============ */

    public static function addBonus(): void {
        if (!Auth::can('employees.edit')) { http_response_code(403); exit('Forbidden'); }
        require_post(); csrf_verify();
        $employeeId = intv(inp('employee_id'));
        $amount = round((float)num(inp('amount')), 2);
        $reason = trim(inp('reason'));
        $date = inp('date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : today();
        $method = in_array(inp('payment_method'), ['cash', 'bank'], true) ? inp('payment_method') : 'cash';
        $accountId = intv(inp('account_id'));
        if (!$employeeId || $amount <= 0 || $reason === '') { flash('error', __('Reason and amount are required.')); redirect('employees'); }
        if (!$accountId) { flash('error', __('Select the payout account - needed to pay the bonus after approval.')); redirect('employees/' . $employeeId, ['tab' => 'bonus']); }
        $newId = (int)db_insert('employee_bonuses', ['employee_id' => $employeeId, 'date' => $date, 'amount' => $amount, 'reason' => $reason, 'payment_method' => $method, 'account_id' => $accountId, 'status' => 'pending', 'requested_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local()]);
        Approvals::create('employee_bonus', 'employee_bonuses', $newId, $amount, 'Employee bonus — ' . db_val('SELECT name FROM employees WHERE id = ?', [$employeeId]) . ' — ' . $reason, (int)($_SESSION['user_id'] ?? 0));
        audit('create', 'employee_bonuses', $newId, 'Bonus request Rs ' . number_format($amount, 2));
        flash('success', __('Bonus requested — awaiting Owner/Admin approval.'));
        redirect('employees/' . $employeeId, ['tab' => 'bonus']);
    }

    /* ============ Employees list toolbar helper ============ */

    /** List of distinct job titles currently in use. */
    public static function inUseJobTitles(): array {
        $rows = db_all("SELECT DISTINCT designation FROM employees WHERE is_active = 1 AND designation IS NOT NULL AND designation <> '' ORDER BY designation");
        return array_column($rows, 'designation');
    }
}
