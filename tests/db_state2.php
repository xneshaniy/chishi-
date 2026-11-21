<?php
$path = 'C:/Users/Haseeb/Desktop/chishti/storage/dev.sqlite';
echo "exists: ", (file_exists($path) ? 'yes' : 'no'), " bytes: ", filesize($path), " mtime: ", date('Y-m-d H:i:s', filemtime($path)), "\n";
try {
    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "products: ", $db->query('SELECT COUNT(*) FROM products')->fetchColumn(), "\n";
    echo "users: ", $db->query('SELECT COUNT(*) FROM users')->fetchColumn(), "\n";
} catch (Throwable $e) {
    echo 'ERROR: ', $e->getMessage(), "\n";
}
