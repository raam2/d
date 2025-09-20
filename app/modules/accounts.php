<?php
require_once 'lib/accounting.php';

$accounting = new AccountingCore($db);
$message = '';
$error = '';

// Handle form submissions
if ($_POST['action'] ?? false) {
    try {
        switch ($_POST['action']) {
            case 'add':
                $sql = "INSERT INTO accounts (code, name, account_type, parent_code, description) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $_POST['code'],
                    $_POST['name'],
                    $_POST['account_type'],
                    $_POST['parent_code'] ?: null,
                    $_POST['description']
                ]);
                $message = "Account added successfully!";
                break;
                
            case 'update':
                $sql = "UPDATE accounts SET name = ?, account_type = ?, parent_code = ?, description = ?, is_active = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $_POST['name'],
                    $_POST['account_type'],
                    $_POST['parent_code'] ?: null,
                    $_POST['description'],
                    $_POST['is_active'] ? 1 : 0,
                    $_POST['id']
                ]);
                $message = "Account updated successfully!";
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all accounts
$accounts_sql = "SELECT * FROM accounts ORDER BY code";
$stmt = $db->prepare($accounts_sql);
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get account for editing
$edit_account = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_account = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="page-header">
    <h2>Chart of Accounts</h2>
    <a href="?module=accounts&action=new" class="btn btn-primary">Add New Account</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'new' || $edit_account): ?>
    <!-- Add/Edit Account Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= $edit_account ? 'Edit Account' : 'Add New Account' ?></h3>
        </div>
        
        <form method="post">
            <input type="hidden" name="action" value="<?= $edit_account ? 'update' : 'add' ?>">
            <?php if ($edit_account): ?>
                <input type="hidden" name="id" value="<?= $edit_account['id'] ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="code">Account Code *</label>
                    <input type="text" id="code" name="code" class="form-control" 
                           value="<?= htmlspecialchars($edit_account['code'] ?? '') ?>" 
                           <?= $edit_account ? 'readonly' : 'required' ?>>
                    <small class="text-muted">Unique account code (e.g., 1010, 2100)</small>
                </div>
                
                <div class="form-group">
                    <label for="account_type">Account Type *</label>
                    <select id="account_type" name="account_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="ASSET" <?= ($edit_account['account_type'] ?? '') === 'ASSET' ? 'selected' : '' ?>>Asset</option>
                        <option value="LIABILITY" <?= ($edit_account['account_type'] ?? '') === 'LIABILITY' ? 'selected' : '' ?>>Liability</option>
                        <option value="EQUITY" <?= ($edit_account['account_type'] ?? '') === 'EQUITY' ? 'selected' : '' ?>>Equity</option>
                        <option value="INCOME" <?= ($edit_account['account_type'] ?? '') === 'INCOME' ? 'selected' : '' ?>>Income</option>
                        <option value="EXPENSE" <?= ($edit_account['account_type'] ?? '') === 'EXPENSE' ? 'selected' : '' ?>>Expense</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="name">Account Name *</label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?= htmlspecialchars($edit_account['name'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="parent_code">Parent Account</label>
                <select id="parent_code" name="parent_code" class="form-control">
                    <option value="">No Parent (Top Level)</option>
                    <?php foreach ($accounts as $acc): ?>
                        <?php if (!$edit_account || $acc['code'] !== $edit_account['code']): ?>
                            <option value="<?= $acc['code'] ?>" 
                                    <?= ($edit_account['parent_code'] ?? '') === $acc['code'] ? 'selected' : '' ?>>
                                <?= $acc['code'] ?> - <?= htmlspecialchars($acc['name']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($edit_account['description'] ?? '') ?></textarea>
            </div>
            
            <?php if ($edit_account): ?>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?= $edit_account['is_active'] ? 'checked' : '' ?>>
                        Account is active
                    </label>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary"><?= $edit_account ? 'Update Account' : 'Add Account' ?></button>
                <a href="?module=accounts" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <!-- Accounts List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">All Accounts</h3>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Parent</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <?php
                        $balance = $accounting->getAccountBalance($account['code']);
                        $balance_class = $balance > 0 ? 'credit' : ($balance < 0 ? 'debit' : '');
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($account['code']) ?></strong></td>
                            <td><?= htmlspecialchars($account['name']) ?></td>
                            <td>
                                <span class="<?= getAccountTypeClass($account['account_type']) ?>">
                                    <?= $account['account_type'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($account['parent_code'] ?? '-') ?></td>
                            <td class="amount <?= $balance_class ?>">
                                <?= formatCurrency(abs($balance)) ?>
                            </td>
                            <td>
                                <?php if ($account['is_active']): ?>
                                    <span style="color: var(--accent-green)">Active</span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted)">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?module=accounts&edit=<?= $account['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                                <a href="?module=ledger&account=<?= $account['code'] ?>" class="btn btn-sm btn-secondary">Ledger</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Account Summary by Type -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Account Summary by Type</h3>
        </div>
        
        <?php
        $summary = [];
        foreach ($accounts as $account) {
            if (!isset($summary[$account['account_type']])) {
                $summary[$account['account_type']] = ['count' => 0, 'balance' => 0];
            }
            $summary[$account['account_type']]['count']++;
            $summary[$account['account_type']]['balance'] += $accounting->getAccountBalance($account['code']);
        }
        ?>
        
        <div class="stats-grid">
            <?php foreach ($summary as $type => $data): ?>
                <div class="stat-card">
                    <div class="stat-value <?= getAccountTypeClass($type) ?>"><?= $data['count'] ?></div>
                    <div class="stat-label"><?= $type ?> Accounts</div>
                    <div class="text-muted"><?= formatCurrency(abs($data['balance'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>