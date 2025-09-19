<?php
/**
 * Manual Journal Entry Module
 * Create manual journal entries
 */

require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/accounting.php';

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_entry'])) {
    try {
        $date = $_POST['entry_date'];
        $description = trim($_POST['description']);
        $reference = trim($_POST['reference']);
        
        if (empty($date) || empty($description)) {
            throw new Exception('Date and description are required.');
        }
        
        // Parse journal lines
        $lines = [];
        $accounts = $_POST['account'] ?? [];
        $debits = $_POST['debit'] ?? [];
        $credits = $_POST['credit'] ?? [];
        $line_descriptions = $_POST['line_description'] ?? [];
        
        for ($i = 0; $i < count($accounts); $i++) {
            if (empty($accounts[$i])) continue;
            
            $debit = floatval($debits[$i] ?? 0);
            $credit = floatval($credits[$i] ?? 0);
            
            if ($debit == 0 && $credit == 0) continue;
            if ($debit > 0 && $credit > 0) {
                throw new Exception("Line " . ($i + 1) . ": Cannot have both debit and credit amounts.");
            }
            
            $lines[] = [
                'account' => $accounts[$i],
                'debit' => $debit,
                'credit' => $credit,
                'description' => trim($line_descriptions[$i] ?? '')
            ];
        }
        
        if (count($lines) < 2) {
            throw new Exception('Journal entry must have at least 2 lines.');
        }
        
        // Post the journal entry
        $entry_id = post_journal_entry($date, $description, $lines, $reference);
        
        $message = "Journal entry #$entry_id posted successfully!";
        $message_type = 'ok';
        
        // Clear form data
        $_POST = [];
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'err';
    }
}

// Get active accounts for dropdown
$accounts = $db->query("SELECT code, name, account_type FROM accounts WHERE is_active = 1 ORDER BY code")->fetchAll();

?>

<h2>📝 Manual Journal Entry</h2>

<?php if ($message): ?>
    <div class="<?= $message_type ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div style="background: #2d2d2d; padding: 20px; border-radius: 6px;">
    <form method="POST" id="journalForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_csrf']) ?>">
        
        <div class="form-row">
            <label>
                Entry Date *
                <input type="date" name="entry_date" value="<?= $_POST['entry_date'] ?? date('Y-m-d') ?>" required>
            </label>
            
            <label>
                Reference
                <input type="text" name="reference" value="<?= htmlspecialchars($_POST['reference'] ?? '') ?>" 
                       placeholder="JV-001, CHQ-1234, etc.">
            </label>
        </div>
        
        <label>
            Description *
            <input type="text" name="description" value="<?= htmlspecialchars($_POST['description'] ?? '') ?>" 
                   required placeholder="Brief description of the transaction">
        </label>
        
        <h3>Journal Lines</h3>
        <div id="journalLines">
            <!-- Initial rows -->
            <?php 
            $line_count = max(4, count($_POST['account'] ?? []));
            for ($i = 0; $i < $line_count; $i++): 
            ?>
                <div class="journal-line">
                    <select name="account[]" class="account-select">
                        <option value="">Select Account</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= htmlspecialchars($account['code']) ?>" 
                                    data-type="<?= $account['account_type'] ?>"
                                    <?= ($_POST['account'][$i] ?? '') === $account['code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($account['code']) ?> - <?= htmlspecialchars($account['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="text" name="line_description[]" placeholder="Line description (optional)"
                           value="<?= htmlspecialchars($_POST['line_description'][$i] ?? '') ?>">
                    
                    <input type="number" name="debit[]" step="0.01" min="0" placeholder="Debit" 
                           class="debit-input" value="<?= $_POST['debit'][$i] ?? '' ?>">
                    
                    <input type="number" name="credit[]" step="0.01" min="0" placeholder="Credit" 
                           class="credit-input" value="<?= $_POST['credit'][$i] ?? '' ?>">
                    
                    <button type="button" class="remove-line" onclick="removeLine(this)">❌</button>
                </div>
            <?php endfor; ?>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 15px; align-items: center;">
            <button type="button" onclick="addLine()" class="button" style="background: #666;">➕ Add Line</button>
            <div id="totals" style="color: #ccc; font-size: 14px;"></div>
        </div>
        
        <div style="margin-top: 20px;">
            <button type="submit" name="post_entry" id="submitBtn" disabled>Post Journal Entry</button>
            <a href="?module=dashboard" class="button" style="background: #666;">Cancel</a>
        </div>
    </form>
</div>

<style>
.journal-line {
    display: grid;
    grid-template-columns: 2fr 2fr 1fr 1fr auto;
    gap: 10px;
    margin-bottom: 10px;
    align-items: center;
}

.journal-line select,
.journal-line input {
    width: 100%;
}

.remove-line {
    background: #666;
    color: white;
    border: none;
    padding: 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.remove-line:hover {
    background: #f44336;
}

.balanced {
    color: #4CAF50 !important;
    font-weight: bold;
}

.unbalanced {
    color: #f44336 !important;
    font-weight: bold;
}

@media (max-width: 768px) {
    .journal-line {
        grid-template-columns: 1fr;
        gap: 5px;
        background: #333;
        padding: 10px;
        border-radius: 4px;
    }
}
</style>

<script>
function addLine() {
    const container = document.getElementById('journalLines');
    const div = document.createElement('div');
    div.className = 'journal-line';
    div.innerHTML = `
        <select name="account[]" class="account-select">
            <option value="">Select Account</option>
            <?php foreach ($accounts as $account): ?>
                <option value="<?= htmlspecialchars($account['code']) ?>" data-type="<?= $account['account_type'] ?>">
                    <?= htmlspecialchars($account['code']) ?> - <?= htmlspecialchars($account['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="line_description[]" placeholder="Line description (optional)">
        <input type="number" name="debit[]" step="0.01" min="0" placeholder="Debit" class="debit-input">
        <input type="number" name="credit[]" step="0.01" min="0" placeholder="Credit" class="credit-input">
        <button type="button" class="remove-line" onclick="removeLine(this)">❌</button>
    `;
    container.appendChild(div);
    updateTotals();
}

function removeLine(button) {
    const lines = document.querySelectorAll('.journal-line');
    if (lines.length > 2) { // Keep at least 2 lines
        button.parentElement.remove();
        updateTotals();
    }
}

function updateTotals() {
    const debitInputs = document.querySelectorAll('.debit-input');
    const creditInputs = document.querySelectorAll('.credit-input');
    
    let totalDebits = 0;
    let totalCredits = 0;
    
    debitInputs.forEach(input => {
        const value = parseFloat(input.value) || 0;
        totalDebits += value;
    });
    
    creditInputs.forEach(input => {
        const value = parseFloat(input.value) || 0;
        totalCredits += value;
    });
    
    const difference = Math.abs(totalDebits - totalCredits);
    const isBalanced = difference < 0.01 && totalDebits > 0;
    
    const totalsDiv = document.getElementById('totals');
    const submitBtn = document.getElementById('submitBtn');
    
    totalsDiv.innerHTML = `
        Debits: ₹${totalDebits.toFixed(2)} | 
        Credits: ₹${totalCredits.toFixed(2)} | 
        <span class="${isBalanced ? 'balanced' : 'unbalanced'}">
            ${isBalanced ? '✓ Balanced' : `Difference: ₹${difference.toFixed(2)}`}
        </span>
    `;
    
    submitBtn.disabled = !isBalanced;
}

// Handle debit/credit mutual exclusion
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('debit-input') || e.target.classList.contains('credit-input')) {
        const line = e.target.closest('.journal-line');
        const debitInput = line.querySelector('.debit-input');
        const creditInput = line.querySelector('.credit-input');
        
        if (e.target.classList.contains('debit-input') && e.target.value) {
            creditInput.value = '';
        } else if (e.target.classList.contains('credit-input') && e.target.value) {
            debitInput.value = '';
        }
        
        updateTotals();
    }
});

// Initial calculation
updateTotals();
</script>