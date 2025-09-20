<?php
require_once 'lib/accounting.php';

$accounting = new AccountingCore($db);
$message = '';
$error = '';

// Handle form submission
if ($_POST['action'] ?? false) {
    try {
        if ($_POST['action'] === 'save_journal') {
            // Prepare journal lines
            $journal_lines = [];
            if (isset($_POST['lines'])) {
                foreach ($_POST['lines'] as $line) {
                    if (!empty($line['account_code']) && (($line['debit_amount'] ?? 0) > 0 || ($line['credit_amount'] ?? 0) > 0)) {
                        $journal_lines[] = [
                            'account_code' => $line['account_code'],
                            'description' => $line['description'] ?? $_POST['description'],
                            'debit_amount' => (float)($line['debit_amount'] ?? 0),
                            'credit_amount' => (float)($line['credit_amount'] ?? 0)
                        ];
                    }
                }
            }
            
            if (count($journal_lines) < 2) {
                throw new Exception("At least 2 journal lines are required");
            }
            
            $journal_id = $accounting->createJournalEntry(
                $_POST['entry_date'],
                $_POST['description'],
                $_POST['reference'],
                $journal_lines
            );
            
            $message = "Journal entry saved successfully! (ID: $journal_id)";
            // Clear form by redirecting
            header("Location: ?module=journal&success=1");
            exit;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all accounts for dropdown
$accounts_sql = "SELECT code, name, account_type FROM accounts WHERE is_active = 1 ORDER BY code";
$stmt = $db->prepare($accounts_sql);
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent journal entries
$recent_sql = "SELECT je.*, COUNT(jl.id) as line_count
               FROM journal_entries je
               LEFT JOIN journal_lines jl ON je.id = jl.entry_id
               GROUP BY je.id
               ORDER BY je.created_at DESC
               LIMIT 20";
$stmt = $db->prepare($recent_sql);
$stmt->execute();
$recent_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get specific journal entry for viewing
$view_entry = null;
$view_lines = [];
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM journal_entries WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $view_entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($view_entry) {
        $stmt = $db->prepare("SELECT jl.*, a.name as account_name FROM journal_lines jl 
                             JOIN accounts a ON jl.account_code = a.code 
                             WHERE entry_id = ? ORDER BY jl.id");
        $stmt->execute([$_GET['id']]);
        $view_lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<script>
// Pass accounts to JavaScript
window.accounts = <?= json_encode($accounts) ?>;
window.accountOptions = '<?= implode('', array_map(function($acc) {
    return '<option value="' . $acc['code'] . '">' . $acc['code'] . ' - ' . htmlspecialchars($acc['name']) . '</option>';
}, $accounts)) ?>';
</script>

<div class="page-header">
    <h2>Journal Entries</h2>
    <div>
        <?php if ((!isset($_GET['action']) || $_GET['action'] !== 'new') && !$view_entry): ?>
            <a href="?module=journal&action=new" class="btn btn-primary">New Journal Entry</a>
        <?php endif; ?>
        <?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
            <a href="?module=journal" class="btn btn-secondary">Back to List</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Journal entry saved successfully!</div>
<?php endif; ?>

<?php if ($view_entry): ?>
    <!-- View Journal Entry -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Journal Entry #<?= $view_entry['id'] ?></h3>
            <a href="?module=journal" class="btn btn-secondary">Back to List</a>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Date:</label>
                <div class="form-control" readonly><?= date('d/m/Y', strtotime($view_entry['entry_date'])) ?></div>
            </div>
            <div class="form-group">
                <label>Reference:</label>
                <div class="form-control" readonly><?= htmlspecialchars($view_entry['reference_no'] ?? '-') ?></div>
            </div>
        </div>
        
        <div class="form-group">
            <label>Description:</label>
            <div class="form-control" readonly><?= htmlspecialchars($view_entry['description']) ?></div>
        </div>
        
        <h4>Journal Lines</h4>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Description</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_debits = 0;
                    $total_credits = 0;
                    foreach ($view_lines as $line): 
                        $total_debits += $line['debit_amount'];
                        $total_credits += $line['credit_amount'];
                    ?>
                    <tr>
                        <td><strong><?= $line['account_code'] ?></strong><br><?= htmlspecialchars($line['account_name']) ?></td>
                        <td><?= htmlspecialchars($view_entry['description'] ?? '') ?></td>
                        <td class="amount text-right">
                            <?= $line['debit_amount'] > 0 ? formatCurrency($line['debit_amount']) : '-' ?>
                        </td>
                        <td class="amount text-right">
                            <?= $line['credit_amount'] > 0 ? formatCurrency($line['credit_amount']) : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                        <td colspan="2">TOTALS</td>
                        <td class="amount text-right"><?= formatCurrency($total_debits) ?></td>
                        <td class="amount text-right"><?= formatCurrency($total_credits) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="text-muted">
            Created: <?= date('d/m/Y H:i', strtotime($view_entry['created_at'])) ?>
        </div>
    </div>

<?php elseif (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
    <!-- New Journal Entry Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">New Journal Entry</h3>
        </div>
        
        <form method="post" id="journal-form">
            <input type="hidden" name="action" value="save_journal">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="entry_date">Entry Date *</label>
                    <input type="date" id="entry_date" name="entry_date" class="form-control" 
                           value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label for="reference">Reference</label>
                    <input type="text" id="reference" name="reference" class="form-control" 
                           placeholder="Invoice#, Receipt#, etc.">
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description *</label>
                <input type="text" id="description" name="description" class="form-control" 
                       placeholder="Brief description of the transaction" required>
            </div>
            
            <h4>Journal Lines</h4>
            <div class="table-container">
                <table class="table" id="journal-lines-table">
                    <thead>
                        <tr>
                            <th style="width: 30%">Account</th>
                            <th style="width: 30%">Description</th>
                            <th style="width: 15%" class="text-right">Debit</th>
                            <th style="width: 15%" class="text-right">Credit</th>
                            <th style="width: 10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="journal-lines-tbody">
                        <!-- Journal lines will be added here by JavaScript -->
                    </tbody>
                    <tfoot>
                        <tr style="background-color: var(--bg-secondary); font-weight: bold;">
                            <td colspan="2">TOTALS</td>
                            <td class="amount text-right" id="total-debits">0.00</td>
                            <td class="amount text-right" id="total-credits">0.00</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="2">DIFFERENCE</td>
                            <td colspan="2" class="amount text-right" id="difference">0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <button type="button" id="add-journal-line-btn" class="btn btn-secondary">Add Line</button>
            
            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <button type="submit" id="journal-submit-btn" class="btn btn-primary" disabled>Balance Required</button>
                <a href="?module=journal" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- Journal Entries List -->
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
                            <th>ID</th>
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
                            <td><strong>#<?= $entry['id'] ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($entry['entry_date'])) ?></td>
                            <td><?= htmlspecialchars($entry['description']) ?></td>
                            <td><?= htmlspecialchars($entry['reference_no'] ?? '-') ?></td>
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
<?php endif; ?>

<script>
// Initialize with 2 empty lines when creating new entry
if (document.getElementById('journal-lines-tbody')) {
    addJournalLine();
    addJournalLine();
}
</script>