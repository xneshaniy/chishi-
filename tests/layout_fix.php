<?php
$f = 'app/views/layout.php';
$s = file_get_contents($f);
$a = 'strtoupper($currentUser[\'role_codes\'][0] ?? \'user\')';
$b = 'strtoupper(Auth::roleCodes()[0] ?? \'user\')';
$s = str_replace($a, $b, $n1);
$a2 = "\$currentUser['role_codes'][0] ?? ''";
$b2 = "Auth::roleCodes()[0] ?? ''";
$s = str_replace($a2, $b2, $n2);
file_put_contents($f, $s);
echo "layout_role_codes: $n1 $n2\n";
