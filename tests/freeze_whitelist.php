<?php
/** Freeze the known verbatim-acceptable Roman Urdu entries (standard loanwords used identically in Roman Urdu orthography, acronyms, proper nouns, file/code tokens). */
$lang = require __DIR__ . '/../app/lang.php';
$en = $lang['en']; $roman = $lang['roman'];
$list = [];
foreach ($en as $k => $_) if (isset($roman[$k]) && $roman[$k] === $k) $list[] = $k;
$src = "<?php\n/**\n * Verbatim-acceptable Roman Urdu entries.\n * Each is spelled identically in standard Pakistani Roman Urdu (loanwords), is an acronym/code/proper noun/technical token,\n * or is user-entered data. Adding a new untranslated key fails the audit.\n */\nreturn array (\n";
foreach ($list as $k) $src .= '  ' . var_export($k, true) . ",\n";
$src .= ");\n";
file_put_contents(__DIR__ . '/roman_verbatim_whitelist.php', $src);
echo count($list), " whitelisted\n";
