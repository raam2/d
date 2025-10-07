# GST Accounting Portable - Database-Driven PHP Application

A complete PHP-based accounting application designed for Indian businesses with GST compliance. All web pages, CSS, and JavaScript are stored in the database for maximum portability and zero file dependencies.

## Features

- ✅ **Database-Driven Architecture**: All pages, CSS, and JS stored in database
- ✅ **GST Compliance**: Full support for CGST/SGST/IGST accounting
- ✅ **Double-Entry Bookkeeping**: Complete accounting with journal entries
- ✅ **Dark Theme UI**: Memory-efficient, eye-friendly interface
- ✅ **Offline Capable**: Works without internet connection
- ✅ **Dual Environment Support**: MariaDB (local) and MySQL (Hostinger)
- ✅ **No External Dependencies**: Vanilla PHP and JavaScript only

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite (optional)

### Setup Steps

1. **Import the Database**
   ```bash
   mysql -u gstwork -p < gst_accounting_portable.sql
   ```
   
   Or use the database name from config.php:
   ```bash
   mysql -u gstwork -p gst_notebook_lm < gst_accounting_portable.sql
   ```

2. **Configure Database Connection**
   
   Edit `config.php` if needed to match your database credentials:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'gst_notebook_lm');
   define('DB_USER', 'gstwork');
   define('DB_PASS', 'gstwork@123');
   ```

3. **Access the Application**
   
   Navigate to the application URL:
   ```
   http://localhost/your-folder/index.php
   ```
   
   Or if using clean URLs with .htaccess:
   ```
   http://localhost/your-folder/
   ```

## Default Credentials

The application uses the database user:
- **Username**: gstwork
- **Password**: gstwork@123

## Application Structure

```
├── index.php                 # Main entry point (serves all pages from DB)
├── config.php                # Database configuration
├── db.php                    # Database connection handler
├── .htaccess                 # Apache configuration (optional)
├── gst_accounting_portable.sql  # Complete database with schema and data
└── app_build.md             # Detailed documentation
```

## Database Schema

The application creates these main tables:

### Metadata Tables
- `Pages` - Web page definitions with PHP code
- `CSS_Files` - CSS stylesheets
- `JS_Files` - JavaScript code
- `Page_CSS` - Page to CSS mappings
- `Page_JS` - Page to JS mappings

### Accounting Tables
- `accounts` - Chart of accounts with hierarchical structure
- `journal_entries` - Journal entry headers
- `journal_lines` - Journal entry line items (debits/credits)
- `parties` - Customer and supplier master
- `invoices` - Invoice headers
- `invoice_items` - Invoice line items with GST

### Supporting Tables
- `app_settings` - Application configuration
- `diagnostics` - System logs
- `bank_statements` - Bank reconciliation
- `uqc` - Units of quantity codes

## Usage

### Accessing Pages

All pages are served from the database via URL parameters:

```
?page=dashboard           # Main dashboard
?page=page_manager        # Content management
?page=parties/master      # Party master
?page=invoices/list       # Invoice listing
?page=tools/diagnostics   # System diagnostics
```

### Managing Content

Use the **Content Manager** (accessible via `?page=page_manager`) to:

- Create and edit web pages
- Manage CSS stylesheets
- Manage JavaScript files
- Link CSS/JS to pages

### GST Accounting Features

- Pre-configured chart of accounts with GST accounts
- CGST/SGST/IGST input and output accounts
- Invoice management with automatic tax calculation
- Party master for customers and suppliers
- GST summary reports by period

## Security Notes

1. Change default database credentials in production
2. The `.htaccess` file blocks direct access to `config.php` and `db.php`
3. All database queries use prepared statements to prevent SQL injection
4. HTML output is escaped to prevent XSS attacks

## Environment Detection

The application automatically detects the environment:

- **Local**: Uses 127.0.0.1/localhost detection
- **Remote**: Uses Hostinger MySQL settings

You can customize the detection logic in `config.php`.

## Troubleshooting

### Database Connection Error

If you see "Database connection failed":

1. Verify MySQL/MariaDB is running
2. Check credentials in `config.php`
3. Ensure the database `gst_notebook_lm` exists
4. Check that the user has proper permissions

### Page Not Found

If you get 404 errors:

1. Ensure the database has been imported
2. Check that the `Pages` table has data
3. Verify the page name in the URL matches a `name` in the `Pages` table

### CSS/JS Not Loading

1. Check the `CSS_Files` and `JS_Files` tables have data
2. Verify the page-to-CSS/JS mappings in `Page_CSS` and `Page_JS` tables
3. Check browser console for 404 errors

## Development

### Adding New Pages

Use the Content Manager or insert directly into the database:

```sql
INSERT INTO Pages (name, menu_label, menu_group, menu_order, code, tables_used)
VALUES ('my_page', 'My Page', 'Custom', 10, 
        '<?php echo "<h1>Hello World</h1>"; ?>', 
        NULL);
```

### Adding CSS

```sql
INSERT INTO CSS_Files (name, code)
VALUES ('custom_styles', 'body { background: #000; }');

-- Link to a page
INSERT INTO Page_CSS (page_id, css_id)
SELECT p.id, c.id 
FROM Pages p, CSS_Files c
WHERE p.name = 'my_page' AND c.name = 'custom_styles';
```

## Memory Efficiency

The application is optimized for low memory usage:

- No external libraries or frameworks
- Minimal CSS (~8KB total)
- Lightweight JavaScript (~10KB total)
- Efficient database queries
- Server-side rendering only

## License

This is a custom business application. Please check with the repository owner for licensing terms.

## Support

For issues or questions, please refer to the `app_build.md` documentation or create an issue in the repository.
