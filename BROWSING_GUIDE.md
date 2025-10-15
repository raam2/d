# Application Browsing Guide

## Overview
This is a database-driven GST accounting application with a dark-themed UI. The entire application is metadata-driven, with pages and components stored in the database.

## Accessing the Application

### Production URL (Currently Blocked)
The production application is hosted at:
```
https://500875.sahakari.patanjaliayurved.org:8040/#/
```

**Note**: This URL may be blocked in some environments due to domain restrictions.

### Local Development Setup

If you cannot access the production URL, you can run the application locally:

#### Prerequisites
- PHP 8.0+ with PDO MySQL extension
- MySQL 8.0+ or MariaDB 10.3+
- Git (to clone the repository)

#### Quick Start

1. **Clone the repository**:
   ```bash
   git clone https://github.com/raam2/d.git
   cd d
   ```

2. **Set up the database**:
   ```bash
   # Create database and user
   mysql -u root -p << 'EOF'
   CREATE DATABASE accounting_db;
   CREATE USER 'appuser'@'localhost' IDENTIFIED BY 'apppass123';
   GRANT ALL PRIVILEGES ON accounting_db.* TO 'appuser'@'localhost';
   FLUSH PRIVILEGES;
   EOF
   
   # Import the database schema
   mysql -u appuser -papppass123 accounting_db < database_latest_dump.sql
   ```
   
   **Note**: If you encounter errors with generated columns, use `database_fixed.sql` instead.

3. **Configure database credentials**:
   
   Edit `config.php` to set your database credentials, or use environment variables:
   ```bash
   export APP_ENV=local
   export DB_HOST=localhost
   export DB_PORT=3306
   export DB_USER=appuser
   export DB_PASS=apppass123
   export DB_NAME=accounting_db
   ```

4. **Start the PHP development server**:
   ```bash
   php -S 0.0.0.0:8080 main_entry.php
   ```

5. **Browse the application**:
   
   Open your web browser and navigate to:
   ```
   http://localhost:8080
   ```

## Application Structure

### Pages
The application uses a metadata-driven approach where all pages are defined in the `app_pages` table:

- **Dashboard** (`?p=dashboard`): Main dashboard with statistics and quick access
- **Home** (`?p=home`): Welcome page with application information
- **Parties** (`?p=parties`): Manage customers and suppliers
- **Items** (`?p=items`): Product/service catalog with HSN codes
- **Invoices** (`?p=invoices`): Invoice management with GST compliance

### Features
- **Dark Theme**: Optimized for reduced eye strain during extended use
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Database-Driven**: All UI components are stored in the database
- **GST Compliance**: Built for Indian GST requirements (CGST, SGST, IGST)
- **No External Dependencies**: All CSS and JavaScript are inline/internal

### Architecture
```
┌─────────────────┐
│   main_entry.php│  ← Single entry point
└────────┬────────┘
         │
    ┌────▼────┐
    │ db.php  │  ← Database connection layer
    └────┬────┘
         │
    ┌────▼────────┐
    │ config.php  │  ← Environment configuration
    └─────────────┘

Database Tables:
- app_pages: Page definitions (slug, title, template)
- app_components: UI components (lists, forms, actions)
- Accounting tables: parties, items, invoices, etc.
```

## Navigation

### URL Structure
All pages are accessed via query parameter `?p={slug}`:
- `?p=dashboard` - Dashboard page
- `?p=home` - Home page
- `?p=parties` - Parties management
- `?p=items` - Items catalog
- `?p=invoices` - Invoice management

### Sidebar Navigation
The left sidebar shows all available pages dynamically loaded from the database. Click any page link to navigate.

## Development Tips

### Adding New Pages
New pages are added by inserting records into the `app_pages` table:

```sql
INSERT INTO app_pages (slug, title, page_type, template)
VALUES ('mypage', 'My Page', 'workspace', 
        '<div class="card"><h3>My Page</h3>{{component:mylist}}</div>');
```

### Adding Components
Components are added to `app_components` table:

```sql
-- Example: List component
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES ('mypage', 'list', 'mylist',
        'SELECT * FROM my_table',
        '{"columns":[{"field":"id","label":"ID"},{"field":"name","label":"Name"}]}',
        1);
```

### Component Types
1. **list**: Display tabular data from SQL query
2. **form**: Input forms with validation
3. **action**: Quick action buttons for bulk operations

## Troubleshooting

### Cannot Connect to Database
- Check MySQL/MariaDB service is running
- Verify credentials in `config.php`
- Ensure database exists and user has proper permissions

### Page Shows "Component not found"
- Check that component is defined in `app_components` table
- Verify `page_slug` matches the page's slug
- Check component name matches the template placeholder

### SQL Errors
- Review SQL queries in `app_components.sql_text`
- Ensure all referenced tables exist
- Check for generated columns if importing database dump

## Screenshots

### Dashboard - Complete View
![Dashboard](https://github.com/user-attachments/assets/4ba8b101-4db8-4726-9d0e-f1b8499fbebd)
*Central dashboard showing party statistics and recent activity log*

### Parties Management
![Parties](https://github.com/user-attachments/assets/6811bf49-fda3-4631-a709-62f58313ffe5)
*Party master with list view and inline form for adding customers/suppliers*

### Items Catalog
![Items](https://github.com/user-attachments/assets/b10009bc-f50c-4660-bba7-5f0ca19f7a4d)
*Inventory items with HSN codes and GST rates*

### Basic Configuration Views
![Dashboard Simple](https://github.com/user-attachments/assets/ed722115-d4d4-44ff-918c-e593b45893a3)
*Dashboard - minimal setup view*

![Home](https://github.com/user-attachments/assets/3e84ee64-cd2a-47a4-82a7-e82d06cdbd20)
*Home page - welcome screen*

## Support

For issues or questions:
1. Check `live_errors.md` for known issues
2. Review `plan_implementation.md` for architecture details
3. See `app_build.md` for feature documentation

## License

This is a proprietary accounting application for Jayanti Enterprises.
