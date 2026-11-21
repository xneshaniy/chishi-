<?php
/** Repair double-encoded UTF-8 in app/views/sales/invoice_view.php
 *  Original UTF-8 bytes were mis-read as Windows-1252 and re-encoded as UTF-8 once.
 *  Reverse = encode current text as CP1252, giving back the original UTF-8 bytes. */
$file = dirname(__DIR__) . '/app/views/sales/invoice_view.php';
$orig = file_get_contents($file);
// snapshot forensics first
copy($file, 'storage/backups/invoice_view.corrupt.' . date('YmdHis') . '.bak');

// sanity: any *valid* non-ASCII UTF-8 that would NOT be part of mojibake? report candidates
$text = $orig;
preg_match_all('/[\xC2-\xF4][\x80-\xBF]{1,3}/u', $text, $m);
$multiByte = $m[0];
echo "multibyte sequences found: " . count($multiByte) . "\n";
$uniq = array_unique($multiByte);
echo "unique multibyte sequences: " . count($uniq) . "\n";
foreach (array_slice($uniq, 0, 40) as $seq) {
    $cp = iconv('UTF-8', 'CP1252', $seq);
    $back = $cp !== false ? @iconv('CP1252', 'UTF-8', $cp) : 'REVERSE-FAIL';
    $roundtrip = ($back === $seq) ? 'round-trip-ok' : 'no-roundtrip';
    echo '  seq="' . addcslashes($seq, "\0..\x1f") . "\" cp1252-bytes=" . bin2hex((string)$cp) . " → $roundtrip\n";
}
