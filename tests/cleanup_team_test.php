<?php
/** Remove all dev-only testaccttwo users + their employees. */
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
$pdo->exec('PRAGMA foreign_keys = ON');
$users = $pdo->query("SELECT id FROM users WHERE username = 'testaccttwo'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    $emps = $pdo->query("SELECT id, photo_path FROM employees WHERE user_id = {$u['id']}")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($emps as $e) {
        $dir = __DIR__ . '/../storage/uploads/employees/' . (int)$e['id'];
        if (is_dir($dir)) { foreach (glob($dir . '/*') as $f) @unlink($f); @rmdir($dir); }
        $pdo->exec("DELETE FROM employees WHERE id = {$e['id']}");
    }
    $pdo->exec("DELETE FROM user_roles WHERE user_id = {$u['id']}");
    $pdo->exec("DELETE FROM users WHERE id = {$u['id']}");
}
echo "removed ", count($users), " testaccttwo users\n";
