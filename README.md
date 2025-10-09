# Database-Driven GST Accounting App

This repository stores the full database schema and configuration for the Jayanti Enterprises accounting workspace. All application pages, components, styles, and sample data are persisted inside the SQL dump so deployments stay lightweight and offline-ready.

## Quick Setup Guide

### 1. Database Import

Import the database schema on your environment:

**Local (MariaDB):**
```bash
mysql -u u184420243_gst4 -p'Raam2*:1' u184420243_jayanti_enter4 < database_already_exit.sql
```

**Production (MySQL on Hostinger):**
```bash
mysql -h 217.21.95.103 -u u184420243_gst4 -p'Raam2:=195' u184420243_jayanti_enter4 < database_already_exit.sql
```

Or use phpMyAdmin to import `database_already_exit.sql`.

### 2. Deploy PHP Files

Upload these files to your web server:
- `config.php` - Database credentials for local and production
- `db.php` - Database connection helpers
- `main_entry.php` - Application entry point
- `test_connection.php` - Database connection test tool

### 3. Set Environment

**For Local Development:**
```bash
export APP_ENV=local
php -S localhost:8080
```

**For Production (Hostinger):**

Add to `.htaccess`:
```apache
SetEnv APP_ENV production
```

Or edit `config.php` line 5:
```php
$ENV = 'production';  // Force production mode
```

### 4. Test Database Connection

Before using the app, verify the connection:

```
http://localhost:8080/test_connection.php
https://vedanthomestay.co.in/app/test_connection.php
```

This will show:
- ✓ Database connection status
- ✓ Available pages (dashboard, parties, items, invoices)
- ✓ Component count per page
- ✓ Critical tables verification

### 5. Access the Application

Once the test passes:

```
http://localhost:8080/main_entry.php?p=dashboard
https://vedanthomestay.co.in/app/main_entry.php?p=dashboard
```

Available pages:
- `?p=dashboard` - Dashboard with statistics
- `?p=parties` - Customer/supplier management
- `?p=items` - Inventory with GST rates
- `?p=invoices` - Invoice management

## Database Credentials

Configured in `config.php`:

**Local Environment:**
- Host: `localhost`
- User: `u184420243_gst4`
- Password: `Raam2*:1`
- Database: `u184420243_jayanti_enter4`

**Production Environment:**
- Host: `217.21.95.103`
- User: `u184420243_gst4`
- Password: `Raam2:=195`
- Database: `u184420243_jayanti_enter4`

## Repository alignment

- `.github/copilot-coding-agent.yml` codifies the Copilot coding agent workflow (single entry point, dark theme, no external assets).
- `database_already_exit.sql` includes:
  - Metadata tables `app_pages` and `app_components` with seeded dashboard, parties, items, and invoice definitions.
  - Existing accounting schema (parties, items, invoices, invoice_items, GST configuration, etc.).
- `app_build.md` and `plan_implementation.md` describe the architecture and implementation blueprint.

## Troubleshooting

### Blank Page / No Response

1. Run `test_connection.php` first to verify database connectivity
2. Check that `APP_ENV` is set correctly (local or production)
3. Verify credentials in `config.php` match your database
4. Check PHP error logs for details
5. Ensure all tables exist (particularly `app_pages` and `app_components`)

### Database Connection Failed

1. Test manually: `mysql -h HOST -u USER -pPASS DATABASE -e "SELECT 1"`
2. Verify database server is running and accessible
3. Check firewall allows connections to port 3306
4. Confirm user has proper permissions

### Missing Pages or Components

1. Check `test_connection.php` output to see what's in the database
2. Verify `app_pages` has 4 rows (dashboard, parties, items, invoices)
3. Verify `app_components` has 9+ rows
4. Re-import `database_already_exit.sql` if data is missing

## Architecture

- **Single Entry Point**: `main_entry.php` handles all requests via `?p=` parameter
- **Database-Driven UI**: All pages and components stored in metadata tables
- **No External Dependencies**: Pure PHP, vanilla JavaScript, inline CSS
- **Dark Theme**: Memory-efficient design optimized for extended use
- **GST Compliant**: Full CGST/SGST/IGST support for Indian businesses

## Next steps

- Extend metadata by inserting new rows into `app_pages` / `app_components` instead of creating filesystem templates.
- Keep SQL changes idempotent (`INSERT ... ON DUPLICATE KEY UPDATE`) when seeding metadata so repeated imports do not duplicate rows.
- Run `php -l main_entry.php` after PHP changes and re-import the SQL to validate schema updates.
