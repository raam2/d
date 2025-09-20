<?php
require_once 'lib/accounting.php';

$accounting = new AccountingCore($db);

$from_date = $_GET['from'] ?? date('Y-m-01'); // First day of current month
$to_date = $_GET['to'] ?? date('Y-m-d'); // Today

try {
    $pl_data = $accounting->getProfitLossStatement($from_date, $to_date);
    
    // Separate income and expenses
    $income_accounts = array_filter($pl_data, function($account) {
        return $account['account_type'] === 'INCOME';
    });
    
    $expense_accounts = array_filter($pl_data, function($account) {
        return $account['account_type'] === 'EXPENSE';
    });
    
    $total_income = array_sum(array_column($income_accounts, 'amount'));
    $total_expenses = array_sum(array_column($expense_accounts, 'amount'));
    $net_profit = $total_income - $total_expenses;
    
} catch (Exception $e) {
    $error = "Error loading P&L statement: " . $e->getMessage();
}
?>

<div class="page-header">
    <h2>Profit & Loss Statement</h2>
    <div>
        <button onclick="printReport()" class="btn btn-secondary">Print</button>
        <button onclick="exportTableToCSV('#pl-table', 'profit-loss.csv')" class="btn btn-secondary">Export CSV</button>
    </div>
</div>

<!-- Date Range Filter -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Select Period</h3>
    </div>
    
    <form method="get" class="form-row">
        <input type="hidden" name="module" value="profit_loss">
        <div class="form-group">
            <label for="from">From Date:</label>
            <input type="date" id="from" name="from" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
        </div>
        <div class="form-group">
            <label for="to">To Date:</label>
            <input type="date" id="to" name="to" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
        </div>
        <div class="form-group" style="display: flex; align-items: end;">
            <button type="submit" class="btn btn-primary">Update</button>
        </div>
    </form>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php else: ?>

<!-- P&L Summary -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Summary</h3>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value account-income"><?= formatCurrency($total_income) ?></div>
            <div class="stat-label">Total Income</div>
        </div>
        <div class="stat-card">
            <div class="stat-value account-expense"><?= formatCurrency($total_expenses) ?></div>
            <div class="stat-label">Total Expenses</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: <?= $net_profit >= 0 ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                <?= formatCurrency(abs($net_profit)) ?>
            </div>
            <div class="stat-label"><?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></div>
        </div>
    </div>
</div>

<!-- Detailed P&L Statement -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            Profit & Loss Statement for period 
            <?= date('d/M/Y', strtotime($from_date)) ?> to <?= date('d/M/Y', strtotime($to_date)) ?>
        </h3>
    </div>
    
    <div class="table-container">
        <table class="table" id="pl-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <!-- INCOME SECTION -->
                <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                    <td colspan="2" class="account-income">INCOME</td>
                </tr>
                
                <?php if (empty($income_accounts)): ?>
                    <tr>
                        <td colspan="2" class="text-muted">No income accounts found for this period</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($income_accounts as $account): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($account['code']) ?></strong> - 
                            <?= htmlspecialchars($account['name']) ?>
                        </td>
                        <td class="amount text-right account-income">
                            <?= formatCurrency($account['amount']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                        <td>TOTAL INCOME</td>
                        <td class="amount text-right account-income">
                            <?= formatCurrency($total_income) ?>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <!-- EXPENSES SECTION -->
                <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                    <td colspan="2" class="account-expense">EXPENSES</td>
                </tr>
                
                <?php if (empty($expense_accounts)): ?>
                    <tr>
                        <td colspan="2" class="text-muted">No expense accounts found for this period</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expense_accounts as $account): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($account['code']) ?></strong> - 
                            <?= htmlspecialchars($account['name']) ?>
                        </td>
                        <td class="amount text-right account-expense">
                            <?= formatCurrency($account['amount']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                        <td>TOTAL EXPENSES</td>
                        <td class="amount text-right account-expense">
                            <?= formatCurrency($total_expenses) ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: var(--bg-header); color: white; font-weight: bold;">
                    <td><?= $net_profit >= 0 ? 'NET PROFIT' : 'NET LOSS' ?></td>
                    <td class="amount text-right" style="color: <?= $net_profit >= 0 ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                        <?= formatCurrency(abs($net_profit)) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Quick Period Selection -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Quick Period Selection</h3>
    </div>
    
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <a href="?module=profit_loss&from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-secondary">This Month</a>
        <a href="?module=profit_loss&from=<?= date('Y-m-01', strtotime('-1 month')) ?>&to=<?= date('Y-m-t', strtotime('-1 month')) ?>" class="btn btn-secondary">Last Month</a>
        <a href="?module=profit_loss&from=<?= date('Y-01-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-secondary">Year to Date</a>
        <a href="?module=profit_loss&from=<?= date('Y-01-01', strtotime('-1 year')) ?>&to=<?= date('Y-12-31', strtotime('-1 year')) ?>" class="btn btn-secondary">Last Year</a>
    </div>
</div>

<?php endif; ?>