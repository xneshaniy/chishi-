<?php
/**
 * Management Team — Owner/Admin creates and manages Accountant & Manager profiles.
 * Each profile is ONE employee row linked to ONE users row (employees.user_id).
 * Reuses payroll for salary/partial payments/advances/ledger — no duplicate subsystems.
 */
final class ManagementTeam {

    /** Management roles this module handles. */
    public static function roleCodes(): array { return ['accountant', 'manager']; }

    public static function isValidRole(string $code): bool {
        return in_array($code, self::roleCodes(), true);
    }

    /** List all management profiles (joined users + employees). */
    public static function listProfiles(): array {
        return db_all("SELECT u.id AS user_id, u.username, u.full_name, u.email, u.phone, u.is_active AS user_active,
                        e.id AS employee_id, e.code, e.designation, e.department, e.join_date, e.photo_path,
                        e.salary_type, e.salary_rate, e.is_active AS emp_active, r.code AS role_code, r.name AS role_name
                    FROM users u
                    LEFT JOIN user_roles ur ON ur.user_id = u.id
                    LEFT JOIN roles r ON r.id = ur.role_id
                    LEFT JOIN employees e ON e.user_id = u.id
                    WHERE r.code IN ('accountant','manager')
                    ORDER BY r.code, u.full_name", []);
    }

    /** Find-or-create the linked employee row for a user. Returns the employee id. */
    public static function ensureEmployeeLink(int $userId, ?string $designation = null): int {
        $user = db_one('SELECT id, full_name, email, phone FROM users WHERE id = ?', [$userId]);
        if (!$user) throw new RuntimeException(__('User not found.'));
        $existing = db_one('SELECT id FROM employees WHERE user_id = ?', [$userId]);
        if ($existing) return (int)$existing['id'];
        $maxId = (int)db_val('SELECT COALESCE(MAX(id), 0) FROM employees');
        $code = 'EMP-' . str_pad((string)($maxId + 1), 4, '0', STR_PAD_LEFT);
        // collision safety
        $tries = 0;
        while (db_val('SELECT 1 FROM employees WHERE code = ?', [$code]) && $tries++ < 20) {
            $maxId += 1000;
            $code = 'EMP-' . str_pad((string)($maxId + 1), 4, '0', STR_PAD_LEFT);
        }
        return (int)db_insert('employees', [
            'code' => $code, 'name' => $user['full_name'], 'phone' => $user['phone'] ?? null,
            'designation' => $designation ?? 'Staff', 'department' => 'Management',
            'kind' => 'employee', 'join_date' => today(),
            'salary_type' => 'monthly', 'salary_rate' => 0, 'is_active' => 1,
            'user_id' => (int)$userId, 'created_at' => now_local(),
        ]);
    }

    /** Create a management profile: login account + linked employee row (or link an existing account). */
    public static function createProfile(array $in): int {
        $username = trim((string)($in['username'] ?? ''));
        $fullName = trim((string)($in['full_name'] ?? ''));
        $roleCode = strtolower(trim((string)($in['role'] ?? '')));
        $password = (string)($in['password'] ?? '');
        if ($username === '' || $fullName === '' || !self::isValidRole($roleCode)) {
            throw new RuntimeException(__('Username, full name and a valid role are required.'));
        }
        if (preg_match('/[^a-zA-Z0-9_.-]/', $username)) {
            throw new RuntimeException(__('Username can only contain letters, digits, dot, dash and underscore.'));
        }
        if (mb_strlen($password) < 8) {
            throw new RuntimeException(__('Password must be at least 8 characters.'));
        }
        $role = db_one('SELECT id, code, name FROM roles WHERE code = ?', [$roleCode]);
        if (!$role) throw new RuntimeException(__('Role not seeded.'));
        if (strlen($password) < 8) throw new RuntimeException(__('Password must be at least 8 characters.'));
        $salaryType = in_array($in['salary_type'] ?? '', ['monthly', 'daily'], true) ? $in['salary_type'] : 'monthly';
        $salaryRate = round((float)($in['salary_rate'] ?? 0), 2);
        $joinDate = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($in['join_date'] ?? '')) ? $in['join_date'] : today());
        $designation = trim((string)($in['designation'] ?? '')) ?: ($roleCode === 'accountant' ? 'Accountant' : 'Manager');
        $department = trim((string)($in['department'] ?? '')) ?: 'Management';
        $branchId = (int)($in['branch_id'] ?? 0) ?: null;

        Database::transaction(function () use ($username, $fullName, $role, $password, $salaryType, $salaryRate, $joinDate, $designation, $department, $branchId, $in) {
            $user = db_one('SELECT id FROM users WHERE username = ?', [$username]);
            if ($user) {
                $userId = (int)$user['id'];
                if (db_val('SELECT 1 FROM employees WHERE user_id = ?', [$userId])) {
                    throw new RuntimeException(__('This account already has a management profile.'));
                }
                // Refresh existing account details — do NOT create a duplicate, do NOT touch password unless explicitly provided
                db_exec('UPDATE users SET full_name = ?, email = ?, phone = ?, branch_id = ?, is_active = 1 WHERE id = ?',
                    [$fullName, trim((string)($in['email'] ?? '')) ?: null, trim((string)($in['phone'] ?? '')) ?: null, $branchId, $userId]);
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $userId = (int)db_insert('users', [
                    'username' => $username, 'full_name' => $fullName,
                    'email' => trim((string)($in['email'] ?? '')) ?: null, 'phone' => trim((string)($in['phone'] ?? '')) ?: null,
                    'password_hash' => $hash, 'branch_id' => $branchId, 'is_active' => 1,
                    'force_password_change' => 1, 'created_at' => now_local(),
                ]);
            }
            // single role (clean swap — union semantics handled at Users screen if needed)
            db_exec('DELETE FROM user_roles WHERE user_id = ?', [$userId]);
            db_insert('user_roles', ['user_id' => $userId, 'role_id' => (int)$role['id']]);
            // linked employee profile row
            $maxId = (int)db_val('SELECT COALESCE(MAX(id), 0) FROM employees');
            $code = 'EMP-' . str_pad((string)($maxId + 1), 4, '0', STR_PAD_LEFT);
            $tries = 0;
            while (db_val('SELECT 1 FROM employees WHERE code = ?', [$code]) && $tries++ < 20) {
                $maxId += 1000;
                $code = 'EMP-' . str_pad((string)($maxId + 1), 4, '0', STR_PAD_LEFT);
            }
            db_insert('employees', [
                'code' => $code, 'name' => $fullName, 'cnic' => preg_replace('/\s+/', '', (string)($in['cnic'] ?? '')) ?: null,
                'phone' => trim((string)($in['phone'] ?? '')) ?: null, 'designation' => $designation, 'department' => $department,
                'join_date' => $joinDate, 'kind' => 'employee',
                'salary_type' => $salaryType, 'salary_rate' => $salaryRate,
                'payment_method' => in_array($in['payment_method'] ?? '', ['cash', 'bank'], true) ? $in['payment_method'] : 'cash',
                'is_active' => 1, 'user_id' => (int)$userId, 'created_at' => now_local(),
            ]);
        });

        $emp = db_one('SELECT id FROM employees WHERE user_id = (SELECT id FROM users WHERE username = ?)', [$username]);
        audit('create', 'employees', $emp ? (int)$emp['id'] : null, 'Management profile created: ' . $username . ' (' . $roleCode . ')');
        return $emp ? (int)$emp['id'] : 0;
    }

    /** Update the linked user + employee rows together. */
    public static function updateProfile(int $employeeId, array $in): void {
        $emp = db_one('SELECT id, user_id FROM employees WHERE id = ?', [$employeeId]);
        if (!$emp) throw new RuntimeException(__('Profile not found.'));
        $user = $emp['user_id'] ? db_one('SELECT id, username, is_active FROM users WHERE id = ?', [(int)$emp['user_id']]) : null;

        $fullName = trim((string)($in['full_name'] ?? ''));
        if ($fullName === '') throw new RuntimeException(__('Full name is required.'));
        $salaryType = in_array($in['salary_type'] ?? '', ['monthly', 'daily'], true) ? $in['salary_type'] : 'monthly';
        $salaryRate = round((float)($in['salary_rate'] ?? 0), 2);
        $joinDate = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($in['join_date'] ?? '')) ? $in['join_date'] : today());
        $isActive = isset($in['is_active']) ? 1 : 0;
        $branchId = (int)($in['branch_id'] ?? 0) ?: null;

        Database::transaction(function () use ($emp, $user, $fullName, $salaryType, $salaryRate, $joinDate, $isActive, $branchId, $in) {
            if ($user) {
                db_exec('UPDATE users SET full_name = ?, email = ?, phone = ?, branch_id = ?, is_active = ? WHERE id = ?',
                    [$fullName, trim((string)($in['email'] ?? '')) ?: null, trim((string)($in['phone'] ?? '')) ?: null, $branchId, $isActive, $user['id']]);
            }
            db_exec('UPDATE employees SET name = ?, cnic = ?, phone = ?, designation = ?, department = ?, join_date = ?, salary_type = ?, salary_rate = ?, payment_method = ?, is_active = ? WHERE id = ?',
                [$fullName, preg_replace('/\s+/', '', (string)($in['cnic'] ?? '')) ?: null, trim((string)($in['phone'] ?? '')) ?: null,
                 trim((string)($in['designation'] ?? '')) ?: 'Staff', trim((string)($in['department'] ?? '')) ?: 'Management',
                 $joinDate, $salaryType, $salaryRate,
                 in_array($in['payment_method'] ?? '', ['cash', 'bank'], true) ? $in['payment_method'] : 'cash',
                 $isActive, (int)$emp['id']]);
        });

        // optional password change (owner/admin may set one; must be 8+)
        $password = (string)($in['password'] ?? '');
        if ($password !== '' && $user) {
            if (mb_strlen($password) < 8) throw new RuntimeException(__('Password must be at least 8 characters.'));
            db_exec('UPDATE users SET password_hash = ?, force_password_change = 1 WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        // role switch between accountant and manager allowed
        $roleCode = strtolower(trim((string)($in['role'] ?? '')));
        if ($user && self::isValidRole($roleCode)) {
            $role = db_one('SELECT id FROM roles WHERE code = ?', [$roleCode]);
            if ($role) {
                db_exec('DELETE FROM user_roles WHERE user_id = ?', [$user['id']]);
                db_insert('user_roles', ['user_id' => $user['id'], 'role_id' => (int)$role['id']]);
            }
        }
        audit('update', 'employees', $employeeId, 'Management profile updated: ' . $fullName);
    }
}

