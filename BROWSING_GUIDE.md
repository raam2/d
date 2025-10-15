# Application Browsing Guide

## Overview
This is a database-driven GST accounting application with a dark-themed UI. The entire application is metadata-driven, with pages and components stored in the database.

## Accessing the Application

### Production URL
The production application is hosted at:
```
https://500875.sahakari.patanjaliayurved.org
```

**Admin Login Credentials**:
- Username/ID: `admin`
- Password: `16877`

**Note**: This URL may be blocked in some environments due to domain restrictions. See the Local Development Setup section below if you cannot access the production URL.

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

## Admin Features (Production Access)

**Production URL**: https://500875.sahakari.patanjaliayurved.org

**Admin Credentials**:
- **Username/ID**: admin
- **Password**: 16877

The following features are available to admin users after logging in to the production system. These are admin-level capabilities for managing the complete accounting workspace.

### 1. Dashboard (Accounting Workspace)
**Access**: `?p=dashboard` or default landing page

The dashboard provides a central view of the accounting system with:

- **Statistics Panel**: Real-time party statistics broken down by type (customer, supplier, both)
  - Shows count of parties by category
  - Displayed in stat-card layout for quick overview
  
- **Recent Activity Log**: Last 10 diagnostic events with timestamps
  - Displays date/time of each event
  - Shows severity level (info, warning, error)
  - Includes descriptive messages for tracking system activity
  
- **Dark Theme Interface**: Memory-efficient dark UI optimized for extended use with minimal eye strain
  - Uses CSS variables for consistent theming
  - Color-coded elements for better visual hierarchy
  
- **Responsive Layout**: Adapts to desktop, tablet, and mobile viewports
  - Collapsible sidebar on smaller screens
  - Grid-based dashboard cards that reflow automatically

### 2. Parties Management
**Access**: `?p=parties`

Complete CRUD operations for customer and supplier management:

- **Party List View**:
  - Displays all parties with: Name, GSTIN, Type, City, State, Email, Phone, Created Date
  - Sorted by most recently created (descending)
  - Limit of 200 most recent records displayed
  - Edit links for each party record

- **Add New Party Form**:
  - **Required fields**: Party Name
  - **Optional fields**: GSTIN (with validation), City, State, Email, Phone
  - **Party Type selector**: Customer, Supplier, or Both
  - **GSTIN Validation**: Pattern validation for 15-character alphanumeric format (^[0-9A-Z]{15}$)
  - **Default State**: Pre-filled with "Uttarakhand" (configurable)
  - **Email validation**: Built-in email format checking

- **Delete Party Action**: Remove parties via action button with confirmation

### 3. Items Catalog
**Access**: `?p=items`

Inventory and product/service management with GST configuration:

- **Item List View**:
  - Displays: Item Name, HSN Code, Unit, Default Rate, CGST%, SGST%, IGST%, Created Date
  - Shows up to 200 most recent items
  - Sorted by creation date (descending)

- **Add New Item Form**:
  - **Required fields**: Item Name, HSN Code (8 characters max)
  - **Unit of Measurement**: Default "PCS" (pieces), customizable
  - **Pricing**: Default rate with decimal precision (0.01)
  - **GST Tax Configuration**:
    - CGST Rate % (Central GST)
    - SGST Rate % (State GST)  
    - IGST Rate % (Integrated GST)
    - All tax rates support decimal precision (0.01)
  
- **HSN Code Management**: Harmonized System of Nomenclature codes for GST compliance

### 4. Invoice Management
**Access**: `?p=invoices`

Complete invoice processing with GST compliance features:

- **Invoice List View**:
  - Displays: Invoice Number, Date, Type, Status, Place of Supply
  - **Special GST Fields**:
    - Reverse Charge indicator (Yes/No)
    - ITC Eligible indicator (Yes/No)
  - Sorted by invoice date and ID (descending)
  - Shows up to 200 most recent invoices

- **Invoice Types Supported**:
  - Sale invoices
  - Purchase invoices
  - Credit notes
  - Debit notes

- **GST Compliance Features**:
  - Place of Supply tracking for inter-state/intra-state taxation
  - Reverse charge mechanism support
  - Input Tax Credit (ITC) eligibility tracking
  - Status management: Draft, Final, Cancelled

- **Invoice Line Items Detail**:
  - Shows item-level breakdown with quantities, rates
  - CGST, SGST, IGST percentages per line item
  - Line total calculations
  - Links to parent invoice

### 5. Sidebar Navigation
**Dynamic Page Menu**:
- Auto-populated from database (`app_pages` table)
- Shows all available pages sorted alphabetically by title
- Active page highlighting
- Responsive collapse on mobile devices

**Pages Section**: Lists Dashboard, Items, Invoices, Parties (alphabetically)

### 6. Metadata-Driven UI Architecture

The entire application UI is stored in the database:

- **`app_pages` table**: Defines page routes, titles, templates
  - Pages use placeholders like `{{component:name}}` for dynamic content
  - Templates stored as HTML with component injection points

- **`app_components` table**: Defines reusable UI components
  - **List components**: SQL-driven data tables with column configuration
  - **Form components**: Input forms with field definitions, validation rules
  - **Action components**: Bulk operations with confirmation dialogs
  - Each component linked to a page via `page_slug`

- **Component Configuration** (stored as JSON):
  - Column definitions (label, field mapping, formatting)
  - Form field specifications (type, validation, defaults)
  - Layout options (table, stat-card grid)
  - Success/error messages

### 7. Security & Validation

- **SQL Injection Prevention**: All database queries use prepared statements with named parameters
- **XSS Protection**: All output HTML-escaped via `htmlspecialchars()`
- **Input Validation**:
  - Pattern matching for GSTIN (15-character alphanumeric)
  - Email format validation
  - Required field enforcement
  - Maxlength constraints
  - Numeric step validation for decimals

- **CSRF Protection**: Form submission tracking via hidden `__component` and `__page` fields

### 8. Configuration & Setup

- **App Settings** (`app_settings` table):
  - Debug mode toggle
  - Organization state configuration (default: "UK" for Uttarakhand)
  - Database refresh commands
  
- **Environment Support**:
  - Local development (MariaDB)
  - Production hosting (MySQL)
  - Configured via environment variables or `config.php`

### 9. Diagnostics & Logging

- **Diagnostics Table**: Captures system events
  - Timestamp tracking
  - Severity levels (info, warning, error)
  - Descriptive messages
  - Displayed on dashboard for admin visibility

- **Error Logs Table**: Separate error tracking system
- **Activity Monitoring**: Recent activity panel shows last 10 diagnostic entries

### 10. Inline Help & Documentation

- **Contextual Descriptions**: Each page includes muted-text descriptions explaining purpose
  - Dashboard: "Central dashboard for memory-efficient GST accounting"
  - Parties: "Maintain suppliers and customers; data stays inside the database"
  - Items: "Items with GST rates and default pricing"
  - Invoices: "Post sales and purchases directly from database metadata"

- **Form Labels**: Clear, descriptive labels for all input fields
- **Empty State Messages**: Helpful messages when no data exists ("No parties captured yet", "No items defined", "No invoices posted")

### Additional Admin Capabilities

- **Database-Backed Customization**: Admin can modify UI by updating database records
- **No File System Changes**: All pages/components exist only in database tables
- **Offline Capability**: System designed to work without external dependencies
- **Memory Efficiency**: Lightweight CSS/JS, optimized queries, minimal DOM manipulation
- **GST Accounting Features**:
  - Chart of accounts with hierarchical structure
  - Double-entry bookkeeping support
  - Journal entries and journal lines
  - Financial period management
  - Bank reconciliation
  - Inventory movements and valuations

---

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
