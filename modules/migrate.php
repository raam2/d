<?php
/**
 * Migration Module
 * Setup and migrate accounting tables
 */

require_once __DIR__ . '/../lib/database.php';

$step = $_GET['step'] ?? 'check';
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'migrate') {
            // Include and run migration
            include __DIR__ . '/../actions/migrate.php';
            
            migrate_accounting_tables();
            insert_default_coa();
            create_indexes();
            
            $message = 'Migration completed successfully! All accounting tables have been created.';
            $message_type = 'ok';
            $step = 'complete';
        }
    } catch (Exception $e) {
        $message = 'Migration failed: ' . $e->getMessage();
        $message_type = 'err';
    }
}

// Check current database status
$tables_status = [];
$required_tables = ['accounts', 'journal_entries', 'journal_lines', 'bank_reconciliation'];

foreach ($required_tables as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $tables_status[$table] = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $tables_status[$table] = false;
    }
}

$all_tables_exist = array_reduce($tables_status, function($carry, $exists) { return $carry && $exists; }, true);

// Check if we have accounts data
$has_accounts = false;
if ($tables_status['accounts']) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM accounts");
        $count = $stmt->fetch()['count'];
        $has_accounts = $count > 0;
    } catch (PDOException $e) {
        $has_accounts = false;
    }
}

?>

<h2>🔧 Database Migration</h2>

<?php if ($message): ?>
    <div class="<?= $message_type ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($step === 'complete'): ?>
    <div class="ok">
        <h3>✅ Migration Completed Successfully!</h3>
        <p>All accounting tables have been created and default chart of accounts has been set up.</p>
        <p><a href="?module=dashboard" class="button">Go to Dashboard</a></p>
    </div>
<?php else: ?>

<div class="summary-cards">
    <div class="card">
        <h4>Database Status</h4>
        <div class="value"><?= $all_tables_exist ? '✅ Ready' : '⚠️ Needs Setup' ?></div>
        <small><?= count(array_filter($tables_status)) ?>/<?= count($required_tables) ?> tables exist</small>
    </div>
    
    <div class="card">
        <h4>Chart of Accounts</h4>
        <div class="value"><?= $has_accounts ? '✅ Configured' : '❌ Empty' ?></div>
        <small><?= $has_accounts ? 'Ready to use' : 'Needs initialization' ?></small>
    </div>
</div>

<h3>📋 Database Table Status</h3>
<table>
    <tr>
        <th>Table Name</th>
        <th>Status</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>accounts</td>
        <td><?= $tables_status['accounts'] ? '✅ Exists' : '❌ Missing' ?></td>
        <td>Chart of accounts for double-entry bookkeeping</td>
    </tr>
    <tr>
        <td>journal_entries</td>
        <td><?= $tables_status['journal_entries'] ? '✅ Exists' : '❌ Missing' ?></td>
        <td>Journal entry headers</td>
    </tr>
    <tr>
        <td>journal_lines</td>
        <td><?= $tables_status['journal_lines'] ? '✅ Exists' : '❌ Missing' ?></td>
        <td>Journal entry line items (debits/credits)</td>
    </tr>
    <tr>
        <td>bank_reconciliation</td>
        <td><?= $tables_status['bank_reconciliation'] ? '✅ Exists' : '❌ Missing' ?></td>
        <td>Bank reconciliation tracking</td>
    </tr>
</table>

<?php if (!$all_tables_exist || !$has_accounts): ?>
<div style="margin-top: 30px; padding: 20px; background: #2d2d2d; border-radius: 6px; border-left: 4px solid #ff9800;">
    <h3>⚠️ Migration Required</h3>
    <p>The accounting module requires additional database tables to function properly. The migration will:</p>
    
    <ul style="margin: 15px 0; padding-left: 20px;">
        <li>Create accounting tables (accounts, journal_entries, journal_lines, bank_reconciliation)</li>
        <li>Set up a complete Chart of Accounts for Indian businesses</li>
        <li>Create GST-specific accounts (CGST, SGST, IGST input/output)</li>
        <li>Add performance indexes for fast reporting</li>
        <li>Ensure compatibility with existing invoice data</li>
    </ul>
    
    <p><strong>This migration is safe and will not affect your existing data.</strong></p>
    
    <form method="POST" style="margin-top: 20px;">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_csrf']) ?>">
        <input type="hidden" name="action" value="migrate">
        <button type="submit" style="background: #ff9800; font-size: 16px; padding: 12px 24px;">
            🚀 Run Migration Now
        </button>
    </form>
</div>
<?php else: ?>
<div class="ok">
    <h3>✅ Database is Ready</h3>
    <p>All required tables exist and are properly configured. You can now use the accounting module.</p>
    <p><a href="?module=dashboard" class="button">Go to Dashboard</a></p>
</div>
<?php endif; ?>

<h3>📚 What the Migration Does</h3>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
    <div style="background: #333; padding: 15px; border-radius: 6px;">
        <h4>🏦 Chart of Accounts</h4>
        <p>Creates a complete Indian accounting structure with:</p>
        <ul style="margin: 10px 0; padding-left: 20px; font-size: 13px;">
            <li>Current & Fixed Assets</li>
            <li>Current & Long-term Liabilities</li>
            <li>Equity accounts</li>
            <li>Revenue & Expense accounts</li>
            <li>GST Input/Output accounts</li>
        </ul>
    </div>
    
    <div style="background: #333; padding: 15px; border-radius: 6px;">
        <h4>📝 Journal System</h4>
        <p>Double-entry bookkeeping with:</p>
        <ul style="margin: 10px 0; padding-left: 20px; font-size: 13px;">
            <li>Manual journal entries</li>
            <li>Automated posting from invoices</li>
            <li>Complete audit trail</li>
            <li>Balanced entry validation</li>
            <li>Source document tracking</li>
        </ul>
    </div>
    
    <div style="background: #333; padding: 15px; border-radius: 6px;">
        <h4>🏛️ Bank Reconciliation</h4>
        <p>Complete bank reconciliation with:</p>
        <ul style="margin: 10px 0; padding-left: 20px; font-size: 13px;">
            <li>Statement vs book balance</li>
            <li>Outstanding items tracking</li>
            <li>Automated matching</li>
            <li>Reconciliation reports</li>
            <li>Multi-bank support</li>
        </ul>
    </div>
</div>

<?php endif; ?>

<style>
ul {
    color: #ccc;
}
.button {
    display: inline-block;
    background: #4CAF50;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 600;
}
.button:hover {
    background: #45a049;
}
</style>