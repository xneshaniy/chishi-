<?php
/** Update ARCHITECTURE + TEST-CHECKLIST for bonus stock issue. */
$f = 'docs/ARCHITECTURE.md';
$s = file_get_contents($f);
$anchor = "## Stack & shape";
$pos = strpos($s, $anchor);
$chapter = "### Optional bonus on salesman issues (v3)
- `salesman_issues` gains `bonus_reason VARCHAR(255) NULL`; `salesman_issue_items` carries `paid_qty`, `bonus_qty`, `qty` (= paid + bonus), `line_payable` (paid × rate), `bonus_cost` (bonus × avg cost).
- UI: **Give Bonus? Yes/No** per issue (default **No** — bonus qty inputs hidden and forced 0; issue behaves exactly as before). When Yes: per-line bonus qty + optional `bonus_reason` stored on the issue.
- **Posting rules:** bonus moves free with paid stock out of the source warehouse and into the van (`Inventory::transfer` per portion — paid and bonus each move with their own movement rows). Salesman ledger only records the paid portion (line_payable). Bonus cost debits `PROMO`, credits `INVENTORY` — never the salesman's ledger and never added to `total_payable`.
- **Approval gate:** bonus cost above the configured threshold or any restricted bonus scheme makes the whole issue `pending_approval` — `Posting::postSalesmanIssue` refuses pending records (throws); the admin console flips `pending_approval→approved` atomically and then posts (exactly-once via `salesman_issue` movement ref + journal idem key `salesman_issue:<id>`).
- **Reporting/Print:** issue voucher (`salesmen/{id}/issues/{issue_id}/view`) prints paid/bonus/total per line, bonus reason, amount in words, A4 + thermal. FG daily report splits `issued_to_salesmen` into `issued_paid` / `issued_bonus`; bonus report totals per salesman per period; salesman stock report adds issued paid/bonus/cost columns; settlement shows recent issues with the split and a View/Print link back to the voucher.
- **Transfer vs credit issue:** `issue_type='transfer'` posts no journal and no payable (stock-only move); only `credit_issue` books SM_RECEIVABLE/SALES/COGS/PROMO.

## Stack & shape";
$s = substr($s, 0, $pos) . $chapter;
file_put_contents($f, $s);
echo "arch updated\n";

$f = 'docs/TEST-CHECKLIST.md';
$s = file_get_contents($f);
$needle = "- [ ] **A-4b** `php tests\\allocation_test.php` → expect **6 tests, 0 failures** (supplier payment FIFO allocation: Unpaid → Partial → Paid).";
$pos = strpos($s, $needle);
$insert = $needle . "\n- [ ] **A-4d** `php tests\\salesman_bonus_test.php` → expect **22 tests, 0 failures** (bonus issue: stock/payable/PROMO split, approval threshold, free transfer, TB balanced).\n";
$s = substr($s, 0, $pos) . $insert . substr($s, $pos + strlen($needle));
file_put_contents($f, $s);
echo "checklist updated\n";
