<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
try {
    $rows = $pdo->query('SELECT username, preferred_language FROM users')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) echo $r['username'], ' => ', var_export($r['preferred_language'], true), "\n";
} catch (Throwable $e) {
    echo 'ERR: ', $e->getMessage(), "\n";
}
