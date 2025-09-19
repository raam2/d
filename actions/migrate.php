<?php
/**
 * Database Migration Script for Accounting Module
 * Creates all necessary tables for double-entry accounting
 */

require_once __DIR__ . '/../lib/database.php';

function migrate_accounting_tables() {
    global $db;
    
    echo "Creating accounting tables...\n";
    
    // 1. Chart of Accounts
    $db->exec("CREATE TABLE IF NOT EXISTS accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        account_type ENUM('ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE') NOT NULL,
        parent_code VARCHAR(20) NULL,
        is_active BOOLEAN DEFAULT 1,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_code (code),
        INDEX idx_type (account_type),
        INDEX idx_parent (parent_code),
        FOREIGN KEY (parent_code) REFERENCES accounts(code) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Chart of Accounts'");
    
    // 2. Journal Entries (Headers)
    $db->exec("CREATE TABLE IF NOT EXISTS journal_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entry_date DATE NOT NULL,
        reference VARCHAR(100) NULL,
        description TEXT NOT NULL,
        total_debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        total_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        source_type ENUM('MANUAL', 'SALES_INVOICE', 'PURCHASE_INVOICE', 'PAYMENT', 'RECEIPT', 'BANK_RECONCILE') DEFAULT 'MANUAL',
        source_id INT NULL,
        posted_by VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_date (entry_date),
        INDEX idx_source (source_type, source_id),
        INDEX idx_reference (reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Journal Entry Headers'");
    
    // 3. Journal Lines (Details)
    $db->exec("CREATE TABLE IF NOT EXISTS journal_lines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entry_id INT NOT NULL,
        account_code VARCHAR(20) NOT NULL,
        debit_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        credit_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        description TEXT NULL,
        line_number INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_entry (entry_id),
        INDEX idx_account (account_code),
        INDEX idx_account_date (account_code, entry_id),
        FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
        FOREIGN KEY (account_code) REFERENCES accounts(code) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Journal Entry Lines'");
    
    // 4. Bank Reconciliation
    $db->exec("CREATE TABLE IF NOT EXISTS bank_reconciliation (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bank_account_code VARCHAR(20) NOT NULL,
        statement_date DATE NOT NULL,
        statement_balance DECIMAL(18,2) NOT NULL,
        book_balance DECIMAL(18,2) NOT NULL,
        reconciled_balance DECIMAL(18,2) NOT NULL,
        status ENUM('DRAFT', 'RECONCILED') DEFAULT 'DRAFT',
        reconciled_at TIMESTAMP NULL,
        reconciled_by VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_account_date (bank_account_code, statement_date),
        FOREIGN KEY (bank_account_code) REFERENCES accounts(code) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Bank Reconciliation Headers'");
    
    // 5. Bank Reconciliation Items
    $db->exec("CREATE TABLE IF NOT EXISTS bank_reconciliation_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reconciliation_id INT NOT NULL,
        journal_line_id INT NULL,
        transaction_date DATE NOT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        type ENUM('DEPOSIT', 'WITHDRAWAL', 'OUTSTANDING_DEPOSIT', 'OUTSTANDING_WITHDRAWAL') NOT NULL,
        is_reconciled BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_reconciliation (reconciliation_id),
        INDEX idx_journal_line (journal_line_id),
        FOREIGN KEY (reconciliation_id) REFERENCES bank_reconciliation(id) ON DELETE CASCADE,
        FOREIGN KEY (journal_line_id) REFERENCES journal_lines(id) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Bank Reconciliation Items'");
    
    echo "Accounting tables created successfully.\n";
}

function insert_default_coa() {
    global $db;
    
    echo "Inserting default Chart of Accounts...\n";
    
    $default_accounts = [
        // ASSETS
        ['1000', 'CURRENT ASSETS', 'ASSET', null, 'Current Assets'],
        ['1010', 'Bank Account - Main', 'ASSET', '1000', 'Primary bank account'],
        ['1020', 'Bank Account - Secondary', 'ASSET', '1000', 'Secondary bank account'],
        ['1100', 'Accounts Receivable', 'ASSET', '1000', 'Money owed by customers'],
        ['1200', 'Inventory', 'ASSET', '1000', 'Goods for sale'],
        ['1300', 'Prepaid Expenses', 'ASSET', '1000', 'Prepaid expenses'],
        
        // GST Input Accounts
        ['1400', 'GST INPUT ACCOUNTS', 'ASSET', '1000', 'GST paid on purchases'],
        ['1401', 'CGST Input', 'ASSET', '1400', 'Central GST paid'],
        ['1402', 'SGST Input', 'ASSET', '1400', 'State GST paid'],
        ['1403', 'IGST Input', 'ASSET', '1400', 'Integrated GST paid'],
        ['1404', 'CESS Input', 'ASSET', '1400', 'Cess paid'],
        
        // Fixed Assets
        ['1500', 'FIXED ASSETS', 'ASSET', null, 'Fixed Assets'],
        ['1510', 'Plant & Machinery', 'ASSET', '1500', 'Plant and machinery'],
        ['1520', 'Furniture & Fixtures', 'ASSET', '1500', 'Furniture and fixtures'],
        ['1530', 'Computer Equipment', 'ASSET', '1500', 'Computer equipment'],
        
        // LIABILITIES
        ['2000', 'CURRENT LIABILITIES', 'LIABILITY', null, 'Current Liabilities'],
        ['2100', 'Accounts Payable', 'LIABILITY', '2000', 'Money owed to suppliers'],
        ['2200', 'Accrued Expenses', 'LIABILITY', '2000', 'Accrued expenses'],
        
        // GST Output Accounts
        ['2300', 'GST OUTPUT ACCOUNTS', 'LIABILITY', '2000', 'GST collected on sales'],
        ['2301', 'CGST Output', 'LIABILITY', '2300', 'Central GST collected'],
        ['2302', 'SGST Output', 'LIABILITY', '2300', 'State GST collected'],
        ['2303', 'IGST Output', 'LIABILITY', '2300', 'Integrated GST collected'],
        ['2304', 'CESS Output', 'LIABILITY', '2300', 'Cess collected'],
        
        // Long-term Liabilities
        ['2500', 'LONG TERM LIABILITIES', 'LIABILITY', null, 'Long-term Liabilities'],
        ['2510', 'Bank Loans', 'LIABILITY', '2500', 'Bank loans'],
        
        // EQUITY
        ['3000', 'EQUITY', 'EQUITY', null, 'Owner\'s Equity'],
        ['3100', 'Capital', 'EQUITY', '3000', 'Owner\'s capital'],
        ['3200', 'Retained Earnings', 'EQUITY', '3000', 'Retained earnings'],
        ['3300', 'Drawings', 'EQUITY', '3000', 'Owner withdrawals'],
        
        // INCOME
        ['4000', 'SALES REVENUE', 'INCOME', null, 'Sales Revenue'],
        ['4100', 'Sales - Domestic', 'INCOME', '4000', 'Domestic sales'],
        ['4200', 'Sales - Export', 'INCOME', '4000', 'Export sales'],
        ['4300', 'Other Income', 'INCOME', '4000', 'Miscellaneous income'],
        
        // EXPENSES
        ['5000', 'COST OF GOODS SOLD', 'EXPENSE', null, 'Cost of Goods Sold'],
        ['5100', 'Purchases', 'EXPENSE', '5000', 'Purchase of goods'],
        ['5200', 'Direct Labor', 'EXPENSE', '5000', 'Direct labor costs'],
        ['5300', 'Manufacturing Overhead', 'EXPENSE', '5000', 'Manufacturing overhead'],
        
        ['6000', 'OPERATING EXPENSES', 'EXPENSE', null, 'Operating Expenses'],
        ['6100', 'Salaries & Wages', 'EXPENSE', '6000', 'Employee salaries'],
        ['6200', 'Rent Expense', 'EXPENSE', '6000', 'Office/factory rent'],
        ['6300', 'Utilities', 'EXPENSE', '6000', 'Electricity, water, etc.'],
        ['6400', 'Transportation', 'EXPENSE', '6000', 'Transportation costs'],
        ['6500', 'Professional Services', 'EXPENSE', '6000', 'Legal, audit, consulting'],
        ['6600', 'Insurance', 'EXPENSE', '6000', 'Insurance premiums'],
        ['6700', 'Depreciation', 'EXPENSE', '6000', 'Asset depreciation'],
        ['6800', 'Bank Charges', 'EXPENSE', '6000', 'Bank fees and charges'],
        ['6900', 'Miscellaneous Expenses', 'EXPENSE', '6000', 'Other expenses'],
    ];
    
    $stmt = $db->prepare("INSERT IGNORE INTO accounts (code, name, account_type, parent_code, description) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($default_accounts as $account) {
        $stmt->execute($account);
    }
    
    echo "Default Chart of Accounts inserted successfully.\n";
}

function create_indexes() {
    global $db;
    
    echo "Creating additional indexes for performance...\n";
    
    // Add more indexes for common queries
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_journal_lines_account_date ON journal_lines (account_code, entry_id)",
        "CREATE INDEX IF NOT EXISTS idx_journal_entries_date_source ON journal_entries (entry_date, source_type)",
        "CREATE INDEX IF NOT EXISTS idx_accounts_active ON accounts (is_active)",
    ];
    
    foreach ($indexes as $index_sql) {
        try {
            $db->exec($index_sql);
        } catch (PDOException $e) {
            // Index might already exist, continue
        }
    }
    
    echo "Indexes created successfully.\n";
}

// Main migration execution
if (php_sapi_name() === 'cli') {
    // Running from command line
    echo "Starting accounting module migration...\n";
    
    try {
        migrate_accounting_tables();
        insert_default_coa();
        create_indexes();
        
        echo "\nMigration completed successfully!\n";
        echo "You can now use the accounting module.\n";
        
    } catch (Exception $e) {
        echo "Migration failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}