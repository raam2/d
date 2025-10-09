# Database-Driven GST Accounting App

This repository stores the full database schema and configuration for the Jayanti Enterprises accounting workspace. All application pages, components, styles, and sample data are persisted inside the SQL dump so deployments stay lightweight and offline-ready.

## Getting started

1. Provision a MariaDB or MySQL 8+ database.
2. Import the full schema and data:

```bash
mysql --host=127.0.0.1 --port=3306 --user=$DB_USER --password=$DB_PASS $DB_NAME < u184420243_jayanti_enter4.sql
```

3. Deploy the minimal PHP bootstrap (`config.php`, `db.php`, `main_entry.php`) and point it at the database. The entry point reads from `app_pages` / `app_components` to render the UI.

  - Set credentials via environment variables before serving the app:

```bash
export APP_ENV=production
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_USER=your_user
export DB_PASS=your_password
export DB_NAME=u184420243_jayanti_enter4
```

  - Then run the entry point locally (for example with PHP's built-in server):

```bash
php -S 0.0.0.0:8080 main_entry.php
```

  Visiting `http://localhost:8080/?p=dashboard` should now render the metadata-driven UI.

4. If the database already holds live records, run the lightweight `metadata_patch.sql` instead of the full dump to refresh only the UI metadata:

```bash
mysql --host=$DB_HOST --port=$DB_PORT --user=$DB_USER --password=$DB_PASS $DB_NAME < metadata_patch.sql
```

The script uses upserts so component definitions are updated without touching your transactional tables.

## Repository alignment

- `.github/copilot-coding-agent.yml` codifies the Copilot coding agent workflow (single entry point, dark theme, no external assets).
- `u184420243_jayanti_enter4.sql` now includes:
  - Metadata tables `app_pages` and `app_components` with seeded dashboard, parties, items, and invoice definitions.
  - Existing accounting schema (parties, items, invoices, invoice_items, GST configuration, etc.).
- `app_build.md` and `plan_implementation.md` describe the architecture and implementation blueprint.

## Next steps

- Extend metadata by inserting new rows into `app_pages` / `app_components` instead of creating filesystem templates.
- Keep SQL changes idempotent (`INSERT ... ON DUPLICATE KEY UPDATE`) when seeding metadata so repeated imports do not duplicate rows.
- Run `php -l main_entry.php` after PHP changes and re-import the SQL to validate schema updates.
