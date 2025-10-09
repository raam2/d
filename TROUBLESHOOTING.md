# Troubleshooting Guide

This guide helps you diagnose and fix common issues with the database-driven accounting application.

## Quick Diagnostic Steps

### Step 1: Run Diagnostics
Visit: `http://your-server/diagnostics.php`

This comprehensive tool checks:
- PHP version and required extensions
- File existence and permissions
- Configuration loading
- Database connectivity
- Table structure
- Page metadata
- Component metadata
- Page rendering simulation

**If all checks pass:** Your application should work. Proceed to `main_entry.php?p=dashboard`

**If checks fail:** Follow the specific solutions below based on the error messages.

### Step 2: Check Test Connection
Visit: `http://your-server/test_connection.php`

Simpler than diagnostics, focuses on database connectivity only.

## Common Issues & Solutions

### Issue 1: "No Response" or Blank White Page

**Symptoms:**
- Page loads but shows nothing
- Complete white/blank screen
- No errors visible

**Diagnosis:**
1. Run `diagnostics.php` - it will show exactly what's wrong
2. Check browser console (F12) for JavaScript errors
3. View page source - is there any HTML output?

**Solutions:**

**A. PHP Error Not Displaying**
- Temporarily enable errors in `main_entry.php` (already added):
  ```php
  ini_set('display_errors', '1');
  error_reporting(E_ALL);
  ```
- Check web server error logs:
  - Apache: `/var/log/apache2/error.log`
  - Nginx: `/var/log/nginx/error.log`
  - Hostinger: cPanel → Error Logs

**B. Wrong Environment**
- Check `diagnostics.php` - it shows current environment
- If wrong, set in `.htaccess`:
  ```apache
  SetEnv APP_ENV production
  ```
- Or hardcode in `config.php`:
  ```php
  $ENV = 'production'; // line 5
  ```

**C. Database Connection Failed**
- Run `test_connection.php` to see exact error
- Verify credentials in `config.php`:
  - Host matches your database server
  - Username and password are correct
  - Database name exists
- Test manually:
  ```bash
  mysql -h HOST -u USER -pPASS DATABASE -e "SELECT 1"
  ```

### Issue 2: Database Connection Errors

**Error: "Access denied for user"**

**Cause:** Wrong username or password

**Solutions:**
1. Check credentials in `config.php`
2. Reset database password if forgotten
3. Test connection manually:
   ```bash
   mysql -h localhost -u u184420243_gst4 -p'Raam2*:1' u184420243_jayanti_enter4 -e "SELECT 1"
   ```
4. Verify user has permissions:
   ```sql
   SHOW GRANTS FOR 'u184420243_gst4'@'localhost';
   GRANT ALL ON u184420243_jayanti_enter4.* TO 'u184420243_gst4'@'localhost';
   FLUSH PRIVILEGES;
   ```

---

**Error: "Unknown database"**

**Cause:** Database doesn't exist or name is misspelled

**Solutions:**
1. Check database name in `config.php`
2. List databases:
   ```bash
   mysql -u USER -p -e "SHOW DATABASES"
   ```
3. Create database if missing:
   ```sql
   CREATE DATABASE u184420243_jayanti_enter4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. Import schema:
   ```bash
   mysql -u USER -p u184420243_jayanti_enter4 < database_already_exit.sql
   ```

---

**Error: "Can't connect to MySQL server"**

**Cause:** Database server not running or wrong host

**Solutions:**
1. Check if database server is running:
   ```bash
   sudo systemctl status mariadb
   # or
   sudo systemctl status mysql
   ```
2. Start if stopped:
   ```bash
   sudo systemctl start mariadb
   ```
3. Verify host in `config.php`:
   - Local: `localhost` or `127.0.0.1`
   - Production: `217.21.95.103`
4. Check firewall allows port 3306:
   ```bash
   sudo ufw allow 3306/tcp
   ```
5. For remote connections, verify bind-address in MySQL config

### Issue 3: Missing Pages or Components

**Symptoms:**
- Error: "Define pages inside app_pages to render content"
- Dashboard shows but no other pages
- Components don't render

**Diagnosis:**
Run `diagnostics.php` - check sections 4 & 5

**Solutions:**

**A. No Pages Found**
- `app_pages` table is empty
- Re-import database:
  ```bash
  mysql -u USER -p DATABASE < database_already_exit.sql
  ```
- Or manually insert dashboard page:
  ```sql
  INSERT INTO app_pages (slug, title, page_type, template)
  VALUES ('dashboard', 'Accounting Workspace', 'workspace',
    '<div class="card"><h1>{{title}}</h1><p class="muted">Central dashboard.</p></div>');
  ```

**B. No Components Found**
- `app_components` table is empty
- Re-import database (see above)
- Or manually insert a test component:
  ```sql
  INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
  VALUES ('dashboard', 'list', 'test',
    'SELECT 1 as value', '{"layout":"table","columns":[{"label":"Test","field":"value"}]}', 1);
  ```

**C. Specific Page Missing**
- Check which pages exist:
  ```sql
  SELECT slug, title FROM app_pages;
  ```
- Add missing page manually or re-import

### Issue 4: SQL Errors in Components

**Symptoms:**
- Page loads but shows error in component area
- Error message mentions table or column not found

**Diagnosis:**
Check exact error message in component output

**Solutions:**

**A. Table Doesn't Exist**
```
Error: Table 'database.parties' doesn't exist
```
- Run `diagnostics.php` - check section 3
- Missing table? Import full schema:
  ```bash
  mysql -u USER -p DATABASE < database_already_exit.sql
  ```

**B. Column Doesn't Exist**
```
Error: Unknown column 'gstin' in 'field list'
```
- Table structure changed
- Check component's SQL in `app_components`:
  ```sql
  SELECT sql_text FROM app_components WHERE name = 'party_list';
  ```
- Update SQL or add missing column:
  ```sql
  ALTER TABLE parties ADD COLUMN gstin VARCHAR(15);
  ```

**C. Syntax Error in Component SQL**
- Edit component in database:
  ```sql
  UPDATE app_components SET sql_text = 'CORRECT SQL HERE' WHERE name = 'component_name';
  ```
- Or fix in phpMyAdmin

### Issue 5: Form Submission Errors

**Symptoms:**
- Form shows but submit button doesn't work
- Error after clicking submit

**Solutions:**

**A. Named Parameter Mismatch**
```
Error: Invalid parameter number: parameter was not defined
```
- Component SQL uses `:param` but form field name doesn't match
- Check component:
  ```sql
  SELECT sql_text, meta_json FROM app_components WHERE comp_type = 'form';
  ```
- Ensure SQL parameters match form field names:
  ```sql
  -- SQL: INSERT INTO parties (name, city) VALUES (:name, :city)
  -- JSON: "fields":[{"name":"name",...},{"name":"city",...}]
  ```

**B. Required Field Empty**
- Check browser console for validation errors
- Verify `required:true` in form field definition

**C. Data Type Mismatch**
- Inserting text into number field
- Check field types in form definition
- Verify database column types

### Issue 6: Environment Variable Not Set

**Symptoms:**
- `diagnostics.php` shows wrong environment
- Application connects to wrong database

**Solutions:**

**A. Using .htaccess (Apache)**
Create or edit `.htaccess`:
```apache
SetEnv APP_ENV production
```

**B. Using PHP Config (Any Server)**
Edit `config.php` line 5:
```php
$ENV = 'production';  // Force specific environment
```

**C. Using Shell (Local Development)**
```bash
export APP_ENV=local
php -S localhost:8080
```

**D. Verify Current Environment**
Create test file `env_test.php`:
```php
<?php
echo 'APP_ENV: ' . (getenv('APP_ENV') ?: 'not set');
```

### Issue 7: Permission Errors

**Symptoms:**
- "Permission denied" errors
- Files not readable

**Solutions:**

**A. File Permissions**
Set correct permissions:
```bash
chmod 644 *.php
chmod 755 .
```

**B. Directory Permissions**
```bash
chmod 755 /path/to/app
```

**C. SELinux Issues (CentOS/RHEL)**
```bash
sudo chcon -R -t httpd_sys_content_t /path/to/app
```

### Issue 8: Performance Issues

**Symptoms:**
- Pages load very slowly
- Timeouts on large datasets

**Solutions:**

**A. Add Database Indexes**
```sql
ALTER TABLE parties ADD INDEX idx_party_type (party_type);
ALTER TABLE invoices ADD INDEX idx_invoice_date (invoice_date);
ALTER TABLE items ADD INDEX idx_hsn (hsn_code);
```

**B. Optimize Queries**
- Add LIMIT to list components
- Use pagination for large result sets
- Example:
  ```sql
  SELECT * FROM parties ORDER BY created_at DESC LIMIT 100
  ```

**C. Enable Query Cache**
In MySQL config:
```ini
query_cache_type = 1
query_cache_size = 64M
```

**D. Use Connection Pooling**
In production, use persistent connections

### Issue 9: Special Characters Not Displaying

**Symptoms:**
- Indian characters show as �
- Accents display incorrectly

**Solutions:**

**A. Database Charset**
```sql
ALTER DATABASE u184420243_jayanti_enter4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**B. Table Charset**
```sql
ALTER TABLE parties CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**C. Connection Charset**
Already set in `config.php`:
```php
'charset' => 'utf8mb4'
```

### Issue 10: Stored Procedure Error

**Symptoms:**
```
Error: Unrecognized statement type (near "DECLARE")
```

**Cause:** phpMyAdmin can't parse stored procedures without delimiters

**Solutions:**

**A. Remove Procedures**
- The app doesn't need stored procedures
- Use pure SQL in components
- Skip procedure sections when importing

**B. Import via Command Line**
```bash
mysql -u USER -p DATABASE < database_already_exit.sql
```

**C. Extract Tables Only**
Use `sed` to remove procedure sections:
```bash
sed '/^DELIMITER/,/^END/d' database_already_exit.sql > tables_only.sql
mysql -u USER -p DATABASE < tables_only.sql
```

## Debugging Tips

### Enable Verbose Logging

Add to top of `main_entry.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/php_errors.log');
```

### Check Database Logs

**MySQL/MariaDB:**
```bash
sudo tail -f /var/log/mysql/error.log
```

### Test Individual Components

Create `test_component.php`:
```php
<?php
require 'db.php';
$sql = 'SELECT * FROM parties LIMIT 5';
$rows = fetchAll($sql);
echo '<pre>' . print_r($rows, true) . '</pre>';
```

### Browser Developer Tools

- Press F12
- Check Console tab for JavaScript errors
- Check Network tab for failed requests
- Check Response for server errors

## Getting Help

If issues persist:

1. Run `diagnostics.php` and save the output
2. Check error logs
3. Note the exact error message
4. Include:
   - PHP version
   - Database version
   - Operating system
   - Environment (local/production)
   - What you've already tried

## Preventive Measures

### Regular Backups

```bash
# Backup database
mysqldump -u USER -p DATABASE > backup_$(date +%Y%m%d).sql

# Backup files
tar -czf app_backup_$(date +%Y%m%d).tar.gz /path/to/app
```

### Monitor Logs

```bash
# Watch Apache errors
tail -f /var/log/apache2/error.log

# Watch PHP errors
tail -f /tmp/php_errors.log
```

### Keep Updated

- Keep PHP updated
- Keep MySQL/MariaDB updated
- Regular security patches

### Test Before Production

1. Test in local environment first
2. Use `diagnostics.php` to verify
3. Check all pages work
4. Test forms and actions
5. Only then deploy to production
