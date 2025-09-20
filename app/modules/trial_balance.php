<?php
require_once 'lib/accounting.php';

$accounting = new AccountingCore($db);

$as_of_date = $_GET['date'] ?? date('Y-m-d');

try {
    $trial_balance = $accounting->getTrialBalance($as_of_date);
    
    // Calculate totals
    $total_debits = array_sum(array_column($trial_balance, 'total_debits'));
    $total_credits = array_sum(array_column($trial_balance, 'total_credits'));
    $is_balanced = abs($total_debits - $total_credits) < 0.01;
    
} catch (Exception $e) {
    $error = "Error loading trial balance: " . $e->getMessage();
}
?>

<div class="page-header">
    <h2>Trial Balance</h2>
    <div>
        <button onclick="printReport()" class="btn btn-secondary">Print</button>
        <button onclick="exportTableToCSV('#trial-balance-table', 'trial-balance.csv')" class="btn btn-secondary">Export CSV</button>
    </div>
</div>

<!-- Date Filter -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Filter by Date</h3>
    </div>
    
    <form method="get" class="form-row">
        <input type="hidden" name="module" value="trial_balance">
        <div class="form-group">
            <label for="date">As of Date:</label>
            <input type="date" id="date" name="date" class="form-control" value="<?= htmlspecialchars($as_of_date) ?>">
        </div>
        <div class="form-group" style="display: flex; align-items: end;">
            <button type="submit" class="btn btn-primary">Update</button>
        </div>
    </form>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php else: ?>

<!-- Trial Balance Summary -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Trial Balance Summary</h3>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= formatCurrency($total_debits) ?></div>
            <div class="stat-label">Total Debits</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= formatCurrency($total_credits) ?></div>
            <div class="stat-label">Total Credits</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: <?= $is_balanced ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                <?= $is_balanced ? '✓ BALANCED' : '⚠ NOT BALANCED' ?>
            </div>
            <div class="stat-label">Status</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= formatCurrency(abs($total_debits - $total_credits)) ?></div>
            <div class="stat-label">Difference</div>
        </div>
    </div>
</div>

<!-- Trial Balance Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Trial Balance as of <?= date('d/M/Y', strtotime($as_of_date)) ?></h3>
    </div>
    
    <div class="table-container">
        <table class="table" id="trial-balance-table">
            <thead>
                <tr>
                    <th>Account Code</th>
                    <th>Account Name</th>
                    <th>Type</th>
                    <th class="text-right">Debits</th>
                    <th class="text-right">Credits</th>
                    <th class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $subtotals = [
                    'ASSET' => ['debits' => 0, 'credits' => 0, 'balance' => 0],
                    'LIABILITY' => ['debits' => 0, 'credits' => 0, 'balance' => 0],
                    'EQUITY' => ['debits' => 0, 'credits' => 0, 'balance' => 0],
                    'INCOME' => ['debits' => 0, 'credits' => 0, 'balance' => 0],
                    'EXPENSE' => ['debits' => 0, 'credits' => 0, 'balance' => 0]
                ];
                
                $current_type = '';
                foreach ($trial_balance as $account): 
                    // Add subtotal row when account type changes
                    if ($current_type && $current_type !== $account['account_type']):
                ?>
                <tr class="subtotal-row" style="background-color: var(--bg-secondary); font-weight: bold;">
                    <td colspan="2"><?= $current_type ?> SUBTOTAL</td>
                    <td></td>
                    <td class="amount text-right"><?= formatCurrency($subtotals[$current_type]['debits']) ?></td>
                    <td class="amount text-right"><?= formatCurrency($subtotals[$current_type]['credits']) ?></td>
                    <td class="amount text-right"><?= formatCurrency(abs($subtotals[$current_type]['balance'])) ?></td>
                </tr>
                <?php 
                    endif;
                    $current_type = $account['account_type'];
                    
                    // Update subtotals
                    $subtotals[$current_type]['debits'] += $account['total_debits'];
                    $subtotals[$current_type]['credits'] += $account['total_credits'];
                    $subtotals[$current_type]['balance'] += $account['balance'];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($account['code']) ?></strong></td>
                    <td><?= htmlspecialchars($account['name']) ?></td>
                    <td>
                        <span class="<?= getAccountTypeClass($account['account_type']) ?>">
                            <?= $account['account_type'] ?>
                        </span>
                    </td>
                    <td class="amount text-right">
                        <?= $account['total_debits'] > 0 ? formatCurrency($account['total_debits']) : '-' ?>
                    </td>
                    <td class="amount text-right">
                        <?= $account['total_credits'] > 0 ? formatCurrency($account['total_credits']) : '-' ?>
                    </td>
                    <td class="amount text-right <?= $account['balance'] > 0 ? 'credit' : ($account['balance'] < 0 ? 'debit' : '') ?>">
                        <?= $account['balance'] != 0 ? formatCurrency(abs($account['balance'])) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <!-- Final subtotal for last group -->
                <?php if ($current_type): ?>
                <tr class="subtotal-row" style="background-color: var(--bg-secondary); font-weight: bold;">
                    <td colspan="2"><?= $current_type ?> SUBTOTAL</td>
                    <td></td>
                    <td class="amount text-right"><?= formatCurrency($subtotals[$current_type]['debits']) ?></td>
                    <td class="amount text-right"><?= formatCurrency($subtotals[$current_type]['credits']) ?></td>
                    <td class="amount text-right"><?= formatCurrency(abs($subtotals[$current_type]['balance'])) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: var(--bg-header); color: white; font-weight: bold;">
                    <td colspan="3">GRAND TOTAL</td>
                    <td class="amount text-right"><?= formatCurrency($total_debits) ?></td>
                    <td class="amount text-right"><?= formatCurrency($total_credits) ?></td>
                    <td class="amount text-right" style="color: <?= $is_balanced ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                        <?= formatCurrency(abs($total_debits - $total_credits)) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Account Type Summary -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Summary by Account Type</h3>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Account Type</th>
                    <th class="text-right">Total Debits</th>
                    <th class="text-right">Total Credits</th>
                    <th class="text-right">Net Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subtotals as $type => $totals): ?>
                    <?php if ($totals['debits'] > 0 || $totals['credits'] > 0): ?>
                    <tr>
                        <td>
                            <span class="<?= getAccountTypeClass($type) ?>">
                                <strong><?= $type ?></strong>
                            </span>
                        </td>
                        <td class="amount text-right"><?= formatCurrency($totals['debits']) ?></td>
                        <td class="amount text-right"><?= formatCurrency($totals['credits']) ?></td>
                        <td class="amount text-right <?= $totals['balance'] > 0 ? 'credit' : ($totals['balance'] < 0 ? 'debit' : '') ?>">
                            <?= formatCurrency(abs($totals['balance'])) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>