<?php
require_once 'lib/accounting.php';

$accounting = new AccountingCore($db);

$as_of_date = $_GET['date'] ?? date('Y-m-d');

try {
    $balance_sheet_data = $accounting->getBalanceSheet($as_of_date);
    
    // Separate by account type
    $assets = array_filter($balance_sheet_data, function($account) {
        return $account['account_type'] === 'ASSET';
    });
    
    $liabilities = array_filter($balance_sheet_data, function($account) {
        return $account['account_type'] === 'LIABILITY';
    });
    
    $equity = array_filter($balance_sheet_data, function($account) {
        return $account['account_type'] === 'EQUITY';
    });
    
    // Calculate totals
    $total_assets = array_sum(array_column($assets, 'balance'));
    $total_liabilities = array_sum(array_column($liabilities, 'balance'));
    $total_equity = array_sum(array_column($equity, 'balance'));
    
    // Calculate net income for the period (this would be moved to equity)
    $from_date = date('Y-01-01'); // Year start
    $pl_data = $accounting->getProfitLossStatement($from_date, $as_of_date);
    $income_accounts = array_filter($pl_data, function($account) {
        return $account['account_type'] === 'INCOME';
    });
    $expense_accounts = array_filter($pl_data, function($account) {
        return $account['account_type'] === 'EXPENSE';
    });
    $net_income = array_sum(array_column($income_accounts, 'amount')) - array_sum(array_column($expense_accounts, 'amount'));
    
    $total_equity_with_income = $total_equity + $net_income;
    $balance_check = abs($total_assets - ($total_liabilities + $total_equity_with_income));
    $is_balanced = $balance_check < 0.01;
    
} catch (Exception $e) {
    $error = "Error loading balance sheet: " . $e->getMessage();
}
?>

<div class="page-header">
    <h2>Balance Sheet</h2>
    <div>
        <button onclick="printReport()" class="btn btn-secondary">Print</button>
        <button onclick="exportTableToCSV('#balance-sheet-table', 'balance-sheet.csv')" class="btn btn-secondary">Export CSV</button>
    </div>
</div>

<!-- Date Filter -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Select Date</h3>
    </div>
    
    <form method="get" class="form-row">
        <input type="hidden" name="module" value="balance_sheet">
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

<!-- Balance Sheet Summary -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Balance Sheet Summary</h3>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value account-asset"><?= formatCurrency($total_assets) ?></div>
            <div class="stat-label">Total Assets</div>
        </div>
        <div class="stat-card">
            <div class="stat-value account-liability"><?= formatCurrency($total_liabilities) ?></div>
            <div class="stat-label">Total Liabilities</div>
        </div>
        <div class="stat-card">
            <div class="stat-value account-equity"><?= formatCurrency($total_equity_with_income) ?></div>
            <div class="stat-label">Total Equity</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: <?= $is_balanced ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                <?= $is_balanced ? '✓ BALANCED' : '⚠ NOT BALANCED' ?>
            </div>
            <div class="stat-label">Status</div>
        </div>
    </div>
    
    <?php if (!$is_balanced): ?>
        <div class="alert alert-warning">
            <strong>Balance Sheet is not balanced!</strong> 
            Difference: <?= formatCurrency($balance_check) ?>
        </div>
    <?php endif; ?>
</div>

<!-- Detailed Balance Sheet -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Balance Sheet as of <?= date('d/M/Y', strtotime($as_of_date)) ?></h3>
    </div>
    
    <div class="table-container">
        <table class="table" id="balance-sheet-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <!-- ASSETS SECTION -->
                <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                    <td colspan="2" class="account-asset">ASSETS</td>
                </tr>
                
                <?php if (empty($assets)): ?>
                    <tr>
                        <td colspan="2" class="text-muted">No asset accounts found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assets as $account): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($account['code']) ?></strong> - 
                            <?= htmlspecialchars($account['name']) ?>
                        </td>
                        <td class="amount text-right">
                            <?= formatCurrency(abs($account['balance'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                        <td>TOTAL ASSETS</td>
                        <td class="amount text-right account-asset">
                            <?= formatCurrency($total_assets) ?>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <!-- LIABILITIES SECTION -->
                <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                    <td colspan="2" class="account-liability">LIABILITIES</td>
                </tr>
                
                <?php if (empty($liabilities)): ?>
                    <tr>
                        <td colspan="2" class="text-muted">No liability accounts found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($liabilities as $account): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($account['code']) ?></strong> - 
                            <?= htmlspecialchars($account['name']) ?>
                        </td>
                        <td class="amount text-right">
                            <?= formatCurrency(abs($account['balance'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                        <td>TOTAL LIABILITIES</td>
                        <td class="amount text-right account-liability">
                            <?= formatCurrency($total_liabilities) ?>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <!-- EQUITY SECTION -->
                <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                    <td colspan="2" class="account-equity">EQUITY</td>
                </tr>
                
                <?php foreach ($equity as $account): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($account['code']) ?></strong> - 
                        <?= htmlspecialchars($account['name']) ?>
                    </td>
                    <td class="amount text-right">
                        <?= formatCurrency(abs($account['balance'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <!-- Net Income for the period -->
                <?php if (abs($net_income) > 0.01): ?>
                <tr>
                    <td>Net Income (Year to Date)</td>
                    <td class="amount text-right" style="color: <?= $net_income >= 0 ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                        <?= formatCurrency(abs($net_income)) ?>
                    </td>
                </tr>
                <?php endif; ?>
                
                <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                    <td>TOTAL EQUITY</td>
                    <td class="amount text-right account-equity">
                        <?= formatCurrency($total_equity_with_income) ?>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background-color: var(--bg-header); color: white; font-weight: bold;">
                    <td>TOTAL LIABILITIES & EQUITY</td>
                    <td class="amount text-right">
                        <?= formatCurrency($total_liabilities + $total_equity_with_income) ?>
                    </td>
                </tr>
                <tr style="background-color: var(--bg-header); color: white; font-weight: bold;">
                    <td>BALANCE CHECK (Assets - Liab. & Equity)</td>
                    <td class="amount text-right" style="color: <?= $is_balanced ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                        <?= formatCurrency($balance_check) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Accounting Equation -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Accounting Equation</h3>
    </div>
    
    <div style="text-align: center; font-size: 1.25rem; padding: 1rem;">
        <span class="account-asset"><?= formatCurrency($total_assets) ?></span>
        <span style="margin: 0 1rem;">=</span>
        <span class="account-liability"><?= formatCurrency($total_liabilities) ?></span>
        <span style="margin: 0 0.5rem;">+</span>
        <span class="account-equity"><?= formatCurrency($total_equity_with_income) ?></span>
    </div>
    
    <div style="text-align: center; font-size: 0.875rem; color: var(--text-muted);">
        Assets = Liabilities + Equity
    </div>
</div>

<!-- Quick Date Selection -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Quick Date Selection</h3>
    </div>
    
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <a href="?module=balance_sheet&date=<?= date('Y-m-d') ?>" class="btn btn-secondary">Today</a>
        <a href="?module=balance_sheet&date=<?= date('Y-m-t') ?>" class="btn btn-secondary">End of Month</a>
        <a href="?module=balance_sheet&date=<?= date('Y-m-t', strtotime('-1 month')) ?>" class="btn btn-secondary">End of Last Month</a>
        <a href="?module=balance_sheet&date=<?= date('Y-12-31', strtotime('-1 year')) ?>" class="btn btn-secondary">End of Last Year</a>
    </div>
</div>

<?php endif; ?>