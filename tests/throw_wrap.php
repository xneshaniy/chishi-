<?php
/** Wrap literal segments of throw new RuntimeException(...) / InvalidArgumentException(...) in __(). */
$files = array_merge(glob(__DIR__ . '/../app/services/*.php'), glob(__DIR__ . '/../app/core/*.php'));
$skipped = [];
$changed = 0;

foreach ($files as $f) {
    $src = file_get_contents($f);
    $out = '';
    $i = 0; $len = strlen($src);
    while ($i < $len) {
        if (preg_match('/throw new (RuntimeException|InvalidArgumentException|Exception)\(/A', $src, $m, 0, $i)) {
            $out .= substr($src, $i, strlen($m[0]));
            $i += strlen($m[0]);
            // find matching close paren (strings aware)
            $depth = 1;
            $j = $i;
            while ($j < $len && $depth > 0) {
                $c = $src[$j];
                if ($c === '(') $depth++;
                elseif ($c === ')') $depth--;
                elseif ($c === "'") { $j++; while ($j < $len && $src[$j] !== "'") { if ($src[$j] === '\\') $j++; $j++; } }
                elseif ($c === '"') { $j++; while ($j < $len && $src[$j] !== '"') { if ($src[$j] === '\\') $j++; $j++; } }
                $j++;
            }
            $expr = substr($src, $i, $j - 1 - $i);
            $newExpr = transform_expr($expr, $skipped, basename($f));
            $out .= $newExpr . ')';
            $i = $j;
            $changed++;
        } else { $out .= $src[$i]; $i++; }
    }
    if ($out !== $src) file_put_contents($f, $out);
}
echo "throw sites processed: $changed\n";
echo "skipped (double-quoted with vars, manual review):\n";
foreach ($skipped as $s) echo "   $s\n";

function transform_expr(string $expr, array &$skipped, string $file): string {
    $tokens = [];
    $i = 0; $len = strlen($expr);
    $buf = '';
    while ($i < $len) {
        $c = $expr[$i];
        if ($c === "'" || $c === '"') {
            if ($buf !== '') { $tokens[] = ['other', $buf]; $buf = ''; }
            $q = $c; $j = $i + 1; $s = '';
            while ($j < $len && $expr[$j] !== $q) {
                if ($expr[$j] === '\\') { $s .= $expr[$j] . $expr[$j+1]; $j += 2; }
                else { $s .= $expr[$j]; $j++; }
            }
            $raw = stripcslashes_key($s);
            if ($q === '"' && preg_match('/\$[a-zA-Z_{]/', $s)) {
                $skipped[] = "$file: double-quoted with variable: $s";
                $tokens[] = ['rawstr', $q . $s . $q];
            } else {
                $tokens[] = ['str', $raw];
            }
            $i = $j + 1;
        } else { $buf .= $c; $i++; }
    }
    if ($buf !== '') $tokens[] = ['other', $buf];

    $out = '';
    foreach ($tokens as [$type, $val]) {
        if ($type === 'other') { $out .= $val; }
        elseif ($type === 'rawstr') { $out .= $val; }
        elseif ($type === 'str') {
            if (!preg_match('/[A-Za-z]{2,}/', $val)) { $out .= "'".$val."'" ; continue; }
            $out .= '__(' . var_export($val, true) . ')';
        }
    }
    return $out;
}
function stripcslashes_key(string $s): string {
    return stripslashes($s);
}
