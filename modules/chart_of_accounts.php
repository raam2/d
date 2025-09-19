<?php
/**
 * Chart of Accounts Module
 * Manage the chart of accounts
 */

require_once __DIR__ . '/../lib/database.php';

$action = $_GET['action'] ?? 'list';
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_account'])) {
            $code = trim($_POST['code']);
            $name = trim($_POST['name']);
            $type = $_POST['account_type'];
            $parent_code = empty($_POST['parent_code']) ? null : trim($_POST['parent_code']);
            $description = trim($_POST['description']);
            
            // Validate required fields
            if (empty($code) || empty($name) || empty($type)) {
                throw new Exception('Code, Name, and Account Type are required.');
            }
            
            // Insert new account
            $stmt = $db->prepare("INSERT INTO accounts (code, name, account_type, parent_code, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$code, $name, $type, $parent_code, $description]);
            
            $message = "Account '$name' created successfully.";
            $message_type = 'ok';
            
        } elseif (isset($_POST['update_account'])) {
            $id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            $type = $_POST['account_type'];
            $parent_code = empty($_POST['parent_code']) ? null : trim($_POST['parent_code']);
            $description = trim($_POST['description']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $db->prepare("UPDATE accounts SET name = ?, account_type = ?, parent_code = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $type, $parent_code, $description, $is_active, $id]);
            
            $message = "Account updated successfully.";
            $message_type = 'ok';
            
        } elseif (isset($_POST['delete_account'])) {
            $id = (int)$_POST['id'];
            
            // Check if account has any journal entries
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM journal_lines WHERE account_code = (SELECT code FROM accounts WHERE id = ?)");
            $stmt->execute([$id]);
            $usage_count = $stmt->fetch()['count'];
            
            if ($usage_count > 0) {
                throw new Exception("Cannot delete account. It has $usage_count journal entries. Deactivate instead.");
            }
            
            $stmt = $db->prepare("DELETE FROM accounts WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = "Account deleted successfully.";
            $message_type = 'ok';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'err';
    }
}

// Get account for editing
$edit_account = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $edit_account = $stmt->fetch();
    if (!$edit_account) {
        $message = "Account not found.";
        $message_type = 'err';
        $action = 'list';
    }
}

// Get all accounts organized by type
$accounts_by_type = [];
$stmt = $db->query("SELECT * FROM accounts ORDER BY account_type, code");
while ($account = $stmt->fetch()) {
    $accounts_by_type[$account['account_type']][] = $account;
}

// Get parent accounts for dropdown
$parent_accounts = $db->query("SELECT code, name, account_type FROM accounts WHERE parent_code IS NULL ORDER BY code")->fetchAll();

?>

<h2>📊 Chart of Accounts</h2>

<?php if ($message): ?>
    <div class="<?= $message_type ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 20px;">
    <a href="?module=chart_of_accounts&action=add" class="button">➕ Add New Account</a>
    <a href="?module=chart_of_accounts" class="button" style="background: #666;">📋 View All</a>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <div style="background: #2d2d2d; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
        <h3><?= $action === 'edit' ? 'Edit Account' : 'Add New Account' ?></h3>
        
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_csrf']) ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?= $edit_account['id'] ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <label>
                    Account Code *
                    <input type="text" name="code" value="<?= htmlspecialchars($edit_account['code'] ?? '') ?>" 
                           required maxlength="20" placeholder="e.g., 1010"
                           <?= $action === 'edit' ? 'readonly style="background: #444;"' : '' ?>>
                    <small>Unique account code (cannot be changed after creation)</small>
                </label>
                
                <label>
                    Account Type *
                    <select name="account_type" required>
                        <option value="">Select Type</option>
                        <?php 
                        $types = ['ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE'];
                        foreach ($types as $type): 
                        ?>
                            <option value="<?= $type ?>" <?= ($edit_account['account_type'] ?? '') === $type ? 'selected' : '' ?>>
                                <?= $type ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            
            <label>
                Account Name *
                <input type="text" name="name" value="<?= htmlspecialchars($edit_account['name'] ?? '') ?>" 
                       required maxlength="255" placeholder="e.g., Bank Account - Main">
            </label>
            
            <label>
                Parent Account
                <select name="parent_code">
                    <option value="">None (Top Level)</option>
                    <?php foreach ($parent_accounts as $parent): ?>
                        <option value="<?= htmlspecialchars($parent['code']) ?>" 
                                <?= ($edit_account['parent_code'] ?? '') === $parent['code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($parent['code']) ?> - <?= htmlspecialchars($parent['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Select a parent account to create a sub-account</small>
            </label>
            
            <label>
                Description
                <textarea name="description" rows="3" placeholder="Optional description"><?= htmlspecialchars($edit_account['description'] ?? '') ?></textarea>
            </label>
            
            <?php if ($action === 'edit'): ?>
                <label style="flex-direction: row; align-items: center; gap: 10px;">
                    <input type="checkbox" name="is_active" <?= $edit_account['is_active'] ? 'checked' : '' ?>>
                    Account is active
                </label>
            <?php endif; ?>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="<?= $action === 'edit' ? 'update_account' : 'add_account' ?>">
                    <?= $action === 'edit' ? 'Update Account' : 'Create Account' ?>
                </button>
                <a href="?module=chart_of_accounts" class="button" style="background: #666;">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($action === 'list' || $action === 'add' || $action === 'edit'): ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
        <?php 
        $type_colors = [
            'ASSET' => '#4CAF50',
            'LIABILITY' => '#f44336', 
            'EQUITY' => '#2196F3',
            'INCOME' => '#FF9800',
            'EXPENSE' => '#9C27B0'
        ];
        
        foreach ($accounts_by_type as $type => $accounts): 
        ?>
            <div style="background: #2d2d2d; border-radius: 6px; border-left: 4px solid <?= $type_colors[$type] ?>;">
                <div style="padding: 15px; border-bottom: 1px solid #3a3a3a;">
                    <h3 style="color: <?= $type_colors[$type] ?>; margin: 0;">
                        <?= $type ?> (<?= count($accounts) ?>)
                    </h3>
                </div>
                
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($accounts as $account): ?>
                        <div style="padding: 10px 15px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: <?= $account['is_active'] ? '#e0e0e0' : '#888' ?>;">
                                    <?= htmlspecialchars($account['code']) ?> - <?= htmlspecialchars($account['name']) ?>
                                </div>
                                <?php if ($account['parent_code']): ?>
                                    <div style="font-size: 12px; color: #888;">
                                        Parent: <?= htmlspecialchars($account['parent_code']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($account['description']): ?>
                                    <div style="font-size: 12px; color: #bbb; margin-top: 2px;">
                                        <?= htmlspecialchars($account['description']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!$account['is_active']): ?>
                                    <span style="background: #666; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">INACTIVE</span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: flex; gap: 5px;">
                                <a href="?module=chart_of_accounts&action=edit&id=<?= $account['id'] ?>" 
                                   style="background: #666; color: white; padding: 4px 8px; text-decoration: none; border-radius: 3px; font-size: 12px;">
                                    Edit
                                </a>
                                <a href="?module=ledger&account=<?= urlencode($account['code']) ?>" 
                                   style="background: #4CAF50; color: white; padding: 4px 8px; text-decoration: none; border-radius: 3px; font-size: 12px;">
                                    Ledger
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
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