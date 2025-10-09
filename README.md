# Database-Driven GST Accounting App

This repository stores the full database schema and configuration for the Jayanti Enterprises accounting workspace. All application pages, components, styles, and sample data are persisted inside the SQL dump so deployments stay lightweight and offline-ready.

## 🆕 Database Normalization (Latest Update)

**NEW**: Comprehensive database normalization addressing HSN codes, product name variants, and denormalized staging tables.

### Quick Links
- 📘 **[Quick Reference](QUICK_REFERENCE.md)** - 1-minute deploy guide
- 📚 **[Full Guide](NORMALIZATION_GUIDE.md)** - Complete documentation
- 🧪 **[Testing Guide](TESTING_GUIDE.md)** - Test before deploying
- 🏗️ **[Architecture](ARCHITECTURE.md)** - Design diagrams

### What's Fixed
- ✅ **Multiple Product Names** → `item_name_variants` table maps Hindi/English/alias names
- ✅ **Missing HSN Codes** → Auto-populated from staging tables (0% → 60-80% coverage)
- ✅ **Denormalized Tables** → Proper 3NF structure for purchase invoices
- ✅ **No Search by Variants** → Fast lookup by any product name
- ✅ **No Data Quality Monitoring** → New diagnostics dashboard

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

## Repository alignment

- `.github/copilot-coding-agent.yml` codifies the Copilot coding agent workflow (single entry point, dark theme, no external assets).
- `u184420243_jayanti_enter4.sql` now includes:
  - Metadata tables `app_pages` and `app_components` with seeded dashboard, parties, items, and invoice definitions.
  - Existing accounting schema (parties, items, invoices, invoice_items, GST configuration, etc.).
- `app_build.md` and `plan_implementation.md` describe the architecture and implementation blueprint.

## Database Normalization

The repository now includes comprehensive database normalization to address:

1. **Multiple Product Names**: Created `item_name_variants` table to map Hindi/English/alias names to canonical items
2. **Missing HSN Codes**: Automated migration to populate HSN codes from staging tables
3. **Denormalized Staging Tables**: Created proper normalized `purchase_invoice_header` and `purchase_invoice_line_items` tables

See [NORMALIZATION_GUIDE.md](NORMALIZATION_GUIDE.md) for detailed documentation.

### Quick Start

```bash
# 1. Backup your database first!
mysqldump -h HOST -u USER -p DATABASE > backup.sql

# 2. Run normalization (automated)
./deploy_normalization.sh production

# 3. Or run manually
mysql -h HOST -u USER -p DATABASE < database_normalization.sql
mysql -h HOST -u USER -p DATABASE < metadata_update.sql
```

### New Features

- **Item Variants Management**: Access at `?p=item_variants` to manage multiple product names
- **Normalized Purchase Invoices**: View at `?p=purchase_invoices` for clean invoice data
- **Data Quality Diagnostics**: Check at `?p=data_diagnostics` for HSN coverage and migration status
- **Search by Any Name**: Find items using Hindi names, English names, or aliases
- **Automated HSN Updates**: Bulk update items with HSN codes from staging data

## Next steps

- Extend metadata by inserting new rows into `app_pages` / `app_components` instead of creating filesystem templates.
- Keep SQL changes idempotent (`INSERT ... ON DUPLICATE KEY UPDATE`) when seeding metadata so repeated imports do not duplicate rows.
- Run `php -l main_entry.php` after PHP changes and re-import the SQL to validate schema updates.
- After normalization, archive old staging tables once data is verified.
