<?php
declare(strict_types=1);

/**
 * Zoho Books Export Utility
 * 
 * Exports data from the accounting database to CSV files compatible with Zoho Books.
 * Supports exporting: Contacts (Parties), Items (Products), and Invoices.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/db.php';

// Helper function to format CSV row
function formatCsvRow(array $data): string
{
    return implode(',', array_map(function($field) {
        // Escape double quotes and wrap in quotes if contains comma, quote, or newline
        if ($field === null) {
            return '';
        }
        $field = str_replace('"', '""', (string)$field);
        if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            return '"' . $field . '"';
        }
        return $field;
    }, $data)) . "\n";
}

// Export Contacts (Parties)
function exportContacts(): string
{
    $filename = __DIR__ . '/exports/zoho_contacts_' . date('Y-m-d_His') . '.csv';
    $fp = fopen($filename, 'w');
    
    if (!$fp) {
        throw new RuntimeException("Cannot create file: $filename");
    }
    
    // Zoho Books Contacts CSV Headers
    // https://www.zoho.com/books/help/import-data/contacts.html
    $headers = [
        'Contact Name', 'Company Name', 'Contact Type', 'Customer Sub Type',
        'Credit Limit', 'Payment Terms', 'Currency Code', 'Website',
        'Designation', 'Department', 'Contact Persons',
        'Salutation', 'First Name', 'Last Name', 'Email', 'Work Phone', 'Mobile',
        'Billing Address', 'Billing Street2', 'Billing City', 'Billing State',
        'Billing Country', 'Billing Code', 'Billing Fax',
        'Shipping Address', 'Shipping Street2', 'Shipping City', 'Shipping State',
        'Shipping Country', 'Shipping Code', 'Shipping Fax',
        'GST Treatment', 'GST Identification Number (GSTIN)', 'Place of Contact',
        'Notes', 'Custom Field 1', 'Custom Field 2'
    ];
    
    fwrite($fp, formatCsvRow($headers));
    
    // Fetch all parties
    $parties = fetchAll("
        SELECT 
            id,
            name,
            gstin,
            party_type,
            city,
            state,
            state_code
        FROM parties
        ORDER BY name
    ");
    
    $exported = 0;
    foreach ($parties as $party) {
        $contactType = match($party['party_type']) {
            'customer' => 'customer',
            'supplier' => 'vendor',
            'both' => 'customer', // Export as customer, will need manual handling
            default => 'customer'
        };
        
        $gstTreatment = !empty($party['gstin']) ? 'registered_business' : 'unregistered_business';
        
        $row = [
            $party['name'],                           // Contact Name
            $party['name'],                           // Company Name
            $contactType,                             // Contact Type (customer/vendor)
            '',                                       // Customer Sub Type
            '',                                       // Credit Limit
            '',                                       // Payment Terms
            'INR',                                    // Currency Code
            '',                                       // Website
            '',                                       // Designation
            '',                                       // Department
            '',                                       // Contact Persons
            '',                                       // Salutation
            '',                                       // First Name
            '',                                       // Last Name
            '',                                       // Email
            '',                                       // Work Phone
            '',                                       // Mobile
            $party['city'] ?? '',                     // Billing Address
            '',                                       // Billing Street2
            $party['city'] ?? '',                     // Billing City
            $party['state'] ?? '',                    // Billing State
            'India',                                  // Billing Country
            '',                                       // Billing Code
            '',                                       // Billing Fax
            $party['city'] ?? '',                     // Shipping Address
            '',                                       // Shipping Street2
            $party['city'] ?? '',                     // Shipping City
            $party['state'] ?? '',                    // Shipping State
            'India',                                  // Shipping Country
            '',                                       // Shipping Code
            '',                                       // Shipping Fax
            $gstTreatment,                           // GST Treatment
            $party['gstin'] ?? '',                   // GSTIN
            $party['state'] ?? '',                   // Place of Contact
            'Imported from legacy system',           // Notes
            $party['id'],                            // Custom Field 1 (store original ID)
            $party['party_type']                     // Custom Field 2 (store original type)
        ];
        
        fwrite($fp, formatCsvRow($row));
        $exported++;
    }
    
    fclose($fp);
    return "Exported $exported contacts to $filename";
}

// Export Items (Products)
function exportItems(): string
{
    $filename = __DIR__ . '/exports/zoho_items_' . date('Y-m-d_His') . '.csv';
    $fp = fopen($filename, 'w');
    
    if (!$fp) {
        throw new RuntimeException("Cannot create file: $filename");
    }
    
    // Zoho Books Items CSV Headers
    // https://www.zoho.com/books/help/import-data/items.html
    $headers = [
        'Item Name', 'SKU', 'Unit', 'Description', 'Item Type',
        'Product Type', 'Sales Rate', 'Purchase Rate', 'Tax', 'Tax Type',
        'Sales Account', 'Purchase Account', 'Inventory Account',
        'Opening Stock', 'Opening Stock Rate', 'Reorder Level',
        'Manufacturer', 'Brand', 'UPC', 'EAN', 'ISBN',
        'Part Number', 'HSN/SAC', 'SAC', 'Exemption Reason',
        'Custom Field 1', 'Custom Field 2'
    ];
    
    fwrite($fp, formatCsvRow($headers));
    
    // Fetch all items
    $items = fetchAll("
        SELECT 
            id,
            canonical_name,
            hsn,
            is_active,
            is_prepackaged_labelled
        FROM items
        WHERE is_active = 1
        ORDER BY canonical_name
    ");
    
    $exported = 0;
    foreach ($items as $item) {
        $row = [
            $item['canonical_name'],                 // Item Name
            'ITEM-' . $item['id'],                   // SKU
            'pcs',                                   // Unit (default to pieces)
            $item['canonical_name'],                 // Description
            'sales_and_purchases',                   // Item Type
            'goods',                                 // Product Type
            '',                                      // Sales Rate (to be filled in Zoho)
            '',                                      // Purchase Rate
            '',                                      // Tax (will be set per transaction)
            'taxable',                               // Tax Type
            'Sales',                                 // Sales Account
            'Cost of Goods Sold',                    // Purchase Account
            'Inventory Asset',                       // Inventory Account
            '',                                      // Opening Stock
            '',                                      // Opening Stock Rate
            '',                                      // Reorder Level
            '',                                      // Manufacturer
            '',                                      // Brand
            '',                                      // UPC
            '',                                      // EAN
            '',                                      // ISBN
            '',                                      // Part Number
            $item['hsn'] ?? '',                      // HSN/SAC
            '',                                      // SAC
            '',                                      // Exemption Reason
            $item['id'],                             // Custom Field 1 (original ID)
            $item['is_prepackaged_labelled'] ? 'Prepackaged' : '' // Custom Field 2
        ];
        
        fwrite($fp, formatCsvRow($row));
        $exported++;
    }
    
    fclose($fp);
    return "Exported $exported items to $filename";
}

// Export Invoices
function exportInvoices(string $type = 'sale'): string
{
    $typeLabel = $type === 'sale' ? 'sales' : 'purchase';
    $filename = __DIR__ . '/exports/zoho_' . $typeLabel . '_invoices_' . date('Y-m-d_His') . '.csv';
    $fp = fopen($filename, 'w');
    
    if (!$fp) {
        throw new RuntimeException("Cannot create file: $filename");
    }
    
    // Zoho Books Invoice CSV Headers
    $headers = [
        'Invoice Number', 'Invoice Date', 'Customer Name', 'GSTIN',
        'Place of Supply', 'Item Name', 'Quantity', 'Rate', 'Discount',
        'Tax Name', 'Tax Percentage', 'Item Total',
        'Notes', 'Terms & Conditions', 'Status'
    ];
    
    fwrite($fp, formatCsvRow($headers));
    
    // Fetch invoices with line items
    $invoices = fetchAll("
        SELECT 
            i.invoice_no,
            i.invoice_date,
            p.name as party_name,
            p.gstin,
            i.place_of_supply,
            i.status,
            i.inv_type,
            ii.item_id,
            it.canonical_name as item_name,
            ii.quantity,
            ii.rate,
            ii.discount_percent,
            ii.cgst_rate,
            ii.sgst_rate,
            ii.igst_rate
        FROM invoices i
        JOIN parties p ON i.party_id = p.id
        LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
        LEFT JOIN items it ON ii.item_id = it.id
        WHERE i.inv_type = :inv_type
            AND i.status = 'final'
        ORDER BY i.invoice_date DESC, i.invoice_no, ii.id
    ", [':inv_type' => $type]);
    
    $exported = 0;
    foreach ($invoices as $inv) {
        if (!$inv['item_name']) {
            continue; // Skip invoices without line items
        }
        
        $cgst = (float)($inv['cgst_rate'] ?? 0);
        $sgst = (float)($inv['sgst_rate'] ?? 0);
        $igst = (float)($inv['igst_rate'] ?? 0);
        $totalTax = $cgst + $sgst + $igst;
        
        // Determine tax name based on rates
        if ($igst > 0) {
            $taxName = "IGST@{$igst}%";
        } elseif ($cgst > 0 || $sgst > 0) {
            $taxName = "GST@{$totalTax}%";
        } else {
            $taxName = "Exempt";
        }
        
        $quantity = (float)($inv['quantity'] ?? 0);
        $rate = (float)($inv['rate'] ?? 0);
        $discount = (float)($inv['discount_percent'] ?? 0);
        
        $itemTotal = $quantity * $rate * (1 - $discount / 100);
        
        $row = [
            $inv['invoice_no'],
            date('Y-m-d', strtotime($inv['invoice_date'])),
            $inv['party_name'],
            $inv['gstin'] ?? '',
            $inv['place_of_supply'] ?? '',
            $inv['item_name'],
            $quantity,
            $rate,
            $discount,
            $taxName,
            $totalTax,
            number_format($itemTotal, 2, '.', ''),
            'Imported from legacy system',
            '',
            $inv['status']
        ];
        
        fwrite($fp, formatCsvRow($row));
        $exported++;
    }
    
    fclose($fp);
    return "Exported $exported invoice lines to $filename";
}

// Main execution
if (php_sapi_name() === 'cli') {
    // CLI mode
    $action = $argv[1] ?? 'all';
    
    echo "Zoho Books Export Utility\n";
    echo "=========================\n\n";
    
    try {
        switch ($action) {
            case 'contacts':
                echo exportContacts() . "\n";
                break;
            case 'items':
                echo exportItems() . "\n";
                break;
            case 'sales':
                echo exportInvoices('sale') . "\n";
                break;
            case 'purchases':
                echo exportInvoices('purchase') . "\n";
                break;
            case 'all':
            default:
                echo exportContacts() . "\n";
                echo exportItems() . "\n";
                echo exportInvoices('sale') . "\n";
                echo exportInvoices('purchase') . "\n";
                echo "\nAll exports completed successfully!\n";
                break;
        }
        
        echo "\nExport files are in: " . __DIR__ . "/exports/\n";
        echo "\nNext steps:\n";
        echo "1. Log in to https://books.zoho.in/app\n";
        echo "2. Go to Settings > Import Data\n";
        echo "3. Import in this order: Contacts → Items → Invoices\n";
        echo "4. Review imported data and adjust tax rates/accounts as needed\n";
        
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
} elseif (isset($_GET['export'])) {
    // Web mode
    header('Content-Type: text/plain; charset=utf-8');
    
    try {
        $type = $_GET['export'];
        
        switch ($type) {
            case 'contacts':
                echo exportContacts();
                break;
            case 'items':
                echo exportItems();
                break;
            case 'sales':
                echo exportInvoices('sale');
                break;
            case 'purchases':
                echo exportInvoices('purchase');
                break;
            default:
                echo "Invalid export type. Use: contacts, items, sales, or purchases";
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo "ERROR: " . $e->getMessage();
    }
} else {
    // Show web interface
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zoho Books Export</title>
    <style>
        :root { color-scheme: dark; }
        body { 
            margin: 0; 
            background: #0b0c10; 
            color: #e5e7eb; 
            font-family: system-ui, sans-serif;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #60a5fa; }
        .card {
            background: #0f172a;
            border: 1px solid #1f2937;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #1d4ed8;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 5px;
        }
        .btn:hover { background: #1e40af; }
        ul { line-height: 1.8; }
        .muted { color: #94a3b8; }
        code {
            background: #0b1120;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📤 Zoho Books Export Utility</h1>
        
        <div class="card">
            <h2>Export Data</h2>
            <p class="muted">Click below to export your data for Zoho Books import:</p>
            <a href="?export=contacts" class="btn">Export Contacts</a>
            <a href="?export=items" class="btn">Export Items</a>
            <a href="?export=sales" class="btn">Export Sales Invoices</a>
            <a href="?export=purchases" class="btn">Export Purchase Invoices</a>
        </div>
        
        <div class="card">
            <h2>📋 Import Instructions</h2>
            <ol>
                <li>Click the export buttons above to generate CSV files</li>
                <li>Log in to <a href="https://books.zoho.in/app" target="_blank">Zoho Books</a></li>
                <li>Navigate to <strong>Settings → Import Data</strong></li>
                <li>Import in this order:
                    <ol type="a">
                        <li><strong>Contacts</strong> (Customers and Vendors) first</li>
                        <li><strong>Items</strong> (Products) second</li>
                        <li><strong>Invoices</strong> (Sales/Purchase) last</li>
                    </ol>
                </li>
                <li>Review imported data and configure:
                    <ul>
                        <li>Tax rates for each item</li>
                        <li>Default accounts</li>
                        <li>Payment terms</li>
                    </ul>
                </li>
            </ol>
        </div>
        
        <div class="card">
            <h2>⚠️ Important Notes</h2>
            <ul>
                <li>Parties marked as "both" will be exported as customers - create vendor records manually if needed</li>
                <li>HSN codes are included but verify GST rates in Zoho Books</li>
                <li>Invoice line items are exported - Zoho will calculate taxes based on your settings</li>
                <li>Opening stock balances are not included - enter manually if needed</li>
                <li>Custom fields store original IDs for reference</li>
            </ul>
        </div>
        
        <div class="card">
            <h2>🖥️ CLI Usage</h2>
            <p>You can also run exports from the command line:</p>
            <code>php zoho_export.php all</code><br>
            <code>php zoho_export.php contacts</code><br>
            <code>php zoho_export.php items</code><br>
            <code>php zoho_export.php sales</code><br>
            <code>php zoho_export.php purchases</code>
        </div>
    </div>
</body>
</html>
    <?php
}
