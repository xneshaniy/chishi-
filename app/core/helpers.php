<?php
/**
 * Core helpers: escaping, i18n, currency, URLs, flash, pagination, CSRF.
 */

/** Product kinds groups used for raw-material and finished-goods separation. */
function product_kinds_raw(): array { return ['raw', 'packaging']; }
function product_kinds_finished(): array { return ['finished']; }
/** Is the current user Owner or Admin (full stock/approval powers)? */
function is_owner_or_admin(): bool {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (!$uid || !class_exists('Auth')) return false;
    $roles = Auth::roleCodes();
    return in_array('owner', $roles, true) || in_array('admin', $roles, true);
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function app_config(array $path = []) {
    global $CONFIG;
    $v = $CONFIG;
    foreach ($path as $key) {
        if (!is_array($v) || !array_key_exists($key, $v)) return null;
        $v = $v[$key];
    }
    return $v;
}

function cfg(string $dot, $default = null) {
    $parts = explode('.', $dot);
    $v = app_config();
    foreach ($parts as $p) {
        if (!is_array($v) || !array_key_exists($p, $v)) return $default;
        $v = $v[$p];
    }
    return $v ?? $default;
}

/* ---------- Approval request labels (user-facing) ---------- */
/**
 * Display label for a `request_type` value stored in `approval_requests`.
 * Kept in sync with `notifications` map; stable keys so anything else falls back to a title-cased version.
 */
function approval_label(string $requestType): string {
    $map = [
        'registration'    => __('Registration'),
        'payment'         => __('Payment'),
        'transfer'        => __('Bank Transfer'),
        'expense'         => __('Expense'),
        'payroll'         => __('Salary'),
        'payroll_payment' => __('Salary Payment'),
        'advance'         => __('Salary Advance'),
        'loan'            => __('Employee Loan'),
        'bonus'           => __('Bonus'),
        'employee_bonus'  => __('Employee Bonus'),
        'discount'        => __('Discount'),
        'refund'          => __('Refund'),
        'other'           => __('Request'),
        'salesman_issue'  => __('Salesman Goods Issue'),
        'claim'           => __('Claim'),
        'purchase_request'=> __('Purchase Request'),
        'purchase_invoice'=> __('Purchase / GRN'),
        'maintenance'     => __('Maintenance'),
        'rm_consumption'  => __('Consumption'),
        'other_income'    => __('Other Income'),
    ];
    return $map[$requestType] ?? ucwords(str_replace('_', ' ', $requestType));
}
/** Display label for an `approval_requests.reference_type` — the underlying transactional table the request came from. */
function approval_reference_label(string $referenceType): string {
    $map = [
        'payments'           => __('Payments'),
        'bank_transfers'     => __('Bank Transfers'),
        'expenses'           => __('Expenses'),
        'payroll'            => __('Payroll'),
        'payroll_payments'   => __('Salary Payments'),
        'employee_advances'  => __('Salary Advances'),
        'employee_loans'     => __('Employee Loans'),
        'employees'          => __('Employee'),
        'salesmen'           => __('Salesman'),
        'employee_bonuses'   => __('Employee Bonuses'),
        'salesman_salary_payments' => __('Salesman Salary Payments'),
        'salesman_salary_advances' => __('Salesman Salary Advances'),
        'salesman_issues'    => __('Salesman Goods Issues'),
        'sales_invoices'     => __('Sales Invoice'),
        'salesman_expenses'  => __('Salesman Expense'),
        'claim_goods'        => __('Claim Goods'),
        'claim_goods_payments'=> __('Claim Goods Payment'),
        'purchase_orders'    => __('Purchase Orders'),
        'purchase_invoices'  => __('Purchase / GRN'),
        'maintenance_records'=> __('Maintenance'),
        'factory_issues'     => __('Factory Consumption'),
    ];
    return $map[$referenceType] ?? ucwords(str_replace('_', ' ', $referenceType));
}

/* ---------- i18n (EN / Urdu RTL) ---------- */
function lang(string $key): string {
    static $strings = null;
    if ($strings === null) { $strings = require __DIR__ . '/../lang.php'; }
    $l = $_SESSION['lang'] ?? (cfg('app.language', 'en'));
    return $strings[$l][$key] ?? $strings['en'][$key] ?? $key;
}
function __(string $key): string { return lang($key); }
function is_rtl(): bool { return ($_SESSION['lang'] ?? cfg('app.language', 'en')) === 'ur'; }
function current_lang(): string { return $_SESSION['lang'] ?? cfg('app.language', 'en'); }
function dasti_label(): string {
    $lang = current_lang();
    if ($lang === 'ur') return 'قرض (دستی)';
    if ($lang === 'roman') return 'Dasti';
    return 'Loans (Dasti)';
}
/** Save language choice for the logged-in user (falls back to session-only for guests). */
function save_user_language(string $lang): void {
    if (!in_array($lang, ['en', 'ur', 'roman'], true)) return;
    $_SESSION['lang'] = $lang;
    if (!empty($_SESSION['user_id'])) {
        try { db_exec('UPDATE users SET preferred_language = ? WHERE id = ?', [$lang, (int)$_SESSION['user_id']]); } catch (Throwable $e) { error_log('lang save: ' . $e->getMessage()); }
    }
}

/* ---------- Base URL (root or subdirectory mount — needed for pretty /admin & /management URLs) ---------- */
function base_url(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '/index.php');
    $dir = str_replace('\\', '/', dirname($script));
    $base = ($dir === '/' || $dir === '.') ? '/' : rtrim($dir, '/') . '/';
    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $base;
}
function asset(string $path): string { return e(base_url() . ltrim($path, '/')); }

/* ---------- URLs & redirects ---------- */
function url(string $route, array $params = []): string {
    $q = array_merge(['r' => $route], $params);
    return base_url() . 'index.php?' . http_build_query($q);
}
function redirect(string $route, array $params = []): void {
    header('Location: ' . url($route, $params));
    exit;
}
function base_asset(string $path): string { return htmlspecialchars($path, ENT_QUOTES); }

/* ---------- Flash messages ---------- */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg]; }
function flash_get(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$token)) {
        http_response_code(419);
        exit('Invalid or expired CSRF token. Please go back and try again.');
    }
}

/* ---------- Idempotency (duplicate submission protection) ---------- */
function idempotency_input(): string {
    return '<input type="hidden" name="idem_key" value="' . e(bin2hex(random_bytes(16))) . '">';
}
/** Returns true if this key was already processed by this user for this route. */
function idempotency_seen(string $route, ?string $key, int $userId): bool {
    if (!$key) return false;
    try {
        db()->prepare('INSERT INTO idempotency_keys (idem_key, user_id, route, created_at) VALUES (?,?,?,?)')
            ->execute([$key, $userId, $route, now_local()]);
        return false; // first time
    } catch (PDOException $e) {
        return true; // unique constraint → duplicate
    }
}

/* ---------- Money / dates ---------- */
function money($n, bool $withSymbol = true): string {
    $s = number_format((float)$n, 2);
    return $withSymbol ? 'Rs ' . $s : $s;
}
function qty($n): string { $s = rtrim(rtrim(number_format((float)$n, 3, '.', ''), '0'), '.'); return $s === '' ? '0' : $s; }
function today(): string { return (new DateTimeImmutable('now', new DateTimeZone(cfg('app.timezone','Asia/Karachi'))))->format('Y-m-d'); }
function now_local(): string { return (new DateTimeImmutable('now', new DateTimeZone(cfg('app.timezone','Asia/Karachi'))))->format('Y-m-d H:i:s'); }
function dt_display(?string $s): string { if (!$s) return '—'; return date('d M Y, h:i A', strtotime($s)); }
function date_display(?string $d): string { if (!$d) return '—'; return date('d M Y', strtotime($d)); }

/* ---------- Pagination ---------- */
function paginate(string $sql, array $params, int $page, int $perPage = 20): array {
    $page = max(1, $page);
    // Subquery wrap — robust for joins and nested SELECTs (ORDER BY inside subquery is legal)
    $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') count_sub';
    $total = (int) db_val($countSql, $params);
    $offset = ($page - 1) * $perPage;
    $rowsSql = $sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $rows = db_all($rowsSql, $params);
    return ['rows' => $rows, 'total' => $total, 'page' => $page,
            'pages' => max(1, (int)ceil($total / $perPage))];
}
function pager_html(array $p, string $route, array $keep = []): string {
    if ($p['pages'] <= 1) return '';
    $h = '<div class="pager no-print">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        $cls = $i === $p['page'] ? ' class="on"' : '';
        $h .= '<a' . $cls . ' href="' . e(url($route, array_merge($keep, ['page' => $i]))) . '">' . $i . '</a>';
    }
    return $h . '</div>';
}

/* ---------- Inline avatar as a self-contained data URI (renders in any proxy/browser context) ---------- */
function photo_data_uri(?string $rel): string {
    if (!$rel) return '';
    $path = dirname(__DIR__, 2) . '/storage/uploads/' . $rel;
    if (!is_file($path)) return '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? null;
    if (!$mime || filesize($path) > 512 * 1024) return ''; // guard against runaway pages
    $raw = @file_get_contents($path);
    if ($raw === false) return '';
    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

/* ---------- Profile Active/Inactive toggle (Owner/Admin only) ---------- */
/**
 * Toggle a person-profile row between Active and Inactive.
 * - Activating a record that has a pending registration approval decides THAT approval
 *   (exactly-once flip + optional warehouse creation + notifications) — never a raw status flip.
 * - Deactivating is always a direct flip, audited with who + why.
 * The caller must already verify is_owner_or_admin() before calling this helper.
 */
function profile_toggle_active(string $entity, int $id): string {
    $isActive = (int)db_val("SELECT is_active FROM $entity WHERE id = ?", [$id]);
    if ($isActive === 1) {
        db_exec("UPDATE $entity SET is_active = 0 WHERE id = ?", [$id]);
        audit('deactivate', $entity, $id, 'Manually deactivated by Owner/Admin');
        flash('success', __('Profile deactivated. It stays inactive until an Owner/Admin reactivates it.'));
        return 'deactivated';
    }
    // Off → On: if a pending registration approval exists, decide it (single code path, exactly-once flip + warehouse)
    $pending = db_one("SELECT id FROM approval_requests WHERE reference_type = ? AND reference_id = ? AND request_type = 'registration' AND status = 'pending'", [$entity, $id]);
    $decider = (int)($_SESSION['user_id'] ?? 0);
    if ($pending) {
        Approvals::decide((int)$pending['id'], true, 'Activated by Owner/Admin', $decider);
        flash('success', __('Registration approved — profile is now Active.'));
        return 'activated';
    }
    db_exec("UPDATE $entity SET is_active = 1 WHERE id = ?", [$id]);
    audit('activate', $entity, $id, 'Manually activated by Owner/Admin');
    flash('success', __('Profile activated.'));
    return 'activated';
}

/* ---------- Display-time normaliser for stored audit log lines ---------- */
/**
 * The audit_logs table stores raw technical text (never rewritten). When older
 * technical approval prefixes appear in the details, show users the friendly label.
 */
function friendly_audit_details(string $details): string {
    return str_replace([
        'PAYROLL_PAYMENT',
        'SALARY_PAYMENT',
        'SALARY PAYMENT',
    ], 'Salary Payment', $details);
}

/* ---------- Audit ---------- */
function audit(string $action, string $entity, $entityId = null, string $details = ''): void {
    try {
        db()->prepare('INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address, created_at) VALUES (?,?,?,?,?,?,?)')
            ->execute([$_SESSION['user_id'] ?? null, $action, $entity, $entityId !== null ? (string)$entityId : null, $details, $_SERVER['REMOTE_ADDR'] ?? null, now_local()]);
    } catch (Throwable $e) {
        error_log('audit failed: ' . $e->getMessage());
    }
}

/* ---------- English number-to-words (for invoice amounts) ---------- */
function amount_in_words(float $amount): string {
    $amount = round($amount, 2);
    $rupees = (int)floor($amount);
    $paisa = (int)round(($amount - $rupees) * 100);
    $words = function (int $n) use (&$words): string {
        static $units = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
        static $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
        if ($n < 20) return $units[$n];
        if ($n < 100) return trim($tens[intdiv($n, 10)] . ' ' . $units[$n % 10]);
        if ($n < 1000) return trim($units[intdiv($n, 100)] . ' Hundred ' . $words($n % 100));
        if ($n < 100000) return trim($words(intdiv($n, 1000)) . ' Thousand ' . $words($n % 1000));
        if ($n < 10000000) return trim($words(intdiv($n, 100000)) . ' Lakh ' . $words($n % 100000));
        if ($n < 1000000000) return trim($words(intdiv($n, 10000000)) . ' Crore ' . $words($n % 10000000));
        return (string)$n;
    };
    $rs = $rupees > 0 ? trim($words($rupees)) . ' Rupees' : 'Zero Rupees';
    if ($paisa > 0) $rs .= ' and ' . trim($words($paisa)) . ' Paisa';
    return $rs . ' Only';
}

/* ---------- Company settings (driver-portable upsert) ---------- */
function setting(string $key, $default = null) {
    $v = db_val('SELECT setting_value FROM company_settings WHERE setting_key = ?', [$key]);
    return $v === false ? $default : $v;
}
function setting_set(string $key, string $value): void {
    $updated = db_exec('UPDATE company_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?', [$value, now_local(), $key]);
    if ($updated === 0) {
        // insert if not exists (race-safe enough for single-user settings page)
        $exists = db_val('SELECT 1 FROM company_settings WHERE setting_key = ?', [$key]);
        if ($exists) { db_exec('UPDATE company_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?', [$value, now_local(), $key]); }
        else db_insert('company_settings', ['setting_key' => $key, 'setting_value' => $value, 'updated_at' => now_local()]);
    }
}

/* ---------- Salesman scoping helpers ---------- */
/** True when the logged-in user has ONLY the salesman role (no owner/admin/manager/accountant). */
function is_salesman_only(): bool {
    if (empty($_SESSION['user_id'])) return false;
    $roles = db_all('SELECT r.code FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?', [(int)$_SESSION['user_id']]);
    if (!$roles) return false;
    foreach ($roles as $r) { if ($r['code'] !== 'salesman') return false; }
    return true;
}
/** salesman profile id linked to this login user (null if none). */
function my_salesman_id(): ?int {
    $id = db_val('SELECT salesman_id FROM users WHERE id = ?', [(int)($_SESSION['user_id'] ?? 0)]);
    return $id ? (int)$id : null;
}

/* ---------- Pakistan location helpers (provinces / cities / areas) ---------- */
/** All active provinces, ordered for display. */
function location_provinces(): array {
    return db_all('SELECT id, name FROM pk_provinces WHERE is_active = 1 ORDER BY sort_order, name');
}
/** Cities grouped by province_id: [province_id => [ [id, name], ... ]]. */
function location_cities(): array {
    $rows = db_all('SELECT id, province_id, name FROM pk_cities WHERE is_active = 1 ORDER BY name');
    $out = [];
    foreach ($rows as $r) $out[(int)$r['province_id']][] = ['id' => (int)$r['id'], 'name' => $r['name']];
    return $out;
}
/** Active areas grouped by city_id: [city_id => [ [id, name], ... ]]. */
function location_areas(): array {
    $rows = db_all('SELECT id, city_id, name FROM areas WHERE is_active = 1 AND city_id IS NOT NULL ORDER BY name');
    $out = [];
    foreach ($rows as $r) $out[(int)$r['city_id']][] = ['id' => (int)$r['id'], 'name' => $r['name']];
    return $out;
}
/** Find (or create) an area under a city from free text. Returns the area id, or null. */
function find_or_create_area(?int $provinceId, ?int $cityId, string $name): ?int {
    $name = trim($name);
    if ($name === '') return null;
    if (!$cityId) { // province-only: match against areas with that province and no city
        $row = db_one('SELECT id FROM areas WHERE province_id = ? AND city_id IS NULL AND name = ? COLLATE NOCASE', [$provinceId, $name]);
        if ($row) return (int)$row['id'];
        return (int)db_insert('areas', ['province_id' => $provinceId, 'city_id' => null, 'name' => $name, 'is_active' => 1, 'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local()]);
    }
    $row = db_one('SELECT id FROM areas WHERE city_id = ? AND name = ? COLLATE NOCASE', [$cityId, $name]);
    if ($row) return (int)$row['id'];
    return (int)db_insert('areas', ['province_id' => $provinceId, 'city_id' => $cityId, 'name' => $name, 'is_active' => 1, 'created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null, 'created_at' => now_local()]);
}
/** Human-readable single-line location for an entity (city, area). */
function location_text(?array $row): string {
    if (!$row) return '';
    $city = (int)($row['city_id'] ?? 0);
    $area = (int)($row['area_id'] ?? 0);
    $name = '';
    if ($area) $name = (string)db_val('SELECT name FROM areas WHERE id = ?', [$area]);
    elseif ($city) $name = (string)db_val('SELECT name FROM pk_cities WHERE id = ?', [$city]);
    return $name ?: (string)($row['city'] ?? '') ?: (string)($row['area'] ?? '');
}

/* ---------- Input helpers ---------- */
function inp(string $key, $default = '') { return isset($_POST[$key]) ? trim((string)$_POST[$key]) : (isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default); }
function num($v): float { return is_numeric($v) ? (float)$v : 0.0; }
function intv($v): int { return is_numeric($v) ? (int)$v : 0; }
function require_post(): void { if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Method not allowed'); } }
