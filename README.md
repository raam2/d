# Database-Driven GST Accounting Application

A memory-efficient, database-driven PHP accounting application with dual environment support (local and production).

## Features

- **Database-Driven Architecture**: All pages, forms, lists, and actions are stored in the database
- **Dual Environment Support**: Seamlessly switch between local and production environments
- **Dark Theme UI**: Memory-efficient dark theme optimized for extended use
- **No External Dependencies**: Pure PHP with vanilla JavaScript, no external libraries
- **GST Compliance**: Built for Indian businesses with CGST/SGST/IGST support

## Setup

### 1. Database Setup

Import the SQL file to create the schema and metadata:

```bash
mysql -h your_host -u your_user -p your_database < u184420243_jayanti_enter4.sql
```

Or via phpMyAdmin: Import the `u184420243_jayanti_enter4.sql` file.

### 2. Environment Configuration

#### Option A: Using .htaccess (Recommended for production)

Edit `.htaccess` and uncomment the appropriate environment:

```apache
SetEnv APP_ENV production
```

#### Option B: Using environment variables

Export the environment variable before running PHP:

```bash
export APP_ENV=production
php -S 0.0.0.0:8080 main_entry.php
```

#### Option C: Hard-code in config.php

Edit `config.php` and change the first line:

```php
$ENV = 'production'; // or 'local'
```

### 3. Database Credentials

The application uses these credentials by default (can be overridden via environment variables):

**Local environment:**
- Host: localhost
- Port: 3306
- User: u184420243_gst4
- Password: Raam2*:1
- Database: u184420243_jayanti_enter4

**Production environment:**
- Host: 217.21.95.103
- Port: 3306
- User: u184420243_gst4
- Password: Raam2:=195
- Database: u184420243_jayanti_enter4

To override any credential, set the corresponding environment variable:
- `DB_HOST`
- `DB_PORT`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

### 4. Deploy Files

Upload these files to your web server:
- `main_entry.php` - Main application entry point
- `config.php` - Environment configuration
- `db.php` - Database connection and helpers
- `.htaccess` - Web server configuration (optional)

### 5. Access the Application

Navigate to:
- Local: `http://localhost/main_entry.php?p=dashboard`
- Production: `https://yourdomain.com/app/main_entry.php?p=dashboard`

## Available Pages

- `?p=dashboard` - Accounting Workspace
- `?p=parties` - Parties Master (customers/suppliers)
- `?p=items` - Inventory Items
- `?p=invoices` - Invoice Management

## File Structure

```
.
├── main_entry.php          # Main application router & renderer
├── config.php              # Dual environment configuration
├── db.php                  # Database connection & helper functions
├── .htaccess               # Web server configuration
├── u184420243_jayanti_enter4.sql  # Database schema and metadata
├── app_build.md            # Application architecture documentation
└── plan_implementation.md  # Implementation plan and specifications
```

## How It Works

1. All web pages are stored in the `app_pages` table with their templates
2. Each page can have multiple components (lists, forms, actions) stored in `app_components`
3. The `main_entry.php` file reads the page metadata and renders the UI dynamically
4. Forms submit to actions that execute parameterized SQL queries
5. All UI styling is inline CSS with a dark theme

## Extending the Application

### Add a New Page

```sql
INSERT INTO app_pages (slug, title, page_type, template)
VALUES ('mypage', 'My Page', 'workspace', '<div class="card"><h1>{{title}}</h1></div>');
```

### Add a Component to a Page

```sql
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES ('mypage', 'list', 'my_list',
        'SELECT id, name FROM my_table ORDER BY name',
        '{"columns":[{"label":"ID","field":"id"},{"label":"Name","field":"name"}]}',
        1);
```

## Troubleshooting

### Page Not Responding

1. Check PHP syntax: `php -l main_entry.php`
2. Verify database connection credentials in `config.php`
3. Ensure the `app_pages` table has data: `SELECT slug, title FROM app_pages;`
4. Check PHP error logs for connection or query errors

### Environment Not Switching

1. Verify `.htaccess` is being read by the web server
2. Check that `APP_ENV` is set correctly: add `echo $ENV;` to `config.php`
3. Ensure credentials for the selected environment are correct

## Security Notes

- All user input is escaped using `htmlspecialchars()`
- Database queries use prepared statements with parameter binding
- No PHP code from the database is executed (only SQL queries)
- Credentials should be managed via environment variables in production

## License

This is a custom application for GST accounting in Indian businesses.
