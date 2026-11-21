<?php
/** Classify flash('type', ...) call sites: pure literals vs dynamic. */
$files = glob(__DIR__ . '/../app/controllers/*.php');
$pure = []; $dyn = [];
foreach ($files as $f) {
    $src = file_get_contents($f);
    preg_match_all("/flash\\(\\s*'(?:success|error|info|warn)'\\s*,\\s*('(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\")\\s*\\)/", $src, $m);
    foreach ($m[1] as $arg) $pure[] = trim($arg);
    // anything else with flash('type', but not matching the pure pattern above — heuristic
    preg_match_all("/flash\\(\\s*'(?:success|error|info|warn)'\\s*,/", $src, $m2);
    $dynCount = count($m2[0]) - count($m[0]);
    if ($dynCount > 0) $dyn[] = basename($f) . ': ' . $dynCount . ' dynamic';
}
sort($pure);
file_put_contents(__DIR__ . '/flash_pure.txt', implode("\n", $pure));
echo "pure single-arg literals: ", count($pure), "\n";
echo "dynamic call sites: ", count($dyn), "\n";
foreach ($dyn as $d) echo "   $d\n";
