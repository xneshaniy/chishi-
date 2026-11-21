<?php
/** Debug fix_nested line-by-line on a real broken line to find the bug. */
$open = '<' . '?= __(';
$lines = [
    '  <div class="card blue"><div class="lbl"><?= __(\'Used in Production\') ?></div></div>',
    '<div class="card red"><div class="lbl"><?= __(\'Low Stock Items\') ?></div></div>',
];
function scanLine(string $line, int &$count, string $open): string {
    $out = '';
    $i = 0;
    $n = strlen($line);
    while (($pos = strpos($line, $open, $i)) !== false) {
        $before = substr($line, 0, $pos);
        $sq = substr_count($before, "'") - substr_count($before, "\\'");
        $dq = substr_count($before, '"') - substr_count($before, '\\"');
        $inStr = (($sq % 2) === 1) || (($dq % 2) === 1);
        echo "tag at $pos inStr=" . ($inStr ? 'yes' : 'no') . " sq=$sq dq=$dq\n";
        if (!$inStr) { $out .= substr($line, $i, $pos - $i) . $open; $i = $pos + strlen($open); continue; }
        $q = $pos + strlen($open);
        while ($q < $n && ctype_space($line[$q])) $q++;
        if ($q >= $n || $line[$q] !== "'") { $out .= substr($line, $i, $pos - $i) . $open; $i = $pos + strlen($open); continue; }
        $q++;
        $key = '';
        while ($q < $n) {
            $ch = $line[$q];
            if ($ch === '\\' && $q + 1 < $n) { $key .= $line[$q + 1]; $q += 2; continue; }
            if ($ch === "'") break;
            $key .= $ch; $q++;
        }
        $q++;
        while ($q < $n && ctype_space($line[$q])) $q++;
        if ($q + 1 < $n && $line[$q] === ')' && $line[$q + 1] === '>') $q += 2;
        else if (substr($line, $q, 2) === ')' . '>') $q += 2;
        $out .= substr($line, $i, $pos - $i) . $key;
        $i = $q;
        $count++;
    }
    $out .= substr($line, $i);
    return $out;
}
$c = 0;
foreach ($lines as $l) { echo "LINE: $l\n"; echo "OUT:  ", scanLine($l, $c, $open), "\n\n"; }
