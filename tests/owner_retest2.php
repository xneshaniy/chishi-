<?php
$jar = tempnam(sys_get_temp_dir(), 'jar');
$login = shell_exec('curl.exe -s -i -c ' . escapeshellarg($jar) . ' "http://127.0.0.1:8080/index.php?r=auth/login"');
preg_match('/name="csrf" value="([a-f0-9]+)"/', $login, $m);
$csrf = $m[1] ?? '';
$resp = shell_exec('curl.exe -s -i -c ' . escapeshellarg($jar) . ' -b ' . escapeshellarg($jar) . ' -X POST --data-urlencode ' . escapeshellarg('csrf=' . $csrf) . ' --data-urlencode "username=owner" --data-urlencode "password=owner1234" "http://127.0.0.1:8080/index.php?r=auth/login"');
echo $resp;
