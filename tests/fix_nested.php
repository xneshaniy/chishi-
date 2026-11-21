<?php
/** Unwrap __('KEY') calls that sit INSIDE single/double-quoted PHP strings (invalid nesting).
 *  This file deliberately contains no php close tags inside strings or comments. */
$views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
$fixedTags = 0;
$open = '<' . '?= __(';
$close = ')' . '>';
foreach ($views as $f) {
    if ($f->getExtension() !== 'php') continue;
    $lines = file($f->getPathname());
    $changed = false;
    foreach ($lines as $i => $line) {
        $out = '';
        $pos = 0;
        while (($p = strpos($line, $open, $pos)) !== false) {
            $before = substr($line, 0, $p);
            $sq = substr_count($before, "'") - substr_count($before, "\\'");
            $dq = substr_count($before, '"') - substr_count($before, '\\"');
            $inStr = (($sq % 2) === 1) || (($dq % 2) === 1);
            if (!$inStr) { $out .= substr($line, $pos, $p - $pos); $pos = $p + strlen($open); continue; }
            $q = $p + strlen($open);
            while ($q < strlen($line) && ctype_space($line[$q])) $q++;
            if ($line[$q] !== "'") { $out .= substr($line, $pos, $p - $pos); $pos = $p + strlen($open); continue; }
            $q++;
            $key = '';
            while ($q < strlen($line)) {
                if ($line[$q] === '\\') { $key .= $line[$q + 1]; $q += 2; continue; }
                if ($line[$q] === "'") break;
                $key .= $line[$q]; $q++;
            }
            $q++;
            while ($q < strlen($line) && ctype_space($line[$q])) $q++;
            if ($line[$q] === ')') { $q++; while ($q < strlen($line) && ctype_space($line[$q])) $q++; if (substr($line, $q, 2) === '?>') $q += 2; elseif ($line[$q] === '>') $q++; }
            $out .= substr($line, $pos, $p - $pos) . $key;
            $pos = $q; $fixedTags++; $changed = true;
        }
        $out .= substr($line, $pos);
        if ($out !== $line) $lines[$i] = $out;
    }
    if ($changed) { file_put_contents($f->getPathname(), implode('', $lines)); echo "fixed: ", str_replace(__DIR__ . '/../', '', $f->getPathname()), "\n"; }
}
echo "tags unwrapped: $fixedTags\n";
