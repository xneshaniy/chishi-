<?php
/**
 * Repair views broken by the over-broad artifact sweep.
 * fix2 removed `) ?>` before `</span>` everywhere: in badge strings that was correct,
 * but it also ate the close tags of `<?= ... ?>` expressions directly before `</span>`.
 * This pass re-inserts ` ?>` before a `</span>` that appears inside an OPEN PHP tag,
 * outside a string literal. In-string damage is left alone (those fixes were correct).
 */
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/views', FilesystemIterator::SKIP_DOTS));
$totalFix = 0;
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') continue;
    $src = file_get_contents($file->getPathname());
    $len = strlen($src);
    $out = '';
    $i = 0; $mode = 'html'; $string = null; // string: null | "'" | '"'
    $fixes = 0;
    while ($i < $len) {
        $two = substr($src, $i, 2);
        if ($mode === 'html') {
            if ($two === '<?') {
                $open = substr($src, $i, 5) === '<?php' ? 5 : ((substr($src, $i, 3) === '<?=') ? 3 : 2);
                $out .= substr($src, $i, $open); $i += $open; $mode = 'php'; continue;
            }
            $out .= $src[$i]; $i++; continue;
        }
        // PHP mode
        $c = $src[$i];
        if ($string === null) {
            if ($two === '?>') { $out .= '?>'; $i += 2; $mode = 'html'; continue; }
            if ($c === "'" || $c === '"') { $string = $c; $out .= $c; $i++; continue; }
            // illegal bare </span> inside PHP, outside a string → close the tag first
            if (substr($src, $i, 7) === '</span>') { $out .= ' ?>'; $fixes++; $mode = 'html'; continue; }
            $out .= $c; $i++; continue;
        }
        // inside a string literal
        $out .= $c;
        if ($c === '\\') { $out .= $src[$i + 1] ?? ''; $i += 2; continue; }
        if ($c === $string) { $string = null; $i++; continue; }
        $i++;
    }
    if ($fixes) {
        file_put_contents($file->getPathname(), $out);
        echo "REPAIRED ($fixes) {$file->getPathname()}\n";
        $totalFix += $fixes;
    }
}
echo "TOTAL reinserted close tags: $totalFix\n";
