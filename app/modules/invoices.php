<?php
$message = '';
$error = '';

// Simple invoice data (placeholder - this would connect to your existing invoice system)
$invoices = [
    [
        'id' => 1,
        'invoice_number' => 'INV-001',
        'party_name' => 'Sample Customer',
        'invoice_date' => '2025-09-20',
        'total_amount' => 1180.00,
        'taxable_amount' => 1000.00,
        'cgst_amount' => 90.00,
        'sgst_amount' => 90.00,
        'igst_amount' => 0.00
    ],
    [
        'id' => 2,
        'invoice_number' => 'INV-002',
        'party_name' => 'Another Customer',
        'invoice_date' => '2025-09-19',
        'total_amount' => 590.00,
        'taxable_amount' => 500.00,
        'cgst_amount' => 45.00,
        'sgst_amount' => 45.00,
        'igst_amount' => 0.00
    ]
];
?>

<div class="page-header">
    <h2>Invoice Management</h2>
    <span class="text-muted">Manage invoices and post to accounting</span>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Recent Invoices -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Invoices</h3>
    </div>
    
    <div class="alert alert-warning">
        <strong>Note:</strong> This is a sample invoice module. In a real application, this would connect to your existing invoice system.
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th class="text-right">Taxable Amount</th>
                    <th class="text-right">GST Amount</th>
                    <th class="text-right">Total Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></td>
                    <td><?= htmlspecialchars($invoice['party_name']) ?></td>
                    <td><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></td>
                    <td class="amount text-right"><?= formatCurrency($invoice['taxable_amount']) ?></td>
                    <td class="amount text-right"><?= formatCurrency($invoice['cgst_amount'] + $invoice['sgst_amount'] + $invoice['igst_amount']) ?></td>
                    <td class="amount text-right"><?= formatCurrency($invoice['total_amount']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="alert('This would show invoice details')">View</button>
                        <button class="btn btn-sm btn-primary" onclick="alert('This would post invoice to accounting system')">Post to Accounting</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Integration Instructions -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Integration Instructions</h3>
    </div>
    
    <p>To integrate this accounting system with your existing invoice system:</p>
    
    <ol>
        <li><strong>Database Integration:</strong> Connect your existing <code>invoices</code> and <code>invoice_items</code> tables</li>
        <li><strong>Auto-Posting:</strong> Use the <code>AccountingCore::postInvoiceToAccounting()</code> function to automatically create journal entries</li>
        <li><strong>GST Calculation:</strong> The system handles CGST/SGST vs IGST based on state codes</li>
        <li><strong>Accounts Receivable:</strong> Automatically debits customer accounts and credits sales/GST accounts</li>
    </ol>
    
    <h4>Sample Code for Invoice Posting:</h4>
    <pre style="background-color: var(--bg-secondary); padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>// Post invoice to accounting
require_once 'lib/accounting.php';
$accounting = new AccountingCore($db);

try {
    $journal_id = $accounting->postInvoiceToAccounting($invoice_id);
    echo "Invoice posted successfully! Journal Entry ID: $journal_id";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
</code></pre>
    
    <h4>Required Database Tables:</h4>
    <ul>
        <li><code>invoices</code> - Invoice headers with total amounts and GST breakdown</li>
        <li><code>invoice_items</code> - Invoice line items (optional for accounting posting)</li>
        <li><code>parties</code> - Customer/vendor master data</li>
    </ul>
</div>