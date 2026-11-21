<?php
$css = file_get_contents('assets/css/app.css');
echo 'active', ' = ', (strpos($css, '.nav a.active') !== false ? 'yes' : 'NO'), "\n";
echo 'sidebar-overlay', ' = ', (strpos($css, '.sidebar-overlay') !== false ? 'yes' : 'NO'), "\n";
echo 'doc-head', ' = ', (strpos($css, '.doc-head') !== false ? 'yes' : 'NO'), "\n";
echo 'alert-error', ' = ', (strpos($css, '.alert-error') !== false ? 'yes' : 'NO'), "\n";
echo 'alert-success', ' = ', (strpos($css, '.alert-success') !== false ? 'yes' : 'NO'), "\n";
echo 'thermal-sheet', ' = ', (strpos($css, '.thermal-sheet') !== false ? 'yes' : 'NO'), "\n";
