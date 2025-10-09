# Application Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                        USER BROWSER                         │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  │ HTTP Request (?p=dashboard)
                  │
                  ▼
┌─────────────────────────────────────────────────────────────┐
│                    main_entry.php                           │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ 1. Load db.php (database connection)                 │  │
│  │ 2. Determine page slug from ?p= parameter            │  │
│  │ 3. Query app_pages table for page definition         │  │
│  │ 4. Query app_components table for page components    │  │
│  │ 5. Render each component:                            │  │
│  │    - Lists: Run SQL SELECT, display table            │  │
│  │    - Forms: Generate HTML form inputs                │  │
│  │    - Actions: Generate action buttons                │  │
│  │ 6. Replace {{component:name}} in template            │  │
│  │ 7. Output HTML to browser                            │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  │ SQL Queries via PDO
                  │
                  ▼
┌─────────────────────────────────────────────────────────────┐
│                  DATABASE (MySQL/MariaDB)                   │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ Metadata Tables:                                      │  │
│  │  - app_pages: Page definitions and templates         │  │
│  │  - app_components: Lists, forms, actions             │  │
│  │                                                       │  │
│  │ Business Tables:                                      │  │
│  │  - parties: Customers and suppliers                  │  │
│  │  - items: Inventory with GST rates                   │  │
│  │  - invoices: Invoice headers                         │  │
│  │  - invoice_items: Invoice line items                 │  │
│  │  - accounts: Chart of accounts                       │  │
│  │  - journal_entries: Accounting entries               │  │
│  │  - diagnostics: Activity log                         │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

## File Structure

```
/app/
├── config.php              # Database credentials (local & production)
├── db.php                  # PDO connection and query helpers
├── main_entry.php          # Application entry point
│
├── diagnostics.php         # System diagnostic tool
├── test_connection.php     # Quick DB connection test
│
├── .htaccess.example       # Production environment config
├── config.php.example      # Config template (no passwords)
│
├── database_already_exit.sql   # Complete database schema
│
└── Documentation/
    ├── README.md           # Quick setup guide
    ├── DEPLOYMENT.md       # Step-by-step deployment
    ├── TROUBLESHOOTING.md  # Common issues & solutions
    ├── QUICKREF.md         # Quick reference card
    ├── SOLUTION_SUMMARY.md # Issue resolution summary
    └── ARCHITECTURE.md     # This file
```

## Request Flow

### 1. Page Request
```
User visits: https://vedanthomestay.co.in/app/main_entry.php?p=dashboard
                                                            └─────────┘
                                                            Page slug
```

### 2. Database Query
```sql
SELECT * FROM app_pages WHERE slug = 'dashboard'
```
Returns:
- `template`: HTML with {{component:name}} placeholders
- `title`: Page title
- `page_type`: workspace/list/form

### 3. Component Query
```sql
SELECT * FROM app_components 
WHERE page_slug = 'dashboard' 
ORDER BY ord, id
```
Returns multiple components, each with:
- `comp_type`: list/form/action
- `sql_text`: SQL query to run
- `meta_json`: Configuration (columns, fields, etc.)

### 4. Component Rendering

**For Lists:**
```
1. Execute component's sql_text
2. Fetch all rows
3. Parse meta_json for column definitions
4. Generate HTML table or stat cards
```

**For Forms:**
```
1. Parse meta_json for field definitions
2. Generate HTML input elements
3. Set up POST handler for submission
4. On submit: execute component's sql_text with form data
```

**For Actions:**
```
1. Parse meta_json for parameters
2. Generate button/form
3. On submit: execute component's sql_text with parameters
```

### 5. Template Assembly
```
Original template:
<h1>Dashboard</h1>
{{component:dashboard_summary}}
{{component:recent_activity}}

After replacement:
<h1>Dashboard</h1>
[HTML table with party statistics]
[HTML table with recent activity log]
```

## Database Schema

### Metadata Tables

**app_pages**
```sql
id          INT          Primary key
slug        VARCHAR(100) Unique route identifier (e.g., 'dashboard')
title       VARCHAR(120) Page display title
page_type   ENUM         'list', 'form', 'workspace'
template    MEDIUMTEXT   HTML with {{component:name}} placeholders
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

**app_components**
```sql
id          INT          Primary key
page_slug   VARCHAR(100) References app_pages.slug
comp_type   ENUM         'list', 'form', 'action'
name        VARCHAR(100) Component identifier
sql_text    MEDIUMTEXT   SQL query with :named parameters
meta_json   MEDIUMTEXT   JSON configuration
ord         INT          Display order
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

### Example Component

**List Component:**
```json
{
  "layout": "table",
  "columns": [
    {"label": "Name", "field": "name"},
    {"label": "City", "field": "city"}
  ],
  "emptyText": "No parties found."
}
```

**Form Component:**
```json
{
  "method": "POST",
  "success": "Party saved successfully.",
  "fields": [
    {
      "name": "name",
      "label": "Party Name",
      "type": "text",
      "required": true
    },
    {
      "name": "city",
      "label": "City",
      "type": "text"
    }
  ]
}
```

## Environment Configuration

### Local Development
```php
// config.php
$ENV = 'local';

$config['local'] = [
    'host' => 'localhost',
    'port' => '3306',
    'user' => 'YOUR_USER',
    'pass' => 'YOUR_PASS',
    'db'   => 'YOUR_DATABASE',
    'charset' => 'utf8mb4'
];
```

### Production (Hostinger)
```php
// config.php
$ENV = 'production';

$config['production'] = [
    'host' => 'YOUR_PROD_HOST',
    'port' => '3306',
    'user' => 'YOUR_PROD_USER',
    'pass' => 'YOUR_PROD_PASS',
    'db'   => 'YOUR_DATABASE',
    'charset' => 'utf8mb4'
];
```

Or set via .htaccess:
```apache
SetEnv APP_ENV production
```

## Security Model

### SQL Injection Prevention
- All queries use PDO prepared statements
- Named parameters (`:name`) for user input
- No string concatenation in SQL

### XSS Prevention
- All output escaped with `htmlspecialchars()`
- No eval() or dynamic PHP execution
- JSON metadata parsed, not executed

### Configuration Protection
- .htaccess blocks direct access to config.php
- Passwords not stored in code (use env vars)
- config.php.example for version control

### Database Security
- Separate users for local/production
- Least privilege access (no SUPER/FILE)
- Connection uses SSL (if available)

## Adding New Features

### Add a New Page
```sql
INSERT INTO app_pages (slug, title, page_type, template)
VALUES (
  'reports',
  'Reports',
  'workspace',
  '<h1>Reports</h1>{{component:sales_report}}'
);
```

### Add a List Component
```sql
INSERT INTO app_components 
  (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES (
  'reports',
  'list',
  'sales_report',
  'SELECT invoice_no, total FROM invoices WHERE inv_type="sale"',
  '{"layout":"table","columns":[{"label":"Invoice","field":"invoice_no"},{"label":"Total","field":"total"}]}',
  1
);
```

### Add a Form Component
```sql
INSERT INTO app_components 
  (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES (
  'parties',
  'form',
  'add_party',
  'INSERT INTO parties (name, city) VALUES (:name, :city)',
  '{"fields":[{"name":"name","label":"Name","type":"text","required":true},{"name":"city","label":"City","type":"text"}],"success":"Party added!"}',
  2
);
```

## Performance Optimization

### Database Indexes
```sql
ALTER TABLE parties ADD INDEX idx_name (name);
ALTER TABLE items ADD INDEX idx_hsn (hsn_code);
ALTER TABLE invoices ADD INDEX idx_date (invoice_date);
```

### Query Optimization
- Use LIMIT in list queries
- Index frequently searched columns
- Avoid SELECT * in production

### Caching (Future)
- Cache page/component metadata
- Use Redis/Memcached for query results
- CDN for static assets (if any)

## Monitoring & Diagnostics

### Health Checks
1. `/diagnostics.php` - Full system check
2. `/test_connection.php` - Quick DB test
3. Database `diagnostics` table - Activity log

### Error Logging
- PHP errors: `/tmp/php_errors.log`
- MySQL errors: `/var/log/mysql/error.log`
- Application events: `diagnostics` table

### Metrics
- Page load time
- Query execution time
- Database connection count
- Error rate

## Deployment Workflow

### Development
1. Make changes to `app_components` in local DB
2. Test locally: `php -S localhost:8080`
3. Export changes: `mysqldump app_components > update.sql`

### Production
1. Backup production database
2. Import changes: `mysql < update.sql`
3. Test on production
4. Monitor `diagnostics` table

## Backup & Recovery

### Database Backup
```bash
mysqldump -u USER -p DATABASE > backup_$(date +%Y%m%d).sql
```

### File Backup
```bash
tar -czf app_backup.tar.gz /path/to/app
```

### Recovery
```bash
mysql -u USER -p DATABASE < backup_20250109.sql
```

## Technology Stack

- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+ or MariaDB 10.3+
- **Frontend:** Vanilla JavaScript (minimal)
- **Styling:** Inline CSS (dark theme)
- **Security:** PDO prepared statements, XSS escaping
- **Architecture:** Database-driven, metadata-based

## Design Principles

1. **Database-Driven**: All UI stored in database, not files
2. **Zero Dependencies**: No external libraries or frameworks
3. **Memory Efficient**: Minimal footprint, optimized queries
4. **Security First**: Prepared statements, output escaping
5. **Dark Theme**: Reduced eye strain, professional look
6. **Offline Capable**: Works without internet after setup
7. **GST Compliant**: Full Indian tax support

---

**Last Updated:** 2025-01-09
**Version:** 1.0
**Author:** Database-Driven Accounting System
