<?php
$root = dirname(__DIR__);
foreach (['app','config','database','storage','tests','bin','docs'] as $d) {
    $dir = $root . '/' . $d;
    @mkdir($dir, 0755, true);
    file_put_contents($dir . '/.htaccess', "# Block web access to this directory (Apache 2.4 + 2.2 syntax for Hostinger)\nRequire all denied\nDeny from all\n");
}
echo "protected dirs written to $root\n";
