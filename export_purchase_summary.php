<?php
/**
 * Export Purchase Summary Report - Enhanced Version
 * This script generates a CSV that matches the Purchase_Summary.csv format
 * using data from the imported database.
 */

// Database connection function (reuse from reports.php)
function get_pdo(): PDO {
    $host = '127.0.0.1';
    $port = '3306';
    $dbname = 'gst_accounting';
    $username = 'gstwork';
    $password = 'gstwork@123';
    
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

$pdo = get_pdo();

// Input parameters
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$party_id = isset($_GET['party_id']) && $_GET['party_id'] !== '' ? (int)$_GET['party_id'] : null;
$action = $_GET['action'] ?? '';

// Build filters
$bind = [];
$whereClause = "WHERE i.inv_type = 'purchase'";

if ($from !== '') {
    $whereClause .= " AND i.invoice_date >= :from";
    $bind[':from'] = $from;
}
if ($to !== '') {
    $whereClause .= " AND i.invoice_date <= :to";
    $bind[':to'] = $to;
}
if ($party_id !== null) {
    $whereClause .= " AND i.party_id = :party";
    $bind[':party'] = $party_id;
}

// Enhanced SQL query that matches Purchase_Summary.csv structure
$sql = "
SELECT 
    i.id as invoice_id,
    i.invoice_no,
    i.invoice_date,
    p.name AS supplier_name,
    p.gstin AS supplier_gstin,
    p.state AS supplier_state,
    i.place_of_supply,
    
    -- Aggregated amounts per invoice
    ROUND(SUM(ii.taxable_amount), 2) AS taxable,
    ROUND(SUM(ii.cgst_amount), 2) AS cgst,
    ROUND(SUM(ii.sgst_amount), 2) AS sgst,
    ROUND(SUM(ii.igst_amount), 2) AS igst,
    ROUND(SUM(ii.line_total), 2) AS bill_amount,
    ROUND(SUM(ii.cgst_amount + ii.sgst_amount + ii.igst_amount), 2) AS gst_amount,
    
    -- Count items
    COUNT(ii.id) AS item_count,
    
    -- Additional fields to match original format
    'Auto' AS purchasetype,
    'credit' AS payment_terms,
    'credit' AS grn_type,
    'JAYANTI ENTERPRISES' AS branch_name,
    'ADMIN' AS login_id,
    0 AS cash_discount,
    0 AS cash_discount_percent,
    0 AS total_discount,
    0 AS manual_discount,
    0 AS round_off,
    0 AS gst_cess_amount
    
FROM invoices i
JOIN parties p ON p.id = i.party_id
JOIN invoice_items ii ON ii.invoice_id = i.id
{$whereClause}
GROUP BY i.id, i.invoice_no, i.invoice_date, p.name, p.gstin, p.state, i.place_of_supply
ORDER BY i.invoice_date DESC, i.invoice_no DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($bind);
$invoices = $stmt->fetchAll();

// Export to CSV if requested
if ($action === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="purchase_summary_export.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers matching Purchase_Summary.csv
    $headers = [
        'cgst', 'remarks', 'igst', 'sgst', 'purchasetype', 'taxable', 
        'order_id', 'display_code', 'chalanno', 'GRN No', 'GRN Date', 
        'PO No', 'PO Date', 'Supplier Name', 'Supplier Bill NO', 
        'Suplier Bill Date', 'Bill Amount', 'Cash Discount', 'Cash Discount %', 
        'Total Discount', 'Manual Discount', 'Round Off', 'Supplier Payable', 
        'GRN Amount', 'Branch Name', 'Payment Terms', 'GRN Type', 'Login ID', 
        'qty', 'GST CESS Amnt', 'GST Amnt', 'Supplier Code', 'Supplier State', 
        'Supplier GSTIN'
    ];
    
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($invoices as $row) {
        $csvRow = [
            $row['cgst'],                    // cgst
            '',                              // remarks
            $row['igst'],                    // igst
            $row['sgst'],                    // sgst  
            $row['purchasetype'],            // purchasetype
            $row['taxable'],                 // taxable
            '',                              // order_id
            '',                              // display_code
            $row['invoice_no'],              // chalanno (using invoice_no)
            $row['invoice_no'],              // GRN No
            date('d/m/Y', strtotime($row['invoice_date'])), // GRN Date
            '',                              // PO No
            '',                              // PO Date
            $row['supplier_name'],           // Supplier Name
            $row['invoice_no'],              // Supplier Bill NO
            date('d/m/Y', strtotime($row['invoice_date'])), // Suplier Bill Date
            $row['bill_amount'],             // Bill Amount
            $row['cash_discount'],           // Cash Discount
            $row['cash_discount_percent'],   // Cash Discount %
            $row['total_discount'],          // Total Discount
            $row['manual_discount'],         // Manual Discount
            $row['round_off'],               // Round Off
            $row['bill_amount'],             // Supplier Payable
            $row['bill_amount'],             // GRN Amount
            $row['branch_name'],             // Branch Name
            $row['payment_terms'],           // Payment Terms
            $row['grn_type'],                // GRN Type
            $row['login_id'],                // Login ID
            $row['item_count'],              // qty
            $row['gst_cess_amount'],         // GST CESS Amnt
            $row['gst_amount'],              // GST Amnt
            '',                              // Supplier Code
            $row['supplier_state'],          // Supplier State
            $row['supplier_gstin']           // Supplier GSTIN
        ];
        fputcsv($output, $csvRow);
    }
    
    fclose($output);
    exit;
}

// Get parties for dropdown
$parties = $pdo->query("SELECT id, name FROM parties WHERE party_type IN ('supplier', 'both') ORDER BY name")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Summary Export</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin: 10px 0; }
        label { display: inline-block; width: 120px; }
        input, select { padding: 5px; margin: 5px; }
        .btn { background: #007cba; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .btn:hover { background: #005a87; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .amount { text-align: right; }
        .total-row { background-color: #f9f9f9; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Purchase Summary Report</h1>
    
    <form method="GET">
        <div class="form-group">
            <label for="from">From Date:</label>
            <input type="date" id="from" name="from" value="<?= htmlspecialchars($from) ?>">
        </div>
        
        <div class="form-group">
            <label for="to">To Date:</label>
            <input type="date" id="to" name="to" value="<?= htmlspecialchars($to) ?>">
        </div>
        
        <div class="form-group">
            <label for="party_id">Supplier:</label>
            <select id="party_id" name="party_id">
                <option value="">All Suppliers</option>
                <?php foreach ($parties as $party): ?>
                    <option value="<?= $party['id'] ?>" <?= $party_id == $party['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($party['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn">Show Report</button>
            <button type="submit" name="action" value="export_csv" class="btn">Export CSV</button>
        </div>
    </form>

    <?php if (!empty($invoices)): ?>
        <h2>Purchase Summary (<?= count($invoices) ?> invoices)</h2>
        
        <?php
        // Calculate totals
        $totals = [
            'taxable' => 0,
            'cgst' => 0, 
            'sgst' => 0,
            'igst' => 0,
            'bill_amount' => 0,
            'gst_amount' => 0
        ];
        
        foreach ($invoices as $inv) {
            $totals['taxable'] += $inv['taxable'];
            $totals['cgst'] += $inv['cgst'];
            $totals['sgst'] += $inv['sgst'];
            $totals['igst'] += $inv['igst'];
            $totals['bill_amount'] += $inv['bill_amount'];
            $totals['gst_amount'] += $inv['gst_amount'];
        }
        ?>
        
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice No</th>
                    <th>Supplier</th>
                    <th>Taxable Amount</th>
                    <th>CGST</th>
                    <th>SGST</th>
                    <th>IGST</th>
                    <th>GST Total</th>
                    <th>Bill Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td>
                        <td><?= htmlspecialchars($inv['invoice_no']) ?></td>
                        <td><?= htmlspecialchars($inv['supplier_name']) ?></td>
                        <td class="amount"><?= number_format($inv['taxable'], 2) ?></td>
                        <td class="amount"><?= number_format($inv['cgst'], 2) ?></td>
                        <td class="amount"><?= number_format($inv['sgst'], 2) ?></td>
                        <td class="amount"><?= number_format($inv['igst'], 2) ?></td>
                        <td class="amount"><?= number_format($inv['gst_amount'], 2) ?></td>
                        <td class="amount"><?= number_format($inv['bill_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="3">TOTAL</td>
                    <td class="amount"><?= number_format($totals['taxable'], 2) ?></td>
                    <td class="amount"><?= number_format($totals['cgst'], 2) ?></td>
                    <td class="amount"><?= number_format($totals['sgst'], 2) ?></td>
                    <td class="amount"><?= number_format($totals['igst'], 2) ?></td>
                    <td class="amount"><?= number_format($totals['gst_amount'], 2) ?></td>
                    <td class="amount"><?= number_format($totals['bill_amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>