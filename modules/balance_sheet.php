<?php
/**
 * Balance Sheet Module
 * Generate balance sheet as of specified date
 */

require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/accounting.php';

$as_of_date = $_GET['as_of'] ?? date('Y-m-d');

// Get all asset, liability, and equity accounts with balances
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
        WHERE a.account_type IN ('ASSET', 'LIABILITY', 'EQUITY') 
        AND a.is_active = 1
        AND e.entry_date <= ?
        GROUP BY a.code, a.name, a.account_type, a.parent_code
        HAVING (total_debit != 0 OR total_credit != 0)
        ORDER BY a.account_type, a.code";

$stmt = $db->prepare($sql);
$stmt->execute([$as_of_date]);

$accounts = [];
$total_assets = 0;
$total_liabilities = 0;
$total_equity = 0;

while ($row = $stmt->fetch()) {
    $debit = $row['total_debit'];
    $credit = $row['total_credit'];
    $type = $row['account_type'];
    
    // Calculate balance based on account type
    if ($type === 'ASSET') {
        $balance = $debit - $credit;
        $total_assets += $balance;
    } elseif ($type === 'LIABILITY') {
        $balance = $credit - $debit;
        $total_liabilities += $balance;
    } else { // EQUITY
        $balance = $credit - $debit;
        $total_equity += $balance;
    }
    
    // Only include accounts with non-zero balances
    if (abs($balance) > 0.01) {
        $accounts[$type][] = [
            'code' => $row['code'],
            'name' => $row['name'],
            'parent_code' => $row['parent_code'],
            'balance' => $balance
        ];
    }
}

// Calculate retained earnings (Net Profit/Loss carried forward)
$fy_start = date('Y-04-01', strtotime($as_of_date)); // Financial year start
if (date('m-d', strtotime($as_of_date)) < '04-01') {
    $fy_start = date('Y-04-01', strtotime($as_of_date . ' -1 year'));
}

$retained_earnings_sql = "SELECT 
            COALESCE(SUM(CASE WHEN a.account_type = 'INCOME' THEN l.credit_amount - l.debit_amount ELSE 0 END), 0) as total_income,
            COALESCE(SUM(CASE WHEN a.account_type = 'EXPENSE' THEN l.debit_amount - l.credit_amount ELSE 0 END), 0) as total_expenses
        FROM accounts a
        LEFT JOIN journal_lines l ON a.code = l.account_code
        LEFT JOIN journal_entries e ON l.entry_id = e.id
        WHERE a.account_type IN ('INCOME', 'EXPENSE') 
        AND a.is_active = 1
        AND e.entry_date BETWEEN ? AND ?";

$stmt = $db->prepare($retained_earnings_sql);
$stmt->execute([$fy_start, $as_of_date]);
$earnings_data = $stmt->fetch();

$current_year_profit = ($earnings_data['total_income'] ?? 0) - ($earnings_data['total_expenses'] ?? 0);
$total_equity += $current_year_profit;

// Group assets by current/fixed
$current_assets = [];
$fixed_assets = [];

if (isset($accounts['ASSET'])) {
    foreach ($accounts['ASSET'] as $asset) {
        // Simple classification based on account code (1000-1499 = current, 1500+ = fixed)
        if (intval($asset['code']) < 1500) {
            $current_assets[] = $asset;
        } else {
            $fixed_assets[] = $asset;
        }
    }
}

$total_current_assets = array_sum(array_column($current_assets, 'balance'));
$total_fixed_assets = array_sum(array_column($fixed_assets, 'balance'));

// Check if balance sheet balances
$total_assets_calc = $total_current_assets + $total_fixed_assets;
$total_liab_equity = $total_liabilities + $total_equity;
$is_balanced = abs($total_assets_calc - $total_liab_equity) < 0.01;

?>

<h2>🏛️ Balance Sheet</h2>

<div style="background: #2d2d2d; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: end;">
        <input type="hidden" name="module" value="balance_sheet">
        
        <label>
            As of Date
            <input type="date" name="as_of" value="<?= htmlspecialchars($as_of_date) ?>">
        </label>
        
        <button type="submit">Generate Report</button>
        
        <div style="display: flex; gap: 5px;">
            <a href="?module=balance_sheet&as_of=<?= date('Y-m-d') ?>" 
               class="button" style="background: #666; font-size: 12px;">Today</a>
            <a href="?module=balance_sheet&as_of=<?= date('Y-03-31', strtotime('+1 year')) ?>" 
               class="button" style="background: #666; font-size: 12px;">FY End</a>
            <a href="?module=balance_sheet&as_of=<?= date('Y-12-31') ?>" 
               class="button" style="background: #666; font-size: 12px;">Year End</a>
        </div>
    </form>
</div>

<div style="text-align: center; margin-bottom: 20px;">
    <h3 style="color: #4CAF50; margin: 0;">Balance Sheet</h3>
    <p style="color: #ccc; margin: 5px 0;">
        As of <?= date('d M Y', strtotime($as_of_date)) ?>
    </p>
</div>

<div class="summary-cards">
    <div class="card">
        <h4>Total Assets</h4>
        <div class="value">₹<?= number_format($total_assets_calc, 2) ?></div>
        <small>All resources owned</small>
    </div>
    
    <div class="card">
        <h4>Total Liabilities</h4>
        <div class="value">₹<?= number_format($total_liabilities, 2) ?></div>
        <small>Amounts owed</small>
    </div>
    
    <div class="card">
        <h4>Total Equity</h4>
        <div class="value">₹<?= number_format($total_equity, 2) ?></div>
        <small>Owner's equity</small>
    </div>
    
    <div class="card">
        <h4>Balance Status</h4>
        <div class="value" style="color: <?= $is_balanced ? '#4CAF50' : '#f44336' ?>;">
            <?= $is_balanced ? '✓ Balanced' : '✗ Unbalanced' ?>
        </div>
        <small>Assets = Liabilities + Equity</small>
    </div>
</div>

<?php if (!$is_balanced): ?>
    <div class="err">
        <strong>⚠️ Balance Sheet is Not Balanced!</strong><br>
        Difference: ₹<?= number_format(abs($total_assets_calc - $total_liab_equity), 2) ?><br>
        Please check your accounts and journal entries.
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Assets Side -->
    <div style="background: #2d2d2d; border-radius: 6px; border-left: 4px solid #4CAF50;">
        <div style="padding: 15px; border-bottom: 1px solid #3a3a3a;">
            <h3 style="color: #4CAF50; margin: 0;">💰 ASSETS</h3>
        </div>
        
        <div style="padding: 15px;">
            <!-- Current Assets -->
            <?php if (!empty($current_assets)): ?>
                <h4 style="color: #4CAF50; margin-bottom: 10px;">Current Assets</h4>
                <table style="width: 100%; margin-bottom: 20px;">
                    <?php foreach ($current_assets as $asset): ?>
                        <tr>
                            <td style="padding: 3px 0; border: none; font-size: 14px;">
                                <a href="?module=ledger&account=<?= urlencode($asset['code']) ?>&to=<?= $as_of_date ?>" 
                                   style="color: #4CAF50; text-decoration: none;">
                                    <?= htmlspecialchars($asset['name']) ?>
                                </a>
                            </td>
                            <td style="padding: 3px 0; text-align: right; border: none; font-size: 14px;">
                                ₹<?= number_format($asset['balance'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="border-top: 1px solid #666; font-weight: bold;">
                        <td style="padding: 8px 0; color: #4CAF50;">Total Current Assets</td>
                        <td style="padding: 8px 0; text-align: right; color: #4CAF50;">
                            ₹<?= number_format($total_current_assets, 2) ?>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
            
            <!-- Fixed Assets -->
            <?php if (!empty($fixed_assets)): ?>
                <h4 style="color: #4CAF50; margin-bottom: 10px;">Fixed Assets</h4>
                <table style="width: 100%; margin-bottom: 20px;">
                    <?php foreach ($fixed_assets as $asset): ?>
                        <tr>
                            <td style="padding: 3px 0; border: none; font-size: 14px;">
                                <a href="?module=ledger&account=<?= urlencode($asset['code']) ?>&to=<?= $as_of_date ?>" 
                                   style="color: #4CAF50; text-decoration: none;">
                                    <?= htmlspecialchars($asset['name']) ?>
                                </a>
                            </td>
                            <td style="padding: 3px 0; text-align: right; border: none; font-size: 14px;">
                                ₹<?= number_format($asset['balance'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="border-top: 1px solid #666; font-weight: bold;">
                        <td style="padding: 8px 0; color: #4CAF50;">Total Fixed Assets</td>
                        <td style="padding: 8px 0; text-align: right; color: #4CAF50;">
                            ₹<?= number_format($total_fixed_assets, 2) ?>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
            
            <!-- Total Assets -->
            <table style="width: 100%;">
                <tr style="border-top: 2px solid #4CAF50; font-weight: bold; font-size: 16px;">
                    <td style="padding: 10px 0; color: #4CAF50;">TOTAL ASSETS</td>
                    <td style="padding: 10px 0; text-align: right; color: #4CAF50;">
                        ₹<?= number_format($total_assets_calc, 2) ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Liabilities & Equity Side -->
    <div style="background: #2d2d2d; border-radius: 6px; border-left: 4px solid #f44336;">
        <div style="padding: 15px; border-bottom: 1px solid #3a3a3a;">
            <h3 style="color: #f44336; margin: 0;">🏛️ LIABILITIES & EQUITY</h3>
        </div>
        
        <div style="padding: 15px;">
            <!-- Liabilities -->
            <?php if (isset($accounts['LIABILITY']) && !empty($accounts['LIABILITY'])): ?>
                <h4 style="color: #f44336; margin-bottom: 10px;">Liabilities</h4>
                <table style="width: 100%; margin-bottom: 20px;">
                    <?php foreach ($accounts['LIABILITY'] as $liability): ?>
                        <tr>
                            <td style="padding: 3px 0; border: none; font-size: 14px;">
                                <a href="?module=ledger&account=<?= urlencode($liability['code']) ?>&to=<?= $as_of_date ?>" 
                                   style="color: #f44336; text-decoration: none;">
                                    <?= htmlspecialchars($liability['name']) ?>
                                </a>
                            </td>
                            <td style="padding: 3px 0; text-align: right; border: none; font-size: 14px;">
                                ₹<?= number_format($liability['balance'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="border-top: 1px solid #666; font-weight: bold;">
                        <td style="padding: 8px 0; color: #f44336;">Total Liabilities</td>
                        <td style="padding: 8px 0; text-align: right; color: #f44336;">
                            ₹<?= number_format($total_liabilities, 2) ?>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
            
            <!-- Equity -->
            <h4 style="color: #2196F3; margin-bottom: 10px;">Equity</h4>
            <table style="width: 100%; margin-bottom: 20px;">
                <?php if (isset($accounts['EQUITY']) && !empty($accounts['EQUITY'])): ?>
                    <?php foreach ($accounts['EQUITY'] as $equity): ?>
                        <tr>
                            <td style="padding: 3px 0; border: none; font-size: 14px;">
                                <a href="?module=ledger&account=<?= urlencode($equity['code']) ?>&to=<?= $as_of_date ?>" 
                                   style="color: #2196F3; text-decoration: none;">
                                    <?= htmlspecialchars($equity['name']) ?>
                                </a>
                            </td>
                            <td style="padding: 3px 0; text-align: right; border: none; font-size: 14px;">
                                ₹<?= number_format($equity['balance'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Current Year Earnings -->
                <?php if (abs($current_year_profit) > 0.01): ?>
                    <tr>
                        <td style="padding: 3px 0; border: none; font-size: 14px;">
                            <a href="?module=pl&from=<?= $fy_start ?>&to=<?= $as_of_date ?>" 
                               style="color: #2196F3; text-decoration: none;">
                                Current Year Earnings
                            </a>
                        </td>
                        <td style="padding: 3px 0; text-align: right; border: none; font-size: 14px;">
                            ₹<?= number_format($current_year_profit, 2) ?>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <tr style="border-top: 1px solid #666; font-weight: bold;">
                    <td style="padding: 8px 0; color: #2196F3;">Total Equity</td>
                    <td style="padding: 8px 0; text-align: right; color: #2196F3;">
                        ₹<?= number_format($total_equity, 2) ?>
                    </td>
                </tr>
            </table>
            
            <!-- Total Liabilities & Equity -->
            <table style="width: 100%;">
                <tr style="border-top: 2px solid #f44336; font-weight: bold; font-size: 16px;">
                    <td style="padding: 10px 0; color: #f44336;">TOTAL LIAB. & EQUITY</td>
                    <td style="padding: 10px 0; text-align: right; color: #f44336;">
                        ₹<?= number_format($total_liab_equity, 2) ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<?php if (empty($accounts)): ?>
    <div style="text-align: center; padding: 40px; color: #888;">
        <h3>No Data Available</h3>
        <p>No account balances found as of the selected date.</p>
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