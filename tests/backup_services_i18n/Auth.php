<?php
/** Authentication + RBAC */
final class Auth {
    public static function attempt(string $username, string $password): array {
        // Rate limit: lockout after N failures in window
        $max = (int)cfg('security.login_max_attempts', 5);
        $window = (int)cfg('security.login_lockout_minutes', 15);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $fails = (int)db_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND username = ? AND attempted_at > ?',
            [$ip, $username, date('Y-m-d H:i:s', time() - $window * 60)]);
        if ($fails >= $max) {
            return [false, 'Too many failed attempts. Try again in ' . $window . ' minutes.'];
        }
        $user = db_one('SELECT u.* FROM users u WHERE u.username = ? LIMIT 1', [$username]);
        $ok = $user && $user['is_active'] && password_verify($password, $user['password_hash']);
        try {
            db_exec('INSERT INTO login_attempts (ip, username, success, attempted_at) VALUES (?,?,?,?)',
                [$ip, $username, $ok ? 1 : 0, now_local()]);
        } catch (Throwable $e) { error_log('login_attempts: ' . $e->getMessage()); }
        if (!$ok) {
            self::history($user['id'] ?? null, $username, 'failed');
            return [false, 'Invalid username or password.'];
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $pref = db_val('SELECT preferred_language FROM users WHERE id = ?', [(int)$user['id']]) ?: 'en';
        $_SESSION['lang'] = in_array($pref, ['en', 'ur', 'roman'], true) ? $pref : 'en';
        $_SESSION['role_ids'] = array_map('intval', array_column(db_all('SELECT role_id FROM user_roles WHERE user_id = ?', [$user['id']]), 'role_id'));
        db_exec('UPDATE users SET last_login = ? WHERE id = ?', [now_local(), $user['id']]);
        self::history((int)$user['id'], $username, 'success');
        audit('login', 'users', $user['id'], 'Successful login');
        return [true, null];
    }

    private static function history(?int $userId, string $username, string $status): void {
        try {
            db_exec('INSERT INTO login_history (user_id, username, ip, user_agent, status, created_at) VALUES (?,?,?,?,?,?)',
                [$userId, $username, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $status, now_local()]);
        } catch (Throwable $e) { error_log('login_history: ' . $e->getMessage()); }
    }

    public static function check(): bool { return !empty($_SESSION['user_id']); }

    public static function user(): ?array {
        if (!self::check()) return null;
        return db_one('SELECT id, username, full_name, email, phone FROM users WHERE id = ?', [$_SESSION['user_id']]);
    }

    public static function logout(): void {
        audit('logout', 'users', $_SESSION['user_id'] ?? null, 'Logout');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();
    }

    /* ============ Separate login areas ============ */

    /** Role codes for the current user, cached per USER (never shared across users in a persistent process). */
    public static function roleCodes(): array {
        static $codes = [];
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if (!$uid) return [];
        if (isset($codes[$uid])) return $codes[$uid];
        return $codes[$uid] = array_values(array_unique(array_column(
            db_all('SELECT r.code FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?', [$uid]),
            'code'
        )));
    }

    /** Is this user allowed to enter the given area? (owner/admin → admin; owner/admin/manager/accountant → management) */
    public static function areaAllowed(string $area): bool {
        if (!self::check()) return false;
        $roles = self::roleCodes();
        if ($area === 'admin')      return in_array('owner', $roles, true) || in_array('admin', $roles, true);
        if ($area === 'management') return (bool)array_intersect(['owner', 'admin', 'manager', 'accountant'], $roles);
        return true;
    }

    /** Gate for the /admin and /management areas. Unauthenticated or wrong-role users see the area login with an error. */
    public static function requireArea(string $area, string $errorRoute): void {
        if (self::areaAllowed($area)) return;
        $_SESSION['flash'][] = ['type' => 'error', 'msg' => $area === 'admin' ? 'Only the Owner and Admin can sign in to the Admin area.' : 'Only Managers, Accountants and Admin can sign in to the Management area.'];
        header('Location: ' . url($errorRoute));
        exit;
    }

    public static function permission_codes(): array {
        static $cache = [];
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid && isset($cache[$uid])) return $cache[$uid];
        $rows = db_all('SELECT DISTINCT p.code FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            JOIN user_roles ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = ?', [$uid]);
        return $cache[$uid] = array_column($rows, 'code');
    }

    public static function can(string $perm): bool {
        // Owner role (code owner) has all permissions
        $isOwner = db_val('SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.code = ?',
            [$_SESSION['user_id'] ?? 0, 'owner']) > 0;
        return $isOwner || in_array($perm, self::permission_codes(), true);
    }

    public static function require_login(): void {
        if (!self::check()) redirect('auth/login');
        // Session inactivity timeout
        $life = (int)cfg('session.lifetime', 120) * 60;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $life) {
            self::logout();
            redirect('auth/login', ['timeout' => 1]);
        }
        $_SESSION['last_activity'] = time();
    }

    public static function require_perm(string $perm): void {
        self::require_login();
        if (!self::can($perm)) {
            http_response_code(403);
            view('error/403', ['perm' => $perm]);
            exit;
        }
    }

    public static function require_any_perm(array $perms): void {
        self::require_login();
        foreach ($perms as $p) { if (self::can($p)) return; }
        http_response_code(403);
        view('error/403', ['perm' => implode('|', $perms)]);
        exit;
    }
}
