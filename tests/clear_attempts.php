<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
$n = $pdo->exec("DELETE FROM login_attempts WHERE username = 'manager1'");
echo "manager1 login attempts cleared: $n\n";
