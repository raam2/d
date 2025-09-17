<?php
// db_admin.php -- Simple database admin page for GST accounting
//
// This script provides two main functions beyond the existing schema explorer:
// 1) Detect and fix purchase invoices that have missing or invalid item mappings.
//    When run, it will create new items based on the description/HSN of each
//    invoice line and update the invoice_items table accordingly. Only
//    purchase invoices belonging to the unregistered vendor (party_id=4) are
//    considered. No existing purchase records are deleted.
// 2) Export the entire database (structure and data) as a single SQL script.
//
// The user interface offers buttons to perform each action. You must be
// logged in or otherwise authorised to access this script. It is intended
// for administrators. Use at your own risk on production databases.

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Helper: Generate a full SQL dump of the current database
function generate_full_dump(PDO $pdo): string {
    $pdo->exec("SET SESSION group_concat_max_len = 10485760");
    $out = "-- Database export generated on " . date('Y-m-d H:i:s') . "\n\n";

    // Disable foreign key checks for import
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // Fetch all base tables
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as $tblRow) {
        $table = $tblRow[0];
        // Create statement
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $out .= $create['Create Table'] . ";\n\n";
        // Data
        $rows = $pdo->query("SELECT * FROM `$table`");
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $cols = array_map(static function ($k) { return "`" . str_replace("`", "``", $k) . "`"; }, array_keys($row));
            $vals = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $vals[] = 'NULL';
                } elseif (is_numeric($val)) {
                    // For numeric values, do not quote
                    $vals[] = $val;
                } else {
                    // Use PDO::quote for strings
                    $vals[] = $pdo->quote((string)$val);
                }
            }
            $out .= "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
        }
        $out .= "\n\n";
    }
    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

// Helper: Find missing/invalid item mappings in purchase invoices for party_id=4
function find_missing_item_rows(PDO $pdo): array {
    $sql = "SELECT ii.id AS invoice_item_id, inv.invoice_no, inv.invoice_date, ii.description, ii.hsn, ii.rate\n"
         . "FROM invoice_items ii\n"
         . "JOIN invoices inv ON inv.id = ii.invoice_id\n"
         . "WHERE inv.party_id = 4 AND inv.inv_type = 'purchase'\n"
         . "  AND (ii.item_id IS NULL OR NOT EXISTS (SELECT 1 FROM items it WHERE it.id = ii.item_id))\n"
         . "ORDER BY inv.invoice_date, inv.invoice_no, ii.id";
    return $pdo->query($sql)->fetchAll();
}

// Helper: Fix missing item mappings by inserting new items and updating invoice_items
function fix_missing_item_rows(PDO $pdo): array {
    $output = ['inserted_items' => 0, 'updated_lines' => 0];
    // Insert new items for descriptions that do not already exist
    $insertSql = "INSERT INTO items (company_id, canonical_name, hsn, is_SSC_PAYABLE, is_active, is_prepackaged_labelled)\n"
               . "SELECT 1 AS company_id, TRIM(ii.description) AS canonical_name,\n"
               . "       NULLIF(TRIM(ii.hsn), '') AS hsn, 0 AS is_SSC_PAYABLE, 1 AS is_active,\n"
               . "       COALESCE(ii.is_prepackaged_labelled, 0) AS is_prepackaged_labelled\n"
               . "FROM invoice_items ii\n"
               . "JOIN invoices inv ON inv.id = ii.invoice_id\n"
               . "LEFT JOIN items it ON it.company_id = 1 AND it.canonical_name = TRIM(ii.description)\n"
               . "WHERE inv.party_id = 4 AND inv.inv_type = 'purchase'\n"
               . "  AND (ii.item_id IS NULL OR NOT EXISTS (SELECT 1 FROM items it2 WHERE it2.id = ii.item_id))\n"
               . "  AND it.id IS NULL\n"
               . "GROUP BY TRIM(ii.description), ii.hsn";
    $output['inserted_items'] = $pdo->exec($insertSql);

    // Update invoice_items with newly inserted or existing items
    $updateSql = "UPDATE invoice_items ii\n"
               . "JOIN invoices inv ON inv.id = ii.invoice_id\n"
               . "JOIN items it ON it.company_id = 1 AND it.canonical_name = TRIM(ii.description)\n"
               . "SET ii.item_id = it.id\n"
               . "WHERE inv.party_id = 4 AND inv.inv_type = 'purchase'\n"
               . "  AND (ii.item_id IS NULL OR NOT EXISTS (SELECT 1 FROM items it2 WHERE it2.id = ii.item_id))";
    $output['updated_lines'] = $pdo->exec($updateSql);

    return $output;
}

// Determine action based on query parameters
$action = $_GET['action'] ?? '';
$section = $_GET['section'] ?? '';

// If export requested, output SQL and exit
if ($section === 'export') {
    $dump = generate_full_dump($pdo);
    // Stream the SQL to the client
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="gst_accounting_export.sql"');
    header('Content-Length: ' . strlen($dump));
    echo $dump;
    exit;
}

// If fix requested, perform fix and display results
$fixResult = null;
if ($action === 'fix_missing') {
    $fixResult = fix_missing_item_rows($pdo);
}

// Retrieve missing items if requested or after fixing
$missingRows = [];
if ($action === 'find_missing' || $action === 'fix_missing') {
    $missingRows = find_missing_item_rows($pdo);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Admin – GST Accounting</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 1em; background-color: #f9f9f9; }
        h1 { font-size: 1.5em; margin-bottom: 1em; }
        button { padding: 0.5em 1em; margin: 0.3em; font-size: 1em; }
        table { border-collapse: collapse; width: 100%; margin-top: 1em; }
        th, td { border: 1px solid #ccc; padding: 0.4em; text-align: left; }
        th { background-color: #eee; }
        .alert { padding: 0.8em; background-color: #fffae6; border: 1px solid #f0e0b0; margin-top: 1em; }
    </style>
</head>
<body>
    <h1>Database Admin Panel</h1>
    <p>Use this page to audit and correct missing item mappings in purchase invoices and to export the entire database. Actions apply only to invoices belonging to the unregistered vendor (party_id&nbsp;= 4). No existing purchase records will be deleted.</p>

    <!-- Action Buttons -->
    <form method="get" style="display:inline">
        <input type="hidden" name="action" value="find_missing" />
        <button type="submit">Find Missing Items</button>
    </form>
    <form method="get" style="display:inline">
        <input type="hidden" name="action" value="fix_missing" />
        <button type="submit">Fix Missing Items</button>
    </form>
    <form method="get" style="display:inline">
        <input type="hidden" name="section" value="export" />
        <button type="submit">Export Full Database</button>
    </form>

<?php if ($fixResult !== null): ?>
    <div class="alert">
        <strong>Fix completed.</strong>
        Inserted <?php echo (int)$fixResult['inserted_items']; ?> new items and updated <?php echo (int)$fixResult['updated_lines']; ?> invoice items.
    </div>
<?php endif; ?>

<?php if (!empty($missingRows)): ?>
    <h2>Purchase Invoice Items with Missing/Invalid item_id (<?php echo count($missingRows); ?> found)</h2>
    <table>
        <thead>
            <tr>
                <th>Invoice No</th>
                <th>Date</th>
                <th>Item Description</th>
                <th>HSN</th>
                <th>Rate</th>
                <th>Invoice Item ID</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($missingRows as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['invoice_no']); ?></td>
                    <td><?php echo htmlspecialchars($row['invoice_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($row['hsn'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($row['rate'] ?? ''); ?></td>
                    <td><?php echo (int)$row['invoice_item_id']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($action === 'find_missing' || $action === 'fix_missing'): ?>
    <div class="alert">
        <strong>No missing items found.</strong> All purchase invoices are properly linked.
    </div>
<?php endif; ?>

</body>
</html>