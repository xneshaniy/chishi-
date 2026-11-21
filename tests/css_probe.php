<?php
$css = file_get_contents('assets/css/app.css');
preg_match_all('/\.([A-Za-z][\w-]*)/', $css, $m);
$c = array_unique($m[1]);
foreach (['alert-error','thermal-sheet','accent','gold','red','on','green','amber','blue','gray','open','paid','partial','posted','received','rejected','reversed'] as $t) {
    echo $t, ' = ', in_array($t, $c) ? 'yes' : 'NO', "\n";
}
