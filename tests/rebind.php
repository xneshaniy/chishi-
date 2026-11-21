<?php
/**
 * Re-bind translations to ENGLISH KEYS (not line numbers):
 *   current used_keys.txt (1267 keys) + reconstructed old list (1335) + tr_*_*.php (1-based indexes)
 * Old list position i (0-based) <=> tr[i+1]. Match by whitespace/apostrophe-normalized string.
 */
$old = [];
foreach (glob(__DIR__ . '/used_keys_old_*.txt') as $f) {
    $old = array_merge($old, file($f, FILE_IGNORE_NEW_LINES));
}
echo "old: ", count($old), "\n";
$norm = fn(string $s) => preg_replace('/\s+/u', '', str_replace([chr(194) . chr(160), chr(160), chr(92) . chr(39)], [' ', ' ', chr(39)], $s));
$oldMap = [];
foreach ($old as $i => $k) { $oldMap[$norm($k)] = $i + 1; }

$load = function (string $pattern): array {
    $out = [];
    foreach (glob(__DIR__ . '/' . $pattern) as $f) { $out += require $f; }
    return $out;
};
$trUr = $load('tr_ur_*.php');
$trRo = $load('tr_roman_*.php');
echo "trUr: ", count($trUr), " | trRo: ", count($trRo), "\n";

$ur = []; $roman = []; $missing = [];
foreach (file(__DIR__ . '/used_keys.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $key) {
    $idx = $oldMap[$norm($key)] ?? null;
    if ($idx === null) { $missing[] = $key; continue; }
    $ur[$key] = $trUr[$idx] ?? null;
    $roman[$key] = $trRo[$idx] ?? null;
    if ($ur[$key] === null || $roman[$key] === null) { echo "MISSING tr at old index $idx for key: $key\n"; }
}
echo "\nkeys with no translation bound: ", count($missing), "\n";
foreach ($missing as $m) echo "  NO-BIND: $m\n";

file_put_contents(__DIR__ . '/dict_ur_generated.php', "<?php\nreturn " . var_export($ur, true) . ";\n");
file_put_contents(__DIR__ . '/dict_roman_generated.php', "<?php\nreturn " . var_export($roman, true) . ";\n");
echo "\ngenerated dict files written\n";
