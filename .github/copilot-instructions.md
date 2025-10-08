# Copilot Instructions for Database-Driven PHP Accounting Application

## Project Overview
This is a database-driven PHP accounting application designed for Indian businesses with GST compliance. The entire web application is stored and managed inside the database, with only a single entry point PHP file in the file system.

## Core Technical Principles

### Technology Stack
- Use vanilla PHP (no frameworks) with PDO for database operations
- Use vanilla JavaScript (no external libraries or frameworks)
- No external dependencies, no CDN links, no npm packages
- Dark theme UI using CSS variables and simple inline styles
- Memory-efficient design optimized for extended use

### Architecture
- Single entry point: `main_entry.php` handles all routing and rendering
- Database-first approach: pages, components, forms, and actions are defined in database tables (`app_pages`, `app_components`)
- Dual environment support: local MariaDB and Hostinger MySQL
- Server-side template rendering only (no eval of PHP from database)
- Use prepared statements for all database queries

### Database Configuration
- Local environment: MariaDB on 127.0.0.1:3306, database `gst_accounting`
- Hostinger environment: MySQL on srv684.hstgr.io:3306, database `u184420243_jayanti_enterp`, user `u184420243_gst`
- Environment switching via `APP_ENV` environment variable ('local' or 'hostinger')

### Code Style
- Simple, readable code - no heavy abstractions
- Use `db()` helper function for PDO connections
- Use `q()` helper for parameterized queries
- Use `fetchAll()` and `fetchOne()` for query results
- Always use `h()` for HTML escaping and `j()` for JSON encoding
- Dark theme everywhere with color scheme: background #0e0f13, text #e5e7eb

## Accounting Domain

### Database Schema
- `parties`: customers and suppliers with GSTIN
- `items`: product catalog with HSN codes and GST rates
- `invoices`: sales/purchase invoices with GST compliance fields
- `invoice_items`: line items with separate CGST/SGST/IGST amounts
- `app_pages`: page definitions (slug, title, template)
- `app_components`: reusable components (list, form, action types)

### GST Compliance
- Support for CGST/SGST/IGST tax calculations
- State-wise tax logic (IGST for inter-state, CGST+SGST for intra-state)
- ITC (Input Tax Credit) eligibility tracking
- Reverse charge mechanism support
- Pre-packaged and labelled goods tracking

### Invoice Types
- Sale invoices
- Purchase invoices
- Credit notes
- Debit notes

## Development Guidelines

### When Adding Features
- Store new pages in `app_pages` table
- Define components in `app_components` table with JSON metadata
- Keep all business logic in PHP, not in database
- Use parameterized SQL with positional placeholders (?)
- Compute tax amounts in PHP before inserting into database

### Component Types
- `list`: Display SELECT results in table format with optional FK links
- `form`: Render input fields and execute INSERT/UPDATE via POST
- `action`: Bulk operations or updates with inline controls

### Security
- Always use prepared statements, never string concatenation for SQL
- Use `h()` function for HTML escaping in all output
- Never use `eval()` or execute PHP code from database
- Validate and sanitize all user inputs

### UI/UX Standards
- Responsive design for desktop, tablet, and mobile
- Color-coded account types in reports
- Print-ready layouts for financial statements
- Accessible typography optimized for reduced eye strain
- Cards and borders using dark theme colors (#0b0c10, #1f2937, #111827)

## Workflow Preferences
- When adding new items, consider auto-fill rate from item master
- Support bulk rate updates with percentage adjustments
- Provide workspace pages for complex operations (e.g., invoice line item entry)
- Include quick search functionality across entities
- Display FK relationships as clickable links in list views

## File Organization
- Minimal files in file system: only main_entry.php, config.php, db.php
- All page definitions, routes, and UI components live in database
- SQL schema files for both environments
- Documentation in markdown files (app_build.md, plan_implementation.md)
