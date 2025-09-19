<?php
/**
 * Profit & Loss Statement Module
 * Generate P&L statement for specified period
 */

require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/accounting.php';

$from_date = $_GET['from'] ?? date('Y-04-01'); // Default to financial year start (April 1)
$to_date = $_GET['to'] ?? date('Y-m-d');

// Get income and expense accounts with balances
$sql = "SELECT 
            a.code,
            a.name,
            a.account_type,
            a.parent_code,
            COALESCE(SUM(l.debit_amount), 0) as total_debit,
            COALESCE(SUM(l.credit_amount), 0) as total_credit
        FROM accounts a
        LEFT JOIN journal_lines l ON a.code = l.account_code
        LEFT JOIN journal_entries e ON l.entry_id = e.id
        WHERE a.account_type IN ('INCOME', 'EXPENSE') 
        AND a.is_active = 1";

$params = [];

if ($from_date && $to_date) {
    $sql .= " AND e.entry_date BETWEEN ? AND ?";
    $params = [$from_date, $to_date];
}

$sql .= " GROUP BY a.code, a.name, a.account_type, a.parent_code
          HAVING (total_debit != 0 OR total_credit != 0)
          ORDER BY a.account_type DESC, a.code";

$stmt = $db->prepare($sql);
$stmt->execute($params);

$accounts = [];
$total_income = 0;
$total_expenses = 0;

while ($row = $stmt->fetch()) {
    $debit = $row['total_debit'];
    $credit = $row['total_credit'];
    $type = $row['account_type'];
    
    // Calculate balance (Income: credit-debit, Expense: debit-credit)
    if ($type === 'INCOME') {
        $balance = $credit - $debit;
        $total_income += $balance;
    } else {
        $balance = $debit - $credit;
        $total_expenses += $balance;
    }
    
    $accounts[$type][] = [
        'code' => $row['code'],
        'name' => $row['name'],
        'parent_code' => $row['parent_code'],
        'balance' => $balance
    ];
}

$net_profit = $total_income - $total_expenses;

// Get comparative data (previous year same period)
$prev_from = date('Y-m-d', strtotime($from_date . ' -1 year'));
$prev_to = date('Y-m-d', strtotime($to_date . ' -1 year'));

$prev_sql = str_replace('AND e.entry_date BETWEEN ? AND ?', 'AND e.entry_date BETWEEN ? AND ?', $sql);
$prev_stmt = $db->prepare($prev_sql);
$prev_stmt->execute([$prev_from, $prev_to]);

$prev_total_income = 0;
$prev_total_expenses = 0;

while ($row = $prev_stmt->fetch()) {
    $debit = $row['total_debit'];
    $credit = $row['total_credit'];
    $type = $row['account_type'];
    
    if ($type === 'INCOME') {
        $balance = $credit - $debit;
        $prev_total_income += $balance;
    } else {
        $balance = $debit - $credit;
        $prev_total_expenses += $balance;
    }
}

$prev_net_profit = $prev_total_income - $prev_total_expenses;

?>

<h2>📈 Profit & Loss Statement</h2>

<div style="background: #2d2d2d; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: end;">
        <input type="hidden" name="module" value="pl">
        
        <label>
            From Date
            <input type="date" name="from" value="<?= htmlspecialchars($from_date) ?>">
        </label>
        
        <label>
            To Date
            <input type="date" name="to" value="<?= htmlspecialchars($to_date) ?>">
        </label>
        
        <button type="submit">Generate Report</button>
        
        <div style="display: flex; gap: 5px;">
            <a href="?module=pl&from=<?= date('Y-04-01') ?>&to=<?= date('Y-03-31', strtotime('+1 year')) ?>" 
               class="button" style="background: #666; font-size: 12px;">Current FY</a>
            <a href="?module=pl&from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" 
               class="button" style="background: #666; font-size: 12px;">This Month</a>
            <a href="?module=pl&from=<?= date('Y-01-01') ?>&to=<?= date('Y-12-31') ?>" 
               class="button" style="background: #666; font-size: 12px;">Calendar Year</a>
        </div>
    </form>
</div>

<div style="text-align: center; margin-bottom: 20px;">
    <h3 style="color: #4CAF50; margin: 0;">Profit & Loss Statement</h3>
    <p style="color: #ccc; margin: 5px 0;">
        For the period from <?= date('d M Y', strtotime($from_date)) ?> to <?= date('d M Y', strtotime($to_date)) ?>
    </p>
</div>

<div class="summary-cards">
    <div class="card">
        <h4>Total Income</h4>
        <div class="value">₹<?= number_format($total_income, 2) ?></div>
        <small>Revenue earned</small>
    </div>
    
    <div class="card">
        <h4>Total Expenses</h4>
        <div class="value">₹<?= number_format($total_expenses, 2) ?></div>
        <small>Costs incurred</small>
    </div>
    
    <div class="card">
        <h4>Net Profit</h4>
        <div class="value" style="color: <?= $net_profit >= 0 ? '#4CAF50' : '#f44336' ?>;">
            ₹<?= number_format($net_profit, 2) ?>
        </div>
        <small><?= $net_profit >= 0 ? 'Profit' : 'Loss' ?></small>
    </div>
    
    <div class="card">
        <h4>Previous Year</h4>
        <div class="value" style="color: <?= $prev_net_profit >= 0 ? '#4CAF50' : '#f44336' ?>;">
            ₹<?= number_format($prev_net_profit, 2) ?>
        </div>
        <small>Same period last year</small>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Income Section -->
    <div style="background: #2d2d2d; border-radius: 6px; border-left: 4px solid #4CAF50;">
        <div style="padding: 15px; border-bottom: 1px solid #3a3a3a;">
            <h3 style="color: #4CAF50; margin: 0;">💰 INCOME</h3>
        </div>
        
        <div style="padding: 15px;">
            <?php if (isset($accounts['INCOME']) && !empty($accounts['INCOME'])): ?>
                <table style="width: 100%; margin: 0;">
                    <?php foreach ($accounts['INCOME'] as $account): ?>
                        <tr>
                            <td style="padding: 5px 0; border: none;">
                                <a href="?module=ledger&account=<?= urlencode($account['code']) ?>&from=<?= $from_date ?>&to=<?= $to_date ?>" 
                                   style="color: #4CAF50; text-decoration: none; font-size: 14px;">
                                    <?= htmlspecialchars($account['code']) ?> - <?= htmlspecialchars($account['name']) ?>
                                </a>
                            </td>
                            <td style="padding: 5px 0; text-align: right; border: none;">
                                ₹<?= number_format($account['balance'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <tr style="border-top: 2px solid #4CAF50; font-weight: bold; font-size: 16px;">
                        <td style="padding: 10px 0; color: #4CAF50;">TOTAL INCOME</td>
                        <td style="padding: 10px 0; text-align: right; color: #4CAF50;">
                            ₹<?= number_format($total_income, 2) ?>
                        </td>
                    </tr>
                </table>
            <?php else: ?>
                <p style="color: #888; text-align: center; padding: 20px;">
                    No income recorded for this period
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Expenses Section -->
    <div style="background: #2d2d2d; border-radius: 6px; border-left: 4px solid #f44336;">
        <div style="padding: 15px; border-bottom: 1px solid #3a3a3a;">
            <h3 style="color: #f44336; margin: 0;">💸 EXPENSES</h3>
        </div>
        
        <div style="padding: 15px;">
            <?php if (isset($accounts['EXPENSE']) && !empty($accounts['EXPENSE'])): ?>
                <table style="width: 100%; margin: 0;">
                    <?php foreach ($accounts['EXPENSE'] as $account): ?>
                        <tr>
                            <td style="padding: 5px 0; border: none;">
                                <a href="?module=ledger&account=<?= urlencode($account['code']) ?>&from=<?= $from_date ?>&to=<?= $to_date ?>" 
                                   style="color: #f44336; text-decoration: none; font-size: 14px;">
                                    <?= htmlspecialchars($account['code']) ?> - <?= htmlspecialchars($account['name']) ?>
                                </a>
                            </td>
                            <td style="padding: 5px 0; text-align: right; border: none;">
                                ₹<?= number_format($account['balance'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <tr style="border-top: 2px solid #f44336; font-weight: bold; font-size: 16px;">
                        <td style="padding: 10px 0; color: #f44336;">TOTAL EXPENSES</td>
                        <td style="padding: 10px 0; text-align: right; color: #f44336;">
                            ₹<?= number_format($total_expenses, 2) ?>
                        </td>
                    </tr>
                </table>
            <?php else: ?>
                <p style="color: #888; text-align: center; padding: 20px;">
                    No expenses recorded for this period
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Net Profit Summary -->
<div style="background: #2d2d2d; padding: 20px; border-radius: 6px; margin-top: 20px; border-left: 4px solid <?= $net_profit >= 0 ? '#4CAF50' : '#f44336' ?>;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="color: <?= $net_profit >= 0 ? '#4CAF50' : '#f44336' ?>; margin: 0;">
            <?= $net_profit >= 0 ? '📈 NET PROFIT' : '📉 NET LOSS' ?>
        </h3>
        <div style="font-size: 24px; font-weight: bold; color: <?= $net_profit >= 0 ? '#4CAF50' : '#f44336' ?>;">
            ₹<?= number_format(abs($net_profit), 2) ?>
        </div>
    </div>
    
    <div style="margin-top: 15px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div>
            <strong>Gross Margin:</strong> 
            <?= $total_income > 0 ? number_format(($net_profit / $total_income) * 100, 2) : 0 ?>%
        </div>
        <div>
            <strong>Year-over-Year:</strong>
            <?php 
            $yoy_change = $prev_net_profit != 0 ? (($net_profit - $prev_net_profit) / abs($prev_net_profit)) * 100 : 0;
            $yoy_color = $yoy_change >= 0 ? '#4CAF50' : '#f44336';
            ?>
            <span style="color: <?= $yoy_color ?>;">
                <?= $yoy_change >= 0 ? '+' : '' ?><?= number_format($yoy_change, 1) ?>%
            </span>
        </div>
        <div>
            <strong>Previous Period:</strong> 
            ₹<?= number_format($prev_net_profit, 2) ?>
        </div>
    </div>
</div>

<?php if (empty($accounts)): ?>
    <div style="text-align: center; padding: 40px; color: #888;">
        <h3>No Data Available</h3>
        <p>No income or expense transactions found for the selected period.</p>
        <p><a href="?module=journal_new" class="button">Create Journal Entry</a></p>
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