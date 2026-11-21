<?php
/**
 * Pass-1 bug: wrapped HTML text inside single/double-quoted PHP strings.
 * Hand-rolled scanner: finds <?= __('KEY') ?> tags, checks quote parity before them,
 * and unwraps those inside a PHP string literal back to raw KEY.
 */
$views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/views', FilesystemIterator::SKIP_DOTS));
$fixedFiles = 0; $fixedTags = 0;

function scanLine(string $line, int &$count): string {
    $out = '';
    $i = 0;
    $n = strlen($line);
    while (($pos = strpos($line, "<?= __(", $i)) !== false) {
        $before = substr($line, 0, $pos);
        // parity of unescaped quotes before the tag
        $sq = substr_count($before, "'") - substr_count($before, "\\'");
        $dq = substr_count($before, '"') - substr_count($before, '\\"');
        $inString = (($sq % 2) === 1) || (($dq % 2) === 1);
        if (!$inString) { $out .= substr($line, $i, $pos - $i) . "<?= __("; $i = $pos + 6; continue; }
        // parse KEY: <?= __('KEY') ?> starting at $pos
        $p = $pos + 6; // after <?= __(
        // skip whitespace
        while ($p < $n && ctype_space($line[$p])) $p++;
        if ($p >= $n || $line[$p] !== "'") { $out .= substr($line, $i, $pos - $i) . "<?= __("; $i = $pos + 6; continue; }
        $p++; // skip opening quote
        $key = '';
        while ($p < $n) {
            $ch = $line[$p];
            if ($ch === '\\' && $p + 1 < $n) { $key .= $line[$p + 1]; $p += 2; continue; }
            if ($ch === "'") break;
            $key .= $ch; $p++;
        }
        $p++; // skip closing quote
        while ($p < $n && ctype_space($line[$p])) $p++;
        if ($p + 1 < $n && $line[$p] === ')' && $line[$p + 1] === '?') {
            $p += 2;
            while ($p < $n && ctype_space($line[$p])) $p++;
            if ($p < $n && $line[$p] === '>') $p++;
        }
        $out .= substr($line, $i, $pos - $i) . $key;
        $i = $p;
        $count++;
    }
    $out .= substr($line, $i);
    return $out;
}

foreach ($views as $f) {
    if ($f->getExtension() !== 'php') continue;
    $content = file_get_contents($f->getPathname());
    $count = 0;
    $lines = explode("\n", $content);
    foreach ($lines as $idx => $line) { $lines[$idx] = scanLine($line, $count); }
    if ($count > 0) {
        file_put_contents($f->getPathname(), implode("\n", $lines));
        $fixedFiles++;
        $fixedTags += $count;
        echo "fixed ", str_replace(__DIR__ . '/../', '', $f->getPathname()), " ($count tags)\n";
    }
}
echo "Files fixed: $fixedFiles | tags unwrapped: $fixedTags\n";
