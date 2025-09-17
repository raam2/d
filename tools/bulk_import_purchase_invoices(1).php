<?php
/**
 * Bulk Import Purchase Invoices from CSV
 * 
 * Reads Purchase_Summary.csv and Purchase-Invoice-Detail-Enhanced.csv to import:
 * - invoices (purchase invoices)
 * - invoice_items (line items with GST calculations)
 * 
 * Prerequisites: Run bulk_import_purchase_items.php first to populate items table
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

/** ===== Database Connection ===== */
class Database {
    private $connection;

    public function getConnection() {
        if ($this->connection === null) {
            $host = '127.0.0.1';
            $port = '3306';
            $dbname = 'gst_accounting';
            $username = 'gstwork';
            $password = 'gstwork@123';

            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                $this->connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]);
            } catch (PDOException $e) {
                die("Connection failed: " . $e->getMessage());
            }
        }
        return $this->connection;
    }
}

$db = (new Database())->getConnection();

/** ===== Config ===== */
$SUMMARY_CSV = $argv[1] ?? 'Purchase_Summary.csv';
$DETAIL_CSV = $argv[2] ?? 'Purchase-Invoice-Detail-Enhanced.csv';
$COMPANY_ID = isset($argv[3]) ? (int)$argv[3] : 1;

if (!is_file($SUMMARY_CSV)) {
    fwrite(STDERR, "Summary CSV not found: {$SUMMARY_CSV}\n");
    exit(1);
}

if (!is_file($DETAIL_CSV)) {
    fwrite(STDERR, "Detail CSV not found: {$DETAIL_CSV}\n");
    exit(1);
}

/** ===== Helper Functions ===== */
function parseDate(string $dateStr): string {
    // Convert DD/MM/YYYY to YYYY-MM-DD
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $matches)) {
        return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
    }
    return date('Y-m-d'); // fallback to today
}

/** ===== Prepare SQL Statements ===== */
$pdo = $db;

// Get or create supplier
$getSupplier = $pdo->prepare("
    SELECT id FROM parties 
    WHERE company_id = :company_id AND name = :name AND party_type IN ('supplier', 'both')
    LIMIT 1
");

$insSupplier = $pdo->prepare("
    INSERT INTO parties (company_id, name, gstin, party_type, state)
    VALUES (:company_id, :name, :gstin, 'supplier', :state)
    ON DUPLICATE KEY UPDATE gstin = VALUES(gstin)
");

// Invoice operations
$insInvoice = $pdo->prepare("
    INSERT INTO invoices (company_id, party_id, inv_type, invoice_no, invoice_date, status)
    VALUES (:company_id, :party_id, 'purchase', :invoice_no, :invoice_date, 'final')
    ON DUPLICATE KEY UPDATE invoice_date = VALUES(invoice_date)
");

$getInvoiceId = $pdo->prepare("
    SELECT id FROM invoices 
    WHERE company_id = :company_id AND inv_type = 'purchase' AND invoice_no = :invoice_no
    LIMIT 1
");

// Get item ID by canonical name
$getItemId = $pdo->prepare("
    SELECT id FROM items 
    WHERE company_id = :company_id AND canonical_name = :name
    LIMIT 1
");

// Invoice items
$insInvoiceItem = $pdo->prepare("
    INSERT INTO invoice_items 
    (invoice_id, item_id, description, hsn, quantity, rate, cgst_rate, sgst_rate, igst_rate)
    VALUES 
    (:invoice_id, :item_id, :description, :hsn, :quantity, :rate, :cgst_rate, :sgst_rate, :igst_rate)
");

/** ===== Import Process ===== */
echo "🔄 Starting invoice import process...\n";

$pdo->beginTransaction();

try {
    /** ===== Step 1: Process Summary CSV for Suppliers & Invoices ===== */
    echo "📋 Processing suppliers and invoices from {$SUMMARY_CSV}...\n";

    $fhSummary = new SplFileObject($SUMMARY_CSV);
    $fhSummary->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
    $fhSummary->setCsvControl(',', '"', '\\');

    $headerSummary = $fhSummary->fgetcsv();
    $mapSummary = [];
    foreach ($headerSummary as $i => $h) {
        $mapSummary[strtolower(trim((string)$h))] = $i;
    }

    $suppliersCreated = 0;
    $invoicesCreated = 0;

    foreach ($fhSummary as $row) {
        if (!is_array($row) || empty($row[0])) continue;

        $supplierName = trim($row[$mapSummary['supplier name']] ?? '');
        $supplierGstin = trim($row[$mapSummary['supplier gstin']] ?? '');
        $supplierState = trim($row[$mapSummary['supplier state']] ?? '');
        $invoiceNo = trim($row[$mapSummary['supplier bill no']] ?? '');
        $invoiceDate = trim($row[$mapSummary['suplier bill date']] ?? '');

        if (!$supplierName || !$invoiceNo) continue;

        // Create/get supplier
        $getSupplier->execute([
            ':company_id' => $COMPANY_ID,
            ':name' => $supplierName
        ]);

        $supplierId = $getSupplier->fetchColumn();

        if (!$supplierId) {
            $insSupplier->execute([
                ':company_id' => $COMPANY_ID,
                ':name' => $supplierName,
                ':gstin' => $supplierGstin ?: null,
                ':state' => $supplierState ?: null
            ]);
            $supplierId = $pdo->lastInsertId();
            $suppliersCreated++;
        }

        // Create invoice
        $insInvoice->execute([
            ':company_id' => $COMPANY_ID,
            ':party_id' => $supplierId,
            ':invoice_no' => $invoiceNo,
            ':invoice_date' => parseDate($invoiceDate)
        ]);

        if ($insInvoice->rowCount() > 0) {
            $invoicesCreated++;
        }
    }

    echo "✅ Created {$suppliersCreated} suppliers and {$invoicesCreated} invoices\n";

    /** ===== Step 2: Process Detail CSV for Invoice Items ===== */
    echo "📦 Processing invoice items from {$DETAIL_CSV}...\n";

    $fhDetail = new SplFileObject($DETAIL_CSV);
    $fhDetail->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
    $fhDetail->setCsvControl(',', '"', '\\');

    $headerDetail = $fhDetail->fgetcsv();
    $mapDetail = [];
    foreach ($headerDetail as $i => $h) {
        $mapDetail[strtolower(trim((string)$h))] = $i;
    }

    $itemsAdded = 0;
    $itemsSkipped = 0;

    foreach ($fhDetail as $row) {
        if (!is_array($row) || empty($row[0])) continue;

        $canonicalName = trim($row[$mapDetail['canonical_name']] ?? '');
        $invoiceNo = trim($row[$mapDetail['invoice no']] ?? '');
        $hsn = trim($row[$mapDetail['hsn code']] ?? '');
        $quantity = floatval($row[$mapDetail['qty in pcs']] ?? 1);
        $rate = floatval($row[$mapDetail['purchase rate']] ?? 0);
        $cgstRate = floatval($row[$mapDetail['cgstrate']] ?? 0);
        $sgstRate = floatval($row[$mapDetail['sgstrate']] ?? 0);
        $igstRate = floatval($row[$mapDetail['igst']] ?? 0);

        if (!$canonicalName || !$invoiceNo) continue;

        // Get invoice ID
        $getInvoiceId->execute([
            ':company_id' => $COMPANY_ID,
            ':invoice_no' => $invoiceNo
        ]);

        $invoiceId = $getInvoiceId->fetchColumn();
        if (!$invoiceId) {
            $itemsSkipped++;
            continue;
        }

        // Get item ID
        $getItemId->execute([
            ':company_id' => $COMPANY_ID,
            ':name' => $canonicalName
        ]);

        $itemId = $getItemId->fetchColumn();
        if (!$itemId) {
            $itemsSkipped++;
            continue;
        }

        // Insert invoice item
        $insInvoiceItem->execute([
            ':invoice_id' => $invoiceId,
            ':item_id' => $itemId,
            ':description' => $canonicalName,
            ':hsn' => $hsn ?: null,
            ':quantity' => $quantity,
            ':rate' => $rate,
            ':cgst_rate' => $cgstRate,
            ':sgst_rate' => $sgstRate,
            ':igst_rate' => $igstRate
        ]);

        $itemsAdded++;
    }

    echo "✅ Added {$itemsAdded} invoice line items\n";
    echo "⚠️  Skipped {$itemsSkipped} items (missing invoice or item references)\n";

    $pdo->commit();

    echo "\n🎉 Import completed successfully!\n";
    echo "Summary:\n";
    echo "- Suppliers created: {$suppliersCreated}\n";
    echo "- Invoices created: {$invoicesCreated}\n";
    echo "- Invoice items added: {$itemsAdded}\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "❌ Error: " . $e->getMessage() . "\n");
    exit(1);
}
?>
