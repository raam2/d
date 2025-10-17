This PR implements a complete PHP-based accounting application designed for Indian businesses with GST compliance. The application addresses the need for a memory-efficient, offline-capable accounting system with a dark theme UI optimized for extended use.

Key Features Implemented
🏗️ Complete Accounting System
Double-entry bookkeeping with real-time debit/credit validation
Indian GST compliance with CGST/SGST/IGST account structure
Chart of accounts management with hierarchical organization
Financial reporting including Trial Balance, P&L Statement, and Balance Sheet
Journal entry system with automatic balance checking
🎨 Dark Theme UI
Memory-efficient dark theme using CSS variables (8KB total CSS)
Responsive design optimized for desktop, tablet, and mobile
Color-coded account types (Assets=Green, Liabilities=Red, etc.)
Print-ready layouts for financial reports
Accessible typography optimized for reduced eye strain
💻 Technical Implementation
Vanilla JavaScript implementation (10KB) with no external dependencies
Offline capability with SQLite fallback database
MySQL support configured for gstwork/gstwork@123 credentials
Modular architecture with separate modules for each feature
Database migration system for easy setup.
Application Structure- 	
app/
├── index.php                    # Main application router & layout
├── config/database.php          # Database connection (MySQL/SQLite fallback)
seperate table for css,js,php,ajax ,.. etc .
Database Schema
The application creates a complete accounting database structure:	i need each web page file to be exist only inside my database , only basic one frontend is to be exist in project . i do not need web_pages in file system .

accounts - Chart of accounts with hierarchical structure and account types
journal_entries - Double-entry journal headers with source tracking
journal_lines - Individual debit/credit transactions
app_settings - Application configuration
no heavy code . Simple readable with Dark Theme everywhere .
Memory Efficiency
The application is specifically designed for memory efficiency:

No external dependencies - No CDN links, no external libraries
Lightweight assets 
Optimized queries - Efficient database operations for large datasets
Minimal DOM manipulation - Vanilla JavaScript with targeted interactions
GST Compliance
Full support for Indian GST requirements:

Pre-configured CGST/SGST/IGST input and output accounts
State-wise tax calculation support
Invoice posting framework for automatic journal entries
GST-compliant chart of accounts structure
Usage -
The application provides a complete, production-ready accounting system that can operate entirely offline while maintaining professional accounting standards and GST compliance for Indian businesses.
Database-driven php accounting app with dual environment
You want a single entry point (main_entry.php), running on MariaDB locally and MySQL on Hostinger, with the entire web application stored and managed inside the database. Below is a clean, secure architecture, schema, and drop-in code to serve pages, forms, lists, and actions from DB metadata—no external libraries, dark UI, and vanilla JS.

Architecture overview
Single entry: main_entry.php

Dual environment PDO (local MariaDB, Hostinger MySQL) via config.php + db.php

App lives in DB: pages, routes, components, list views, forms, and actions are defined in metadata tables

Render engine: safe, server-side template rendering (no eval of PHP from DB), supports:

Lists: run SELECT and render rows

Forms: generate inputs, run INSERT/UPDATE/DELETE with prepared statements

Actions: bulk updates via parameterized SQL

Dark UI: small CSS, zero external assets

Accounting domain: parties, items, invoices, invoice_items with normalized schema .
