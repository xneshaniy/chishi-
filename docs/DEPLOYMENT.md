# Deployment Guide — JUSTI NUMBER ONE ERP on Hostinger Premium

Target: **https://software.chishtitobaccocompany.com** (Hostinger Premium Web Hosting, PHP + MySQL).

---

## 0 · Before you begin

- Hostinger account with the domain `chishtitobaccocompany.com` (or a free subdomain) attached.
- Access to **hPanel** (Hostinger control panel).
- The ERP source code folder on your PC.

## 1 · Upload the files

**Option A — File Manager (easiest)**
1. hPanel → **Files → File Manager** → open `public_html`.
2. Upload a ZIP of the project folder (`ctc_erp.zip`) and use **Extract**.
   - If you want the ERP at the domain root, extract so that `public_html/index.php` exists.
   - If you want it under a subdomain (e.g. `erp.chishtitobaccocompany.com`), create the subdomain first (hPanel → **Domains → Subdomains**) and upload into its document root.

**Option B — FTP/SFTP** (FileZilla): upload all files preserving the folder structure.

> Do **not** upload your development folder (it contains the local database file, local config and tests). Upload only the contents of `justi_erp_production.zip`. The production `config/config.php` will be created by the installer in step 4.

## 2 · Create the MySQL database

1. hPanel → **Databases → MySQL Databases**.
2. Create a database, e.g. name it `u123456789_ctc_erp`.
3. Create a MySQL user with a **strong unique password**, e.g. `u123456789_ctcadmin`, and **add the user to the database with ALL PRIVILEGES**.
4. Note: Host, Database Name, Username, Password — you'll need them for the installer.
   - Hostinger MySQL host is usually `localhost`.

## 3 · Import the schema (recommended before the installer)

1. hPanel → **Databases → phpMyAdmin** → select your new database.
2. Click **Import** → upload `database/schema.mysql.sql` from the project → **Go**. You should see ~60 tables.
   - If you skip this, the web installer in step 4 will also create the tables (it runs the same script). Doing it in phpMyAdmin first makes errors easier to diagnose.

## 4 · Run the installer

1. Visit **`https://software.chishtitobaccocompany.com/install/index.php`**.
2. Requirements checklist must be all green (PHP ≥ 8.1, pdo_mysql, mbstring, config/ and storage/ writable).
   - If `config/` or `storage/` is not writable: File Manager → right-click the folder → **Permissions (755)** for `config`, `storage`, `storage/logs`, `storage/backups`, `storage/uploads`.
3. Enter: MySQL host/db/user/password, company name, and the **owner account** (full name, username, password ≥ 8 chars).
4. Click **Run Installation**. It will:
   - create all tables,
   - seed roles/permissions, the standard chart of accounts (with PK business structure), the current Pakistani fiscal year (July–June) with 12 periods, units, main branch/warehouses and default cash/bank accounts,
   - create your owner account,
   - write `config/config.php` (only on the server — never in source control),
   - write `install/.installed` (locks the installer permanently).

## 5 · Delete the installer

**Immediately** remove the `install/` folder (File Manager → select `install` → Delete). This is the single most important security step after go-live.

## 6 · HTTPS & security hardening

1. hPanel → **SSL** → ensure the free certificate is issued and active for the domain (it usually is by default).
2. The ERP forces HTTPS via `.htaccess` (root) and sets `Secure` session cookies when `app.https = true` in config.
3. Verify `config/config.php` is not downloadable: visit `https://software.chishtitobaccocompany.com/config/config.php` — must show **403** or the page must not render the file. The bundled `config/.htaccess` blocks it.
4. Sign in with the owner account → **change the password** (Users & Roles → your user → Edit).
5. Review **Settings → Company Profile** and upload the official logo.

## 7 · First-run setup checklist (order matters)

1. Settings → Company Profile (logo, NTN/STRN, bank details, invoice terms/footer).
2. Settings → Branches → your factories; Settings → Warehouses → WH locations.
3. Users & Roles → create accounts for admin/accountant/manager/storekeeper/salesmen (assign the salesman profile link for salesman logins).
4. Products → categories, then finished goods / raw materials / packaging (set pack sizes, reorder levels, tax rates).
5. Suppliers, Customers (opening balances if migrating), Salesmen (each gets a van warehouse automatically).
6. Inventory → Opening Stock (quantities + cost) — this posts the Dr Inventory / Cr Capital journal.
7. Settings → Opening Balances → post customer/supplier/cash-bank openings (idempotent; run once).
8. Accounting → Trial Balance → verify it agrees before any new transactions.
9. Cash & Bank → create your real bank accounts; reconcile GL vs statement.
10. Reports → confirm dashboard numbers look right.

## 8 · Cron: daily automated backups

1. hPanel → **Advanced → Cron Jobs**.
2. Add a daily job (e.g. 02:30 AM):

   ```
   /usr/bin/php /home/USER/public_html/bin/backup.php
   ```
   (`USER` is your Hostinger FTP username; find the PHP path in hPanel → PHP Configuration → “CLI path”.)

3. Output: `storage/backups/ctc_erp_YYYYMMDD_HHMMSS.sql` (keeps last 10, prunes >30 days).
4. Enable **Hostinger weekly backups** (hPanel → Backups) as a second, off-site layer. Download a monthly copy to your PC/Drive.
5. Full restore procedure: see [BACKUP-RESTORE.md](BACKUP-RESTORE.md).

## 9 · Updating the ERP later

1. Generate a backup (Settings → Backup → Generate).
2. Upload only changed files (never overwrite `config/config.php`).
3. If a new release includes a `database/migrations/` script, run it once via phpMyAdmin (SQL tab) or CLI: `php bin/migrate.php` (when provided). Current release has no incremental migrations — the schema is stable at v1.0.

## 10 · Troubleshooting

| Symptom | Fix |
|---|---|
| Installer: "config/config.php missing" after install | Upload was incomplete — re-upload missing files. |
| Installer refused to run | It already ran; installer is locked by design. Delete `install/`. |
| 403/500 on all pages | Check `.htaccess` uploaded and `mod_rewrite`/LiteSpeed rules are active; check `storage/logs/error.log`. |
| phpMyAdmin import fails on a statement | Import tables one-by-one or use the installer (step 4) instead; report the failing statement. |
| "No fiscal period covers …" | Create/activate the fiscal year: the installer creates the current FY. If today is outside it, add a period in Settings → Financial Year. |
| White screen / HTTP 500 | `config/app.debug=false` is correct for production; read `storage/logs/error.log` for the real error. Never enable debug on the live site. |
| Login loop / session keeps expiring | Confirm HTTPS works end-to-end (no mixed content), cookie domain matches, server time is correct (Asia/Karachi). |
| CSV opens garbled in Excel | CSV is UTF-8 with BOM; in Excel choose Data → From Text/CSV → UTF-8. |

## 11 · PHP version & extensions on Hostinger

- hPanel → **Advanced → PHP Configuration** → select PHP **8.1** (or newest available) and ensure extensions: `pdo_mysql`, `mbstring`, `gd`, `zip`, `fileinfo`, `openssl` are enabled.
- memory_limit ≥ 128M, max_execution_time ≥ 120 (defaults are fine).
