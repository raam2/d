<?php
/**
 * Bank Reconciliation Module
 * Reconcile bank statements with book records
 */

require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/accounting.php';

$action = $_GET['action'] ?? 'list';
$message = '';
$message_type = '';

// Get bank accounts (accounts starting with 10)
$bank_accounts = $db->query("SELECT code, name FROM accounts WHERE code LIKE '10%' AND is_active = 1 ORDER BY code")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_reconciliation'])) {
        try {
            $account_code = $_POST['account_code'];
            $statement_date = $_POST['statement_date'];
            $statement_balance = (float)$_POST['statement_balance'];
            
            // Get book balance as of statement date
            $book_balance = get_account_balance($account_code, $statement_date);
            
            // Create reconciliation record
            $stmt = $db->prepare("INSERT INTO bank_reconciliation (bank_account_code, statement_date, statement_balance, book_balance, reconciled_balance, status) VALUES (?, ?, ?, ?, ?, 'DRAFT')");
            $stmt->execute([$account_code, $statement_date, $statement_balance, $book_balance, $book_balance]);
            
            $recon_id = $db->lastInsertId();
            
            $message = "Bank reconciliation created successfully.";
            $message_type = 'ok';
            
            header("Location: ?module=reconcile&action=reconcile&id=$recon_id");
            exit;
            
        } catch (Exception $e) {
            $message = "Error creating reconciliation: " . $e->getMessage();
            $message_type = 'err';
        }
    }
}

// Get existing reconciliations
$reconciliations = $db->query("
    SELECT r.*, a.name as account_name 
    FROM bank_reconciliation r 
    JOIN accounts a ON r.bank_account_code = a.code 
    ORDER BY r.statement_date DESC, r.id DESC 
    LIMIT 50
")->fetchAll();

?>

<h2>🏛️ Bank Reconciliation</h2>

<?php if ($message): ?>
    <div class="<?= $message_type ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($action === 'new'): ?>
    <!-- Create New Reconciliation -->
    <div style="background: #2d2d2d; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
        <h3>🆕 Create New Bank Reconciliation</h3>
        
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_csrf']) ?>">
            
            <div class="form-row">
                <label>
                    Bank Account *
                    <select name="account_code" required>
                        <option value="">Select Bank Account</option>
                        <?php foreach ($bank_accounts as $account): ?>
                            <option value="<?= htmlspecialchars($account['code']) ?>">
                                <?= htmlspecialchars($account['code']) ?> - <?= htmlspecialchars($account['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                
                <label>
                    Statement Date *
                    <input type="date" name="statement_date" required value="<?= date('Y-m-d') ?>">
                </label>
            </div>
            
            <label>
                Statement Balance *
                <input type="number" name="statement_balance" step="0.01" required placeholder="0.00">
                <small>Enter the closing balance as shown in your bank statement</small>
            </label>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="create_reconciliation">Create Reconciliation</button>
                <a href="?module=reconcile" class="button" style="background: #666;">Cancel</a>
            </div>
        </form>
    </div>

<?php elseif ($action === 'reconcile' && isset($_GET['id'])): ?>
    <!-- Reconciliation Interface -->
    <?php
    $recon_id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT r.*, a.name as account_name FROM bank_reconciliation r JOIN accounts a ON r.bank_account_code = a.code WHERE r.id = ?");
    $stmt->execute([$recon_id]);
    $reconciliation = $stmt->fetch();
    
    if (!$reconciliation) {
        echo '<div class="err">Reconciliation not found.</div>';
        echo '<p><a href="?module=reconcile" class="button">Back to List</a></p>';
    } else {
        // Get unreconciled transactions
        $stmt = $db->prepare("
            SELECT l.*, e.entry_date, e.reference, e.description
            FROM journal_lines l
            JOIN journal_entries e ON l.entry_id = e.id
            WHERE l.account_code = ? 
            AND e.entry_date <= ?
            AND l.id NOT IN (SELECT COALESCE(journal_line_id, 0) FROM bank_reconciliation_items WHERE reconciliation_id = ?)
            ORDER BY e.entry_date DESC, e.id DESC
        ");
        $stmt->execute([$reconciliation['bank_account_code'], $reconciliation['statement_date'], $recon_id]);
        $transactions = $stmt->fetchAll();
        
        $difference = $reconciliation['statement_balance'] - $reconciliation['book_balance'];
    ?>
    
    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
        <a href="?module=reconcile" class="button" style="background: #666;">← Back to List</a>
        <?php if ($reconciliation['status'] === 'DRAFT'): ?>
            <button type="button" class="button" style="background: #4CAF50;" onclick="markReconciled()">
                ✓ Mark as Reconciled
            </button>
        <?php endif; ?>
    </div>
    
    <div class="summary-cards">
        <div class="card">
            <h4>Bank Account</h4>
            <div class="value" style="font-size: 16px;"><?= htmlspecialchars($reconciliation['account_name']) ?></div>
            <small><?= htmlspecialchars($reconciliation['bank_account_code']) ?></small>
        </div>
        
        <div class="card">
            <h4>Statement Date</h4>
            <div class="value"><?= date('d M Y', strtotime($reconciliation['statement_date'])) ?></div>
            <small>As of date</small>
        </div>
        
        <div class="card">
            <h4>Statement Balance</h4>
            <div class="value">₹<?= number_format($reconciliation['statement_balance'], 2) ?></div>
            <small>Per bank statement</small>
        </div>
        
        <div class="card">
            <h4>Book Balance</h4>
            <div class="value">₹<?= number_format($reconciliation['book_balance'], 2) ?></div>
            <small>Per accounting records</small>
        </div>
        
        <div class="card">
            <h4>Difference</h4>
            <div class="value" style="color: <?= abs($difference) < 0.01 ? '#4CAF50' : '#f44336' ?>;">
                ₹<?= number_format($difference, 2) ?>
            </div>
            <small><?= abs($difference) < 0.01 ? 'Reconciled' : 'To be investigated' ?></small>
        </div>
        
        <div class="card">
            <h4>Status</h4>
            <div class="value" style="color: <?= $reconciliation['status'] === 'RECONCILED' ? '#4CAF50' : '#ff9800' ?>;">
                <?= $reconciliation['status'] ?>
            </div>
            <small>Reconciliation status</small>
        </div>
    </div>
    
    <h3>📋 Bank Transactions</h3>
    <p style="color: #ccc; margin-bottom: 15px;">
        Review the transactions below and mark any that appear on your bank statement. 
        Outstanding items will be carried forward to the next reconciliation.
    </p>
    
    <?php if (empty($transactions)): ?>
        <div style="text-align: center; padding: 20px; color: #888;">
            <p>No unreconciled transactions found for this period.</p>
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
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $txn): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($txn['entry_date'])) ?></td>
                            <td><?= htmlspecialchars($txn['reference'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($txn['description']) ?></td>
                            <td class="text-right">
                                <?= $txn['debit_amount'] > 0 ? '₹' . number_format($txn['debit_amount'], 2) : '-' ?>
                            </td>
                            <td class="text-right">
                                <?= $txn['credit_amount'] > 0 ? '₹' . number_format($txn['credit_amount'], 2) : '-' ?>
                            </td>
                            <td>
                                <span style="background: #ff9800; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    OUTSTANDING
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <?php } ?>

<?php else: ?>
    <!-- List Reconciliations -->
    <div style="margin-bottom: 20px;">
        <a href="?module=reconcile&action=new" class="button">🆕 New Reconciliation</a>
        <a href="?module=dashboard" class="button" style="background: #666;">Dashboard</a>
    </div>
    
    <h3>📋 Bank Reconciliations</h3>
    
    <?php if (empty($reconciliations)): ?>
        <div style="text-align: center; padding: 40px; color: #888;">
            <h4>No Reconciliations Found</h4>
            <p>Create your first bank reconciliation to get started.</p>
            <p><a href="?module=reconcile&action=new" class="button">Create Reconciliation</a></p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Account</th>
                        <th>Statement Date</th>
                        <th>Statement Balance</th>
                        <th>Book Balance</th>
                        <th>Difference</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reconciliations as $recon): ?>
                        <?php $diff = $recon['statement_balance'] - $recon['book_balance']; ?>
                        <tr>
                            <td>#<?= $recon['id'] ?></td>
                            <td>
                                <?= htmlspecialchars($recon['bank_account_code']) ?><br>
                                <small style="color: #888;"><?= htmlspecialchars($recon['account_name']) ?></small>
                            </td>
                            <td><?= date('d/m/Y', strtotime($recon['statement_date'])) ?></td>
                            <td class="text-right">₹<?= number_format($recon['statement_balance'], 2) ?></td>
                            <td class="text-right">₹<?= number_format($recon['book_balance'], 2) ?></td>
                            <td class="text-right" style="color: <?= abs($diff) < 0.01 ? '#4CAF50' : '#f44336' ?>;">
                                ₹<?= number_format($diff, 2) ?>
                            </td>
                            <td>
                                <span style="background: <?= $recon['status'] === 'RECONCILED' ? '#4CAF50' : '#ff9800' ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    <?= $recon['status'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="?module=reconcile&action=reconcile&id=<?= $recon['id'] ?>" 
                                   style="background: #4CAF50; color: white; padding: 3px 6px; text-decoration: none; border-radius: 3px; font-size: 12px;">
                                    <?= $recon['status'] === 'DRAFT' ? 'Continue' : 'View' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 30px; background: #2d2d2d; padding: 15px; border-radius: 6px;">
        <h3>📚 Bank Reconciliation Guide</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
            <div>
                <h4 style="color: #4CAF50; margin-bottom: 8px;">1️⃣ Start Reconciliation</h4>
                <p style="font-size: 14px; color: #ccc;">
                    Create a new reconciliation by selecting your bank account and entering 
                    the closing balance from your bank statement.
                </p>
            </div>
            
            <div>
                <h4 style="color: #4CAF50; margin-bottom: 8px;">2️⃣ Review Transactions</h4>
                <p style="font-size: 14px; color: #ccc;">
                    Compare your book transactions with bank statement entries. 
                    Outstanding items will show the difference to investigate.
                </p>
            </div>
            
            <div>
                <h4 style="color: #4CAF50; margin-bottom: 8px;">3️⃣ Complete Reconciliation</h4>
                <p style="font-size: 14px; color: #ccc;">
                    When statement and book balances match (difference = 0), 
                    mark the reconciliation as complete.
                </p>
            </div>
        </div>
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
    cursor: pointer;
    border: none;
}
.button:hover {
    background: #45a049;
}
</style>