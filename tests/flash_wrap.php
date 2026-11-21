<?php
/**
 * Mechanical i18n wrapper for flash('type', EXPR) call sites.
 * Wraps each static string segment of EXPR in __() (dynamic parts like money($x) stay as-is).
 * Double-quoted strings with embedded variables are reported for manual review, not rewritten.
 */
$files = glob(__DIR__ . '/../app/controllers/*.php');
$skipped = [];
$changed = 0;

foreach ($files as $f) {
    $src = file_get_contents($f);
    $out = '';
    $i = 0; $len = strlen($src);
    while ($i < $len) {
        if (preg_match('/flash\(\s*\'(?:success|error|info|warn)\'\s*,/A', $src, $m, 0, $i)) {
            $out .= substr($src, $i, strlen($m[0]));
            $i += strlen($m[0]);
            // find matching close paren for flash( starting from $i
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
            $closing = ')'; // the ')' we stopped before is part of flash call
            $newExpr = transform_expr($expr, $skipped, basename($f));
            $out .= $newExpr . ')';
            $i = $j;
            $changed++;
        } else {
            $out .= $src[$i];
            $i++;
        }
    }
    if ($out !== $src) file_put_contents($f, $out);
}
echo "flash call sites processed: $changed\n";
echo "skipped (double-quoted with vars, manual review):\n";
foreach ($skipped as $s) echo "   $s\n";

function transform_expr(string $expr, array &$skipped, string $file): string {
    // split into alternating (string literal | other) using a small tokenizer
    $tokens = [];
    $i = 0; $len = strlen($expr);
    $buf = '';
    while ($i < $len) {
        $c = $expr[$i];
        if ($c === "'") {
            if ($buf !== '') { $tokens[] = ['other', $buf]; $buf = ''; }
            $j = $i + 1; $s = '';
            while ($j < $len && $expr[$j] !== "'") {
                if ($expr[$j] === '\\') { $s .= $expr[$j] . $expr[$j+1]; $j += 2; }
                else { $s .= $expr[$j]; $j++; }
            }
            $tokens[] = ['str', stripcslashes_key(stripslashes_custom($s))];
            $i = $j + 1;
        } elseif ($c === '"') {
            if ($buf !== '') { $tokens[] = ['other', $buf]; $buf = ''; }
            $j = $i + 1; $s = '';
            while ($j < $len && $expr[$j] !== '"') {
                if ($expr[$j] === '\\') { $s .= $expr[$j] . $expr[$j+1]; $j += 2; }
                else { $s .= $expr[$j]; $j++; }
            }
            if (preg_match('/\$[a-zA-Z_{]/', $s)) {
                $skipped[] = "$file: double-quoted with variable: $s";
                $tokens[] = ['str_raw', $s];
            } else {
                $tokens[] = ['str', stripcslashes_key(stripslashes_custom($s))];
            }
            $i = $j + 1;
        } else {
            $buf .= $c; $i++;
        }
    }
    if ($buf !== '') $tokens[] = ['other', $buf];

    $out = '';
    foreach ($tokens as [$type, $val]) {
        if ($type === 'other') { $out .= $val; }
        elseif ($type === 'str_raw') { $out .= '"' . $val . '"'; }
        elseif ($type === 'str') {
            // skip translation for pure punctuation / too-short segments
            if (!preg_match('/[A-Za-z]{2,}/', $val)) { $out .= "'" . str_replace("'", "\\'", $val) . "'"; continue; }
            $out .= '__(' . var_export($val, true) . ')';
        }
    }
    return $out;
}

function stripslashes_custom(string $s): string {
    return stripslashes($s);
}
function stripcslashes_key(string $s): string {
    return $s; // keep raw content; var_export will re-escape properly
}
