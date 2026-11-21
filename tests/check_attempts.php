<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
echo "login_attempts: ", $pdo->query("SELECT COUNT(*) FROM login_attempts")->fetchColumn(), "\n";
foreach ($pdo->query("SELECT username, ip, success, attempted_at FROM login_attempts ORDER BY id DESC LIMIT 10") as $r)
    echo "{$r['attempted_at']} {$r['username']} @ {$r['ip']} success={$r['success']}\n";
