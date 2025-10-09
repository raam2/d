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

## Zoho Books Integration

This repository includes a complete export utility for migrating data to Zoho Books.

### 🚀 Quick Start

**👉 New to Zoho Books migration? Start here: [START_HERE.md](START_HERE.md)**

```bash
# 1. Configure (one time)
cp .env.example .env
# Edit .env with your credentials

# 2. Export all data
source .env
php zoho_export.php all

# 3. Import to Zoho Books
# Visit https://books.zoho.in/app
# Settings → Import Data
```

### 📚 Documentation

- **[START_HERE.md](START_HERE.md)** - Begin here! Quick start guide
- **[ZOHO_QUICK_REFERENCE.md](ZOHO_QUICK_REFERENCE.md)** - Command reference
- **[ZOHO_IMPORT_GUIDE.md](ZOHO_IMPORT_GUIDE.md)** - Complete 10+ page guide
- **[zoho_migration_guide.html](zoho_migration_guide.html)** - Visual guide
- **[SECURITY.md](SECURITY.md)** - Security best practices

### What Gets Exported

- **Contacts:** All parties (customers and vendors) with GSTIN
- **Items:** All active products with HSN codes
- **Invoices:** Sales and purchase invoices with line items
- **GST Data:** Tax calculations and compliance information

### Files

- `zoho_export.php` - Export utility (web + CLI)
- `test_exports.sh` - Test suite
- `zoho_export_components.sql` - Database integration (optional)
- `.env.example` - Environment template

## Next steps

- Extend metadata by inserting new rows into `app_pages` / `app_components` instead of creating filesystem templates.
- Keep SQL changes idempotent (`INSERT ... ON DUPLICATE KEY UPDATE`) when seeding metadata so repeated imports do not duplicate rows.
- Run `php -l main_entry.php` after PHP changes and re-import the SQL to validate schema updates.
