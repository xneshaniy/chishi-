<?php
$pdo = new PDO('sqlite:storage/dev.sqlite');
$n = $pdo->exec("DELETE FROM login_attempts");
echo "all login attempts cleared: $n\n";
