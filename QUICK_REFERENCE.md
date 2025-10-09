# Quick Reference Card

## Files to Deploy to Server

Upload these to your `/app/` directory:

```
/app/
├── main_entry.php    ← Main application (REQUIRED)
├── config.php        ← Database config (REQUIRED)
├── db.php            ← DB helpers (REQUIRED)
├── diagnostic.php    ← Troubleshooting tool (RECOMMENDED)
└── .htaccess         ← Environment config (OPTIONAL)
```

## Configuration

Edit `config.php` line 5:

```php
$ENV = 'local';      // For local server (10.160.118.61)
$ENV = 'production'; // For production (vedanthomestay.co.in)
```

## URLs

### Your URLs:
- Local: `http://10.160.118.61/app/main_entry.php?p=dashboard`
- Production: `https://vedanthomestay.co.in/app/main_entry.php?p=dashboard`

### Available Pages:
- `?p=dashboard` - Accounting Workspace
- `?p=parties` - Parties (customers/suppliers)
- `?p=items` - Inventory Items
- `?p=invoices` - Invoice Management

## First Time Setup

1. Upload files to `/app/` on server
2. Edit `config.php` - set `$ENV = 'local';` or `'production'`
3. Visit `diagnostic.php` to verify setup
4. Open `main_entry.php?p=dashboard`

## Database Credentials

### Local Environment
```
Host: localhost
User: u184420243_gst4
Pass: Raam2*:1
DB:   u184420243_jayanti_enter4
```

### Production Environment
```
Host: 217.21.95.103
User: u184420243_gst4
Pass: Raam2:=195
DB:   u184420243_jayanti_enter4
```

## Troubleshooting

### Page is blank?
1. Run `diagnostic.php` first
2. Check PHP error logs
3. Enable errors in `main_entry.php`:
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

### Can't connect to database?
1. Check `config.php` environment setting
2. Verify credentials match your database
3. Test connection:
   ```bash
   mysql -h localhost -u u184420243_gst4 -p
   ```

### Page loads but no data?
1. Check database has metadata:
   ```bash
   mariadb u184420243_jayanti_enter4 \
     -e "SELECT slug, title FROM app_pages;"
   ```
2. Should show: dashboard, invoices, items, parties

## How to Add New Pages

```sql
-- 1. Add page
INSERT INTO app_pages (slug, title, page_type, template)
VALUES ('mypage', 'My Page', 'workspace', 
        '<div class="card"><h1>{{title}}</h1></div>');

-- 2. Add a list component
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES ('mypage', 'list', 'my_list',
        'SELECT id, name FROM mytable ORDER BY name',
        '{"columns":[{"label":"ID","field":"id"},{"label":"Name","field":"name"}]}',
        1);

-- 3. Access at: ?p=mypage
```

## Testing Checklist

After deployment:

- [ ] `diagnostic.php` shows all ✓ checks
- [ ] Dashboard loads with dark theme
- [ ] Sidebar shows page links
- [ ] Navigation works between pages
- [ ] Lists display data (or empty table)
- [ ] Forms are visible

## Common Tasks

### Change Environment
Edit `config.php`, change `$ENV = 'local'` to `'production'`

### View Error Logs
Add to top of `main_entry.php`:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Test Database Connection
```bash
mysql -h localhost -u u184420243_gst4 -p u184420243_jayanti_enter4
```

### Check Metadata
```bash
mariadb u184420243_jayanti_enter4 \
  -e "SELECT page_slug, name FROM app_components;"
```

## File Sizes

```
main_entry.php    8.6 KB  - Main application
config.php        1.1 KB  - Configuration
db.php            1.1 KB  - Database helpers
diagnostic.php    5.9 KB  - Diagnostic tool
.htaccess         341 B   - Apache config
```

**Total: ~17 KB** (excluding documentation)

## Support Resources

- **README.md** - Full documentation
- **DEPLOYMENT.md** - Step-by-step deployment
- **IMPLEMENTATION_SUMMARY.md** - Complete overview
- **diagnostic.php** - Automated troubleshooting

## Success Indicators

✓ No PHP syntax errors
✓ Database has app_pages table with 4 pages
✓ Database has app_components table with components
✓ Config has correct credentials
✓ diagnostic.php shows green checkmarks

**Ready to deploy!** 🚀
