# Accounting Software — Bundle

**Environment detected:** PHP 8.2, MariaDB 10.11 — compatible.

## Quick start
1. Copy the contents of this folder to your web root, e.g. `/var/www/html/bharat_accounting`.
2. Ensure `db.php` has your DB host/user/password/dbname.
3. Open `http://YOUR-HOST/bharat_accounting/check_env.php` — confirm DB connects.
4. Open `http://YOUR-HOST/bharat_accounting/scan_php_files.php` — fix any highlighted files if you move/rename.
5. Go to `index.php` for quick links.
6. For SSC payable tagging, use `bulk_mark_ssc_payable.php` (chunked, retry, timeout-safe).
7. For realtime sales entry (low memory), use `sales-entry_ssc_payable.php`.

## Notes
- This bundle avoids long locks and timeouts (portable for MariaDB & MySQL).
- If a reverse proxy times out, reduce batch size in bulk pages and prefer CLI.
- CLI usage example: `php bulk_mark_ssc_payable.php 1 ids ids.txt`

Generated on 2025-09-11 09:37:33.
