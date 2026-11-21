<?php
$p = __DIR__ . '/../app/services/ManagementTeam.php';
$s = file_get_contents($p);
$s = str_replace(
    "        \$roleIds = role_ids_for_codes(self::roleCodes());\n        if (!\$roleIds) return [];\n        \$in = implode(',', array_fill(0, count(\$roleIds), '?'));\n        return db_all(",
    "        return db_all(",
    $s
);
$marker = "\n/** role ids helper (code list → ids) */";
$pos = strpos($s, $marker);
if ($pos !== false) {
    $s = substr($s, 0, $pos) . "\n";
}
file_put_contents($p, $s);
echo "done\n";
