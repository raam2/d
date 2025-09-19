# GST India Accounting System

A complete double-entry accounting module designed specifically for Indian businesses with GST compliance. This system integrates with existing invoice data and provides comprehensive financial reporting.

## 🎯 Features

### 📊 Complete Chart of Accounts
- Indian accounting standard structure
- GST-specific accounts (CGST, SGST, IGST Input/Output)
- Hierarchical account organization
- Asset, Liability, Equity, Income, Expense account types
- Account activation/deactivation

### ⚖️ Double-Entry Bookkeeping
- Automatic debit/credit validation (debits must equal credits)
- Manual journal entry creation with real-time balance checking
- Automated invoice posting to ledger
- Complete audit trail with source document tracking
- Transaction reversal and correction capabilities

### 📈 Financial Reports
- **Trial Balance** - Real-time balance verification with drill-down to ledgers
- **Profit & Loss Statement** - Period-based P&L with previous year comparison
- **Balance Sheet** - Assets = Liabilities + Equity validation
- **Account Ledgers** - Detailed transaction history with running balances

### 🧾 GST Invoice Integration
- Automatic posting of sales invoices to accounting
- Intelligent GST split calculation (CGST/SGST vs IGST based on state)
- State-wise tax determination
- Accounts Receivable automation
- Support for existing invoice/invoice_items tables

### 🏛️ Bank Reconciliation
- Statement vs book balance comparison
- Outstanding items tracking
- Multi-bank account support
- Reconciliation audit trail

### 🎨 User Interface
- Dark theme optimized for reduced eye strain
- Responsive design for mobile/tablet access
- Intuitive navigation and workflow
- No external JavaScript dependencies
- Memory-efficient operation

## 🚀 Installation & Setup

### 1. System Requirements
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- PDO MySQL extension

### 2. Database Configuration
Create or update `con.php` in the project root:

```php
<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306'); 
define('DB_NAME', 'gst_accounting');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 3. Database Migration
Run the migration to create accounting tables:

1. Access the application in your browser
2. Navigate to **Setup Database** or go to `?module=migrate`
3. Click **Run Migration Now**

This will create:
- `accounts` - Chart of accounts
- `journal_entries` - Journal entry headers
- `journal_lines` - Journal entry details (debits/credits)
- `bank_reconciliation` - Bank reconciliation records
- `bank_reconciliation_items` - Reconciliation line items
- Default Indian chart of accounts with GST structure

### 4. Initial Setup
1. Go to **Settings** to configure your organization's state code
2. Review the **Chart of Accounts** and customize as needed
3. Set up your bank accounts in the chart of accounts
4. Begin posting invoices or creating manual journal entries

## 📋 Usage Guide

### Dashboard
The main dashboard provides an overview of:
- Account statistics by type
- Recent journal entries
- Bank reconciliation status
- Quick action links

### Chart of Accounts Management
- **View Accounts**: Browse by account type with color coding
- **Add Account**: Create new accounts with proper codes and hierarchy
- **Edit Account**: Modify account details and activate/deactivate
- **Account Linking**: Set up parent-child relationships

### Manual Journal Entries
1. Select **Manual Journal** from navigation
2. Enter date, description, and reference
3. Add journal lines (minimum 2 required)
4. Ensure debits equal credits (automatic validation)
5. Submit to post the entry

### Invoice Posting
1. Go to **Post Invoices** to see unposted invoices
2. Select invoices to post to accounting
3. System automatically creates journal entries:
   - **Debit**: Accounts Receivable (customer owes money)
   - **Credit**: Sales Revenue (income earned)
   - **Credit**: GST Output accounts (tax collected)

### Financial Reports
- **Trial Balance**: Verify all accounts are balanced
- **Profit & Loss**: View income vs expenses for any period
- **Balance Sheet**: See financial position at any date
- **Ledger**: Drill down into individual account transactions

### Bank Reconciliation
1. Go to **Bank Reconcile** > **New Reconciliation**
2. Select bank account and statement date
3. Enter statement balance from your bank
4. Review outstanding transactions
5. Mark as reconciled when balanced

## 🔧 Technical Details

### Database Schema
The system creates additional tables alongside existing ones:

**Existing Tables** (used as-is):
- `invoices` - Sales invoice headers
- `invoice_items` - Sales invoice line items  
- `parties` - Customer/vendor master data

**New Accounting Tables**:
- `accounts` - Chart of accounts structure
- `journal_entries` - Double-entry journal headers
- `journal_lines` - Individual debit/credit lines
- `bank_reconciliation` - Bank reconciliation records

### GST Logic
```php
// Intra-state (same state): CGST + SGST
if ($supplier_state === $customer_state) {
    $cgst = $gst_amount / 2;
    $sgst = $gst_amount / 2;
    $igst = 0;
}
// Inter-state (different states): IGST
else {
    $cgst = 0;
    $sgst = 0; 
    $igst = $gst_amount;
}
```

### Module Structure
```
├── index.php              # Main application entry point
├── lib/
│   ├── database.php       # Database connection
│   └── accounting.php     # Core accounting functions
├── modules/
│   ├── dashboard.php      # Main dashboard
│   ├── chart_of_accounts.php # COA management
│   ├── journal_new.php    # Manual journal entry
│   ├── ledger.php         # Account ledgers
│   ├── trial_balance.php  # Trial balance report
│   ├── pl.php            # Profit & Loss statement
│   ├── balance_sheet.php  # Balance sheet
│   ├── post_invoices.php  # Invoice posting
│   └── reconcile.php     # Bank reconciliation
├── actions/
│   └── migrate.php       # Database migration
└── con.php               # Database configuration
```

## 🛠️ Customization

### Adding New Account Types
1. Go to **Chart of Accounts** > **Add New Account**
2. Choose appropriate account type and parent
3. Use standard account coding (e.g., 1000-1999 for Assets)

### Custom Reports
The accounting library provides functions to build custom reports:
- `get_account_balance($code, $date)` - Get balance for any account
- `get_trial_balance($date)` - Get trial balance data
- `get_account_ledger($code, $from, $to)` - Get ledger transactions

### Integration with Existing Systems
The system is designed to work alongside existing invoice/inventory systems:
- Reads from existing `invoices` and `invoice_items` tables
- Creates journal entries without modifying invoice data
- Maintains referential integrity through `source_type` and `source_id`

## 🔍 Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check `con.php` database credentials
   - Ensure MySQL/MariaDB service is running
   - Verify database exists and user has permissions

2. **Trial Balance Not Balanced**
   - Review recent journal entries for errors
   - Check for incomplete transactions
   - Use the journal entry edit feature to correct

3. **Invoice Posting Errors**
   - Ensure Chart of Accounts is properly set up
   - Check that required accounts exist (1100-AR, 4100-Sales, GST accounts)
   - Verify invoice data integrity

4. **Migration Errors**
   - Check database user has CREATE TABLE permissions
   - Ensure sufficient disk space
   - Review error logs for specific issues

### Performance Optimization
- Index on `account_code` and `entry_date` for faster queries
- Regular database maintenance and optimization
- Archive old transactions if needed
- Use date ranges in reports for large datasets

## 📚 Accounting Concepts

### Double-Entry Bookkeeping
Every transaction affects at least two accounts with equal debits and credits:
- **Assets & Expenses**: Increase with debits, decrease with credits
- **Liabilities, Equity & Income**: Increase with credits, decrease with debits

### Account Types
- **Assets (1000-1999)**: Resources owned (cash, inventory, equipment)
- **Liabilities (2000-2999)**: Amounts owed (loans, accounts payable)
- **Equity (3000-3999)**: Owner's investment and retained earnings
- **Income (4000-4999)**: Revenue earned from sales/services
- **Expenses (5000-6999)**: Costs incurred to generate revenue

### GST Compliance
- **Input GST**: Tax paid on purchases (asset accounts)
- **Output GST**: Tax collected on sales (liability accounts)
- **CGST**: Central GST (intra-state transactions)
- **SGST**: State GST (intra-state transactions)  
- **IGST**: Integrated GST (inter-state transactions)

## 📞 Support

For technical support or customization requests:
1. Check this documentation first
2. Review the source code comments
3. Test in a development environment before production use
4. Backup your database before major changes

## 📄 License

This accounting module is designed for integration with existing GST invoice systems. Modify and extend as needed for your business requirements.

---

**GST India Accounting System** - Complete double-entry accounting for Indian businesses with GST compliance.