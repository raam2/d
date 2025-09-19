<?php
/**
 * Demo Dashboard Module
 * Shows the accounting system with mock data when database is not available
 */

?>

<h2>📊 Dashboard (Demo Mode)</h2>

<div class="warning" style="margin-bottom: 20px;">
    <h3>⚠️ Demo Mode</h3>
    <p>Database connection not available. This is a demonstration of the accounting module with sample data.</p>
    <p>In a live environment, this would connect to your MySQL database with real invoice and accounting data.</p>
</div>

<div class="summary-cards">
    <div class="card">
        <h4>Chart of Accounts</h4>
        <div class="value">45</div>
        <small>Active accounts</small>
    </div>
    
    <div class="card">
        <h4>Journal Entries</h4>
        <div class="value">127</div>
        <small>This month</small>
    </div>
    
    <div class="card">
        <h4>Bank Reconciliation</h4>
        <div class="value">3</div>
        <small>Pending items</small>
    </div>
    
    <div class="card">
        <h4>Total Assets</h4>
        <div class="value">₹15,47,832.50</div>
        <small>Current book value</small>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
    <div>
        <h3>📋 Account Summary</h3>
        <table>
            <tr><th>Account Type</th><th>Count</th></tr>
            <tr><td>Assets</td><td>12</td></tr>
            <tr><td>Liabilities</td><td>8</td></tr>
            <tr><td>Equity</td><td>5</td></tr>
            <tr><td>Income</td><td>8</td></tr>
            <tr><td>Expenses</td><td>12</td></tr>
        </table>
    </div>
    
    <div>
        <h3>📄 Recent Invoices</h3>
        <table>
            <tr><th>Invoice #</th><th>Date</th><th>Amount</th></tr>
            <tr><td>INV-2025-001</td><td>18/09/2025</td><td>₹12,450.00</td></tr>
            <tr><td>INV-2025-002</td><td>17/09/2025</td><td>₹8,920.00</td></tr>
            <tr><td>INV-2025-003</td><td>16/09/2025</td><td>₹15,340.00</td></tr>
            <tr><td>INV-2025-004</td><td>15/09/2025</td><td>₹6,750.00</td></tr>
            <tr><td>INV-2025-005</td><td>14/09/2025</td><td>₹22,180.00</td></tr>
        </table>
        <p><a href="?module=invoice_list">View all invoices →</a></p>
    </div>
</div>

<div style="margin-top: 30px;">
    <h3>🚀 Quick Actions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="?module=migrate" style="display: block; padding: 15px; background: #333; color: #ff9800; text-decoration: none; border-radius: 6px; text-align: center;">
            <strong>Setup Database</strong><br>
            <small>Run migration to create tables</small>
        </a>
        
        <a href="?module=chart_of_accounts" style="display: block; padding: 15px; background: #333; color: #4CAF50; text-decoration: none; border-radius: 6px; text-align: center;">
            <strong>Chart of Accounts</strong><br>
            <small>Manage your accounts</small>
        </a>
        
        <a href="?module=journal_new" style="display: block; padding: 15px; background: #333; color: #4CAF50; text-decoration: none; border-radius: 6px; text-align: center;">
            <strong>New Journal Entry</strong><br>
            <small>Record manual transactions</small>
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

<div style="margin-top: 30px; background: #2d2d2d; padding: 20px; border-radius: 6px;">
    <h3>🎯 GST India Accounting Module Features</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 15px;">
        <div>
            <h4 style="color: #4CAF50; margin-bottom: 10px;">📊 Complete Chart of Accounts</h4>
            <ul style="color: #ccc; font-size: 14px; line-height: 1.8;">
                <li>Indian accounting standard structure</li>
                <li>GST-specific accounts (CGST, SGST, IGST)</li>
                <li>Hierarchical account organization</li>
                <li>Asset, Liability, Equity, Income, Expense types</li>
            </ul>
        </div>
        
        <div>
            <h4 style="color: #4CAF50; margin-bottom: 10px;">⚖️ Double-Entry Bookkeeping</h4>
            <ul style="color: #ccc; font-size: 14px; line-height: 1.8;">
                <li>Automatic debit/credit validation</li>
                <li>Manual journal entry creation</li>
                <li>Automated invoice posting</li>
                <li>Complete audit trail</li>
            </ul>
        </div>
        
        <div>
            <h4 style="color: #4CAF50; margin-bottom: 10px;">📈 Financial Reports</h4>
            <ul style="color: #ccc; font-size: 14px; line-height: 1.8;">
                <li>Trial Balance with real-time balancing</li>
                <li>Profit & Loss statement</li>
                <li>Balance Sheet</li>
                <li>Account-wise ledger views</li>
            </ul>
        </div>
        
        <div>
            <h4 style="color: #4CAF50; margin-bottom: 10px;">🧾 GST Invoice Integration</h4>
            <ul style="color: #ccc; font-size: 14px; line-height: 1.8;">
                <li>Automatic posting of sales invoices</li>
                <li>GST split calculation (CGST/SGST/IGST)</li>
                <li>State-wise tax determination</li>
                <li>Accounts Receivable automation</li>
            </ul>
        </div>
        
        <div>
            <h4 style="color: #4CAF50; margin-bottom: 10px;">🏛️ Bank Reconciliation</h4>
            <ul style="color: #ccc; font-size: 14px; line-height: 1.8;">
                <li>Statement vs book balance comparison</li>
                <li>Outstanding items tracking</li>
                <li>Multi-bank account support</li>
                <li>Reconciliation audit trail</li>
            </ul>
        </div>
        
        <div>
            <h4 style="color: #4CAF50; margin-bottom: 10px;">🎨 User Interface</h4>
            <ul style="color: #ccc; font-size: 14px; line-height: 1.8;">
                <li>Dark theme for reduced eye strain</li>
                <li>Responsive design for mobile/tablet</li>
                <li>Intuitive navigation and workflow</li>
                <li>No external dependencies</li>
            </ul>
        </div>
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