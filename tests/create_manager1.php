<?php
/** DEV-ONLY: create a manager account so purchase-request approval flows can be tested in the UI. */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$exists = $pdo->query("SELECT id FROM users WHERE username = 'manager1'")->fetchColumn();
if (!$exists) {
    $pdo->prepare('INSERT INTO users (username, full_name, password_hash, is_active, created_at) VALUES (?,?,?,1,?)')
        ->execute(['manager1', 'Dev Manager One', password_hash('Manager@1234x', PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
    $uid = (int)$pdo->lastInsertId();
    $rid = (int)$pdo->query("SELECT id FROM roles WHERE code = 'manager'")->fetchColumn();
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)')->execute([$uid, $rid]);
    echo "manager1 created (dev only)\n";
} else {
    echo "manager1 already exists\n";
}
