<?php
require_once 'lib/accounting.php';

$accounting = new AccountingCore($db);

$account_code = $_GET['account'] ?? '';
$from_date = $_GET['from'] ?? date('Y-m-01'); // First day of current month
$to_date = $_GET['to'] ?? date('Y-m-d'); // Today

$account_info = null;
$ledger_data = [];
$error = '';

if ($account_code) {
    try {
        // Get account info
        $stmt = $db->prepare("SELECT * FROM accounts WHERE code = ?");
        $stmt->execute([$account_code]);
        $account_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account_info) {
            $error = "Account not found";
        } else {
            // Get ledger data
            $ledger_data = $accounting->getAccountLedger($account_code, $from_date, $to_date);
            
            // Calculate opening balance
            $opening_balance = $accounting->getAccountBalance($account_code, date('Y-m-d', strtotime($from_date . ' -1 day')));
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all accounts for selection
$accounts_sql = "SELECT code, name, account_type FROM accounts WHERE is_active = 1 ORDER BY code";
$stmt = $db->prepare($accounts_sql);
$stmt->execute();
$all_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>Account Ledger</h2>
    <div>
        <?php if ($account_info): ?>
            <button onclick="printReport()" class="btn btn-secondary">Print</button>
            <button onclick="exportTableToCSV('#ledger-table', 'ledger-<?= $account_code ?>.csv')" class="btn btn-secondary">Export CSV</button>
        <?php endif; ?>
    </div>
</div>

<!-- Account & Date Selection -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Select Account & Date Range</h3>
    </div>
    
    <form method="get">
        <input type="hidden" name="module" value="ledger">
        
        <div class="form-row">
            <div class="form-group">
                <label for="account">Account:</label>
                <select id="account" name="account" class="form-control" required onchange="this.form.submit()">
                    <option value="">Select Account</option>
                    <?php foreach ($all_accounts as $account): ?>
                        <option value="<?= $account['code'] ?>" <?= $account['code'] === $account_code ? 'selected' : '' ?>>
                            <?= $account['code'] ?> - <?= htmlspecialchars($account['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
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
        </div>
    </form>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php elseif ($account_info): ?>

<!-- Account Information -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Account Information</h3>
    </div>
    
    <div class="form-row">
        <div class="form-group">
            <label>Account Code:</label>
            <div class="form-control" readonly><strong><?= htmlspecialchars($account_info['code']) ?></strong></div>
        </div>
        
        <div class="form-group">
            <label>Account Name:</label>
            <div class="form-control" readonly><?= htmlspecialchars($account_info['name']) ?></div>
        </div>
        
        <div class="form-group">
            <label>Account Type:</label>
            <div class="form-control" readonly>
                <span class="<?= getAccountTypeClass($account_info['account_type']) ?>">
                    <?= $account_info['account_type'] ?>
                </span>
            </div>
        </div>
        
        <div class="form-group">
            <label>Current Balance:</label>
            <div class="form-control amount" readonly>
                <?= formatCurrency($accounting->getAccountBalance($account_code)) ?>
            </div>
        </div>
    </div>
</div>

<!-- Ledger Summary -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Ledger Summary</h3>
    </div>
    
    <?php
    $period_debits = array_sum(array_column($ledger_data, 'debit_amount'));
    $period_credits = array_sum(array_column($ledger_data, 'credit_amount'));
    $closing_balance = $accounting->getAccountBalance($account_code, $to_date);
    ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= formatCurrency($opening_balance ?? 0) ?></div>
            <div class="stat-label">Opening Balance</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= formatCurrency($period_debits) ?></div>
            <div class="stat-label">Period Debits</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= formatCurrency($period_credits) ?></div>
            <div class="stat-label">Period Credits</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= formatCurrency($closing_balance) ?></div>
            <div class="stat-label">Closing Balance</div>
        </div>
    </div>
</div>

<!-- Ledger Details -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            Ledger Details - <?= htmlspecialchars($account_info['name']) ?> 
            (<?= date('d/M/Y', strtotime($from_date)) ?> to <?= date('d/M/Y', strtotime($to_date)) ?>)
        </h3>
    </div>
    
    <?php if (empty($ledger_data)): ?>
        <p class="text-muted">No transactions found for the selected period.</p>
    <?php else: ?>
        <div class="table-container">
            <table class="table" id="ledger-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Reference</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                        <th class="text-right">Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                        <td><?= date('d/M/Y', strtotime($from_date . ' -1 day')) ?></td>
                        <td colspan="2">Opening Balance</td>
                        <td class="text-right">-</td>
                        <td class="text-right">-</td>
                        <td class="amount text-right balance"><?= formatCurrency($opening_balance ?? 0) ?></td>
                    </tr>
                    
                    <?php 
                    $running_balance = $opening_balance ?? 0;
                    foreach ($ledger_data as $transaction): 
                        // Calculate running balance based on account type
                        if (in_array($account_info['account_type'], ['ASSET', 'EXPENSE'])) {
                            $running_balance += $transaction['debit_amount'] - $transaction['credit_amount'];
                        } else {
                            $running_balance += $transaction['credit_amount'] - $transaction['debit_amount'];
                        }
                    ?>
                    <tr>
                        <td><?= date('d/M/Y', strtotime($transaction['entry_date'])) ?></td>
                        <td>
                            <?= htmlspecialchars($transaction['description']) ?>
                            <?php if ($transaction['line_description'] && $transaction['line_description'] !== $transaction['description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($transaction['line_description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($transaction['reference'] ?? '-') ?></td>
                        <td class="amount text-right">
                            <?= $transaction['debit_amount'] > 0 ? formatCurrency($transaction['debit_amount']) : '-' ?>
                        </td>
                        <td class="amount text-right">
                            <?= $transaction['credit_amount'] > 0 ? formatCurrency($transaction['credit_amount']) : '-' ?>
                        </td>
                        <td class="amount text-right balance">
                            <?= formatCurrency($running_balance) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Closing Balance Row -->
                    <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                        <td><?= date('d/M/Y', strtotime($to_date)) ?></td>
                        <td colspan="2">Closing Balance</td>
                        <td class="amount text-right"><?= formatCurrency($period_debits) ?></td>
                        <td class="amount text-right"><?= formatCurrency($period_credits) ?></td>
                        <td class="amount text-right balance"><?= formatCurrency($closing_balance) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php elseif (!$account_code): ?>
    <div class="alert alert-warning">
        Please select an account to view its ledger.
    </div>
<?php endif; ?>