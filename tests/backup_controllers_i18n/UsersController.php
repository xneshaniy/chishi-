<?php
final class UsersController {
    public static function index(): void {
        $q = inp('q');
        $where = $q !== '' ? " WHERE u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? " : '';
        $p = $q !== '' ? ["%$q%", "%$q%", "%$q%"] : [];
        $rows = db_all("SELECT u.*, GROUP_CONCAT(r.name, ', ') AS roles FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id $where GROUP BY u.id ORDER BY u.full_name", $p);
        view('users/index', ['rows' => $rows, 'q' => $q, 'title' => 'Users & Roles']);
    }

    public static function newUser(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $username = inp('username');
            $fullName = inp('full_name');
            $password = $_POST['password'] ?? '';
            $roleIds = array_map('intval', $_POST['roles'] ?? []);
            $branchId = intv(inp('branch_id')) ?: null;
            $salesmanId = intv(inp('salesman_id')) ?: null;
            $errors = [];
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username)) $errors[] = 'Username must be 3–60 chars (letters, digits, . _ -).';
            if ($fullName === '') $errors[] = 'Full name is required.';
            if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
            if (db_val('SELECT 1 FROM users WHERE username = ?', [$username])) $errors[] = 'Username already exists.';
            if (!$roleIds) $errors[] = 'Select at least one role.';
            if ($errors) { flash('error', implode(' ', $errors)); redirect('users/new'); }
            Database::transaction(function () use ($username, $fullName, $password, $roleIds, $branchId, $salesmanId) {
                $uid = db_insert('users', ['username' => $username, 'full_name' => $fullName, 'email' => inp('email'), 'phone' => inp('phone'), 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'branch_id' => $branchId, 'salesman_id' => $salesmanId, 'is_active' => 1, 'force_password_change' => 1, 'created_at' => now_local()]);
                foreach ($roleIds as $rid) db_exec('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)', [$uid, $rid]);
                audit('create', 'users', $uid, "Created user $username");
            });
            flash('success', "User $username created. They must change their password at first login.");
            redirect('users');
        }
        view('users/form', ['user' => null, 'roles' => db_all('SELECT * FROM roles ORDER BY id'), 'branches' => db_all('SELECT id, name FROM branches WHERE is_active = 1'), 'salesmen' => db_all('SELECT id, name FROM salesmen WHERE is_active = 1'), 'title' => 'New User']);
    }

    public static function editUser(): void {
        $id = intv(inp('id'));
        $user = db_one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) { flash('error', __('User not found.')); redirect('users'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $roleIds = array_map('intval', $_POST['roles'] ?? []);
            $fullName = inp('full_name');
            if ($fullName === '' || !$roleIds) { flash('error', __('Full name and at least one role are required.')); redirect('users/' . $id . '/edit'); }
            Database::transaction(function () use ($id, $fullName, $user, $roleIds) {
                $data = ['full_name' => $fullName, 'email' => inp('email'), 'phone' => inp('phone'), 'branch_id' => intv(inp('branch_id')) ?: null, 'salesman_id' => intv(inp('salesman_id')) ?: null];
                db_exec('UPDATE users SET full_name = ?, email = ?, phone = ?, branch_id = ?, salesman_id = ? WHERE id = ?', [$data['full_name'], $data['email'], $data['phone'], $data['branch_id'], $data['salesman_id'], $id]);
                $newPassword = $_POST['password'] ?? '';
                if ($newPassword !== '') {
                    if (strlen($newPassword) < 8) throw new RuntimeException('Password must be at least 8 characters.');
                    db_exec('UPDATE users SET password_hash = ?, force_password_change = 1 WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $id]);
                }
                db_exec('DELETE FROM user_roles WHERE user_id = ?', [$id]);
                foreach ($roleIds as $rid) db_exec('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)', [$id, $rid]);
                audit('update', 'users', $id, "Updated user {$user['username']}" . ($newPassword !== '' ? ' (password reset)' : ''));
            });
            flash('success', __('User updated.'));
            redirect('users');
        }
        $userRoles = array_map('intval', array_column(db_all('SELECT role_id FROM user_roles WHERE user_id = ?', [$id]), 'role_id'));
        view('users/form', ['user' => $user, 'userRoles' => $userRoles, 'roles' => db_all('SELECT * FROM roles ORDER BY id'), 'branches' => db_all('SELECT id, name FROM branches WHERE is_active = 1'), 'salesmen' => db_all('SELECT id, name FROM salesmen WHERE is_active = 1'), 'title' => 'Edit User — ' . $user['username']]);
    }

    public static function toggleUser(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        if ($id === (int)($_SESSION['user_id'] ?? 0)) { flash('error', __('You cannot deactivate yourself.')); redirect('users'); }
        db_exec('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$id]);
        audit('update', 'users', $id, 'Toggled active');
        redirect('users');
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
            flash('success', 'Permissions updated for ' . e($role['name']) . '.');
            redirect('users/roles/' . $id);
        }
        $perms = db_all('SELECT * FROM permissions ORDER BY module, code');
        $byModule = [];
        foreach ($perms as $p) $byModule[$p['module']][] = $p;
        $current = array_map('intval', array_column(db_all('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$id]), 'permission_id'));
        view('users/role_form', ['role' => $role, 'byModule' => $byModule, 'current' => $current, 'title' => 'Edit Role — ' . $role['name']]);
    }
}
