# Deployment Guide

This guide walks you through deploying the database-driven accounting application to both local and production environments.

## Prerequisites

- PHP 7.4 or higher (8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache, Nginx, or PHP built-in server for local)

## Local Deployment (Development)

### Step 1: Database Setup

1. Ensure MariaDB is running:
```bash
sudo systemctl status mariadb
```

2. Import the database:
```bash
mysql -u u184420243_gst4 -p'Raam2*:1' u184420243_jayanti_enter4 < database_already_exit.sql
```

### Step 2: Configure Environment

Edit `config.php` or set environment variable:
```bash
export APP_ENV=local
```

### Step 3: Start PHP Server

```bash
cd /path/to/project
php -S localhost:8080
```

### Step 4: Test Connection

Open browser: `http://localhost:8080/test_connection.php`

Expected output:
- ✓ Database connection successful
- ✓ Found 4 pages in app_pages table
- ✓ Found 9+ components in app_components table
- ✓ All critical tables exist

### Step 5: Access Application

Open: `http://localhost:8080/main_entry.php?p=dashboard`

## Production Deployment (Hostinger)

### Step 1: Upload Files

Using FTP/SFTP or File Manager, upload these files to `/public_html/app/`:
- `config.php`
- `db.php`
- `main_entry.php`
- `test_connection.php`
- `.htaccess` (copy from `.htaccess.example`)

### Step 2: Database Setup

**Option A: Via phpMyAdmin**
1. Login to phpMyAdmin
2. Select database `u184420243_jayanti_enter4`
3. Click "Import"
4. Choose `database_already_exit.sql`
5. Click "Go"

**Option B: Via SSH (if available)**
```bash
mysql -h 217.21.95.103 -u u184420243_gst4 -p'Raam2:=195' u184420243_jayanti_enter4 < database_already_exit.sql
```

### Step 3: Configure .htaccess

Create or edit `/public_html/app/.htaccess`:
```apache
SetEnv APP_ENV production
```

Or edit `config.php` line 5:
```php
$ENV = 'production';
```

### Step 4: Set Permissions

Ensure files have correct permissions:
```bash
chmod 644 *.php
chmod 644 .htaccess
```

### Step 5: Test Connection

Visit: `https://vedanthomestay.co.in/app/test_connection.php`

You should see:
- Environment: **production**
- Database Host: **217.21.95.103**
- ✓ Database connection successful
- ✓ Found 4 pages
- ✓ Found 9+ components

### Step 6: Access Application

Visit: `https://vedanthomestay.co.in/app/main_entry.php?p=dashboard`

## Troubleshooting

### "No response" or Blank Page

**Step 1: Check test_connection.php**
- If test shows errors, database credentials are wrong
- If test shows "Table not found", re-import SQL file

**Step 2: Enable Error Display**
In `config.php`, temporarily add at top:
```php
ini_set('display_errors', '1');
error_reporting(E_ALL);
```

**Step 3: Check PHP Error Log**
- Hostinger: Check error logs in cPanel
- Local: Check terminal output where PHP server is running

**Step 4: Verify Environment**
Run `test_connection.php` and confirm:
- Environment matches (local or production)
- Database host is correct
- Credentials are valid

### Database Connection Errors

**Error: "Access denied"**
- Double-check username and password in `config.php`
- Ensure database user has privileges:
  ```sql
  GRANT ALL ON u184420243_jayanti_enter4.* TO 'u184420243_gst4'@'%';
  FLUSH PRIVILEGES;
  ```

**Error: "Unknown database"**
- Database name is wrong or doesn't exist
- Create database first:
  ```sql
  CREATE DATABASE u184420243_jayanti_enter4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```

**Error: "Can't connect to MySQL server"**
- Check if database server is running
- Verify hostname (localhost vs 217.21.95.103)
- Check firewall allows port 3306

### Missing Pages

**Error: "Define pages inside app_pages"**
- The `app_pages` table is empty
- Re-import `database_already_exit.sql`
- Or manually insert pages from the SQL file

### Performance Issues

**Slow page loads:**
1. Add indexes to frequently queried columns
2. Optimize SQL queries in `app_components`
3. Enable MySQL query cache
4. Use connection pooling

## Verification Checklist

Before going live, verify:

- [ ] Database imported successfully
- [ ] `test_connection.php` shows all green checkmarks
- [ ] Dashboard page loads (`?p=dashboard`)
- [ ] Parties page loads and form works (`?p=parties`)
- [ ] Items page loads and form works (`?p=items`)
- [ ] Invoices page loads (`?p=invoices`)
- [ ] Error display is disabled in production (remove ini_set from main_entry.php)
- [ ] `.htaccess` sets correct environment
- [ ] HTTPS is working (if SSL certificate installed)

## Updating the Application

### Update PHP Files Only
1. Upload new `main_entry.php`, `config.php`, or `db.php`
2. Clear browser cache
3. Test with `test_connection.php`

### Update Database Schema
1. Create SQL migration script with ALTER/CREATE statements
2. Import via phpMyAdmin or command line
3. Verify with `test_connection.php`

### Update Pages/Components
1. Login to phpMyAdmin
2. Edit `app_pages` or `app_components` tables directly
3. Refresh browser - changes appear immediately

## Security Checklist

- [ ] Change default database passwords
- [ ] Disable error display in production
- [ ] Protect `config.php` and `db.php` (see `.htaccess`)
- [ ] Enable HTTPS
- [ ] Set restrictive file permissions
- [ ] Regular database backups
- [ ] Keep PHP version updated

## Support

If issues persist:
1. Check `test_connection.php` output
2. Review PHP error logs
3. Verify database structure with `database_already_exit.sql`
4. Check environment configuration
5. Test database connection manually with mysql client
