<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
foreach ($pdo->query("SELECT id, username, password_hash FROM users")->fetchAll(PDO::FETCH_ASSOC) as $u) {
    echo $u['id'], ' ', $u['username'], ' | hash starts: ', substr($u['password_hash'], 0, 10), "\n";
}
$m = $pdo->query("SELECT password_hash FROM users WHERE username = 'manager1'")->fetchColumn();
foreach (['Manager@1234x', 'Manager@1234', 'manager1234', 'Manager1234!', 'Manager@12345'] as $p) {
    echo (password_verify($p, $m) ? 'MATCH' : '----'), "  $p\n";
}
