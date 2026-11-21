<?php
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(file_get_contents('database/schema.sqlite.sql'));
    echo "sqlite OK\n";
} catch (Throwable $e) {
    echo "ERR: ", $e->getMessage(), "\n";
}
