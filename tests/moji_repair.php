<?php
/**
 * Repair cp1252-misread UTF-8 mojibake (e.g. â€" -> em dash) in a targeted set of files.
 * Strategy: at each marker (c3 a2 = U+00E2 'â'), consume the run of non-ASCII chars
 * that fully decode via mb_convert_encoding(..., 'WINDOWS-1252','UTF-8') into bytes,
 * and splice those bytes back. Only ASCII allowed after the run. Dry-run by default.
 */
$files = ['app/controllers/SalesController.php', 'app/controllers/SalesmenController.php'];
$apply = in_array('--apply', $argv, true);

// decode one UTF-8 char at $i; return [codepoint, byteLength] or null
$utf8Char = function (string $s, int $i): ?array {
    $b = ord($s[$i]);
    if ($b < 0x80) return [$b, 1];
    $len = $b >= 0xF0 ? 4 : ($b >= 0xE0 ? 3 : ($b >= 0xC0 ? 2 : 0));
    if ($len === 0 || $i + $len > strlen($s)) return null;
    $cp = $b & (0xFF >> ($len + 1));
    for ($k = 1; $k < $len; $k++) {
        $cb = ord($s[$i + $k]);
        if (($cb & 0xC0) !== 0x80) return null;
        $cp = ($cp << 6) | ($cb & 0x3F);
    }
    return [$cp, $len];
};
// codepoints that exist in cp1252 (0xA0-0xFF latin-1 + selected high range)
$cp1252High = [0x20AC=>0x80,0x201A=>0x82,0x0192=>0x83,0x201E=>0x84,0x2026=>0x85,0x2020=>0x86,0x2021=>0x87,0x02C6=>0x88,0x2030=>0x89,0x0160=>0x8A,0x2039=>0x8B,0x0152=>0x8C,0x017D=>0x8E,0x2018=>0x91,0x2019=>0x92,0x201C=>0x93,0x201D=>0x94,0x2022=>0x95,0x2013=>0x96,0x2014=>0x97,0x02DC=>0x98,0x2122=>0x99,0x0161=>0x9A,0x203A=>0x9B,0x0153=>0x9C,0x017E=>0x9E,0x0178=>0x9F];

foreach ($files as $file) {
    $s = file_get_contents($file);
    $out = ''; $i = 0; $n = strlen($s); $fixed = 0; $skipped = 0;
    while ($i < $n) {
        if (substr($s, $i, 2) === "\xc3\xa2") {
            // consume run of chars that are cp1252-mapped
            $j = $i; $run = ''; $ok = true;
            while (true) {
                $ch = $utf8Char($s, $j);
                if ($ch === null) break;
                [$cp, $len] = $ch;
                if ($cp === 0xE2 || $cp === 0x00E2) { $run .= "\xc3\xa2"; $j += 2; continue; } // â itself
                if ($cp < 0x80) break; // ASCII terminates
                $inLatin1 = ($cp >= 0xA0 && $cp <= 0xFF); // latin-1 == cp1252 for these
                $inHigh = isset($cp1252High[$cp]);
                if ($inLatin1 || $inHigh) { $run .= substr($s, $j, $len); $j += $len; } else break;
            }
            $decoded = @mb_convert_encoding($run, 'WINDOWS-1252', 'UTF-8');
            if ($decoded !== false && $decoded !== '') {
                $out .= $decoded; $fixed++; $i = $j; continue;
            }
            $skipped++;
        }
        $out .= $s[$i]; $i++;
    }
    echo "=== $file : fixed=$fixed skipped=$skipped\n";
    if ($apply) file_put_contents($file, $out);
}
