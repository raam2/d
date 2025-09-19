<?php
/**
 * Post Invoices Module
 * Automatically post invoices to accounting system
 */

require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/accounting.php';

$action = $_GET['action'] ?? 'list';
$message = '';
$message_type = '';

// Handle bulk posting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_invoices'])) {
    $selected_invoices = $_POST['invoice_ids'] ?? [];
    $posted_count = 0;
    $errors = [];
    
    if (empty($selected_invoices)) {
        $message = 'No invoices selected for posting.';
        $message_type = 'warning';
    } else {
        foreach ($selected_invoices as $invoice_id) {
            try {
                $entry_id = post_sales_invoice((int)$invoice_id);
                if ($entry_id) {
                    $posted_count++;
                }
            } catch (Exception $e) {
                $errors[] = "Invoice #$invoice_id: " . $e->getMessage();
            }
        }
        
        if ($posted_count > 0) {
            $message = "Successfully posted $posted_count invoice(s) to accounting.";
            $message_type = 'ok';
        }
        
        if (!empty($errors)) {
            $message .= " Errors: " . implode('; ', $errors);
            $message_type = $posted_count > 0 ? 'warning' : 'err';
        }
    }
}

// Get unposted invoices
$unposted_sql = "SELECT i.*, 
                        COALESCE(p.name, CONCAT('Party #', i.party_id)) as party_name
                 FROM invoices i
                 LEFT JOIN parties p ON i.party_id = p.id
                 LEFT JOIN journal_entries je ON je.source_type = 'SALES_INVOICE' AND je.source_id = i.id
                 WHERE je.id IS NULL
                 ORDER BY i.invoice_date DESC, i.id DESC
                 LIMIT 100";

try {
    $unposted_invoices = $db->query($unposted_sql)->fetchAll();
} catch (PDOException $e) {
    // Try alternative column names
    try {
        $unposted_sql = "SELECT i.*, 
                                COALESCE(p.name, CONCAT('Party #', i.party_id)) as party_name
                         FROM invoices i
                         LEFT JOIN parties p ON i.party_id = p.id
                         LEFT JOIN journal_entries je ON je.source_type = 'SALES_INVOICE' AND je.source_id = i.id
                         WHERE je.id IS NULL
                         ORDER BY i.issue_date DESC, i.id DESC
                         LIMIT 100";
        $unposted_invoices = $db->query($unposted_sql)->fetchAll();
    } catch (PDOException $e2) {
        $unposted_invoices = [];
        $message = 'Error loading invoices: ' . $e2->getMessage();
        $message_type = 'err';
    }
}

// Get posted invoices for reference
$posted_sql = "SELECT i.*, 
                      COALESCE(p.name, CONCAT('Party #', i.party_id)) as party_name,
                      je.id as journal_entry_id,
                      je.entry_date as posted_date
               FROM invoices i
               LEFT JOIN parties p ON i.party_id = p.id
               INNER JOIN journal_entries je ON je.source_type = 'SALES_INVOICE' AND je.source_id = i.id
               ORDER BY je.entry_date DESC, i.id DESC
               LIMIT 50";

try {
    $posted_invoices = $db->query($posted_sql)->fetchAll();
} catch (PDOException $e) {
    $posted_invoices = [];
}

// Calculate totals
$total_unposted_amount = 0;
$total_posted_amount = 0;

foreach ($unposted_invoices as $invoice) {
    $total_unposted_amount += $invoice['grand_total'] ?? $invoice['total_value'] ?? 0;
}

foreach ($posted_invoices as $invoice) {
    $total_posted_amount += $invoice['grand_total'] ?? $invoice['total_value'] ?? 0;
}

?>

<h2>📋 Post Invoices to Accounting</h2>

<?php if ($message): ?>
    <div class="<?= $message_type ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="summary-cards">
    <div class="card">
        <h4>Unposted Invoices</h4>
        <div class="value"><?= count($unposted_invoices) ?></div>
        <small>Pending posting</small>
    </div>
    
    <div class="card">
        <h4>Unposted Amount</h4>
        <div class="value">₹<?= number_format($total_unposted_amount, 2) ?></div>
        <small>Total value pending</small>
    </div>
    
    <div class="card">
        <h4>Posted Invoices</h4>
        <div class="value"><?= count($posted_invoices) ?></div>
        <small>Already in accounting</small>
    </div>
    
    <div class="card">
        <h4>Posted Amount</h4>
        <div class="value">₹<?= number_format($total_posted_amount, 2) ?></div>
        <small>Total value posted</small>
    </div>
</div>

<div style="display: flex; gap: 20px; margin-bottom: 20px;">
    <a href="?module=post_invoices&action=list" 
       class="button <?= $action === 'list' ? '' : 'style="background: #666;"' ?>">
        Unposted Invoices
    </a>
    <a href="?module=post_invoices&action=posted" 
       class="button <?= $action === 'posted' ? '' : 'style="background: #666;"' ?>">
        Posted Invoices
    </a>
</div>

<?php if ($action === 'list'): ?>
    <!-- Unposted Invoices -->
    <h3>📋 Unposted Invoices</h3>
    
    <?php if (empty($unposted_invoices)): ?>
        <div style="text-align: center; padding: 40px; color: #888;">
            <h4>🎉 All Invoices Posted!</h4>
            <p>All invoices have been posted to the accounting system.</p>
            <p><a href="?module=invoice_list" class="button">View All Invoices</a></p>
        </div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_csrf']) ?>">
            
            <div style="margin-bottom: 15px;">
                <button type="button" onclick="selectAll()" class="button" style="background: #666; font-size: 14px;">
                    Select All
                </button>
                <button type="button" onclick="selectNone()" class="button" style="background: #666; font-size: 14px;">
                    Select None
                </button>
                <button type="submit" name="post_invoices" class="button" style="background: #FF9800;">
                    Post Selected Invoices
                </button>
            </div>
            
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllCheck" onchange="toggleAll()"></th>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Party</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unposted_invoices as $invoice): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="invoice_ids[]" value="<?= $invoice['id'] ?>" class="invoice-checkbox">
                                </td>
                                <td>
                                    <?= htmlspecialchars($invoice['invoice_number'] ?? $invoice['no'] ?? $invoice['id']) ?>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($invoice['invoice_date'] ?? $invoice['issue_date'])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($invoice['party_name']) ?>
                                </td>
                                <td class="text-right">
                                    ₹<?= number_format($invoice['grand_total'] ?? $invoice['total_value'] ?? 0, 2) ?>
                                </td>
                                <td>
                                    <span style="background: #ff9800; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                        UNPOSTED
                                    </span>
                                </td>
                                <td>
                                    <a href="?module=invoice_view&id=<?= $invoice['id'] ?>" 
                                       style="color: #4CAF50; text-decoration: none; font-size: 12px;">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>

<?php else: ?>
    <!-- Posted Invoices -->
    <h3>✅ Posted Invoices</h3>
    
    <?php if (empty($posted_invoices)): ?>
        <div style="text-align: center; padding: 40px; color: #888;">
            <h4>No Posted Invoices</h4>
            <p>No invoices have been posted to accounting yet.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Party</th>
                        <th>Amount</th>
                        <th>Posted Date</th>
                        <th>Journal Entry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posted_invoices as $invoice): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($invoice['invoice_number'] ?? $invoice['no'] ?? $invoice['id']) ?>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($invoice['invoice_date'] ?? $invoice['issue_date'])) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($invoice['party_name']) ?>
                            </td>
                            <td class="text-right">
                                ₹<?= number_format($invoice['grand_total'] ?? $invoice['total_value'] ?? 0, 2) ?>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($invoice['posted_date'])) ?>
                            </td>
                            <td>
                                <a href="?module=journal_view&id=<?= $invoice['journal_entry_id'] ?>" 
                                   style="color: #4CAF50; text-decoration: none;">
                                    #<?= $invoice['journal_entry_id'] ?>
                                </a>
                            </td>
                            <td>
                                <a href="?module=invoice_view&id=<?= $invoice['id'] ?>" 
                                   style="color: #4CAF50; text-decoration: none; font-size: 12px;">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div style="margin-top: 30px; background: #2d2d2d; padding: 15px; border-radius: 6px;">
    <h3>📚 How Invoice Posting Works</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
        <div>
            <h4 style="color: #4CAF50; margin-bottom: 8px;">📝 Journal Entry Creation</h4>
            <p style="font-size: 14px; color: #ccc;">
                Each invoice creates a balanced journal entry with debits to Accounts Receivable 
                and credits to Sales Revenue and GST Output accounts.
            </p>
        </div>
        
        <div>
            <h4 style="color: #4CAF50; margin-bottom: 8px;">🏛️ GST Handling</h4>
            <p style="font-size: 14px; color: #ccc;">
                GST amounts are automatically split into CGST/SGST (intra-state) or IGST (inter-state) 
                based on supplier and customer locations.
            </p>
        </div>
        
        <div>
            <h4 style="color: #4CAF50; margin-bottom: 8px;">🔄 Audit Trail</h4>
            <p style="font-size: 14px; color: #ccc;">
                Posted invoices maintain a link to their journal entries, ensuring complete 
                traceability between sales records and accounting entries.
            </p>
        </div>
    </div>
</div>

<script>
function selectAll() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheck').checked = true;
}

function selectNone() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheck').checked = false;
}

function toggleAll() {
    const selectAllCheck = document.getElementById('selectAllCheck');
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAllCheck.checked);
}
</script>

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