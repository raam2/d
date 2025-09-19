<?php
/**
 * Trial Balance Module
 * Generate trial balance report
 */

require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/accounting.php';

$as_of_date = $_GET['as_of'] ?? date('Y-m-d');
$show_zero_balances = isset($_GET['show_zero']);

// Get trial balance
$trial_balance = get_trial_balance($as_of_date);

// Group accounts by type
$accounts_by_type = [];
foreach ($trial_balance['accounts'] as $account) {
    $accounts_by_type[$account['type']][] = $account;
}

// Calculate totals by type
$type_totals = [];
foreach ($accounts_by_type as $type => $accounts) {
    $type_totals[$type] = [
        'debit' => array_sum(array_column($accounts, 'debit')),
        'credit' => array_sum(array_column($accounts, 'credit'))
    ];
}

?>

<h2>⚖️ Trial Balance</h2>

<div style="background: #2d2d2d; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: end;">
        <input type="hidden" name="module" value="trial_balance">
        
        <label>
            As of Date
            <input type="date" name="as_of" value="<?= htmlspecialchars($as_of_date) ?>">
        </label>
        
        <label style="flex-direction: row; align-items: center; gap: 8px;">
            <input type="checkbox" name="show_zero" <?= $show_zero_balances ? 'checked' : '' ?>>
            Show zero balances
        </label>
        
        <button type="submit">Generate Report</button>
        <a href="?module=trial_balance&as_of=<?= date('Y-m-d') ?>" class="button" style="background: #666;">Today</a>
    </form>
</div>

<div class="summary-cards">
    <div class="card">
        <h4>Report Date</h4>
        <div class="value"><?= date('d M Y', strtotime($as_of_date)) ?></div>
        <small>As of date</small>
    </div>
    
    <div class="card">
        <h4>Total Debits</h4>
        <div class="value">₹<?= number_format($trial_balance['total_debits'], 2) ?></div>
        <small>Sum of all debit balances</small>
    </div>
    
    <div class="card">
        <h4>Total Credits</h4>
        <div class="value">₹<?= number_format($trial_balance['total_credits'], 2) ?></div>
        <small>Sum of all credit balances</small>
    </div>
    
    <div class="card">
        <h4>Balance Status</h4>
        <div class="value" style="color: <?= $trial_balance['is_balanced'] ? '#4CAF50' : '#f44336' ?>;">
            <?= $trial_balance['is_balanced'] ? '✓ Balanced' : '✗ Unbalanced' ?>
        </div>
        <small>Trial balance validation</small>
    </div>
</div>

<?php if (!$trial_balance['is_balanced']): ?>
    <div class="err">
        <strong>⚠️ Trial Balance is Not Balanced!</strong><br>
        Difference: ₹<?= number_format(abs($trial_balance['total_debits'] - $trial_balance['total_credits']), 2) ?><br>
        Please check your journal entries for errors.
    </div>
<?php endif; ?>

<div style="overflow-x: auto;">
    <table>
        <thead>
            <tr>
                <th>Account Code</th>
                <th>Account Name</th>
                <th>Type</th>
                <th class="text-right">Debit Balance</th>
                <th class="text-right">Credit Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $type_colors = [
                'ASSET' => '#4CAF50',
                'LIABILITY' => '#f44336',
                'EQUITY' => '#2196F3', 
                'INCOME' => '#FF9800',
                'EXPENSE' => '#9C27B0'
            ];
            
            foreach (['ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE'] as $type):
                if (!isset($accounts_by_type[$type])) continue;
                $accounts = $accounts_by_type[$type];
            ?>
                <!-- Type Header -->
                <tr style="background: #333; font-weight: bold;">
                    <td colspan="3" style="color: <?= $type_colors[$type] ?>;">
                        <?= $type ?> ACCOUNTS (<?= count($accounts) ?>)
                    </td>
                    <td class="text-right" style="color: <?= $type_colors[$type] ?>;">
                        ₹<?= number_format($type_totals[$type]['debit'], 2) ?>
                    </td>
                    <td class="text-right" style="color: <?= $type_colors[$type] ?>;">
                        ₹<?= number_format($type_totals[$type]['credit'], 2) ?>
                    </td>
                </tr>
                
                <!-- Account Lines -->
                <?php foreach ($accounts as $account): ?>
                    <?php if (!$show_zero_balances && $account['debit'] == 0 && $account['credit'] == 0) continue; ?>
                    <tr>
                        <td>
                            <a href="?module=ledger&account=<?= urlencode($account['code']) ?>&to=<?= $as_of_date ?>" 
                               style="color: #4CAF50; text-decoration: none;">
                                <?= htmlspecialchars($account['code']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($account['name']) ?></td>
                        <td style="font-size: 12px; color: #888;"><?= $account['type'] ?></td>
                        <td class="text-right">
                            <?= $account['debit'] > 0 ? '₹' . number_format($account['debit'], 2) : '-' ?>
                        </td>
                        <td class="text-right">
                            <?= $account['credit'] > 0 ? '₹' . number_format($account['credit'], 2) : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Spacing between types -->
                <tr><td colspan="5" style="height: 10px; border: none;"></td></tr>
            <?php endforeach; ?>
            
            <!-- Grand Total -->
            <tr style="background: #444; font-weight: bold; font-size: 16px;">
                <td colspan="3">GRAND TOTAL</td>
                <td class="text-right">₹<?= number_format($trial_balance['total_debits'], 2) ?></td>
                <td class="text-right">₹<?= number_format($trial_balance['total_credits'], 2) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<?php if (empty($trial_balance['accounts'])): ?>
    <div style="text-align: center; padding: 40px; color: #888;">
        <h3>No Data Available</h3>
        <p>No account balances found. This could mean:</p>
        <ul style="list-style: none; color: #aaa;">
            <li>• No journal entries have been posted yet</li>
            <li>• All account balances are zero</li>
            <li>• The selected date is before any transactions</li>
        </ul>
        <p><a href="?module=journal_new" class="button">Create Journal Entry</a></p>
    </div>
<?php endif; ?>

<div style="margin-top: 30px; background: #2d2d2d; padding: 15px; border-radius: 6px;">
    <h3>📋 How to Use Trial Balance</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
        <div>
            <h4 style="color: #4CAF50; margin-bottom: 8px;">✓ Balanced Trial Balance</h4>
            <p style="font-size: 14px; color: #ccc;">
                When total debits equal total credits, your books are mathematically correct. 
                This is the foundation of double-entry bookkeeping.
            </p>
        </div>
        
        <div>
            <h4 style="color: #f44336; margin-bottom: 8px;">✗ Unbalanced Trial Balance</h4>
            <p style="font-size: 14px; color: #ccc;">
                If debits don't equal credits, there's an error in your journal entries. 
                Check for missing entries, wrong amounts, or data entry mistakes.
            </p>
        </div>
        
        <div>
            <h4 style="color: #FF9800; margin-bottom: 8px;">📊 Account Types</h4>
            <p style="font-size: 14px; color: #ccc;">
                Assets & Expenses normally have debit balances. 
                Liabilities, Equity & Income normally have credit balances.
            </p>
        </div>
    </div>
</div>

<style>
.button {
    display: inline-block;
    background: #4CAF50;
    color: white;
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
}
.button:hover {
    background: #45a049;
}
</style>