<?php
$f = 'docs/ARCHITECTURE.md';
$s = file_get_contents($f);
$anchor = "## Stack & shape";
$pos = strpos($s, $anchor);
$chapter = "### Factory Maintenance & Repairs (v3)
- `maintenance_assets` (machine/equipment registry with asset_no, department, location) + `maintenance_records` (problem/work/vendor/parts/labour/other/total/payment method-status/attachment/approval) + `maintenance_parts` (qty × rate lines).
- **Flow:** control creates a record (status `pending`) → `Approvals::create('maintenance', …)` → Owner/Admin decides in Admin Console → `Posting::postMaintenance()` creates a linked `expenses` row (category **Factory Maintenance & Repairs** → GL **5440 Repairs & Maintenance**) and calls `Posting::postExpense()` — exactly once via journal idem key `expense:<id>`; sets record `posted`, `expense_id`, `approved_by/at`. Cash/bank moves only on approval; credit purchases post to AP instead.
- Rejection marks the record `rejected`; an owner/admin reversal runs `Posting::reverseMaintenance()` which reverses the linked expense journal — trial balance stays balanced throughout (proven in `tests/maintenance_test.php`).
- Reports: `reports/maintenance` groups by day/month/year/machine/department/vendor with counts, total, posted-vs-pending split, CSV + print; per-asset history on the record page; dashboard card (this-month posted cost + pending count).
- Attachments stored under `storage/uploads/` (web-denied), served only through auth-gated `maintenance/{id}/attachment`.

### Printing & document management
- Every list/report/voucher renders inside `.print-area` (print CSS shows only it, hides `.no-print`, sidebar, topbar) and has a `🖨 Print` button; PDFs via browser print → Save as PDF.
- `views/partials/doc_head.php` — shared print header (company logo/name/NTN/STRN/address, doc title, doc no, date).
- Printable single-document vouchers added: purchase invoice/GRN, customer receipt, supplier/owner payment, expense voucher — each with doc number, amount-in-words, signature lines, A4 and thermal-80mm modes.
- Urdu prints RTL via the layout's `dir` attribute; print CSS inherits direction from `html[dir]`.

## Stack & shape";
$s = substr($s, 0, $pos) . $chapter;
file_put_contents($f, $s);
echo "architecture updated\n";
