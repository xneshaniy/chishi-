<?php
$p = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');
foreach ($p->query("SELECT type, title, substr(body, 0, 50) AS body, is_read FROM notifications WHERE type IN ('claim','payment_request','approval') ORDER BY id") as $r) echo json_encode($r), "\n";
