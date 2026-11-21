<?php
// minimal repro
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE parent (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL UNIQUE)");
    $pdo->exec("CREATE TABLE child (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INT NULL, CONSTRAINT fk_c FOREIGN KEY (parent_id) REFERENCES parent(id) ON DELETE SET NULL, extra_col VARCHAR(50) NULL)");
    echo "A OK\n";
} catch (Throwable $e) { echo "A ERR: ", $e->getMessage(), "\n"; }
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE parent (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL UNIQUE)");
    $pdo->exec("CREATE TABLE child (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INT NULL, CONSTRAINT fk_c FOREIGN KEY (parent_id) REFERENCES parent(id) ON DELETE SET NULL)");
    echo "B OK\n";
} catch (Throwable $e) { echo "B ERR: ", $e->getMessage(), "\n"; }
