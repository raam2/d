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

## Admin Features

The production application at **https://500875.sahakari.patanjaliayurved.org** provides comprehensive accounting functionality for admin users. 

**Admin Login Credentials:**
- **Username:** admin
- **Password:** 16877

### Core Admin Features

#### 1. Dashboard
The dashboard provides a centralized view of business operations:

- **Statistics Overview**: Real-time counts of customers, suppliers, and combined parties
- **Activity Logs**: Comprehensive system diagnostics showing user actions, timestamps, and operations
- **Dark Theme**: Professional dark UI (#0b0c10 background) optimized for extended use and reduced eye strain
- **Responsive Design**: Fully functional across desktop, tablet, and mobile devices
- **Quick Access**: Direct navigation to all major modules

#### 2. Parties Management
Complete CRUD operations for customer and supplier master data:

- **Create/Read/Update/Delete**: Full lifecycle management of party records
- **GSTIN Validation**: 15-character pattern validation for Indian GST Identification Numbers
- **Party Types**: Support for Customer, Supplier, or Both classifications
- **Contact Information**: Name, email, phone, city, and state tracking
- **State Management**: Dropdown selection for Indian states
- **Search & Filter**: Quick lookup of parties by name, GSTIN, or location

#### 3. Items Catalog
Product and service inventory with comprehensive tax configuration:

- **Item Master CRUD**: Create, view, edit, and delete inventory items
- **HSN Code Management**: 8-character HSN/SAC code support for GST compliance
- **GST Tax Configuration**: 
  - CGST (Central GST) rate configuration
  - SGST (State GST) rate configuration
  - IGST (Integrated GST) rate configuration
- **Unit Management**: Customizable units of measurement (PCS, KG, LTR, etc.)
- **Default Pricing**: Base rate/price per unit
- **Tax Rate Precision**: Decimal precision up to 0.01% for accurate tax calculations

#### 4. Invoice Management
Full-featured invoicing system with Indian GST compliance:

- **Invoice CRUD**: Complete invoice lifecycle management
- **Invoice Types**: Support for different transaction types (Sales, Purchase, etc.)
- **GST Compliance**: 
  - Automatic CGST/SGST/IGST calculation based on place of supply
  - State-wise tax rate application
  - GST-compliant invoice numbering
- **Reverse Charge Mechanism**: Flag support for reverse charge applicability under GST
- **ITC Eligibility**: Input Tax Credit eligibility tracking for purchases
- **Place of Supply**: State selection for determining applicable GST rates
- **Invoice Line Items**: Multiple items per invoice with quantity, rate, and tax details
- **Invoice Status**: Draft, Posted, Cancelled status tracking
- **Party Linkage**: Direct integration with parties master

#### 5. Sidebar Navigation
Dynamic, database-driven navigation system:

- **Auto-generated Menu**: Sidebar automatically populated from `app_pages` table
- **Page Listing**: All available pages displayed with titles
- **Quick Search**: Integrated search for parties, items, and invoices
- **Contextual Navigation**: Current page highlighting
- **Collapsible Design**: Space-efficient sidebar for optimal screen usage

#### 6. Metadata-Driven UI
Complete application configuration stored in database:

- **Page Definitions**: All pages defined in `app_pages` table (slug, title, template)
- **Component System**: Reusable UI components in `app_components` table
- **List Components**: SQL-driven data grids with customizable columns
- **Form Components**: Dynamic form generation from JSON metadata
- **Action Components**: Quick action buttons for batch operations
- **Template Placeholders**: `{{component:name}}` syntax for flexible layouts
- **No Filesystem Changes**: Add new features by inserting database records

#### 7. Security & Validation
Enterprise-grade security throughout the application:

- **Prepared Statements**: All SQL queries use PDO prepared statements preventing SQL injection
- **HTML Escaping**: All output escaped with `htmlspecialchars()` preventing XSS attacks
- **Input Validation**: Pattern validation on critical fields (GSTIN, email, phone)
- **Form Tokens**: Hidden `__page` and `__component` fields for secure routing
- **Type Safety**: Strict type declarations in PHP code
- **Parameter Binding**: Named parameters (`:field`) for all SQL operations
- **Error Handling**: Comprehensive try-catch blocks with safe error messages

#### 8. Configuration & Setup
Flexible environment and database configuration:

- **Dual Environment Support**: Local (MariaDB) and Production (MySQL) configurations
- **Environment Variables**: `APP_ENV`, `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`
- **Database Abstraction**: PDO layer supporting MySQL 8+ and MariaDB 10.3+
- **Connection Pooling**: Efficient database connection management
- **Charset Configuration**: UTF-8 (utf8mb4) for full Unicode support
- **Schema Migrations**: SQL-based database updates and metadata patches

#### 9. Diagnostics & Logs
Comprehensive system monitoring and troubleshooting:

- **Diagnostics Table**: System activity log tracking all major operations
- **Timestamp Tracking**: Precise datetime stamps for all events
- **Action Logging**: User actions, SQL operations, and system events
- **Error Tracking**: Failed operations logged with error messages
- **Activity Dashboard**: Real-time view of recent system activity
- **Performance Monitoring**: Query execution tracking for optimization

#### 10. Inline Help & Documentation
Built-in guidance for users:

- **Form Labels**: Clear, descriptive labels for all input fields
- **Placeholder Text**: Helpful examples in form fields
- **Success Messages**: Confirmation feedback after operations
- **Error Messages**: User-friendly error descriptions
- **Empty States**: Helpful messages when no data is available
- **Field Validation Messages**: Real-time validation feedback

### Technical Architecture

The admin features are built on a robust technical foundation:

- **Single Entry Point**: `main_entry.php` handles all requests
- **Database Layer**: `db.php` provides PDO abstraction with prepared statements
- **Configuration**: `config.php` manages environment-specific settings
- **Template Engine**: Safe server-side rendering without code evaluation
- **Vanilla JavaScript**: 10KB of dependency-free client-side code
- **Inline CSS**: 8KB dark theme CSS with no external dependencies
- **Offline Capability**: Full functionality without internet connection

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
