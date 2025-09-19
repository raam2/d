<?php
/**
 * Invoice List Module
 * View and manage invoices from existing database
 */

require_once __DIR__ . '/../lib/database.php';

$page = (int)($_GET['page'] ?? 1);
$per_page = 50;
$offset = ($page - 1) * $per_page;
$search = trim($_GET['search'] ?? '');

// Build search query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(i.invoice_number LIKE ? OR i.no LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Try to get invoices - handle different possible table structures
$invoices = [];
$total_count = 0;

try {
    // First attempt - standard structure
    $sql = "SELECT i.*, 
                   COALESCE(p.name, CONCAT('Party #', i.party_id)) as party_name,
                   je.id as journal_entry_id
            FROM invoices i
            LEFT JOIN parties p ON i.party_id = p.id
            LEFT JOIN journal_entries je ON je.source_type = 'SALES_INVOICE' AND je.source_id = i.id
            $where_clause
            ORDER BY i.invoice_date DESC, i.id DESC
            LIMIT $per_page OFFSET $offset";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
    
    // Get count
    $count_sql = "SELECT COUNT(*) as count FROM invoices i LEFT JOIN parties p ON i.party_id = p.id $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch()['count'];
    
} catch (PDOException $e) {
    // Try alternative column names
    try {
        $sql = "SELECT i.*, 
                       COALESCE(p.name, CONCAT('Party #', i.party_id)) as party_name,
                       je.id as journal_entry_id
                FROM invoices i
                LEFT JOIN parties p ON i.party_id = p.id
                LEFT JOIN journal_entries je ON je.source_type = 'SALES_INVOICE' AND je.source_id = i.id
                $where_clause
                ORDER BY i.issue_date DESC, i.id DESC
                LIMIT $per_page OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll();
        
        // Get count
        $count_sql = "SELECT COUNT(*) as count FROM invoices i LEFT JOIN parties p ON i.party_id = p.id $where_clause";
        $count_stmt = $db->prepare($count_sql);
        $count_stmt->execute($params);
        $total_count = $count_stmt->fetch()['count'];
        
    } catch (PDOException $e2) {
        $error_message = "Error loading invoices: " . $e2->getMessage();
    }
}

$total_pages = ceil($total_count / $per_page);

// Calculate summary statistics
$total_amount = 0;
$posted_count = 0;

foreach ($invoices as $invoice) {
    $total_amount += $invoice['grand_total'] ?? $invoice['total_value'] ?? 0;
    if ($invoice['journal_entry_id']) {
        $posted_count++;
    }
}

?>

<h2>📄 Invoice List</h2>

<?php if (isset($error_message)): ?>
    <div class="err">
        <?= htmlspecialchars($error_message) ?>
    </div>
<?php else: ?>

<div style="background: #2d2d2d; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: end;">
        <input type="hidden" name="module" value="invoice_list">
        
        <label style="flex: 1;">
            Search Invoices
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Search by invoice number or party name...">
        </label>
        
        <button type="submit">Search</button>
        <?php if ($search): ?>
            <a href="?module=invoice_list" class="button" style="background: #666;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="summary-cards">
    <div class="card">
        <h4>Total Invoices</h4>
        <div class="value"><?= number_format($total_count) ?></div>
        <small><?= $search ? 'Matching search' : 'All invoices' ?></small>
    </div>
    
    <div class="card">
        <h4>Total Amount</h4>
        <div class="value">₹<?= number_format($total_amount, 2) ?></div>
        <small>Current page sum</small>
    </div>
    
    <div class="card">
        <h4>Posted to Accounting</h4>
        <div class="value"><?= $posted_count ?>/<?= count($invoices) ?></div>
        <small>Current page</small>
    </div>
    
    <div class="card">
        <h4>Posting Status</h4>
        <div class="value"><?= count($invoices) > 0 ? round(($posted_count / count($invoices)) * 100) : 0 ?>%</div>
        <small>Completion rate</small>
    </div>
</div>

<div style="margin-bottom: 20px;">
    <a href="?module=post_invoices" class="button">📋 Post to Accounting</a>
    <a href="?module=dashboard" class="button" style="background: #666;">Dashboard</a>
</div>

<?php if (empty($invoices)): ?>
    <div style="text-align: center; padding: 40px; color: #888;">
        <h3>No Invoices Found</h3>
        <?php if ($search): ?>
            <p>No invoices match your search criteria.</p>
            <p><a href="?module=invoice_list" class="button">View All Invoices</a></p>
        <?php else: ?>
            <p>No invoices found in the database.</p>
        <?php endif; ?>
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
                    <th>GST</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($invoice['invoice_number'] ?? $invoice['no'] ?? $invoice['id']) ?></strong>
                        </td>
                        <td>
                            <?= date('d/m/Y', strtotime($invoice['invoice_date'] ?? $invoice['issue_date'])) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($invoice['party_name']) ?>
                        </td>
                        <td class="text-right">
                            ₹<?= number_format($invoice['taxable_value'] ?? 0, 2) ?>
                        </td>
                        <td class="text-right">
                            ₹<?= number_format(($invoice['tax_value'] ?? 0), 2) ?>
                        </td>
                        <td class="text-right">
                            <strong>₹<?= number_format($invoice['grand_total'] ?? $invoice['total_value'] ?? 0, 2) ?></strong>
                        </td>
                        <td>
                            <?php if ($invoice['journal_entry_id']): ?>
                                <span style="background: #4CAF50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    POSTED
                                </span>
                            <?php else: ?>
                                <span style="background: #ff9800; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    UNPOSTED
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="?module=invoice_view&id=<?= $invoice['id'] ?>" 
                                   style="background: #4CAF50; color: white; padding: 3px 6px; text-decoration: none; border-radius: 3px; font-size: 12px;">
                                    View
                                </a>
                                <?php if ($invoice['journal_entry_id']): ?>
                                    <a href="?module=journal_view&id=<?= $invoice['journal_entry_id'] ?>" 
                                       style="background: #2196F3; color: white; padding: 3px 6px; text-decoration: none; border-radius: 3px; font-size: 12px;">
                                        Journal
                                    </a>
                                <?php endif; ?>
                            </div>
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
                    <a href="?module=invoice_list&page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="button" style="background: #666;">← Previous</a>
                <?php endif; ?>
                
                <span style="color: #ccc;">
                    Page <?= $page ?> of <?= $total_pages ?> (<?= number_format($total_count) ?> invoices)
                </span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?module=invoice_list&page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="button" style="background: #666;">Next →</a>
                <?php endif; ?>
            </div>
            
            <?php if ($total_pages <= 10): ?>
                <div style="margin-top: 10px;">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span style="background: #4CAF50; color: white; padding: 5px 10px; margin: 2px; border-radius: 3px;">
                                <?= $i ?>
                            </span>
                        <?php else: ?>
                            <a href="?module=invoice_list&page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               style="background: #666; color: white; padding: 5px 10px; margin: 2px; text-decoration: none; border-radius: 3px;">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div style="margin-top: 30px; background: #2d2d2d; padding: 15px; border-radius: 6px;">
    <h3>💡 Invoice Management Tips</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
        <div>
            <h4 style="color: #4CAF50; margin-bottom: 8px;">🔍 Search & Filter</h4>
            <p style="font-size: 14px; color: #ccc;">
                Use the search box to quickly find invoices by number or party name. 
                Search is case-insensitive and supports partial matches.
            </p>
        </div>
        
        <div>
            <h4 style="color: #FF9800; margin-bottom: 8px;">📋 Accounting Integration</h4>
            <p style="font-size: 14px; color: #ccc;">
                Invoices marked as "POSTED" have been integrated into the accounting system. 
                Use "Post to Accounting" to process unposted invoices.
            </p>
        </div>
        
        <div>
            <h4 style="color: #2196F3; margin-bottom: 8px;">📊 View Details</h4>
            <p style="font-size: 14px; color: #ccc;">
                Click "View" to see full invoice details and line items. 
                Click "Journal" to see the corresponding accounting entry.
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
}
.button:hover {
    background: #45a049;
}
</style>