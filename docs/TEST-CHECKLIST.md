# Test Checklist — JUSTI NUMBER ONE ERP
Two parts: **A) automated/pre-deployment** (on a PC before uploading) and **B) live smoke tests** (on the real server after install). Tick both for sign-off.

## A · Pre-deployment (development machine)

### A1 · Clean-room tests (fresh in-memory database)
- [ ] **A-1** `php tests\functional.php` → expect **44 tests, 0 failures** (weighted-average costing; default).
- [ ] **A-2** `php tests\functional.php ignored fifo` → expect **44 tests, 0 failures** (FIFO costing).
- [ ] **A-3** `php tests\costing_test.php` → both methods print PASS with correct mixed-lot costs.
- [ ] **A-4** `php tests\schema_check.php` → `ALL STATEMENTS OK` (schema applies cleanly).
- [ ] **A-4a** `php tests\purchase_approve_test.php` → expect **18 tests, 0 failures** (manager purchase request → owner approval exactly-once).
- [ ] **A-4b** `php tests\allocation_test.php` → expect **6 tests, 0 failures** (supplier payment FIFO allocation: Unpaid → Partial → Paid).
- [ ] **A-4d** `php tests\salesman_bonus_test.php` → expect **25 tests, 0 failures** (bonus issue: stock/payable/PROMO split, approval threshold, free transfer, TB balanced, rate snapshot survives product price changes, per-line/per-salesman rate overrides).
- [ ] **A-4e** `php tests\payroll_partial_test.php` → expect **14 tests, 0 failures** (net payable creation, partial approval-gated payment posts journal + payroll amount_paid accumulates + payslip goes partial, second partial completes to paid, full approval blocked while payment pending, over-payment approval refused (request rolled back, stays pending), legacy full-pay path, TB balanced).
- [ ] **A-4f** `php tests\rm_packaging_test.php` → expect **24 tests, 0 failures** (TON/KG/MAN purchase booking with KG conversion + stock at 2100 kg, MAN=40 kg, TON/MAN display, factory consumption posting exactly-once + over-issue blocked, batch production absorption (RM NOT re-deducted), FG packaging display, salesman issue payable).
- [ ] **A-4g** `php tests\salesman_salary_test.php` → expect **9 tests, 0 failures** (goods issue payable, diesel expense posted once, advance posted once, salary posted, numbers line up, idempotent re-post, TB balanced).

- [ ] **A-4c** `php tests\maintenance_test.php` → expect **22 tests, 0 failures** (maintenance record → owner/admin approval → expense posted exactly once → reversal).
- [ ] **A-5** `php tests\sweep.php` with the dev server running → every authenticated route returns 200 with no PHP errors (≈95 routes, `Failures: 0`).

### A2 · Static checks
- [ ] **A-6** `php -l` on every `.php` file → no syntax errors (covered by Release build script, see below).
- [ ] **A-7** No file in the ZIP contains development passwords, the local development database file, or real passwords (run the ZIP verifier below).
- [ ] **A-8** ZIP contains no `tests/`, no demo seeding script, no test-only schema, no development database file, no local config file.

### A3 · ZIP verification

The build command `php tests\build_production_zip.php` does this automatically: it lints every PHP file, packages the ZIP and then scans **every file in it** for development passwords, the development database file, demo record markers and development accounts. On success it prints `Verification: no development credentials or demo data in the package.` — otherwise it **aborts and deletes the ZIP**.

- [ ] **A-9** The build script reports verification passed.
- [ ] **A-10** `database/schema.mysql.sql` in the ZIP is the production schema (DDL only, zero INSERT statements).

## B1a — Maintenance & printing
- [ ] **B-5a** Maintenance → Assets → + New Asset: register one machine; it appears in the asset list with 0 jobs.
- [ ] **B-5b** Maintenance → New Maintenance Record: broken machine, parts + labour cost; save; record is **pending**, no expense posted yet.
- [ ] **B-5c** Owner/Admin approves it (Admin Console → Approvals or the Approve & Post button): expense posts exactly once, journal balanced, cash/bank updated.
- [ ] **B-5d** Reports → Maintenance & Repairs: verify the record appears in the day/month/year/machine groupings; print the report and one maintenance voucher; confirm menus/buttons do NOT print and the company logo/number/date shows.
- [ ] **B-5e** Print a purchase invoice, a receipt voucher and a customer/supplier statement; confirm each prints on one page with the company header; use Print preview → Save as PDF on one of them; try the Thermal 80mm mode on a voucher.

## B · Live smoke tests (on the server, after install, ~15 minutes)

Log in as the owner created during install. Use **small values** or real-but-tiny transactions, then reverse them. **Never** use demo seed on production.

### B1 · Security
- [ ] **B-1** Open `https://software.chishtitobaccocompany.com/install/index.php` → page refuses to run (“Installer locked”) — proof the installer is sealed. If it still runs, **delete `install/` now and re-check**.
- [ ] **B-2** Open `https://software.chishtitobaccocompany.com/config/config.php` → 403/blank, no credentials leaked.
- [ ] **B-3** Open `https://software.chishtitobaccocompany.com/storage/backups/` → 403, directory listing off.
- [ ] **B-4** Log in 6 times with a wrong password → 6th attempt blocked with “Too many failed attempts”.
- [ ] **B-5** Salesman test login (if any) sees only their customers; cannot open Settings/Users/Accounting (403 page shown).

### B2 · Master data & openings
- [ ] **B-6** Create one test finished-good product (e.g. code `TEST-001`); it appears in Products with stock 0.
- [ ] **B-7** Inventory → Opening Stock: enter 10 units @ Rs 100 for TEST-001 → verify journal `OB-…` posted (Journal Entries) with Dr Inventory / Cr Capital.
- [ ] **B-8** Settings → Opening Balances → Post All → verify ledger shows openings; **Trial Balance agrees**.

### B3 · Trading cycle
- [ ] **B-9** Purchases → GRN: buy TEST-001, 10 units @ Rs 110, credit from a test supplier → stock 20, supplier payable visible in Payables.
- [ ] **B-10** Sales → New Invoice: sell 2 units to a test customer (credit) @ Rs 150 + tax → stock 18, customer receivable increases, invoice prints with amount-in-words.
- [ ] **B-11** Cash & Bank → New Receipt: collect partial Rs 100 from the customer → receivable decreases; journal SI/RC visible.
- [ ] **B-12** Sales → Sales Returns: return 1 unit of that invoice → stock 19, receivable decreases; return voucher SR-… visible; TB still agrees.
- [ ] **B-13** Inventory → Stock Transfer to the salesman warehouse: issue 1 unit → **no journal created** (check Journal Entries — count unchanged), van stock +1.
- [ ] **B-14** Expenses → New Expense (category "Repairs & Maintenance") → approve it → TB agrees; P&L shows the expense.
- [ ] **B-14a** Payroll → New Employee: create EMP-TEST-1 (monthly, Rs 30,000, payment method Cash/Bank) → save → appears Active with that method.
- [ ] **B-14b** Payroll → Generate Draft Payroll (current month, just EMP-TEST-1) → payslip created with Net Pay Rs 30,000, amount paid Rs 0, status DRAFT.
- [ ] **B-14c** Enter a smaller amount (Rs 12,000) on the payslip and submit for approval (or leave blank and edit the amount) → 302 + "submitted" flash; payslip shows "Awaiting approval: Rs 12,000", remaining Rs 18,000.
- [ ] **B-14d** Admin Console → PAYROLL_PAYMENT filter → request for EMP-TEST-1, amount Rs 12,000 → Approve (as a different user) → flash "journal posted exactly once"; payslip shows PAID 12,000 / remaining 18,000 / PARTIAL badge.
- [ ] **B-14e** Employees → Ledger → EMP-TEST-1's salary ledger: earned 30,000 / paid 12,000 / remaining 18,000 cards correct; Payslips table shows PARTIAL; Payment History shows Rs 12,000 · Main Bank Account. Print Ledger prints the company header.
- [ ] **B-14f** Payslip print view shows "Payment Status" block (paid 12,000, remaining 18,000, approval history).
- [ ] **B-14g** Pay remaining Rs 18,000 → approve as admin → payslip flips to PAID (Rs 30,000/Rs 0/Rs 0); salary ledger totals update; TB still balanced.
- [ ] **B-14h** Advance request as manager: Employees → Request Advance → Rs 5,000 → Reason "test" → lands pending; requester cancels it → advance cancelled, nothing paid, approval note "Cancelled by requester".
- [ ] **B-14i** Settings → Company Profile → "Payroll Policy" → set Maximum Salary Advance Rs 10,000 → Save; as a non-Owner/Admin with advances.request, request Rs 20,000 → rejected with "exceeds the maximum allowed" message; reset to 0 after test.

### B4 · Reports
- [ ] **B-15** Trial Balance → agrees; P&L and Balance Sheet print sensibly; Day Book lists the test vouchers.
- [ ] **B-16** Reports → Stock Valuation shows TEST-001 with correct value; Sales Summary groups by product; Customer Aging shows the test customer bucket.
- [ ] **B-17** Reports → each export CSV opens in Excel with proper UTF-8 text (letters/Urdu intact if used).

### B5 · Backup & recovery
- [ ] **B-18** Settings → Backup & Restore → Generate Backup Now → success message; file listed with size > 0.
- [ ] **B-19** Click cron instructions on screen; hPanel cron set for 02:30 daily; one cron run verified next morning (or run `bin/backup.php` manually once).

### B6 · Clean-up
- [ ] **B-20** Reverse the test transactions (invoice → Reverse; GRN → Reverse; expense → Reverse) **or** delete test products/customers/suppliers and re-check TB balances. Document what was kept.
- [ ] **B-21** Confirm FINAL Trial Balance agrees and stock for TEST-001 is zero/expected, then remove TEST-001 master data.
- [ ] **B-22** Record sign-off: tester name, date, TB status, go-live decision.

## What “pass” means
B1–B5 all pass, B6 reconciles to zero (or a documented, intended state), Trial Balance agrees, and the owner has signed. Any CRITICAL failure in B1–B3 blocks go-live.
