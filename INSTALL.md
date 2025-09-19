# Quick Start Installation Guide

## Prerequisites
- PHP 8.0+ with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx)

## Installation Steps

### 1. Download & Setup
```bash
# Clone or download the accounting module files
# Upload to your web server directory
```

### 2. Configure Database
Create `con.php` in the project root:
```php
<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'gst_accounting');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 3. Access Application
- Open your browser and navigate to the application URL
- You'll see "Database connection not available" with demo data
- This is normal if the database doesn't exist yet

### 4. Create Database
Create the database in MySQL:
```sql
CREATE DATABASE gst_accounting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Run Migration
- Access the application
- Click "Setup Database" or go to `?module=migrate`
- Click "Run Migration Now"
- This creates all accounting tables and default chart of accounts

### 6. Start Using
- Dashboard will now show real data
- Go to Chart of Accounts to review/customize accounts
- Use "Post Invoices" to integrate existing invoice data
- Create manual journal entries as needed

## File Structure
```
your-web-directory/
├── index.php              # Main application
├── con.php                # Database config (create this)
├── README.md              # Full documentation
├── lib/                   # Core libraries
├── modules/               # Application modules
└── actions/               # Database migration
```

## Verification
After setup, you should see:
- Dashboard with account statistics
- Chart of Accounts with Indian GST structure
- All menu items accessible
- No database connection errors

## Next Steps
1. Review Chart of Accounts and customize as needed
2. Configure organization settings (state code for GST)
3. Post existing invoices to accounting system
4. Begin using financial reports

For detailed usage instructions, see the full README.md file.