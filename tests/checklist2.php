<?php
$f = 'docs/TEST-CHECKLIST.md';
$s = file_get_contents($f);
$anchor = "## B"; // first line starting with ## B
$pos = strpos($s, "\n## B");
$chapter = "\n## B1a — Maintenance & printing\n- [ ] **B-5a** Maintenance → Assets → + New Asset: register one machine; it appears in the asset list with 0 jobs.\n- [ ] **B-5b** Maintenance → New Maintenance Record: broken machine, parts + labour cost; save; record is **pending**, no expense posted yet.\n- [ ] **B-5c** Owner/Admin approves it (Admin Console → Approvals or the Approve & Post button): expense posts exactly once, journal balanced, cash/bank updated.\n- [ ] **B-5d** Reports → Maintenance & Repairs: verify the record appears in the day/month/year/machine groupings; print the report and one maintenance voucher; confirm menus/buttons do NOT print and the company logo/number/date shows.\n- [ ] **B-5e** Print a purchase invoice, a receipt voucher and a customer/supplier statement; confirm each prints on one page with the company header; use Print preview → Save as PDF on one of them; try the Thermal 80mm mode on a voucher.\n";
$s = substr($s, 0, $pos) . $chapter . substr($s, $pos);
file_put_contents($f, $s);
echo "inserted before B section\n";
