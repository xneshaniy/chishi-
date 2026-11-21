<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($pdo->query("SELECT username, password_hash, is_active, force_password_change FROM users") as $r) {
    echo $r['username'], ' is_active=', $r['is_active'], ' force=', $r['force_password_change'], ' hash=', $r['password_hash'], "\n";
    if ($r['username'] === 'owner') echo "  owner password check owner1234: ", password_verify('owner1234', $r['password_hash']) ? 'MATCH' : 'NO MATCH', "\n";
}
