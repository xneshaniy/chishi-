<?php
$f = 'app/views/customers/statement.php';
$s = file_get_contents($f);
$n = 0;
$pattern = "/__\\(('[^']*') \\?><\\/span>/";
$s = preg_replace_callback($pattern, function ($m) use (&$n) { $n++; return '__(' . $m[1] . ') ?></span>'; }, $s);
file_put_contents($f, $s);
echo "patched: $n\n";
