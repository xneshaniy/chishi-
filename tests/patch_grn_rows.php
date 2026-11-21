<?php
$f = 'app/views/purchase/invoice_new.php';
$s = file_get_contents($f);
$n = 0;
$s = preg_replace_callback('/(<td><input class="f" style="width:80px" type="number" step="0.001" min="0" name="items\[qty\]\[[^"]*" value="[^"]*" required><\/td>)/', function ($m) use (&$n) {
    $n++;
    $k = $n === 1 ? '0' : ($n - 1);
    // Preserve existing field name exactly if any index pattern
    if (preg_match('/items\[qty\]\[([^\]]+)\]/', $m[1], $mm)) $k = $mm[1];
    return $m[1] . '<td><select class="f item-unit" style="width:74px" name="items[unit][' . $k . ']"><option value="KG">KG</option></select></td>';
}, $s);
file_put_contents($f, $s);
echo "patched lines: $n\n";
