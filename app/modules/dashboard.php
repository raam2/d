<?php
require_once 'lib/accounting.php';

$accounting = new AccountingCore($db);

// Get dashboard statistics
try {
    $trial_balance = $accounting->getTrialBalance();
    
    // Calculate totals by account type
    $stats = [
        'ASSET' => 0,
        'LIABILITY' => 0,
        'EQUITY' => 0,
        'INCOME' => 0,
        'EXPENSE' => 0
    ];
    
    foreach ($trial_balance as $account) {
        $stats[$account['account_type']] += $account['balance'];
    }
    
    // Get recent journal entries
    $recent_sql = "SELECT je.*, COUNT(jl.id) as line_count
                   FROM journal_entries je
                   LEFT JOIN journal_lines jl ON je.id = jl.entry_id
                   GROUP BY je.id
                   ORDER BY je.created_at DESC
                   LIMIT 10";
    $stmt = $db->prepare($recent_sql);
    $stmt->execute();
    $recent_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading dashboard: " . $e->getMessage();
}
?>

<div class="page-header">
    <h2>Dashboard</h2>
    <span class="text-muted">GST Accounting System Overview</span>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<!-- Financial Overview -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value account-asset"><?= formatCurrency($stats['ASSET']) ?></div>
        <div class="stat-label">Total Assets</div>
    </div>
    <div class="stat-card">
        <div class="stat-value account-liability"><?= formatCurrency($stats['LIABILITY']) ?></div>
        <div class="stat-label">Total Liabilities</div>
    </div>
    <div class="stat-card">
        <div class="stat-value account-equity"><?= formatCurrency($stats['EQUITY']) ?></div>
        <div class="stat-label">Total Equity</div>
    </div>
    <div class="stat-card">
        <div class="stat-value account-income"><?= formatCurrency($stats['INCOME']) ?></div>
        <div class="stat-label">Total Income</div>
    </div>
    <div class="stat-card">
        <div class="stat-value account-expense"><?= formatCurrency($stats['EXPENSE']) ?></div>
        <div class="stat-label">Total Expenses</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= formatCurrency($stats['INCOME'] - $stats['EXPENSE']) ?></div>
        <div class="stat-label">Net Profit/Loss</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Quick Actions</h3>
    </div>
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <a href="?module=journal&action=new" class="btn btn-primary">New Journal Entry</a>
        <a href="?module=accounts&action=new" class="btn btn-secondary">Add Account</a>
        <a href="?module=trial_balance" class="btn btn-secondary">View Trial Balance</a>
        <a href="?module=profit_loss" class="btn btn-secondary">P&L Statement</a>
        <a href="?module=balance_sheet" class="btn btn-secondary">Balance Sheet</a>
    </div>
</div>

<!-- Recent Journal Entries -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Journal Entries</h3>
    </div>
    
    <?php if (empty($recent_entries)): ?>
        <p class="text-muted">No journal entries found. <a href="?module=journal&action=new">Create your first journal entry</a></p>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Reference</th>
                        <th>Lines</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_entries as $entry): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($entry['entry_date'])) ?></td>
                        <td><?= htmlspecialchars($entry['description']) ?></td>
                        <td><?= htmlspecialchars($entry['reference_no'] ?? '') ?></td>
                        <td><?= $entry['line_count'] ?> lines</td>
                        <td>
                            <a href="?module=journal&action=view&id=<?= $entry['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- System Health -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">System Status</h3>
    </div>
    
    <?php
    // Check if trial balance is balanced
    $total_debits = array_sum(array_column($trial_balance, 'total_debits'));
    $total_credits = array_sum(array_column($trial_balance, 'total_credits'));
    $is_balanced = abs($total_debits - $total_credits) < 0.01;
    ?>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <div>
            <strong>Trial Balance Status:</strong><br>
            <?php if ($is_balanced): ?>
                <span style="color: var(--accent-green);">✓ Balanced</span>
            <?php else: ?>
                <span style="color: var(--accent-red);">⚠ Not Balanced</span>
            <?php endif; ?>
        </div>
        
        <div>
            <strong>Total Debits:</strong><br>
            <span class="amount"><?= formatCurrency($total_debits) ?></span>
        </div>
        
        <div>
            <strong>Total Credits:</strong><br>
            <span class="amount"><?= formatCurrency($total_credits) ?></span>
        </div>
        
        <div>
            <strong>Active Accounts:</strong><br>
            <?php
            $active_accounts_sql = "SELECT COUNT(*) as count FROM accounts WHERE is_active = 1";
            $stmt = $db->prepare($active_accounts_sql);
            $stmt->execute();
            $active_count = $stmt->fetch()['count'];
            echo $active_count;
            ?>
        </div>
    </div>
</div>