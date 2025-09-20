<?php
class Database {
    private static $host = '127.0.0.1';
    private static $db_name = 'gst_accounting';
    private static $username = 'gstwork'; // Replace with your user
    private static $password = 'gstwork@123'; // Replace with your password
    private static $conn;

    public static function getConnection() {
        self::$conn = null;
        try {
            // For demo purposes, use SQLite if MySQL is not available
            try {
                self::$conn = new PDO('mysql:host=' . self::$host . ';dbname=' . self::$db_name, self::$username, self::$password);
                self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$conn->exec("set names utf8");
            } catch(PDOException $mysql_exception) {
                // Fallback to SQLite for demo
                $sqlite_file = __DIR__ . '/../database/demo_accounting.db';
                $sqlite_dir = dirname($sqlite_file);
                if (!is_dir($sqlite_dir)) {
                    mkdir($sqlite_dir, 0755, true);
                }
                
                self::$conn = new PDO('sqlite:' . $sqlite_file);
                self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create basic structure for SQLite demo
                self::createSQLiteStructure();
            }
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return self::$conn;
    }
    
    private static function createSQLiteStructure() {
        // Create basic accounts table for demo
        $sql = "CREATE TABLE IF NOT EXISTS accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            account_type VARCHAR(20) NOT NULL CHECK(account_type IN ('ASSET','LIABILITY','EQUITY','INCOME','EXPENSE')),
            parent_code VARCHAR(20),
            is_active INTEGER DEFAULT 1,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        self::$conn->exec($sql);
        
        // Create journal_entries table
        $sql = "CREATE TABLE IF NOT EXISTS journal_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_date DATE NOT NULL,
            description VARCHAR(255),
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            reference_no VARCHAR(100),
            notes TEXT,
            source_type VARCHAR(50),
            source_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by VARCHAR(100),
            source VARCHAR(50),
            status VARCHAR(20) DEFAULT 'posted'
        )";
        self::$conn->exec($sql);
        
        // Create journal_lines table
        $sql = "CREATE TABLE IF NOT EXISTS journal_lines (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_id INTEGER NOT NULL,
            account_type VARCHAR(20) NOT NULL DEFAULT 'ledger',
            party_id INTEGER,
            account_code VARCHAR(100),
            debit_amount DECIMAL(12,2) DEFAULT 0,
            credit_amount DECIMAL(12,2) DEFAULT 0,
            FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE
        )";
        self::$conn->exec($sql);
        
        // Create app_settings table
        $sql = "CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        self::$conn->exec($sql);
        
        // Insert sample chart of accounts
        $sample_accounts = [
            ['1000', 'CURRENT ASSETS', 'ASSET', null, 'Current Assets'],
            ['1010', 'Bank Account - Main', 'ASSET', '1000', 'Primary bank account'],
            ['1100', 'Accounts Receivable', 'ASSET', '1000', 'Money owed by customers'],
            ['1200', 'Inventory', 'ASSET', '1000', 'Goods for sale'],
            ['1400', 'GST INPUT ACCOUNTS', 'ASSET', '1000', 'GST paid on purchases'],
            ['1401', 'CGST Input', 'ASSET', '1400', 'Central GST paid'],
            ['1402', 'SGST Input', 'ASSET', '1400', 'State GST paid'],
            ['1403', 'IGST Input', 'ASSET', '1400', 'Integrated GST paid'],
            
            ['2000', 'CURRENT LIABILITIES', 'LIABILITY', null, 'Current Liabilities'],
            ['2100', 'Accounts Payable', 'LIABILITY', '2000', 'Money owed to suppliers'],
            ['2300', 'GST OUTPUT ACCOUNTS', 'LIABILITY', '2000', 'GST collected on sales'],
            ['2301', 'CGST Output', 'LIABILITY', '2300', 'Central GST collected'],
            ['2302', 'SGST Output', 'LIABILITY', '2300', 'State GST collected'],
            ['2303', 'IGST Output', 'LIABILITY', '2300', 'Integrated GST collected'],
            
            ['3000', 'EQUITY', 'EQUITY', null, 'Owner\'s Equity'],
            ['3100', 'Capital', 'EQUITY', '3000', 'Owner\'s capital'],
            ['3200', 'Retained Earnings', 'EQUITY', '3000', 'Retained earnings'],
            
            ['4000', 'SALES REVENUE', 'INCOME', null, 'Sales Revenue'],
            ['4100', 'Sales - Domestic', 'INCOME', '4000', 'Domestic sales'],
            ['4200', 'Sales - Export', 'INCOME', '4000', 'Export sales'],
            
            ['5000', 'COST OF GOODS SOLD', 'EXPENSE', null, 'Cost of Goods Sold'],
            ['5100', 'Purchases', 'EXPENSE', '5000', 'Purchase of goods'],
            
            ['6000', 'OPERATING EXPENSES', 'EXPENSE', null, 'Operating Expenses'],
            ['6100', 'Salaries & Wages', 'EXPENSE', '6000', 'Employee salaries'],
            ['6200', 'Rent Expense', 'EXPENSE', '6000', 'Office/factory rent'],
            ['6300', 'Utilities', 'EXPENSE', '6000', 'Electricity, water, etc.'],
        ];
        
        // Check if accounts already exist
        $count_stmt = self::$conn->query("SELECT COUNT(*) FROM accounts");
        if ($count_stmt->fetchColumn() == 0) {
            $stmt = self::$conn->prepare("INSERT OR IGNORE INTO accounts (code, name, account_type, parent_code, description) VALUES (?, ?, ?, ?, ?)");
            foreach ($sample_accounts as $account) {
                $stmt->execute($account);
            }
        }
    }
}
?>
