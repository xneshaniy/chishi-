<?php
/** Freeze the known verbatim-acceptable Urdu entries (acronyms, codes, proper nouns — unchanged in Urdu too). */
$lang = require __DIR__ . '/../app/lang.php';
$en = $lang['en']; $ur = $lang['ur'];
$list = [];
foreach ($en as $k => $_) if (isset($ur[$k]) && $ur[$k] === $k) $list[] = $k;
$src = "<?php\n/** Verbatim-acceptable Urdu entries (acronyms/codes/proper nouns unchanged in Urdu). */\nreturn array (\n";
foreach ($list as $k) $src .= '  ' . var_export($k, true) . ",\n";
$src .= ");\n";
file_put_contents(__DIR__ . '/ur_verbatim_whitelist.php', $src);
echo count($list), " whitelisted\n";
