<?php
final class UsersController {
    public static function index(): void {
        $q = inp('q');
        $where = $q !== '' ? " WHERE u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? " : '';
        $p = $q !== '' ? ["%$q%", "%$q%", "%$q%"] : [];
        $rows = db_all("SELECT u.*, GROUP_CONCAT(r.name) AS roles, GROUP_CONCAT(r.code) AS role_codes FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id $where GROUP BY u.id ORDER BY u.full_name", $p);
        $linkedById = [];
        $roleCodesById = [];
        foreach ($rows as $r) {
            $linkedById[(int)$r['id']] = self::user_linked_records((int)$r['id']);
            $roleCodesById[(int)$r['id']] = array_values(array_filter(array_map('trim', explode(',', (string)($r['role_codes'] ?? '')))));
        }
        $activeOwnerAdmins = (int)db_val("SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.is_active = 1 AND r.code IN ('owner','admin')");
        view('users/index', ['rows' => $rows, 'q' => $q, 'linkedById' => $linkedById, 'roleCodesById' => $roleCodesById, 'activeOwnerAdmins' => $activeOwnerAdmins, 'currentId' => (int)($_SESSION['user_id'] ?? 0), 'title' => 'Users & Roles']);
    }

    /** Roles that may be assigned to login accounts under the three-role policy. */
    private static function eligibleRoles(): array {
        return db_all("SELECT * FROM roles WHERE code IN ('owner','admin','manager','accountant') ORDER BY id");
    }
    private static function eligibleRoleIds(): array {
        return array_map('intval', array_column(self::eligibleRoles(), 'id'));
    }
    /** Keep only login-eligible role ids (defends against hand-crafted POST bodies). */
    private static function filterEligibleRoleIds(array $ids): array {
        $allowed = array_flip(self::eligibleRoleIds());
        return array_values(array_filter(array_map('intval', $ids), fn($id) => isset($allowed[$id])));
    }

        public static function newUser(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $username = inp('username');
            $fullName = inp('full_name');
            $password = $_POST['password'] ?? '';
            $roleIds = self::filterEligibleRoleIds($_POST['roles'] ?? []);
            $branchId = intv(inp('branch_id')) ?: null;
            $permIds = isset($_POST['perms']) ? array_map('intval', $_POST['perms']) : [];
            $errors = [];
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username)) $errors[] = 'Username must be 3–60 chars (letters, digits, . _ -).';
            if ($fullName === '') $errors[] = 'Full name is required.';
            if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
            if (db_val('SELECT 1 FROM users WHERE username = ?', [$username])) $errors[] = 'Username already exists.';
            if (!$roleIds) $errors[] = 'Select at least one role.';
            if ($errors) { flash('error', implode(' ', $errors)); redirect('users/new'); }
            Database::transaction(function () use ($username, $fullName, $password, $roleIds, $branchId, $permIds) {
                $uid = db_insert('users', [
                    'username' => $username,
                    'full_name' => $fullName,
                    'email' => inp('email'),
                    'phone' => inp('phone'),
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'temp_password' => $password,
                    'branch_id' => $branchId,
                    'is_active' => 1,
                    'force_password_change' => 0,
                    'created_at' => now_local()
                ]);
                foreach ($roleIds as $rid) db_exec('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)', [$uid, $rid]);
                if ($permIds) {
                    foreach ($permIds as $pid) {
                        db_exec('INSERT INTO user_permissions (user_id, permission_id) VALUES (?,?)', [$uid, $pid]);
                    }
                }
                audit('create', 'users', $uid, "Created user $username");
            });
            flash('success', __('User ') . $username . __(' created successfully.'));
            redirect('users');
        }
        $perms = db_all('SELECT id, code, module, description FROM permissions ORDER BY module, code');
        $byModule = [];
        foreach ($perms as $p) $byModule[$p['module']][] = $p;
        $rolePermMap = [];
        foreach (db_all('SELECT role_id, permission_id FROM role_permissions') as $rp) {
            $rolePermMap[(int)$rp['role_id']][] = (int)$rp['permission_id'];
        }
        view('users/form', [
            'user' => null,
            'roles' => self::eligibleRoles(),
            'branches' => db_all('SELECT id, name FROM branches WHERE is_active = 1'),
            'byModule' => $byModule,
            'userPerms' => [],
            'rolePermMap' => $rolePermMap,
            'title' => 'New User'
        ]);
    }

    public static function editUser(): void {
        $id = intv(inp('id'));
        $user = db_one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) { flash('error', __('User not found.')); redirect('users'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $roleIds = self::filterEligibleRoleIds($_POST['roles'] ?? []);
            $fullName = inp('full_name');
            if ($fullName === '' || !$roleIds) { flash('error', __('Full name and at least one authorized role are required.')); redirect('users/' . $id . '/edit'); }
            Database::transaction(function () use ($id, $fullName, $user, $roleIds) {
                $data = ['full_name' => $fullName, 'email' => inp('email'), 'phone' => inp('phone'), 'branch_id' => intv(inp('branch_id')) ?: null];
                db_exec('UPDATE users SET full_name = ?, email = ?, phone = ?, branch_id = ? WHERE id = ?', [$data['full_name'], $data['email'], $data['phone'], $data['branch_id'], $id]);
                $newPassword = $_POST['password'] ?? '';
                if ($newPassword !== '') {
                    if (strlen($newPassword) < 8) throw new RuntimeException('Password must be at least 8 characters.');
                    db_exec('UPDATE users SET password_hash = ?, temp_password = ?, force_password_change = 0 WHERE id = ?', [
                        password_hash($newPassword, PASSWORD_DEFAULT),
                        $newPassword,
                        $id
                    ]);
                }
                db_exec('DELETE FROM user_roles WHERE user_id = ?', [$id]);
                foreach ($roleIds as $rid) db_exec('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)', [$id, $rid]);

                // Save custom permissions if submitted
                db_exec('DELETE FROM user_permissions WHERE user_id = ?', [$id]);
                if (isset($_POST['perms']) && is_array($_POST['perms'])) {
                    $permIds = array_map('intval', $_POST['perms']);
                    foreach ($permIds as $pid) {
                        db_exec('INSERT INTO user_permissions (user_id, permission_id) VALUES (?,?)', [$id, $pid]);
                    }
                }

                audit('update', 'users', $id, "Updated user {$user['username']}" . ($newPassword !== '' ? ' (password reset)' : ''));
            });
            flash('success', __('User updated successfully.'));
            redirect('users');
        }
        $userRoles = array_map('intval', array_column(db_all('SELECT role_id FROM user_roles WHERE user_id = ?', [$id]), 'role_id'));
        $perms = db_all('SELECT id, code, module, description FROM permissions ORDER BY module, code');
        $byModule = [];
        foreach ($perms as $p) $byModule[$p['module']][] = $p;
        $rolePermMap = [];
        foreach (db_all('SELECT role_id, permission_id FROM role_permissions') as $rp) {
            $rolePermMap[(int)$rp['role_id']][] = (int)$rp['permission_id'];
        }
        $custom = array_map('intval', array_column(db_all('SELECT permission_id FROM user_permissions WHERE user_id = ?', [$id]), 'permission_id'));
        if ($custom) {
            $userPerms = $custom;
        } else {
            $userPerms = array_map('intval', array_column(db_all('SELECT DISTINCT rp.permission_id FROM role_permissions rp JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?', [$id]), 'permission_id'));
        }
        view('users/form', [
            'user' => $user,
            'userRoles' => $userRoles,
            'roles' => self::eligibleRoles(),
            'branches' => db_all('SELECT id, name FROM branches WHERE is_active = 1'),
            'byModule' => $byModule,
            'userPerms' => $userPerms,
            'rolePermMap' => $rolePermMap,
            'title' => 'Edit User — ' . $user['username']
        ]);
    }

    public static function resetPassword(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) { flash('error', __('Only Owner/Admin can reset passwords.')); redirect('users'); }
        $id = intv(inp('id'));
        $newPassword = trim((string)($_POST['password'] ?? ''));
        if (strlen($newPassword) < 8) {
            flash('error', __('Password must be at least 8 characters.'));
            redirect('users');
        }
        $user = db_one('SELECT id, username FROM users WHERE id = ?', [$id]);
        if (!$user) { flash('error', __('User not found.')); redirect('users'); }

        db_exec('UPDATE users SET password_hash = ?, temp_password = ?, force_password_change = 0 WHERE id = ?', [
            password_hash($newPassword, PASSWORD_DEFAULT),
            $newPassword,
            $id
        ]);
        audit('update', 'users', $id, "Admin reset password for {$user['username']}");
        flash('success', __('Password reset successfully for ') . $user['username'] . '.');
        redirect('users');
    }

    public static function toggleUser(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        if ($id === (int)($_SESSION['user_id'] ?? 0)) { flash('error', __('You cannot deactivate yourself.')); redirect('users'); }
        $user = $id ? db_one('SELECT id, is_active FROM users WHERE id = ?', [$id]) : null;
        if (!$user) { flash('error', __('User not found.')); redirect('users'); }
        // Never allow the last active Owner/Admin account to be deactivated.
        if ((int)$user['is_active'] === 1 && self::isActiveOwnerOrAdmin($id) && !self::otherActiveOwnerAdmins($id)) {
            flash('error', __('Cannot deactivate the last active Owner/Admin account.')); redirect('users');
        }
        $actor = (int)($_SESSION['user_id'] ?? 0);
        if ((int)$user['is_active'] === 1) {
            db_exec('UPDATE users SET is_active = 0, deactivated_at = ?, deactivated_by = ? WHERE id = ?', [now_local(), $actor, $id]);
            audit('deactivate', 'users', $id, 'Deactivated user #' . $id);
        } else {
            db_exec('UPDATE users SET is_active = 1, deactivated_at = NULL, deactivated_by = NULL WHERE id = ?', [$id]);
            audit('activate', 'users', $id, 'Activated user #' . $id);
        }
        redirect('users');
    }

    /** Permanently delete a user — Owner/Admin only, after confirmation + reason, only when safe. */
    public static function deleteUser(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) { flash('error', __('Only Owner/Admin can permanently delete a user.')); redirect('users'); }
        $id = intv(inp('id'));
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') { flash('error', __('A reason is required to delete a user.')); redirect('users'); }
        $user = $id ? db_one('SELECT * FROM users WHERE id = ?', [$id]) : null;
        if (!$user) { flash('error', __('User not found.')); redirect('users'); }
        $currentId = (int)($_SESSION['user_id'] ?? 0);
        if ($id === $currentId) { flash('error', __('You cannot delete your own account.')); redirect('users'); }
        $codes = array_map('strval', array_column(db_all('SELECT r.code FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?', [$id]), 'code'));
        if (in_array('owner', $codes, true)) { flash('error', __('Owner accounts cannot be permanently deleted. Deactivate instead.')); redirect('users'); }
        if ((int)$user['is_active'] === 1 && array_intersect(['owner', 'admin'], $codes) && !self::otherActiveOwnerAdmins($id)) {
            flash('error', __('Cannot delete the last active Owner/Admin account.')); redirect('users');
        }
        $linked = self::user_linked_records($id);
        if ($linked) {
            $parts = [];
            foreach ($linked as $l) $parts[] = $l['label'] . ' (' . $l['count'] . ')';
            flash('error', __('Cannot delete: linked by ') . implode(', ', $parts) . '. ' . __('Deactivate the user instead.'));
            redirect('users');
        }
        Database::transaction(function () use ($id, $user, $reason) {
            db_exec('DELETE FROM user_roles WHERE user_id = ?', [$id]);
            db_exec('DELETE FROM notifications WHERE user_id = ?', [$id]);
            db_exec('DELETE FROM login_history WHERE user_id = ?', [$id]);
            db_exec('DELETE FROM users WHERE id = ?', [$id]);
            audit('delete', 'users', null, 'Permanently deleted user ' . $user['username'] . ' — ' . $reason);
        });
        flash('success', __('User deleted permanently.'));
        redirect('users');
    }

    /** Is this user an active owner or admin (by role)? */
    private static function isActiveOwnerOrAdmin(int $id): bool {
        return (bool)db_val("SELECT COUNT(*) FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.id = ? AND u.is_active = 1 AND r.code IN ('owner','admin')", [$id]);
    }

    /** How many OTHER active owner/admin accounts exist besides the given user? */
    private static function otherActiveOwnerAdmins(int $excludeId): int {
        return (int)db_val("SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.is_active = 1 AND u.id <> ? AND r.code IN ('owner','admin')", [$excludeId]);
    }

    /**
     * Tables that reference users.id. Column-name based (most user references are not declared FKs):
     * matches `user_id` and any column ending in `_by` (created_by, decided_by, posted_by, recorded_by, …).
     * Ephemeral tables (sessions/notifications) are excluded — they are removed with the user.
     * Deliberately non-destructive: used by the delete guard and the list badge display.
     */
    private static function user_linked_records(int $id): array {
        $skip = ['users', 'user_roles', 'notifications', 'login_history', 'login_attempts', 'idempotency_keys'];
        $driver = (string)db()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $tables = array_column(db_all("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE 'pk_%'"), 'name');
        } else {
            $tables = array_map('reset', db_all("SHOW TABLES"));
        }
        $linked = [];
        foreach ($tables as $t) {
            if (in_array($t, $skip, true)) continue;
            if ($driver === 'sqlite') {
                $cols = array_column(db_all("PRAGMA table_info('" . addslashes($t) . "')"), 'name');
            } else {
                $cols = array_column(db_all("SHOW COLUMNS FROM `" . addslashes($t) . "`"), 'Field');
            }
            foreach ($cols as $c) {
                $cl = strtolower((string)$c);
                if ($cl === 'user_id' || substr($cl, -3) === '_by' || $cl === 'approver_id' || $cl === 'decider_id') {
                    $n = (int)db_val("SELECT COUNT(*) FROM `" . addslashes($t) . "` WHERE `" . addslashes((string)$c) . "` = ?", [$id]);
                    if ($n > 0) {
                        $linked[] = ['table' => $t, 'column' => $c, 'label' => ucwords(str_replace('_', ' ', $t)), 'count' => $n];
                    }
                }
            }
        }
        return $linked;
    }

    public static function roles(): void {
        $roles = db_all('SELECT r.*, (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perm_count FROM roles r ORDER BY r.id');
        view('users/roles', ['roles' => $roles, 'title' => 'Roles & Permissions']);
    }

    public static function editRole(): void {
        $id = intv(inp('id'));
        $role = db_one('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$role) { flash('error', __('Role not found.')); redirect('users/roles'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if ($role['code'] === 'owner') { flash('error', __('The owner role always has full access and cannot be edited.')); redirect('users/roles/' . $id); }
            $selected = array_map('intval', $_POST['perms'] ?? []);
            Database::transaction(function () use ($id, $selected, $role) {
                db_exec('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
                foreach ($selected as $pid) db_exec('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)', [$id, $pid]);
                audit('update', 'roles', $id, "Updated permissions for {$role['code']}");
            });
            flash('success', __('Permissions updated for ') . e($role['name']) . '.');
            redirect('users/roles/' . $id);
        }
        $perms = db_all('SELECT * FROM permissions ORDER BY module, code');
        $byModule = [];
        foreach ($perms as $p) $byModule[$p['module']][] = $p;
        $current = array_map('intval', array_column(db_all('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$id]), 'permission_id'));
        view('users/role_form', ['role' => $role, 'byModule' => $byModule, 'current' => $current, 'title' => 'Edit Role — ' . $role['name']]);
    }
}
