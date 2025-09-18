<?php
/**
 * Fixed Purchase Register Report
 * This corrects the reports.php to generate output matching Purchase_Summary.csv format
 */

function get_pdo(): PDO {
    // Try different connection methods
    foreach ([
        ['127.0.0.1', 'gstwork', 'gstwork@123'],
        ['127.0.0.1', 'root', ''],
        ['localhost', 'gstwork', 'gstwork@123'],
        ['localhost', 'root', '']
    ] as [$host, $user, $pass]) {
        try {
            $dsn = "mysql:host={$host};port=3306;dbname=gst_accounting;charset=utf8mb4";
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        } catch (PDOException $e) {
            continue; // Try next connection
        }
    }
    die("Could not connect to database");
}

$pdo = get_pdo();

// Inputs
$report = $_GET['report'] ?? 'purchase';
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$party_id = isset($_GET['party_id']) && $_GET['party_id'] !== '' ? (int)$_GET['party_id'] : null;
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$pagesz = min(200, max(10, (int)($_GET['pagesz'] ?? 50)));
$action = $_GET['action'] ?? '';

// Get parties for dropdown
$parties = $pdo->query("SELECT id, name FROM parties WHERE party_type IN ('supplier', 'both') ORDER BY name")->fetchAll();

// Build enhanced purchase register query
function fetch_enhanced_purchase_register(PDO $pdo, string $from, string $to, ?int $party_id, string $q, int $page, int $pagesz, bool $for_export = false) {
    $bind = [];
    $whereClause = "WHERE i.inv_type = 'purchase'";
    
    // Date filters
    if ($from !== '') {
        $whereClause .= " AND i.invoice_date >= :from";
        $bind[':from'] = $from;
    }
    if ($to !== '') {
        $whereClause .= " AND i.invoice_date <= :to";
        $bind[':to'] = $to;
    }
    
    // Party filter  
    if ($party_id !== null) {
        $whereClause .= " AND i.party_id = :party";
        $bind[':party'] = $party_id;
    }
    
    // Search filter
    if ($q !== '') {
        $whereClause .= " AND (i.invoice_no LIKE :q OR p.name LIKE :q)";
        $bind[':q'] = "%{$q}%";
    }

    // Count total invoices
    $sqlCount = "SELECT COUNT(DISTINCT i.id) FROM invoices i JOIN parties p ON p.id = i.party_id {$whereClause}";
    $stmtC = $pdo->prepare($sqlCount);
    $stmtC->execute($bind);
    $total_rows = (int)$stmtC->fetchColumn();

    // Calculate totals for all filtered data
    $sqlTotals = "
        SELECT 
            COALESCE(SUM(ii.taxable_amount), 0) AS taxable,
            COALESCE(SUM(ii.cgst_amount), 0) AS cgst,
            COALESCE(SUM(ii.sgst_amount), 0) AS sgst,
            COALESCE(SUM(ii.igst_amount), 0) AS igst
        FROM invoices i 
        JOIN parties p ON p.id = i.party_id 
        JOIN invoice_items ii ON ii.invoice_id = i.id 
        {$whereClause}
    ";
    $stmtT = $pdo->prepare($sqlTotals);
    $stmtT->execute($bind);
    $tot = $stmtT->fetch() ?: ['taxable'=>0,'cgst'=>0,'sgst'=>0,'igst'=>0];
    foreach (['taxable','cgst','sgst','igst'] as $k) $tot[$k] = (float)($tot[$k] ?? 0);
    $tot['grand'] = $tot['taxable'] + $tot['cgst'] + $tot['sgst'] + $tot['igst'];

    // Main data query with enhanced fields
    $sqlData = "
        SELECT 
            i.id,
            i.invoice_date,
            i.invoice_no,
            p.name AS supplier_name,
            p.gstin AS supplier_gstin,
            p.state AS supplier_state,
            COALESCE(i.place_of_supply, p.state, '') AS place_of_supply,
            
            -- Aggregated amounts per invoice
            ROUND(COALESCE(SUM(ii.taxable_amount), 0), 2) AS taxable,
            ROUND(COALESCE(SUM(ii.cgst_amount), 0), 2) AS cgst,
            ROUND(COALESCE(SUM(ii.sgst_amount), 0), 2) AS sgst,
            ROUND(COALESCE(SUM(ii.igst_amount), 0), 2) AS igst,
            ROUND(COALESCE(SUM(ii.line_total), 0), 2) AS bill_amount,
            ROUND(COALESCE(SUM(ii.cgst_amount + ii.sgst_amount + ii.igst_amount), 0), 2) AS gst_amount,
            
            -- Count items per invoice for qty field
            COUNT(ii.id) AS qty,
            
            -- Default values for missing fields
            'Auto' AS purchasetype,
            '' AS remarks,
            '' AS order_id,
            '' AS display_code,
            '' AS po_no,
            '' AS po_date,
            0 AS cash_discount,
            0 AS cash_discount_percent,
            0 AS total_discount,
            0 AS manual_discount,
            0 AS round_off,
            0 AS gst_cess_amount,
            'JAYANTI ENTERPRISES' AS branch_name,
            'credit' AS payment_terms,
            'credit' AS grn_type,
            'ADMIN' AS login_id
            
        FROM invoices i
        JOIN parties p ON p.id = i.party_id
        LEFT JOIN invoice_items ii ON ii.invoice_id = i.id
        {$whereClause}
        GROUP BY i.id, i.invoice_date, i.invoice_no, p.name, p.gstin, p.state, i.place_of_supply
        ORDER BY i.invoice_date DESC, i.invoice_no DESC
    ";
    
    if (!$for_export) {
        $offset = ($page - 1) * $pagesz;
        $sqlData .= " LIMIT :lim OFFSET :off";
    }
    
    $stmtD = $pdo->prepare($sqlData);
    foreach ($bind as $k => $v) $stmtD->bindValue($k, $v);
    
    if (!$for_export) {
        $stmtD->bindValue(':lim', $pagesz, PDO::PARAM_INT);
        $stmtD->bindValue(':off', $offset, PDO::PARAM_INT);
    }
    
    $stmtD->execute();
    $rows = $stmtD->fetchAll();
    
    // Calculate supplier_payable and grn_amount (same as bill_amount)
    foreach ($rows as &$r) {
        $r['supplier_payable'] = $r['bill_amount'];
        $r['grn_amount'] = $r['bill_amount'];
        $r['grand_total'] = $r['bill_amount']; // For display compatibility
    }
    
    return [$rows, $total_rows, $tot];
}

// Export CSV Handler
if ($action === 'export_csv') {
    [$rows, , $tot] = fetch_enhanced_purchase_register($pdo, $from, $to, $party_id, $q, 1, 1000000, true);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="purchase_register_enhanced.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Headers matching Purchase_Summary.csv exactly
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
    foreach ($rows as $row) {
        $csvRow = [
            $row['cgst'],                    // cgst
            $row['remarks'],                 // remarks  
            $row['igst'],                    // igst
            $row['sgst'],                    // sgst
            $row['purchasetype'],            // purchasetype
            $row['taxable'],                 // taxable
            $row['order_id'],                // order_id
            $row['display_code'],            // display_code
            $row['invoice_no'],              // chalanno
            $row['invoice_no'],              // GRN No
            date('d/m/Y', strtotime($row['invoice_date'])), // GRN Date
            $row['po_no'],                   // PO No
            $row['po_date'],                 // PO Date
            $row['supplier_name'],           // Supplier Name
            $row['invoice_no'],              // Supplier Bill NO
            date('d/m/Y', strtotime($row['invoice_date'])), // Suplier Bill Date
            $row['bill_amount'],             // Bill Amount
            $row['cash_discount'],           // Cash Discount
            $row['cash_discount_percent'],   // Cash Discount %
            $row['total_discount'],          // Total Discount
            $row['manual_discount'],         // Manual Discount
            $row['round_off'],               // Round Off
            $row['supplier_payable'],        // Supplier Payable
            $row['grn_amount'],              // GRN Amount
            $row['branch_name'],             // Branch Name
            $row['payment_terms'],           // Payment Terms
            $row['grn_type'],                // GRN Type
            $row['login_id'],                // Login ID
            $row['qty'],                     // qty
            $row['gst_cess_amount'],         // GST CESS Amnt
            $row['gst_amount'],              // GST Amnt
            '',                              // Supplier Code (empty)
            $row['supplier_state'],          // Supplier State
            $row['supplier_gstin']           // Supplier GSTIN
        ];
        fputcsv($output, $csvRow);
    }
    
    fclose($output);
    exit;
}

// Fetch current page data
[$rows, $total_rows, $tot] = fetch_enhanced_purchase_register($pdo, $from, $to, $party_id, $q, $page, $pagesz);
$total_pages = max(1, (int)ceil($total_rows / $pagesz));

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Purchase Register</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .header { background: #f4f4f4; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .form-row { display: flex; gap: 15px; margin: 10px 0; flex-wrap: wrap; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: bold; margin-bottom: 5px; }
        input, select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #005a87; }
        .btn-export { background: #28a745; }
        .btn-export:hover { background: #1e7e34; }
        
        table { border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; position: sticky; top: 0; }
        .amount { text-align: right; font-family: monospace; }
        .total-row { background-color: #e9ecef; font-weight: bold; }
        
        .summary { background: #e8f4f8; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .pagination { margin: 20px 0; }
        .pagination a { padding: 8px 12px; margin: 0 2px; text-decoration: none; border: 1px solid #ddd; }
        .pagination a.current { background: #007cba; color: white; }
        
        @media (max-width: 768px) {
            .form-row { flex-direction: column; }
            table { font-size: 10px; }
            th, td { padding: 4px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Enhanced Purchase Register</h1>
        <p>Generate reports that match Purchase_Summary.csv format</p>
    </div>
    
    <form method="GET">
        <div class="form-row">
            <div class="form-group">
                <label for="from">From Date:</label>
                <input type="date" id="from" name="from" value="<?= h($from) ?>">
            </div>
            
            <div class="form-group">
                <label for="to">To Date:</label>
                <input type="date" id="to" name="to" value="<?= h($to) ?>">
            </div>
            
            <div class="form-group">
                <label for="party_id">Supplier:</label>
                <select id="party_id" name="party_id">
                    <option value="">All Suppliers</option>
                    <?php foreach ($parties as $party): ?>
                        <option value="<?= $party['id'] ?>" <?= $party_id == $party['id'] ? 'selected' : '' ?>>
                            <?= h($party['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="q">Search:</label>
                <input type="text" id="q" name="q" value="<?= h($q) ?>" placeholder="Invoice No or Supplier">
            </div>
        </div>
        
        <div class="form-row">
            <button type="submit" class="btn">Show Report</button>
            <button type="submit" name="action" value="export_csv" class="btn btn-export">📊 Export CSV (Purchase Summary Format)</button>
        </div>
    </form>

    <?php if (!empty($rows)): ?>
        <div class="summary">
            <strong>Summary:</strong> 
            <?= count($rows) ?> invoices shown (<?= $total_rows ?> total) | 
            Taxable: ₹<?= number_format($tot['taxable'], 2) ?> | 
            GST: ₹<?= number_format($tot['cgst'] + $tot['sgst'] + $tot['igst'], 2) ?> | 
            Grand Total: ₹<?= number_format($tot['grand'], 2) ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice No</th>
                    <th>Supplier</th>
                    <th>GSTIN</th>
                    <th>Taxable</th>
                    <th>CGST</th>
                    <th>SGST</th>
                    <th>IGST</th>
                    <th>GST Total</th>
                    <th>Bill Amount</th>
                    <th>Items</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($row['invoice_date'])) ?></td>
                        <td><?= h($row['invoice_no']) ?></td>
                        <td><?= h($row['supplier_name']) ?></td>
                        <td><?= h($row['supplier_gstin']) ?></td>
                        <td class="amount"><?= number_format($row['taxable'], 2) ?></td>
                        <td class="amount"><?= number_format($row['cgst'], 2) ?></td>
                        <td class="amount"><?= number_format($row['sgst'], 2) ?></td>
                        <td class="amount"><?= number_format($row['igst'], 2) ?></td>
                        <td class="amount"><?= number_format($row['gst_amount'], 2) ?></td>
                        <td class="amount"><?= number_format($row['bill_amount'], 2) ?></td>
                        <td class="amount"><?= $row['qty'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= min($total_pages, 20); $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="<?= $i == $page ? 'current' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($total_pages > 20): ?>
                    <span>... (<?= $total_pages ?> total pages)</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="summary">
            <p>No purchase invoices found with the current filters.</p>
        </div>
    <?php endif; ?>
</body>
</html>