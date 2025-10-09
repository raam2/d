# Quick Reference Card

## URLs

### Local Development
```
http://localhost:8080/diagnostics.php        # Full diagnostics
http://localhost:8080/test_connection.php    # Quick connection test
http://localhost:8080/main_entry.php?p=dashboard
http://localhost:8080/main_entry.php?p=parties
http://localhost:8080/main_entry.php?p=items
http://localhost:8080/main_entry.php?p=invoices
```

### Production (Hostinger)
```
https://vedanthomestay.co.in/app/diagnostics.php
https://vedanthomestay.co.in/app/test_connection.php
https://vedanthomestay.co.in/app/main_entry.php?p=dashboard
https://vedanthomestay.co.in/app/main_entry.php?p=parties
https://vedanthomestay.co.in/app/main_entry.php?p=items
https://vedanthomestay.co.in/app/main_entry.php?p=invoices
```

## Database Credentials

### Local
- Host: `localhost`
- Port: `3306`
- User: `u184420243_gst4`
- Pass: `Raam2*:1`
- DB: `u184420243_jayanti_enter4`

### Production
- Host: `217.21.95.103`
- Port: `3306`
- User: `u184420243_gst4`
- Pass: `Raam2:=195`
- DB: `u184420243_jayanti_enter4`

## Common Commands

### Import Database
```bash
# Local
mysql -u u184420243_gst4 -p'Raam2*:1' u184420243_jayanti_enter4 < database_already_exit.sql

# Production
mysql -h 217.21.95.103 -u u184420243_gst4 -p'Raam2:=195' u184420243_jayanti_enter4 < database_already_exit.sql
```

### Test Connection
```bash
# Local
mysql -u u184420243_gst4 -p'Raam2*:1' u184420243_jayanti_enter4 -e "SELECT 1"

# Production
mysql -h 217.21.95.103 -u u184420243_gst4 -p'Raam2:=195' u184420243_jayanti_enter4 -e "SELECT 1"
```

### Start Local Server
```bash
export APP_ENV=local
cd /path/to/project
php -S localhost:8080
```

### Check Syntax
```bash
php -l config.php
php -l db.php
php -l main_entry.php
```

## Environment Setup

### Local (.env or shell)
```bash
export APP_ENV=local
```

### Production (.htaccess)
```apache
SetEnv APP_ENV production
```

### Production (config.php hardcoded)
```php
$ENV = 'production';  // Line 5 in config.php
```

## Files to Deploy

```
config.php           # Database credentials
db.php              # Database connection
main_entry.php      # Application entry point
test_connection.php # Connection test
diagnostics.php     # Full diagnostics
.htaccess           # Environment config (production)
database_already_exit.sql  # Database schema
```

## Troubleshooting Checklist

- [ ] Run `diagnostics.php` - all checks green?
- [ ] Run `test_connection.php` - connection OK?
- [ ] Check `APP_ENV` is set correctly
- [ ] Verify database credentials in `config.php`
- [ ] Confirm database exists and has tables
- [ ] Check `app_pages` has 4 rows
- [ ] Check `app_components` has 9+ rows
- [ ] PHP 7.4+ installed?
- [ ] PDO and PDO_MySQL extensions loaded?
- [ ] Error display enabled for debugging?

## Database Tables (Core)

```
app_pages         # Page definitions
app_components    # UI components
parties           # Customers/suppliers
items             # Inventory items
invoices          # Invoice headers
invoice_items     # Invoice lines
accounts          # Chart of accounts
journal_entries   # Accounting entries
diagnostics       # Activity log
```

## Quick SQL Queries

### Check Pages
```sql
SELECT slug, title FROM app_pages ORDER BY slug;
```

### Check Components
```sql
SELECT page_slug, comp_type, name 
FROM app_components 
ORDER BY page_slug, ord;
```

### Count Records
```sql
SELECT COUNT(*) FROM parties;
SELECT COUNT(*) FROM items;
SELECT COUNT(*) FROM invoices;
```

### Add New Page
```sql
INSERT INTO app_pages (slug, title, page_type, template)
VALUES ('mypage', 'My Page', 'workspace', 
  '<h1>My Custom Page</h1>{{component:mycomponent}}');
```

### Add New List Component
```sql
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES ('mypage', 'list', 'mycomponent',
  'SELECT * FROM parties LIMIT 10',
  '{"layout":"table","columns":[{"label":"Name","field":"name"}]}',
  1);
```

## Emergency Recovery

### Blank Page? No Response?
1. Visit `/diagnostics.php` first
2. Check what section fails
3. See TROUBLESHOOTING.md for solution

### Database Connection Failed?
1. Check credentials in `config.php`
2. Test: `mysql -h HOST -u USER -pPASS DB -e "SELECT 1"`
3. Verify server is running
4. Check firewall allows port 3306

### Missing Pages?
1. Visit `/test_connection.php`
2. If 0 pages found, import SQL:
   ```bash
   mysql -u USER -p DB < database_already_exit.sql
   ```

## Support Resources

- **Full Diagnostics:** `/diagnostics.php`
- **Quick Test:** `/test_connection.php`
- **Deployment Guide:** `DEPLOYMENT.md`
- **Troubleshooting:** `TROUBLESHOOTING.md`
- **README:** `README.md`

## Version Info

- PHP: 7.4+ (8.0+ recommended)
- MySQL: 5.7+ or MariaDB 10.3+
- Required Extensions: PDO, PDO_MySQL, JSON, mbstring

---

**Last Updated:** 2025-01-09
**Repository:** https://github.com/raam2/d
