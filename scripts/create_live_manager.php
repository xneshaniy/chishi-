<?php
header('Content-Type: text/plain');
require __DIR__ . '/config/config.php';
require __DIR__ . '/app/core/bootstrap.php';

$username = 'manager';
$password = 'manager@1234';
$fullName = 'Factory & Store Manager';
$phone = '0300-9876543';

// Check if manager already exists
$existing = db_one("SELECT id, username FROM users WHERE username = ?", [$username]);
if ($existing) {
    echo "Manager already exists with ID: {$existing['id']}\n";
    $userId = (int)$existing['id'];
    // Update password hash just in case
    $hash = password_hash($password, PASSWORD_DEFAULT);
    db_exec("UPDATE users SET password_hash = ?, is_active = 1 WHERE id = ?", [$hash, $userId]);
    echo "Updated password hash for manager.\n";
} else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $userId = db_insert('users', [
        'username' => $username,
        'password_hash' => $hash,
        'full_name' => $fullName,
        'phone' => $phone,
        'is_active' => 1,
        'created_at' => now_local(),
    ]);
    echo "Created user 'manager' with ID: {$userId}\n";
}

// Assign role 'manager'
$mgrRole = db_one("SELECT id FROM roles WHERE code = 'manager'");
if ($mgrRole) {
    $roleId = (int)$mgrRole['id'];
    db_exec("DELETE FROM user_roles WHERE user_id = ?", [$userId]);
    db_exec("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleId]);
    echo "Assigned role 'manager' (role_id={$roleId}) to user ID {$userId}\n";
}

// Verify
$check = db_one("SELECT u.id, u.username, u.full_name, u.is_active, r.code as role 
                 FROM users u 
                 JOIN user_roles ur ON ur.user_id = u.id 
                 JOIN roles r ON r.id = ur.role_id 
                 WHERE u.id = ?", [$userId]);
print_r($check);
echo "MANAGER ACCOUNT READY FOR TEST!\n";
