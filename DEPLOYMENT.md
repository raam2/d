# Deployment Guide

## Quick Start for Your Server

Based on your URLs:
- Local: http://10.160.118.61/app/main_entry.php?p=dashboard
- Production: https://vedanthomestay.co.in/app/main_entry.php?p=dashboard

### Step 1: Upload Files

Upload these files to your `/app/` directory:
1. `main_entry.php`
2. `config.php`
3. `db.php`
4. `.htaccess` (optional, for environment configuration)

### Step 2: Set Environment Variable

Since your files are in the `/app/` directory, you have two options:

**Option A: Edit config.php** (Easiest)

Change line 5 in `config.php` from:
```php
$ENV = getenv('APP_ENV') ?: 'local';
```

To:
```php
$ENV = 'local';  // Use 'production' for production server
```

**Option B: Use .htaccess**

If your server supports `.htaccess`, upload it to `/app/` and uncomment:
```apache
SetEnv APP_ENV local
```
or
```apache
SetEnv APP_ENV production
```

### Step 3: Verify Database

Your database already has the required tables:
- ✓ `app_pages` - Contains: dashboard, invoices, items, parties
- ✓ `app_components` - Contains the metadata for lists and forms

Check with:
```bash
mariadb u184420243_jayanti_enter4 -e "SELECT slug, title FROM app_pages ORDER BY slug;"
```

### Step 4: Test the Application

Visit one of these URLs:
- Dashboard: `http://10.160.118.61/app/main_entry.php?p=dashboard`
- Parties: `http://10.160.118.61/app/main_entry.php?p=parties`
- Items: `http://10.160.118.61/app/main_entry.php?p=items`
- Invoices: `http://10.160.118.61/app/main_entry.php?p=invoices`

### Expected Result

You should see:
- A dark themed page with navigation header
- A sidebar with links to all pages
- The main content area showing the page template and components from the database

### Troubleshooting

**If you see a blank page:**
1. Check PHP error logs
2. Enable error display temporarily by adding to the top of `main_entry.php`:
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

**If you see a database connection error:**
1. Verify the credentials in `config.php` match your database
2. Check which environment is active by echoing `$ENV` in `config.php`
3. Test connection with:
   ```bash
   mysql -h localhost -u u184420243_gst4 -p u184420243_jayanti_enter4
   ```

**If the page shows but no data:**
1. Verify the database has the metadata:
   ```sql
   SELECT COUNT(*) FROM app_pages;
   SELECT COUNT(*) FROM app_components;
   ```
2. If empty, re-import the SQL file

### Current Configuration

**Local Environment (10.160.118.61):**
- Host: localhost
- User: u184420243_gst4
- Password: Raam2*:1
- Database: u184420243_jayanti_enter4

**Production Environment (vedanthomestay.co.in):**
- Host: 217.21.95.103
- User: u184420243_gst4
- Password: Raam2:=195
- Database: u184420243_jayanti_enter4

## File Permissions

Ensure the PHP files are readable by the web server:
```bash
chmod 644 main_entry.php config.php db.php .htaccess
```

## Next Steps After Deployment

1. Test form submissions (add a party, add an item)
2. Check that lists display correctly
3. Verify navigation between pages works
4. Test the search functionality

## Adding New Features

All features are database-driven. To add new pages or components, insert rows into `app_pages` and `app_components` tables. See `README.md` for examples.
