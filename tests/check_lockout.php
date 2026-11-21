<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
try {
    foreach ($pdo->query("SELECT id, username, failed_logins, locked_until FROM users")->fetchAll(PDO::FETCH_ASSOC) as $u) {
        echo $u['id'], ' ', $u['username'], ' | fails: ', ($u['failed_logins'] ?? '-'), ' | locked_until: ', ($u['locked_until'] ?? '-'), "\n";
    }
} catch (Throwable $e) {
    echo "users query failed: ", $e->getMessage(), "\n";
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo "  col: ", $c['name'], "\n";
}
