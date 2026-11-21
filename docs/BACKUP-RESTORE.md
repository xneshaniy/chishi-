# Backup & Restore — JUSTI NUMBER ONE ERP

## What gets backed up

- **Database** — all tables (master data, transactions, journals, audit log) as SQL INSERTs.
- **Uploads** — files under `storage/uploads/` (attachments, if you upload any) should be copied separately via FTP if you use them.
- **Company logo** — `assets/img/company-logo.*` if you uploaded a custom one.

The code itself is not backed up (it's your deployment artifact — keep the project ZIP you deployed from).

## Automatic daily backups (recommended)

**Web UI**: Settings → Backup & Restore → "Generate Backup Now".

**Cron (best)**: hPanel → Advanced → Cron Jobs, daily at 02:30:

```
/usr/bin/php /home/USER/public_html/bin/backup.php
```

Output example:

```
Backup written: /home/u123456789/public_html/storage/backups/ctc_erp_20261113_023000.sql (842.3 KB, 15234 rows)
```

Retention: keeps the newest 10 backups; anything older than 30 days beyond the newest 10 is pruned.

Store at least one copy per month on your PC or Google Drive — backups on the same server don't survive a server failure. Hostinger's own weekly backups (hPanel → Backups) are a useful second layer.

## Manual backup

1. phpMyAdmin → your DB → **Export** → format SQL → enable "Add DROP TABLE" if you want a clean restore → Go.
2. Or SSH: `mysqldump -u USER -p DBNAME > backup.sql`

## Restore — full procedure

**Situation: data corrupted / wrong entries everywhere / testing why not to run DEMO seed.**

1. Put the ERP in maintenance (or accept downtime — small company, do it off-hours).
2. **Create an empty database** in hPanel (e.g. `u123456789_ctc_erp_new`) or drop and re-create the existing one. Same user/permissions.
3. phpMyAdmin → new DB → **Import** → `database/schema.mysql.sql` → Go. (Creates empty tables.)
4. phpMyAdmin → **Import** → your backup `ctc_erp_YYYYMMDD_HHMMSS.sql` → Go.
   - The file contains only INSERTs, so it loads into the empty schema cleanly.
   - If it was created with the web UI/CLI backup tool, data-only is exactly what you want.
5. Point the app at the restored DB: either
   - update `config/config.php` with the new DB name (File Manager → edit), or
   - re-run the installer into the restored (now populated) database — NO, do not: the installer creates the owner and re-seeds; choose the edit option.
6. Sign in and verify: Trial Balance agrees, stock quantities match a physical count on one product, and the last few vouchers (SI/PI/RC) look right.
7. Re-run daily cron.

## Restore — partial (one bad voucher)

Do **not** hand-edit the SQL. Instead:

1. Find the voucher (e.g. SI-00042) in the audit log or Journal Entries.
2. Use the **Reverse** action (invoice → Reverse; expense → Reverse; journal → Reverse) — stock and journals are mirrored automatically and everything stays linked.
3. Re-enter the corrected document. It gets a new voucher number; the audit trail shows both.

## Verifying a backup file

```bash
head -20 storage/backups/ctc_erp_*.sql     # check the header comments
grep -c "INSERT INTO" storage/backups/ctc_erp_*.sql   # count of data rows
```

A valid backup always contains the header line `-- JUSTI NUMBER ONE ERP backup` and `SET FOREIGN_KEY_CHECKS=0;`.

## Disaster recovery checklist

| Event | Action |
|---|---|
| Server lost / account suspended | Fresh Hostinger account → upload code → create DB → import newest backup → point domain → done (≈30 min) |
| Typo in one voucher | Reverse + re-enter (see above) — never edit posted rows |
| Accidental deletion of a table row | Restore latest backup into a **new** DB, copy out the affected table data, fix the live DB |
| Ransomware / compromised hosting | New hosting account, fresh credentials, import backup, rotate all passwords (owner, DB user, FTP, hPanel) |
