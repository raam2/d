<?php
/**
 * Ledger Module
 * View account ledgers and transactions
 */

require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/accounting.php';

$account_code = $_GET['account'] ?? '';
$from_date = $_GET['from'] ?? date('Y-m-01'); // First day of current month
$to_date = $_GET['to'] ?? date('Y-m-d');
$page = (int)($_GET['page'] ?? 1);
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get all active accounts for dropdown
$accounts = $db->query("SELECT code, name, account_type FROM accounts WHERE is_active = 1 ORDER BY code")->fetchAll();

$ledger_data = [];
$account_info = null;
$running_balance = 0;

if ($account_code) {
    // Get account information
    $stmt = $db->prepare("SELECT * FROM accounts WHERE code = ? AND is_active = 1");
    $stmt->execute([$account_code]);
    $account_info = $stmt->fetch();
    
    if ($account_info) {
        // Get opening balance (balance before from_date)
        $opening_balance = get_account_balance($account_code, date('Y-m-d', strtotime($from_date . ' -1 day')));
        
        // Get ledger entries
        $ledger_data = get_account_ledger($account_code, $from_date, $to_date, $per_page, $offset);
        
        // Calculate running balance
        $running_balance = $opening_balance;
        
        // Add running balance to each entry
        foreach ($ledger_data as &$entry) {
            $debit = $entry['debit_amount'];
            $credit = $entry['credit_amount'];
            
            // Update running balance based on account type
            if (in_array($account_info['account_type'], ['ASSET', 'EXPENSE'])) {
                $running_balance += $debit - $credit;
            } else {
                $running_balance += $credit - $debit;
            }
            
            $entry['running_balance'] = $running_balance;
        }
        
        // Get total count for pagination
        $count_sql = "SELECT COUNT(*) as count FROM journal_lines l 
                     JOIN journal_entries e ON l.entry_id = e.id 
                     WHERE l.account_code = ?";
        $count_params = [$account_code];
        
        if ($from_date) {
            $count_sql .= " AND e.entry_date >= ?";
            $count_params[] = $from_date;
        }
        
        if ($to_date) {
            $count_sql .= " AND e.entry_date <= ?";
            $count_params[] = $to_date;
        }
        
        $stmt = $db->prepare($count_sql);
        $stmt->execute($count_params);
        $total_entries = $stmt->fetch()['count'];
        $total_pages = ceil($total_entries / $per_page);
    }
}

?>

<h2>📊 Account Ledger</h2>

<div style="background: #2d2d2d; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
    <form method="GET">
        <input type="hidden" name="module" value="ledger">
        
        <div class="form-row">
            <label>
                Account *
                <select name="account" required onchange="this.form.submit()">
                    <option value="">Select Account</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= htmlspecialchars($account['code']) ?>" 
                                <?= $account_code === $account['code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($account['code']) ?> - <?= htmlspecialchars($account['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            
            <label>
                From Date
                <input type="date" name="from" value="<?= htmlspecialchars($from_date) ?>">
            </label>
            
            <label>
                To Date
                <input type="date" name="to" value="<?= htmlspecialchars($to_date) ?>">
            </label>
        </div>
        
        <button type="submit">View Ledger</button>
        <?php if ($account_code): ?>
            <a href="?module=chart_of_accounts" class="button" style="background: #666;">Back to COA</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($account_info): ?>
    <div class="summary-cards">
        <div class="card">
            <h4>Account Details</h4>
            <div class="value"><?= htmlspecialchars($account_info['code']) ?></div>
            <small><?= htmlspecialchars($account_info['name']) ?></small>
        </div>
        
        <div class="card">
            <h4>Account Type</h4>
            <div class="value"><?= $account_info['account_type'] ?></div>
            <small>Classification</small>
        </div>
        
        <div class="card">
            <h4>Opening Balance</h4>
            <div class="value">₹<?= number_format($opening_balance ?? 0, 2) ?></div>
            <small>As of <?= date('d/m/Y', strtotime($from_date . ' -1 day')) ?></small>
        </div>
        
        <div class="card">
            <h4>Current Balance</h4>
            <div class="value">₹<?= number_format(get_account_balance($account_code, $to_date), 2) ?></div>
            <small>As of <?= date('d/m/Y', strtotime($to_date)) ?></small>
        </div>
    </div>
    
    <?php if (empty($ledger_data)): ?>
        <div style="text-align: center; padding: 40px; color: #888;">
            <h3>No transactions found</h3>
            <p>No journal entries found for this account in the selected date range.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance</th>
                        <th>Entry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($opening_balance != 0): ?>
                        <tr style="background: #333; font-weight: bold;">
                            <td><?= date('d/m/Y', strtotime($from_date)) ?></td>
                            <td>-</td>
                            <td>Opening Balance</td>
                            <td>-</td>
                            <td>-</td>
                            <td class="text-right">₹<?= number_format($opening_balance, 2) ?></td>
                            <td>-</td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php foreach (array_reverse($ledger_data) as $entry): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($entry['entry_date'])) ?></td>
                            <td><?= htmlspecialchars($entry['reference'] ?: '-') ?></td>
                            <td>
                                <?= htmlspecialchars($entry['entry_description']) ?>
                                <?php if ($entry['line_description']): ?>
                                    <br><small style="color: #888;"><?= htmlspecialchars($entry['line_description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?= $entry['debit_amount'] > 0 ? '₹' . number_format($entry['debit_amount'], 2) : '-' ?>
                            </td>
                            <td class="text-right">
                                <?= $entry['credit_amount'] > 0 ? '₹' . number_format($entry['credit_amount'], 2) : '-' ?>
                            </td>
                            <td class="text-right">
                                ₹<?= number_format($entry['running_balance'], 2) ?>
                            </td>
                            <td>
                                <a href="?module=journal_view&id=<?= $entry['entry_id'] ?>" 
                                   style="color: #4CAF50; text-decoration: none; font-size: 12px;">
                                    #<?= $entry['entry_id'] ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div style="margin-top: 20px; text-align: center;">
                <div style="display: inline-flex; gap: 10px; align-items: center;">
                    <?php if ($page > 1): ?>
                        <a href="?module=ledger&account=<?= urlencode($account_code) ?>&from=<?= $from_date ?>&to=<?= $to_date ?>&page=<?= $page - 1 ?>" 
                           class="button" style="background: #666;">← Previous</a>
                    <?php endif; ?>
                    
                    <span style="color: #ccc;">
                        Page <?= $page ?> of <?= $total_pages ?> (<?= number_format($total_entries) ?> entries)
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?module=ledger&account=<?= urlencode($account_code) ?>&from=<?= $from_date ?>&to=<?= $to_date ?>&page=<?= $page + 1 ?>" 
                           class="button" style="background: #666;">Next →</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
<?php elseif ($account_code): ?>
    <div class="err">
        Account not found or inactive.
    </div>
<?php else: ?>
    <div style="text-align: center; padding: 40px; color: #888;">
        <h3>Select an Account</h3>
        <p>Choose an account from the dropdown above to view its ledger.</p>
    </div>
<?php endif; ?>

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