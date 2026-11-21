<?php
final class SettingsController {

    /* ============ BONUS SCHEMES ============ */
    public static function bonusSchemes(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (inp('action') === 'save_rules') {
                $t = max(0, num(inp('bonus_approval_threshold')));
                setting_set('bonus_approval_threshold', (string)$t);
                flash('success', __('Bonus approval threshold saved.'));
                redirect('settings/bonus-schemes');
            }
            $name = inp('name'); $kind = inp('kind') ?: 'salesman';
            if ($name === '') { flash('error', __('Scheme name required.')); redirect('settings/bonus-schemes'); }
            if (db_val('SELECT 1 FROM bonus_schemes WHERE name = ?', [$name])) { flash('error', __('Scheme name already exists.')); redirect('settings/bonus-schemes'); }
            db_insert('bonus_schemes', ['name' => $name, 'kind' => in_array($kind, ['salesman','customer','purchase','promotion','special'], true) ? $kind : 'salesman', 'description' => inp('description'), 'requires_approval' => inp('requires_approval') ? 1 : 0, 'is_active' => 1, 'created_at' => now_local()]);
            audit('create', 'bonus_schemes', null, $name);
            flash('success', __('Bonus scheme added.'));
            redirect('settings/bonus-schemes');
        }
        $threshold = setting('bonus_approval_threshold', '10000');
        view('settings/bonus_schemes', ['rows' => db_all('SELECT * FROM bonus_schemes ORDER BY requires_approval, name'), 'threshold' => $threshold, 'title' => 'Bonus Schemes & Rules']);
    }

    /* ============ UNITS & PACKAGING CONVERSIONS ============ */
    public static function units(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (!Auth::can('settings.edit')) { http_response_code(403); exit('Forbidden'); }
            $fields = ['rm_unit_ton', 'rm_unit_man', 'packaging_pack_pieces', 'packaging_bundle_packs', 'packaging_packet_bundles'];
            foreach ($fields as $f) {
                $v = trim((string)inp($f, '0'));
                $n = $v === '' ? '0' : (string)max(0, round(num($v), 3));
                setting_set($f, $n);
            }
            setting_set('rm_consumption_approval', inp('rm_consumption_approval') ? '1' : '0');
            audit('update', 'company_settings', null, 'Unit & packaging conversion settings updated');
            flash('success', __('Unit & packaging conversion settings saved.'));
            redirect('settings/units');
        }
        $settings = [];
        foreach (db_all('SELECT setting_key, setting_value FROM company_settings') as $r) $settings[$r['setting_key']] = $r['setting_value'];
        view('settings/units', ['settings' => $settings, 'title' => 'Units & Packaging Conversions']);
    }

    /* ============ COMPANY PROFILE & LOGO ============ */
    public static function company(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (!Auth::can('settings.edit')) { http_response_code(403); exit('Forbidden'); }
            $fields = ['company_name','address','phone','email','website','ntn','strn','bank_name','bank_account_title','bank_account_no','invoice_footer','terms','advance_max_amount'];
            if (in_array('advance_max_amount', $fields, true)) {
                $adv = inp('advance_max_amount');
                setting_set('advance_max_amount', $adv === '' ? '0' : (string)max(0, round(num($adv), 2)));
            }
            foreach ($fields as $f) if ($f !== 'advance_max_amount') setting_set($f, inp($f));
            // Logo upload (validated)
            if (!empty($_FILES['logo']['name'])) {
                $f = $_FILES['logo'];
                $maxBytes = 2 * 1024 * 1024;
                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($f['tmp_name']);
                if ($f['size'] > $maxBytes) { flash('error', __('Logo too large (max 2 MB).')); redirect('settings/company'); }
                elseif (!isset($allowed[$mime])) { flash('error', __('Logo must be PNG, JPG, SVG or WebP.')); redirect('settings/company'); }
                else {
                    $dest = dirname(__DIR__, 2) . '/assets/img/company-logo.' . $allowed[$mime];
                    move_uploaded_file($f['tmp_name'], $dest);
                    setting_set('logo_path', 'assets/img/company-logo.' . $allowed[$mime]);
                }
            }
            audit('update', 'company_settings', null, 'Company profile updated');
            flash('success', __('Company profile saved.'));
            redirect('settings/company');
        }
        $settings = [];
        foreach (db_all('SELECT setting_key, setting_value FROM company_settings') as $r) $settings[$r['setting_key']] = $r['setting_value'];
        view('settings/company', ['settings' => $settings, 'title' => 'Company Profile']);
    }

    /* ============ BRANCHES ============ */
    public static function branches(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $action = inp('action');
            if ($action === 'add') {
                $code = strtoupper(inp('code')); $name = inp('name');
                if ($code === '' || $name === '') { flash('error', __('Code and name are required.')); redirect('settings/branches'); }
                try {
                    db_insert('branches', ['code' => $code, 'name' => $name, 'address' => inp('address'), 'city' => inp('city'), 'phone' => inp('phone'), 'email' => inp('email'), 'is_main' => inp('is_main') ? 1 : 0, 'is_active' => 1, 'created_at' => now_local()]);
                    audit('create', 'branches', null, "Branch $code added");
                    flash('success', __('Branch added.'));
                } catch (PDOException $e) { flash('error', __('Branch code already exists.')); }
            } elseif ($action === 'toggle') {
                $id = intv(inp('id'));
                db_exec('UPDATE branches SET is_active = 1 - is_active WHERE id = ?', [$id]);
                audit('update', 'branches', $id, 'Toggled active');
            }
            redirect('settings/branches');
        }
        view('settings/branches', ['rows' => db_all('SELECT * FROM branches ORDER BY is_main DESC, name'), 'title' => 'Branches / Factories']);
    }

    /* ============ WAREHOUSES ============ */
    public static function warehouses(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $action = inp('action');
            if ($action === 'add') {
                $code = strtoupper(inp('code')); $name = inp('name');
                $branchId = intv(inp('branch_id')) ?: null;
                if ($code === '' || $name === '') { flash('error', __('Code and name are required.')); redirect('settings/warehouses'); }
                try {
                    if (inp('is_default')) db_exec('UPDATE warehouses SET is_default = 0 WHERE is_default = 1');
                    db_insert('warehouses', ['branch_id' => $branchId, 'code' => $code, 'name' => $name, 'type' => inp('type') ?: 'warehouse', 'is_default' => inp('is_default') ? 1 : 0, 'is_active' => 1, 'created_at' => now_local()]);
                    audit('create', 'warehouses', null, "Warehouse $code added");
                    flash('success', __('Warehouse added.'));
                } catch (PDOException $e) { flash('error', __('Warehouse code already exists.')); }
            } elseif ($action === 'edit') {
                $id = intv(inp('id'));
                $w = $id ? db_one('SELECT * FROM warehouses WHERE id = ?', [$id]) : null;
                if (!$w) { flash('error', __('Warehouse not found.')); redirect('settings/warehouses'); }
                $name = trim(inp('name'));
                $branchId = intv(inp('branch_id')) ?: null;
                $type = inp('type') ?: $w['type'];
                if (!in_array($type, ['central','factory','warehouse','salesman','vehicle'], true)) $type = $w['type'];
                // Only Owner/Admin may change the default flag; others keep whatever is stored.
                $isDefault = is_owner_or_admin() ? (isset($_POST['is_default']) ? 1 : 0) : (int)$w['is_default'];
                if ($name === '') { flash('error', __('Name is required.')); redirect('settings/warehouses'); }
                // Never leave the system without a default: the last default warehouse cannot lose its flag.
                if (!$isDefault && (int)$w['is_default']) {
                    $otherDefaults = (int)db_val('SELECT COUNT(*) FROM warehouses WHERE is_default = 1 AND id <> ?', [$id]);
                    if ($otherDefaults === 0) { flash('error', __('Cannot remove the default flag from the default warehouse. Mark another warehouse as default first.')); redirect('settings/warehouses'); }
                }
                // If flipping default flag to another row, clear existing defaults so exactly one is marked default.
                if ($isDefault && !(int)$w['is_default']) {
                    db_exec('UPDATE warehouses SET is_default = 0 WHERE is_default = 1');
                }
                db_exec('UPDATE warehouses SET name = ?, branch_id = ?, type = ?, is_default = ? WHERE id = ?', [$name, $branchId, $type, ($isDefault ? 1 : 0), $id]);
                audit('update', 'warehouses', $id, "Warehouse {$w['code']} renamed to $name");
                flash('success', __('Warehouse updated.'));
            } elseif ($action === 'toggle') {
                $id = intv(inp('id'));
                $w = $id ? db_one('SELECT * FROM warehouses WHERE id = ?', [$id]) : null;
                if (!$w) { flash('error', __('Warehouse not found.')); redirect('settings/warehouses'); }
                if ((int)$w['is_default'] && (int)$w['is_active']) { flash('error', __('Cannot deactivate the default warehouse.')); redirect('settings/warehouses'); }
                db_exec('UPDATE warehouses SET is_active = 1 - is_active WHERE id = ?', [$id]);
                audit((int)$w['is_active'] ? 'deactivate' : 'activate', 'warehouses', $id, ((int)$w['is_active'] ? 'Deactivated' : 'Activated') . ' warehouse ' . $w['code']);
            } elseif ($action === 'archive' || $action === 'unarchive') {
                $id = intv(inp('id'));
                $w = $id ? db_one('SELECT * FROM warehouses WHERE id = ?', [$id]) : null;
                if (!$w) { flash('error', __('Warehouse not found.')); redirect('settings/warehouses'); }
                if ((int)$w['is_default']) { flash('error', __('Cannot archive the default warehouse.')); redirect('settings/warehouses'); }
                db_exec('UPDATE warehouses SET is_archived = ? WHERE id = ?', [$action === 'archive' ? 1 : 0, $id]);
                audit($action, 'warehouses', $id, ($action === 'archive' ? 'Archived' : 'Restored') . ' warehouse ' . $w['code']);
            } elseif ($action === 'delete') {
                $id = intv(inp('id'));
                $reason = trim(inp('reason'));
                if (!is_owner_or_admin()) { flash('error', __('Only Owner/Admin can permanently delete a warehouse.')); redirect('settings/warehouses'); }
                if ($reason === '') { flash('error', __('A reason is required to delete a warehouse.')); redirect('settings/warehouses'); }
                $w = $id ? db_one('SELECT * FROM warehouses WHERE id = ?', [$id]) : null;
                if (!$w) { flash('error', __('Warehouse not found.')); redirect('settings/warehouses'); }
                if ((int)$w['is_default']) { flash('error', __('Cannot delete the default warehouse.')); redirect('settings/warehouses'); }
                $linked = self::warehouse_linked_records($id);
                if ($linked) {
                    $parts = [];
                    foreach ($linked as $l) $parts[] = $l['label'] . ' (' . $l['count'] . ')';
                    flash('error', __('Cannot delete: linked by ') . implode(', ', $parts) . '. ' . __('Archive or deactivate the warehouse instead.'));
                    redirect('settings/warehouses');
                }
                db_exec('DELETE FROM warehouses WHERE id = ?', [$id]);
                audit('delete', 'warehouses', $id, 'Deleted warehouse ' . $w['code'] . ' — ' . $reason);
                flash('success', __('Warehouse deleted permanently.'));
            }
            redirect('settings/warehouses');
        }
        $rows = db_all('SELECT w.*, b.name AS branch FROM warehouses w LEFT JOIN branches b ON b.id = w.branch_id WHERE COALESCE(w.is_archived, 0) = 0 ORDER BY w.type, w.name');
        $archived = db_all('SELECT w.*, b.name AS branch FROM warehouses w LEFT JOIN branches b ON b.id = w.branch_id WHERE COALESCE(w.is_archived, 0) = 1 ORDER BY w.type, w.name');
        $branches = db_all('SELECT id, name FROM branches WHERE is_active = 1');
        $linkedById = [];
        foreach ($rows as $r) $linkedById[(int)$r['id']] = self::warehouse_linked_records((int)$r['id']);
        foreach ($archived as $r) $linkedById[(int)$r['id']] = self::warehouse_linked_records((int)$r['id']);
        view('settings/warehouses', ['rows' => $rows, 'archived' => $archived, 'branches' => $branches, 'linkedById' => $linkedById, 'title' => 'Warehouses']);
    }

    /**
     * Tables that hold references to `warehouses.id`. Returns a label/count list per reference.
     * Deliberately non-destructive: nothing is rewritten here. Used by delete-guard + view display.
     */
    private static function warehouse_linked_records(int $id): array {
        $linked = [];
        $checks = [
            ['stock_balances',    'warehouse_id',     __('Stock balance rows')],
            ['stock_movements',   'warehouse_id',     __('Stock movements')],
            ['stock_adjustments', 'warehouse_id',     __('Stock adjustments')],
            ['stock_cost_layers', 'warehouse_id',     __('Cost-layer rows')],
            ['salesman_issues',   'from_warehouse_id',__('Salesman goods issues')],
            ['sales_invoices',    'warehouse_id',     __('Sales invoices')],
            ['purchase_invoices', 'warehouse_id',     __('Purchase / GRN invoices')],
            ['production_orders', 'warehouse_id',     __('Production orders')],
            ['factory_issue_items','warehouse_id',    __('Factory issue lines')],
            ['rm_daily_usage',    'warehouse_id',     __('Daily RM usage entries')],
            ['claim_goods',       'warehouse_id',     __('Claim goods')],
            ['salesmen',          'warehouse_id',     __('Salesman van assignment')],
        ];
        foreach ($checks as [$table, $col, $label]) {
            $n = (int)db_val("SELECT COUNT(*) FROM $table WHERE $col = ?", [$id]);
            if ($n > 0) $linked[] = ['table' => $table, 'column' => $col, 'label' => $label, 'count' => $n];
        }
        return $linked;
    }

    /* ============ FINANCIAL YEAR & PERIODS ============ */
    public static function fiscal(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $action = inp('action');
            if ($action === 'toggle_period') {
                $id = intv(inp('id'));
                $p = db_one('SELECT * FROM fiscal_periods WHERE id = ?', [$id]);
                if ($p) {
                    $new = $p['status'] === 'open' ? 'closed' : 'open';
                    db_exec('UPDATE fiscal_periods SET status = ? WHERE id = ?', [$new, $id]);
                    audit('update', 'fiscal_periods', $id, "Period {$p['name']} → $new");
                    flash('success', __('Period ') . $p['name'] . __(' is now ') . $new . '.');
                }
            } elseif ($action === 'activate_year') {
                $id = intv(inp('id'));
                db_exec('UPDATE fiscal_years SET is_active = 0');
                db_exec('UPDATE fiscal_years SET is_active = 1 WHERE id = ?', [$id]);
                audit('update', 'fiscal_years', $id, 'Activated fiscal year');
            }
            redirect('settings/fiscal');
        }
        $years = db_all('SELECT * FROM fiscal_years ORDER BY start_date DESC');
        $fy = db_one('SELECT * FROM fiscal_years WHERE is_active = 1 LIMIT 1');
        $periods = $fy ? db_all('SELECT * FROM fiscal_periods WHERE fiscal_year_id = ? ORDER BY start_date', [(int)$fy['id']]) : [];
        view('settings/fiscal', ['years' => $years, 'fy' => $fy, 'periods' => $periods, 'title' => 'Financial Year & Periods']);
    }

    /* ============ OPENING BALANCES (GL) ============ */
    public static function opening(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $scope = inp('scope'); // customers|suppliers|cashbank|all
            $posted = 0; $skipped = 0;
            Database::transaction(function () use ($scope, &$posted, &$skipped) {
                if (in_array($scope, ['customers', 'all'], true)) {
                    foreach (db_all("SELECT * FROM customers WHERE opening_balance != 0") as $c) {
                        $ref = 'customer_opening'; $rid = (int)$c['id'];
                        if (db_val('SELECT id FROM journal_entries WHERE reference_type = ? AND reference_id = ?', [$ref, $rid])) { $skipped++; continue; }
                        $amt = (float)$c['opening_balance'];
                        $lines = $c['opening_type'] === 'debit'
                            ? [['account' => 'AR', 'debit' => $amt, 'customer_id' => $rid], ['account' => 'CAPITAL', 'credit' => $amt]]
                            : [['account' => 'CAPITAL', 'debit' => $amt], ['account' => 'AR', 'credit' => $amt, 'customer_id' => $rid]];
                        Accounting::postEntry($lines, ['date' => today(), 'voucher_type' => 'OB', 'reference_type' => $ref, 'reference_id' => $rid, 'memo' => 'Opening balance — customer ' . $c['name'], 'idem_key' => 'ob_customer:' . $rid]);
                        $posted++;
                    }
                }
                if (in_array($scope, ['suppliers', 'all'], true)) {
                    foreach (db_all("SELECT * FROM suppliers WHERE opening_balance != 0") as $s) {
                        $ref = 'supplier_opening'; $rid = (int)$s['id'];
                        if (db_val('SELECT id FROM journal_entries WHERE reference_type = ? AND reference_id = ?', [$ref, $rid])) { $skipped++; continue; }
                        $amt = (float)$s['opening_balance'];
                        $lines = $s['opening_type'] === 'credit'
                            ? [['account' => 'CAPITAL', 'debit' => $amt], ['account' => 'AP', 'credit' => $amt, 'supplier_id' => $rid]]
                            : [['account' => 'AP', 'debit' => $amt, 'supplier_id' => $rid], ['account' => 'CAPITAL', 'credit' => $amt]];
                        Accounting::postEntry($lines, ['date' => today(), 'voucher_type' => 'OB', 'reference_type' => $ref, 'reference_id' => $rid, 'memo' => 'Opening balance — supplier ' . $s['name'], 'idem_key' => 'ob_supplier:' . $rid]);
                        $posted++;
                    }
                }
                if (in_array($scope, ['cashbank', 'all'], true)) {
                    foreach (db_all("SELECT * FROM cash_bank_accounts WHERE opening_balance != 0") as $a) {
                        $ref = 'cashbank_opening'; $rid = (int)$a['id'];
                        if (db_val('SELECT id FROM journal_entries WHERE reference_type = ? AND reference_id = ?', [$ref, $rid])) { $skipped++; continue; }
                        $amt = (float)$a['opening_balance'];
                        $gl = Posting::accountGl($rid);
                        $lines = $amt >= 0
                            ? [['account' => $gl, 'debit' => $amt], ['account' => 'CAPITAL', 'credit' => $amt]]
                            : [['account' => 'CAPITAL', 'debit' => -$amt], ['account' => $gl, 'credit' => -$amt]];
                        Accounting::postEntry($lines, ['date' => today(), 'voucher_type' => 'OB', 'reference_type' => $ref, 'reference_id' => $rid, 'memo' => 'Opening balance — ' . $a['name'], 'idem_key' => 'ob_cashbank:' . $rid]);
                        $posted++;
                    }
                }
            });
            audit('post', 'opening_balances', null, "scope=$scope posted=$posted skipped=$skipped");
            flash('success', __('Opening balances posted: ') . $posted . __(' entries (') . $skipped . __(' already posted).'));
            redirect('settings/opening');
        }
        $customers = db_all("SELECT id, code, name, opening_balance, opening_type FROM customers WHERE opening_balance != 0");
        $suppliers = db_all("SELECT id, code, name, opening_balance, opening_type FROM suppliers WHERE opening_balance != 0");
        $accounts = db_all("SELECT id, name, type, opening_balance FROM cash_bank_accounts WHERE opening_balance != 0");
        $postedCount = (int)db_val("SELECT COUNT(*) FROM journal_entries WHERE reference_type LIKE '%_opening'");
        view('settings/opening', ['customers' => $customers, 'suppliers' => $suppliers, 'accounts' => $accounts, 'postedCount' => $postedCount, 'title' => 'Opening Balances']);
    }

    /* ============ BACKUP ============ */
    public static function backup(): void {
        $backupDir = dirname(__DIR__, 2) . '/storage/backups';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $action = inp('action');
            if ($action === 'generate') {
                if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);
                $file = $backupDir . '/ctc_erp_' . date('Ymd_His') . '.sql';
                $rowsWritten = Backup::dump($file);
                audit('backup', 'database', null, 'Backup generated: ' . basename($file) . ' (' . $rowsWritten . ' rows)');
                flash('success', __('Backup created: ') . basename($file) . ' (' . number_format(filesize($file) / 1024, 1) . __(' KB, ') . $rowsWritten . __(' rows). Restore: mysql DB < file.sql after importing database/schema.mysql.sql.'));
            } elseif ($action === 'delete') {
                $f = basename(inp('file'));
                if (preg_match('/^ctc_erp_\d{8}_\d{6}\.sql$/', $f) && file_exists($backupDir . '/' . $f)) {
                    unlink($backupDir . '/' . $f);
                    audit('backup', 'database', null, 'Backup deleted: ' . $f);
                    flash('success', __('Backup deleted.'));
                }
            }
            redirect('settings/backup');
        }
        $files = glob($backupDir . '/ctc_erp_*.sql') ?: [];
        rsort($files);
        $backups = array_map(fn($f) => ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)], $files);
        view('settings/backup', ['backups' => $backups, 'title' => 'Backup & Restore']);
    }

    /* ============ CHANGE PASSWORD ============ */
    public static function changePassword(): void {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if (!$userId) { redirect('auth/login'); }
        $user = db_one('SELECT id, username, full_name, password_hash, temp_password FROM users WHERE id = ?', [$userId]);
        if (!$user) { flash('error', __('User not found.')); redirect('auth/login'); }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            $errors = [];
            if (!password_verify($currentPassword, $user['password_hash'])) {
                $errors[] = __('Current password is incorrect.');
            }
            if (strlen($newPassword) < 8) {
                $errors[] = __('New password must be at least 8 characters long.');
            }
            if ($newPassword !== $confirmPassword) {
                $errors[] = __('New password and confirmation do not match.');
            }

            if ($errors) {
                flash('error', implode(' ', $errors));
                redirect('settings/password');
            }

            // Update user password and plaintext temp_password so admin can see current password
            db_exec('UPDATE users SET password_hash = ?, temp_password = ?, force_password_change = 0 WHERE id = ?', [
                password_hash($newPassword, PASSWORD_DEFAULT),
                $newPassword,
                $userId
            ]);
            audit('update', 'users', $userId, 'User changed their password');
            flash('success', __('Password changed successfully.'));
            redirect('settings/password');
        }

        view('settings/change_password', ['user' => $user, 'title' => 'Change Password']);
    }
}
