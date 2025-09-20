<?php
session_start();

// Include database configuration
require_once 'config/database.php';

// Get the requested module/page
$module = $_GET['module'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

// Initialize database connection
$db = Database::getConnection();

// Simple authentication check (you can enhance this)
function isLoggedIn() {
    return true; // For now, allow all access
}

if (!isLoggedIn() && $module !== 'login') {
    header('Location: ?module=login');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GST Accounting System</title>
    <link rel="stylesheet" href="assets/css/dark-accounting.css">
</head>
<body>
    
    <header class="header">
        <h1>GST Accounting System</h1>
    </header>

    <nav class="nav-menu">
        <ul>
            <li><a href="?module=dashboard" class="<?= $module === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
            <li><a href="?module=accounts" class="<?= $module === 'accounts' ? 'active' : '' ?>">Chart of Accounts</a></li>
            <li><a href="?module=journal" class="<?= $module === 'journal' ? 'active' : '' ?>">Journal Entry</a></li>
            <li><a href="?module=ledger" class="<?= $module === 'ledger' ? 'active' : '' ?>">Ledger</a></li>
            <li><a href="?module=trial_balance" class="<?= $module === 'trial_balance' ? 'active' : '' ?>">Trial Balance</a></li>
            <li><a href="?module=profit_loss" class="<?= $module === 'profit_loss' ? 'active' : '' ?>">P&L Statement</a></li>
            <li><a href="?module=balance_sheet" class="<?= $module === 'balance_sheet' ? 'active' : '' ?>">Balance Sheet</a></li>
            <li><a href="?module=invoices" class="<?= $module === 'invoices' ? 'active' : '' ?>">Invoices</a></li>
            <li><a href="?module=migrate" class="<?= $module === 'migrate' ? 'active' : '' ?>">Setup DB</a></li>
        </ul>
    </nav>

    <div class="container">
        <main class="main-content">
            <?php
            // Route to appropriate module
            $moduleFile = 'modules/' . $module . '.php';
            
            if (file_exists($moduleFile)) {
                include $moduleFile;
            } else {
                // Default to dashboard if module not found
                include 'modules/dashboard.php';
            }
            ?>
        </main>
    </div>

    <script src="assets/js/accounting.js"></script>
</body>
</html>