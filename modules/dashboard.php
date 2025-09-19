<?php
/**
 * Dashboard Module
 * Main landing page with system overview
 */

require_once __DIR__ . '/../lib/database.php';

// Get basic statistics
try {
    // Check if accounting tables exist
    $tables_exist = false;
    try {
        $db->query("SELECT 1 FROM accounts LIMIT 1");
        $tables_exist = true;
    } catch (PDOException $e) {
        // Tables don't exist yet
    }
    
    if (!$tables_exist) {
        echo '<div class="warning">';
        echo '<h3>⚠️ Accounting Tables Not Found</h3>';
        echo '<p>The accounting module tables have not been created yet. Please run the migration first.</p>';
        echo '<p><a href="?module=migrate" style="background: #ff9800; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">Run Migration</a></p>';
        echo '</div>';
        return;
    }
    
    // Get account counts by type
    $stmt = $db->query("SELECT account_type, COUNT(*) as count FROM accounts WHERE is_active = 1 GROUP BY account_type");
    $account_counts = [];
    while ($row = $stmt->fetch()) {
        $account_counts[$row['account_type']] = $row['count'];
    }
    
    // Get journal entry count for this month
    $current_month = date('Y-m');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM journal_entries WHERE DATE_FORMAT(entry_date, '%Y-%m') = ?");
    $stmt->execute([$current_month]);
    $monthly_entries = $stmt->fetch()['count'] ?? 0;
    
    // Get unreconciled bank items count
    $stmt = $db->query("SELECT COUNT(*) as count FROM bank_reconciliation WHERE status = 'DRAFT'");
    $unreconciled_count = $stmt->fetch()['count'] ?? 0;
    
    // Get total assets and liabilities for quick overview
    $stmt = $db->query("
        SELECT 
            a.account_type,
            COALESCE(SUM(l.debit_amount - l.credit_amount), 0) as balance
        FROM accounts a
        LEFT JOIN journal_lines l ON a.code = l.account_code
        WHERE a.is_active = 1 AND a.account_type IN ('ASSET', 'LIABILITY')
        GROUP BY a.account_type
    ");
    $balances = [];
    while ($row = $stmt->fetch()) {
        $balances[$row['account_type']] = $row['balance'];
    }
    
    // Get recent invoices from existing tables
    $recent_invoices = [];
    try {
        $stmt = $db->query("SELECT id, invoice_number, invoice_date, party_id, grand_total 
                           FROM invoices 
                           ORDER BY invoice_date DESC, id DESC 
                           LIMIT 5");
        $recent_invoices = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Invoices table structure might be different, try alternative
        try {
            $stmt = $db->query("SELECT id, no as invoice_number, issue_date as invoice_date, party_id, total_value as grand_total 
                               FROM invoices 
                               ORDER BY issue_date DESC, id DESC 
                               LIMIT 5");
            $recent_invoices = $stmt->fetchAll();
        } catch (PDOException $e2) {
            // Unable to get invoices, continue without them
        }
    }
    
} catch (Exception $e) {
    echo '<div class="err">Error loading dashboard data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}
?>

<h2>📊 Dashboard</h2>

<div class="summary-cards">
    <div class="card">
        <h4>Chart of Accounts</h4>
        <div class="value"><?= array_sum($account_counts) ?></div>
        <small>Active accounts</small>
    </div>
    
    <div class="card">
        <h4>Journal Entries</h4>
        <div class="value"><?= $monthly_entries ?></div>
        <small>This month</small>
    </div>
    
    <div class="card">
        <h4>Bank Reconciliation</h4>
        <div class="value"><?= $unreconciled_count ?></div>
        <small>Pending items</small>
    </div>
    
    <div class="card">
        <h4>Total Assets</h4>
        <div class="value">₹<?= number_format($balances['ASSET'] ?? 0, 2) ?></div>
        <small>Current book value</small>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
    <div>
        <h3>📋 Account Summary</h3>
        <table>
            <tr><th>Account Type</th><th>Count</th></tr>
            <tr><td>Assets</td><td><?= $account_counts['ASSET'] ?? 0 ?></td></tr>
            <tr><td>Liabilities</td><td><?= $account_counts['LIABILITY'] ?? 0 ?></td></tr>
            <tr><td>Equity</td><td><?= $account_counts['EQUITY'] ?? 0 ?></td></tr>
            <tr><td>Income</td><td><?= $account_counts['INCOME'] ?? 0 ?></td></tr>
            <tr><td>Expenses</td><td><?= $account_counts['EXPENSE'] ?? 0 ?></td></tr>
        </table>
    </div>
    
    <div>
        <h3>📄 Recent Invoices</h3>
        <?php if (empty($recent_invoices)): ?>
            <p>No invoices found. <a href="?module=invoice_list">View all invoices →</a></p>
        <?php else: ?>
            <table>
                <tr><th>Invoice #</th><th>Date</th><th>Amount</th></tr>
                <?php foreach ($recent_invoices as $inv): ?>
                <tr>
                    <td><?= htmlspecialchars($inv['invoice_number'] ?? $inv['id']) ?></td>
                    <td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td>
                    <td>₹<?= number_format($inv['grand_total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p><a href="?module=invoice_list">View all invoices →</a></p>
        <?php endif; ?>
    </div>
</div>

<div style="margin-top: 30px;">
    <h3>🚀 Quick Actions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="?module=journal_new" style="display: block; padding: 15px; background: #333; color: #4CAF50; text-decoration: none; border-radius: 6px; text-align: center;">
            <strong>New Journal Entry</strong><br>
            <small>Record manual transactions</small>
        </a>
        
        <a href="?module=post_invoices" style="display: block; padding: 15px; background: #333; color: #4CAF50; text-decoration: none; border-radius: 6px; text-align: center;">
            <strong>Post Invoices</strong><br>
            <small>Post invoices to ledger</small>
        </a>
        
        <a href="?module=reconcile" style="display: block; padding: 15px; background: #333; color: #4CAF50; text-decoration: none; border-radius: 6px; text-align: center;">
            <strong>Bank Reconciliation</strong><br>
            <small>Reconcile bank statements</small>
        </a>
        
        <a href="?module=trial_balance" style="display: block; padding: 15px; background: #333; color: #4CAF50; text-decoration: none; border-radius: 6px; text-align: center;">
            <strong>Trial Balance</strong><br>
            <small>View current balances</small>
        </a>
    </div>
</div>

<div style="margin-top: 30px;">
    <h3>📈 Reports</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
        <a href="?module=ledger" class="button">Ledger</a>
        <a href="?module=trial_balance" class="button">Trial Balance</a>
        <a href="?module=pl" class="button">P&L Statement</a>
        <a href="?module=balance_sheet" class="button">Balance Sheet</a>
    </div>
</div>

<style>
.button {
    display: inline-block;
    background: #4CAF50;
    color: white;
    padding: 10px 15px;
    text-decoration: none;
    border-radius: 4px;
    text-align: center;
    transition: background 0.3s;
}
.button:hover {
    background: #45a049;
}
</style>