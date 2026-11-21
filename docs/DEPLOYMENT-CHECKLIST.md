# Production Deployment Checklist — JUSTI NUMBER ONE ERP
**Target:** https://software.chishtitobaccocompany.com (Hostinger Premium)
Print this page and tick every box. Do not go live with an unticked CRITICAL item.

## Phase 1 — Before you touch Hostinger

- [ ] **C-1** Backup of any current files/database on the domain (if the site exists).
- [ ] **C-2** `justi_erp_production.zip` present and its contents verified (file list reviewed).
- [ ] **C-3** The upload used is the **production ZIP**, not your development folder (development folders contain the local database, local config, and tests).
- [ ] **C-4** You have: hPanel login, domain DNS, and a decision on document root (`public_html/` or subdomain root).

## Phase 2 — Database (hPanel → Databases → MySQL)

- [ ] **C-5** Created database, e.g. `uXXXXXXXX_ctc_erp` (names are Hostinger-prefixed — do not fight it).
- [ ] **C-6** Created MySQL user with a strong, unique, never-reused password; added to the DB with **ALL PRIVILEGES**.
- [ ] **C-7** Imported `database/schema.mysql.sql` via phpMyAdmin → 55+ tables appear, zero rows of data.

## Phase 3 — Upload (File Manager or FTP/SFTP)

- [ ] **C-8** Uploaded and extracted the ZIP so that `index.php` sits in the document root.
- [ ] **C-9** Confirmed these were uploaded: `index.php`, `.htaccess`, `app/`, `assets/`, `bin/`, `config/`, `database/`, `docs/`, `install/`, `storage/`, `README.md`.
- [ ] **C-10** Confirmed these were **NOT** uploaded: the development database file, the local config file, `tests/`, the demo seeding script, any log files, any SQL backup in `storage/`.
- [ ] **C-11** Folder permissions: `config/`, `storage/`, `storage/logs`, `storage/backups`, `storage/uploads` are **755** (dirs) / 644 (files). `config/config.php` will be created 644 by the installer.

## Phase 4 — Installer

- [ ] **C-12** Visited `https://software.chishtitobaccocompany.com/install/index.php` over **HTTPS**.
- [ ] **C-13** Requirements checklist all green (PHP ≥ 8.1, pdo_mysql, mbstring, writable config/storage).
- [ ] **C-14** Entered DB host (`localhost`), DB name, DB user, DB password, and the **owner account** (full name, username, password ≥ 12 chars, NOT your email password, unique).
- [ ] **C-15** Installer reported success; `install/.installed` exists; installer page now refuses to run.
- [ ] **C-16** **Deleted the `install/` folder** (File Manager → select → Delete). *Critical.*
- [ ] **C-17** Opened https://software.chishtitobaccocompany.com/config/config.php in a browser — it must show **403 / blank / access denied**, never the file contents.

## Phase 5 — HTTPS & account hardening

- [ ] **C-18** Padlock shows on every page; HTTP visitors are redirected (301) to HTTPS.
- [ ] **C-19** Signed in as owner; changed nothing yet but verified the dashboard loads with real (empty) numbers.
- [ ] **C-20** Created the real user accounts (accountant, manager, storekeeper, salesmen) with unique passwords; assigned roles; linked salesman logins to their salesman profile.
- [ ] **C-21** **Owner password** changed from the one typed into the installer (Setup → Users) and written down off-server in a password manager.

## Phase 6 — Master data & opening balances (first week, in this order)

- [ ] **C-22** Settings → Company Profile: company name, address, phone, email, NTN/STRN, bank details, invoice terms; **uploaded the official CTC logo** (the bundled SVG is a recreation — replace it).
- [ ] **C-23** Settings → Bonus Schemes & Rules: set the bonus approval threshold (in Rs) and toggle restricted schemes as needed before any bonus stock is issued.
- [ ] **C-24** Settings → Branches (factories) and Warehouses created; default warehouse set.
- [ ] **C-25** Products → categories, then products (finished goods, raw materials, packaging) with correct UoM, pack size, tax rate, reorder level, **Issue Rate** and **Minimum Price**.
- [ ] **C-25b** Suppliers, Customers (real names/phones/areas/credit limits), Salesmen created.
- [ ] **C-26** Inventory → **Opening Stock**: real quantities + cost; verified the Dr Inventory / Cr Capital journal in Journal Entries.
- [ ] **C-27** Settings → **Opening Balances**: customers, suppliers, cash/bank openings posted (idempotent — safe to click again, it skips already-posted lines).
- [ ] **C-28** Cash & Bank → real bank accounts created and linked (GL); **opening balances posted** (C-27).
- [ ] **C-26b** Employees → create all staff (salary type, rate, designation, department); record attendance for the current month.

## Phase 7 — Financial-year verification

- [ ] **C-29** Settings → Financial Year: current FY active, 12 periods all **open** (none accidentally closed).
- [ ] **C-30** Accounting → Trial Balance: **agrees** (debits = credits) immediately after opening balances are posted. If not, STOP and fix openings before any other transaction.
- [ ] **C-31** Accounting → Balance Sheet balances and equity shows capital + goodwill correctly.

## Phase 8 — Backups & monitoring

- [ ] **C-32** Cron job created (hPanel → Advanced → Cron Jobs), daily 02:30: `/usr/bin/php /home/USER/public_html/bin/backup.php` — and one backup was generated manually via Settings → Backup & Restore to prove it works.
- [ ] **C-33** Backup file downloaded off the server (PC or Google Drive) and opened — it starts with `-- JUSTI NUMBER ONE ERP backup`.
- [ ] **C-34** Hostinger weekly backups enabled (hPanel → Backups).
- [ ] **C-35** `storage/logs/error.log` path known; it was checked and is empty/clean after the first day.

## Phase 9 — Go-live sign-off

- [ ] **C-36** First real sales invoice posted and printed; customer statement opened; receivable visible in Dashboard.
- [ ] **C-37** One real expense entered and approved; one salary advance + payroll draft generated and paid; journals inspected.
- [ ] **C-38** Stock count sheet for one product printed, submitted, and adjustment approved (end-to-end test of the stock-count flow).
- [ ] **C-39** Salesman (if any) logged in on a phone and can see only their customers.
- [ ] **C-40** Owner sign-off recorded: date, name, signature — go-live authorized.

**If anything above fails, stop and do not post real transactions until it is fixed.**
