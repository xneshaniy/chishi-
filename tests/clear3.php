<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
$pdo->exec("DELETE FROM login_attempts");
echo "attempts cleared\n";
