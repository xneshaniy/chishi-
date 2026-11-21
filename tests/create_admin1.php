<?php
/** DEV-ONLY: create a second dev-admin so owner↔admin approval flows can be tested in the UI. */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$exists = $pdo->query("SELECT id FROM users WHERE username = 'admin1'")->fetchColumn();
if (!$exists) {
    $pdo->prepare('INSERT INTO users (username, full_name, password_hash, is_active, notify_sound, notify_desktop, created_at) VALUES (?,?,?,1,1,1,?)')
        ->execute(['admin1', 'Dev Admin One', password_hash('Admin@1234x', PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
    $uid = (int)$pdo->lastInsertId();
    $rid = (int)$pdo->query("SELECT id FROM roles WHERE code = 'admin'")->fetchColumn();
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)')->execute([$uid, $rid]);
    echo "admin1 created (dev only)\n";
} else {
    echo "admin1 already exists\n";
}
