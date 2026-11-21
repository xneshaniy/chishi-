<?php
/** Build a clean Roman Urdu dictionary — no English-as-Roman placeholders. */
$lang = require __DIR__ . '/../app/lang.php';
$en = $lang['en'];
$gen = require __DIR__ . '/dict_roman_generated.php';
$auto = require __DIR__ . '/tr_roman_auto.php';
$full = require __DIR__ . '/tr_roman_full.php';
$ovr = require __DIR__ . '/tr_roman_overrides.php';

$clean = [];
// 1) generated dict — keep only entries that actually translate
foreach ($gen as $k => $v) if ($v !== $k) $clean[$k] = $v;
// 2) auto-transliterated dictionary for everything still missing
foreach ($auto as $k => $v) if (!isset($clean[$k])) $clean[$k] = $v;
// 3) hand-curated full dictionary
foreach ($full as $k => $v) { if ($v !== $k) $clean[$k] = $v; }
// 4) exact overrides — final word
foreach ($ovr as $k => $v) $clean[$k] = $v;

file_put_contents(__DIR__ . '/dict_roman_clean.php', "<?php\nreturn " . var_export($clean, true) . ";\n");
echo count($clean), " cleaned roman entries\n";
$identity = 0;
foreach ($en as $k => $_) {
    if (isset($clean[$k]) && $clean[$k] === $k) $identity++;
}
echo "en-used keys ending identical: $identity\n";
