<?php
/**
 * GST India Accounting Application
 * Main entry point with module routing
 */

// Start session
session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}

// Basic security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Get requested module
$module = $_GET['module'] ?? 'dashboard';

// Allowed modules
$allowed = [
    'dashboard', 'chart_of_accounts', 'journal_new', 'ledger', 'trial_balance',
    'pl', 'balance_sheet', 'reconcile', 'post_invoices', 'settings',
    'contacts', 'sales_new', 'purchase_new', 'payment_new', 'receipt_new',
    'credit_note', 'debit_note', 'aging_ar', 'aging_ap', 'cashflow',
    'invoice_list', 'invoice_view', 'party_list', 'migrate'
];

// Validate module
if (!in_array($module, $allowed)) {
    $module = 'dashboard';
}

// CSRF validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['_csrf'], $_POST['_csrf'])) {
        die('CSRF validation failed');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GST India Accounting</title>
    <style>
        /* Dark theme CSS */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a1a;
            color: #e0e0e0;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: #2a2a2a;
            padding: 15px 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #3a3a3a;
        }
        
        h1 {
            color: #4CAF50;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
        }
        
        nav {
            background: #2d2d2d;
            padding: 10px 0;
            margin-bottom: 20px;
        }
        
        nav ul {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
        }
        
        nav a {
            color: #b0b0b0;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s;
            font-size: 13px;
        }
        
        nav a:hover, nav a.active {
            background: #4CAF50;
            color: white;
        }
        
        .content {
            background: #2a2a2a;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #3a3a3a;
        }
        
        h2, h3 {
            color: #4CAF50;
            margin-bottom: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: #1f1f1f;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #3a3a3a;
        }
        
        th {
            background: #333;
            color: #4CAF50;
            font-weight: 600;
        }
        
        tr:nth-child(even) {
            background: #252525;
        }
        
        tr:hover {
            background: #2d2d2d;
        }
        
        input, select, textarea, button {
            background: #333;
            color: #e0e0e0;
            border: 1px solid #555;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
        }
        
        button {
            background: #4CAF50;
            color: white;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #45a049;
        }
        
        button:disabled {
            background: #555;
            cursor: not-allowed;
        }
        
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 600px;
        }
        
        label {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-weight: 500;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row label {
            flex: 1;
        }
        
        .ok {
            background: #1b5e20;
            color: #a5d6a7;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #4CAF50;
        }
        
        .err {
            background: #b71c1c;
            color: #ffcdd2;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #f44336;
        }
        
        .warning {
            background: #e65100;
            color: #ffcc02;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #ff9800;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .card {
            background: #333;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #4CAF50;
        }
        
        .card h4 {
            color: #4CAF50;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .card .value {
            font-size: 20px;
            font-weight: bold;
            color: #e0e0e0;
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        @media (max-width: 768px) {
            .container { padding: 10px; }
            nav ul { flex-direction: column; gap: 8px; }
            .form-row { flex-direction: column; }
            table { font-size: 12px; }
            th, td { padding: 6px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>GST India Accounting System</h1>
        </div>
    </header>
    
    <nav>
        <div class="container">
            <ul>
                <li><a href="?module=dashboard" <?= $module === 'dashboard' ? 'class="active"' : '' ?>>Dashboard</a></li>
                <li><a href="?module=chart_of_accounts" <?= $module === 'chart_of_accounts' ? 'class="active"' : '' ?>>Chart of Accounts</a></li>
                <li><a href="?module=journal_new" <?= $module === 'journal_new' ? 'class="active"' : '' ?>>Manual Journal</a></li>
                <li><a href="?module=ledger" <?= $module === 'ledger' ? 'class="active"' : '' ?>>Ledger</a></li>
                <li><a href="?module=trial_balance" <?= $module === 'trial_balance' ? 'class="active"' : '' ?>>Trial Balance</a></li>
                <li><a href="?module=pl" <?= $module === 'pl' ? 'class="active"' : '' ?>>P&L</a></li>
                <li><a href="?module=balance_sheet" <?= $module === 'balance_sheet' ? 'class="active"' : '' ?>>Balance Sheet</a></li>
                <li><a href="?module=reconcile" <?= $module === 'reconcile' ? 'class="active"' : '' ?>>Bank Reconcile</a></li>
                <li><a href="?module=post_invoices" <?= $module === 'post_invoices' ? 'class="active"' : '' ?>>Post Invoices</a></li>
                <li><a href="?module=invoice_list" <?= $module === 'invoice_list' ? 'class="active"' : '' ?>>Invoice List</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container">
        <div class="content">
            <?php
            // Include the requested module
            $module_file = __DIR__ . "/modules/{$module}.php";
            if (file_exists($module_file)) {
                include $module_file;
            } else {
                echo "<h2>Module Not Found</h2>";
                echo "<p>The requested module '{$module}' does not exist.</p>";
                echo "<p><a href='?module=dashboard'>← Back to Dashboard</a></p>";
            }
            ?>
        </div>
    </div>
    
    <footer style="text-align: center; padding: 20px; color: #666; border-top: 1px solid #3a3a3a; margin-top: 40px;">
        <p>GST India Accounting System &copy; <?= date('Y') ?></p>
    </footer>
</body>
</html>