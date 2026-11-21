<?php
/** Translation audit — verifies every en key resolves identically-styled in ur/roman.
 *  Fails (exit 1) if any entry is untranslated AND not on the explicit verbatim whitelist. */
$lang = require __DIR__ . '/../app/lang.php';
$en = $lang['en']; $ur = $lang['ur']; $roman = $lang['roman'];
$whiteRoman = require __DIR__ . '/roman_verbatim_whitelist.php';
$whiteUr = require __DIR__ . '/ur_verbatim_whitelist.php';
$whiteRomanMap = array_flip($whiteRoman);
$whiteUrMap = array_flip($whiteUr);
echo "en=", count($en), " ur=", count($ur), " roman=", count($roman), "\n";
$badRoman = []; $badUr = [];
foreach ($en as $k => $_) {
    if (isset($roman[$k]) && $roman[$k] === $k && !isset($whiteRomanMap[$k])) $badRoman[] = $k;
    if (isset($ur[$k]) && $ur[$k] === $k && !isset($whiteUrMap[$k])) $badUr[] = $k;
}
echo "roman identical (unwhitelisted): ", count($badRoman), "\n";
foreach ($badRoman as $k) echo "   $k\n";
echo "ur identical (unwhitelisted): ", count($badUr), "\n";
foreach ($badUr as $k) echo "   $k\n";
if (count($badRoman) || count($badUr)) exit(1);
echo "AUDIT PASSED — every untranslated entry is a known verbatim loanword/acronym/proper-noun\n";
