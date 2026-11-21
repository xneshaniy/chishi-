<?php
$f = 'docs/USER-MANUAL.md';
$s = file_get_contents($f);
// 1) insert manager RM request note reinforcement and maintenance in Storekeeper flow
$storeOld = "3. Stock moving between factory/warehouse/salesman: **Inventory → Stock Transfers** (or Salesmen → Issue Stock for vans).";
$storeNew = "3. Stock moving between factory/warehouse/salesman: **Inventory → Stock Transfers** (or Salesmen → Issue Stock for vans).\n4. Machine broke down? **Maintenance → New Maintenance Record** — enter the machine, what happened, parts used, labour and vendor; the record waits for Owner/Admin approval before any payment posts.";
$s = str_replace($storeOld, $storeNew, $s, $n1);
// 2) add Manager step about maintenance
$mgrOld = "5. Need raw materials? **Purchases → Purchase Orders → New PO** — it goes to the Owner/Admin for approval. You never add raw stock directly.";
$mgrNew = $mgrOld . "\n6. Report a machine fault: **Maintenance → New Maintenance Record** — Owner/Admin must approve before payment posts.";
$s = str_replace($mgrOld, $mgrNew, $s, $n2);
// 3) add full maintenance + print chapter before the Notifications chapter
$anchor = "## Notifications (real-time)";
$pos = strpos($s, $anchor);
$chapter = "## Maintenance, repairs & printable vouchers

**Factory Maintenance & Repairs** (sidebar → Expenses & Payroll → **Factory Maintenance & Repairs**) records every machine/equipment/electrical/vehicle repair.

**Recording a repair:**

1. **Maintenance → New Maintenance Record** (or **Assets → + New Asset** to register the machine first).
2. Enter: machine/asset name (pick from the registry or type it), asset ID/code, department, location, problem description, date, work performed, technician/repair shop, spare parts used (qty × rate — rows added with + Add Part), labour charges, other expenses — the form computes the total automatically. Payment method: Cash, Bank, or Credit/Payable (not paid yet). Attach an invoice, receipt, photo or PDF.
3. **It never posts straight to the ledger.** On save it goes to Owner/Admin for approval (Admin Console → Approvals, or the Approve & Post button on the record/list). No journal is created and no cash/bank moves until an Owner/Admin approves. Exactly-once guarantee — approving twice is impossible.
4. On approval, an expense is created under category **Factory Maintenance & Repairs** and posted to the ledger exactly once. Cash/bank is updated (or Accounts Payable when paying on credit).
5. **Reports → Maintenance & Repairs**: totals grouped by day/month/year/machine/department/vendor, full per-machine history, how much each machine costs to repair, CSV export, printable.

**Printing (button 🖨 on every document):**

- Purchase invoices & GRNs (list + detail → Print / Save as PDF)
- Raw material purchases, production reports, stock ledger, sales invoices, returns, receipts, statements (customers & suppliers), payment vouchers, expense vouchers, maintenance records & reports, salary slips, daily/monthly/yearly reports — every report has a Print button.
- **Thermal 80 mm mode** on payment / receipt / expense vouchers (Thermal 80mm button) — for small printers.
- Documents print with company name, logo, NTN/STRN, document number, date and signature lines; menus and buttons are hidden; Urdu works RTL automatically when the language is set to اردو.
- Print uses the browser's print dialog — choose **Save as PDF** to download a PDF copy. Every print shows a preview first.

**Maintaining the asset registry:** **Maintenance → Maintenance Assets** — every machine with asset ID, department, location; shows total repair jobs and cumulative repair cost per machine; history of all repairs is one click away from each record.

";
$s = substr($s, 0, $pos) . $chapter . substr($s, $pos);
file_put_contents($f, $s);
echo "chapters inserted: $n1 and $n2\n";
