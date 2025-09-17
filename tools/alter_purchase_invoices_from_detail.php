<?php
declare(strict_types=1);

/**
 * alter_purchase_invoices_from_detail.php
 *
 * PURPOSE
 * - Use Purchase-Invoice-Detail-Enhanced.csv as the source of truth
 * - For each invoice_no: find existing purchase invoice (by unique key) and REPLACE its invoice_items
 * - Optionally fix invoice_date and place_of_supply from CSV
 *
 * USAGE
 *   php alter_purchase_invoices_from_detail.php [EnhancedCSV] [CompanyID] [--apply] [--keep-items] [--update-date] [--update-pos]
 * EXAMPLES
 *   php alter_purchase_invoices_from_detail.php "Purchase-Invoice-Detail-Enhanced.csv" 1 --apply --update-date --update-pos
 *
 * SAFETY
 * - Default is DRY-RUN (no changes). Add --apply to commit.
 * - Per-invoice transaction; any error rolls back that invoice only.
 * - Creates a JSON audit summary at the end (stdout).
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

// ---- Load DB connection (uses your db.php) ----
require_once __DIR__ . '/db.php';  // expects Database class returning PDO (matches your db.php)
$pdo = (new Database())->getConnection();

// ---- Inputs: CLI or HTTP (safe for both) ----
$isCli = (php_sapi_name() === 'cli');

// Build a unified $args array irrespective of SAPI
$args = [];

if ($isCli) {
    // Guard even if register_argc_argv=Off
    $args = isset($argv) && is_array($argv) ? $argv : ($_SERVER['argv'] ?? []);
    if (!is_array($args)) { $args = []; }
} else {
    // Emulate argv from GET/POST for web execution
    $args[0] = __FILE__;
    $args[1] = $_GET['csv']        ?? $_POST['csv']        ?? 'Purchase-Invoice-Detail-Enhanced.csv';
    $args[2] = $_GET['company_id'] ?? $_POST['company_id'] ?? '1';

    // Flags via query params: ?apply=1&update-date=1&update-pos=1&keep-items=1
    foreach (['apply','yes','keep-items','update-date','update-pos'] as $flag) {
        if (!empty($_GET[$flag]) || !empty($_POST[$flag])) {
            $args[] = '--' . $flag;
        }
    }
}

// Now read inputs from the unified $args
$DETAIL_CSV = $args[1] ?? 'Purchase-Invoice-Detail-Enhanced.csv';
$COMPANY_ID = isset($args[2]) ? (int)$args[2] : 1;

$APPLY       = in_array('--apply', $args, true) || in_array('--yes', $args, true);
$KEEP_ITEMS  = in_array('--keep-items', $args, true);
$UPDATE_DATE = in_array('--update-date', $args, true);
$UPDATE_POS  = in_array('--update-pos', $args, true);

// Optional: if CSV path is relative, also try __DIR__
if (!is_file($DETAIL_CSV)) {
    $alt = __DIR__ . '/' . ltrim($DETAIL_CSV, '/');
    if (is_file($alt)) { $DETAIL_CSV = $alt; }
}
if (!is_file($DETAIL_CSV)) {
    fwrite(STDERR, "CSV not found: {$DETAIL_CSV}\n");
    exit(1);
}


if (!is_file($DETAIL_CSV)) {
    fwrite(STDERR, "CSV not found: {$DETAIL_CSV}\n");
    exit(1);
}

// ---- Helpers ----
function normalizeHeader(string $h): string {
    return strtolower(trim(preg_replace('/\s+/', '_', $h)));
}
function parseDateDMY(string $s): ?string {
    $s = trim($s);
    if ($s === '') return null;
    // Accept "DD-MM-YYYY" or "DD/MM/YYYY"
    if (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/', $s, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    // Fallback: try strtotime
    $t = strtotime($s);
    return $t ? date('Y-m-d', $t) : null;
}
function dec($v): float {
    // robust decimal parser
    if ($v === null) return 0.0;
    $s = trim((string)$v);
    if ($s === '') return 0.0;
    $s = str_replace([",", "₹", "%"], ["", "", ""], $s);
    return (float)$s;
}

// ---- Prepare statements ----
$getInvoice = $pdo->prepare("
    SELECT id, party_id, invoice_date, place_of_supply
    FROM invoices
    WHERE company_id = :company_id AND inv_type = 'purchase' AND invoice_no = :invoice_no
    LIMIT 1
"); // Unique per schema: company_id + inv_type + invoice_no :contentReference[oaicite:4]{index=4}

$updateInvoiceDate = $pdo->prepare("
    UPDATE invoices SET invoice_date = :invoice_date
    WHERE id = :invoice_id
");

$updateInvoicePOS = $pdo->prepare("
    UPDATE invoices SET place_of_supply = :pos
    WHERE id = :invoice_id
");

$deleteItems = $pdo->prepare("
    DELETE FROM invoice_items WHERE invoice_id = :invoice_id
"); // Safe replace thanks to FK + CASCADE on allocations if any are separately handled. :contentReference[oaicite:5]{index=5}

$getItemByCanonical = $pdo->prepare("
    SELECT id FROM items WHERE company_id = :company_id AND canonical_name = :name LIMIT 1
"); // From your item model. :contentReference[oaicite:6]{index=6}

$getItemByAlias = $pdo->prepare("
    SELECT i.id
    FROM item_alias a
    JOIN items i ON i.id = a.item_id
    WHERE a.company_id = :company_id AND a.alias_text = :alias
    LIMIT 1
"); // Optional alias fallback. :contentReference[oaicite:7]{index=7}

$insertItemLine = $pdo->prepare("
    INSERT INTO invoice_items
    (invoice_id, item_id, description, hsn, quantity, rate, cgst_rate, sgst_rate, igst_rate)
    VALUES
    (:invoice_id, :item_id, :description, :hsn, :quantity, :rate, :cgst_rate, :sgst_rate, :igst_rate)
"); // Computed columns (discount_amount, taxable_amount, tax amounts, line_total) are generated per schema. :contentReference[oaicite:8]{index=8}

// ---- Read CSV & build header map ----
$fh = new SplFileObject($DETAIL_CSV);
$fh->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
$fh->setCsvControl(',', '"', '\\');

$rawHeader = $fh->fgetcsv();
$map = [];
foreach ($rawHeader as $i => $h) {
    $map[normalizeHeader((string)$h)] = $i;
}

// Expected / optional columns (case-insensitive)
$col = [
    'invoice_no'    => $map['invoice_no']        ?? null,
    'invoice_date'  => $map['invoice_date']      ?? null, // DD-MM-YYYY / DD/MM/YYYY
    'canonical_name'=> $map['canonical_name']    ?? null,
    'hsn'           => $map['hsn']               ?? ($map['hsn_code']??null),
    'received_qty'  => $map['received_qty']      ?? ($map['qty']??$map['qty_in_cld']??null),
    'purchase_rate' => $map['purchase_rate']     ?? ($map['actual_purchase_rate']??null),
    'cgstrate'      => $map['cgstrate']          ?? null,
    'sgstrate'      => $map['sgstrate']          ?? null,
    'igstrate'      => $map['igstrate']          ?? null,
    'supplier_state'=> $map['supplier_state']    ?? null,
    'description'   => $map['description']       ?? null,
];

// Minimal guards
foreach (['invoice_no','canonical_name','received_qty','purchase_rate'] as $req) {
    if ($col[$req] === null) {
        fwrite(STDERR, "CSV is missing required column: {$req}\n");
        exit(2);
    }
}

// ---- Group rows by invoice_no ----
$rowsByInv = [];
foreach ($fh as $row) {
    if (!is_array($row) || (count($row) === 1 && trim((string)$row[0])==='')) continue;
    $invNo = trim((string)$row[$col['invoice_no']]);
    if ($invNo === '') continue;

    $rowsByInv[$invNo][] = $row;
}
// ---- Read CSV & build header map ----
$fh = new SplFileObject($DETAIL_CSV);
$fh->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
$fh->setCsvControl(',', '"', '\\');

$rawHeader = $fh->fgetcsv();
$map = [];
foreach ($rawHeader as $i => $h) {
    $map[strtolower(trim(preg_replace('/\s+/', '_', (string)$h)))] = $i;
}

// Expected columns (as before) ...
// $col = [ ... ];

// ---- Prepare header token to detect repeated header rows ----
$invHeaderToken = null;
if (($col['invoice_no'] ?? null) !== null && isset($rawHeader[$col['invoice_no']])) {
    $invHeaderToken = strtolower(trim(preg_replace('/\s+/', '', (string)$rawHeader[$col['invoice_no']])));
}

// ---- Group rows by invoice_no (skip repeated headers) ----
$rowsByInv = [];
foreach ($fh as $row) {
    if (!is_array($row) || (count($row) === 1 && trim((string)$row[0]) === '')) continue;

    // Protect against short/invalid rows
    if (($col['invoice_no'] ?? null) === null || !isset($row[$col['invoice_no']])) continue;

    $cell = (string)$row[$col['invoice_no']];
    $invNo = trim($cell);
    if ($invNo === '') continue;

    // 1) Skip obvious header-like cell value: "Invoice No"
    $normCell = strtolower(trim(preg_replace('/\s+/', '', $invNo)));
    if ($invHeaderToken && $normCell === $invHeaderToken) continue;
    if (preg_match('/^invoice\s*no\.?$/i', $invNo)) continue;

    // 2) Skip if the entire row looks like a reprinted header row
    $looksLikeHeader = true;
    foreach ($map as $normName => $idx) {
        $left  = strtolower(trim((string)($row[$idx] ?? '')));
        $right = strtolower(trim((string)($rawHeader[$idx] ?? '')));
        if ($left !== $right) { $looksLikeHeader = false; break; }
    }
    if ($looksLikeHeader) continue;

    // If we reach here, it's a real data line
    $rowsByInv[$invNo][] = $row;
}

// ---- Process each invoice ----
$audit = [
    'company_id' => $COMPANY_ID,
    'apply'      => $APPLY,
    'updated'    => 0,
    'skipped'    => 0,
    'missing'    => [],
    'details'    => [],
];

foreach ($rowsByInv as $invNo => $rows) {
    // Fetch existing invoice
    $getInvoice->execute([
        ':company_id' => $COMPANY_ID,
        ':invoice_no' => $invNo,
    ]);
    $inv = $getInvoice->fetch();

    if (!$inv) {
        $audit['missing'][] = $invNo;
        continue; // not altering non-existent invoices
    }

    $invoiceId = (int)$inv['id'];
    $oneSample = $rows[0];

    // Derive header fixes (date, PoS) if asked
    $newDate = null;
    if ($UPDATE_DATE && $col['invoice_date'] !== null) {
        $newDate = parseDateDMY((string)$oneSample[$col['invoice_date']]);
        if ($newDate && $newDate !== $inv['invoice_date']) {
            // will update inside transaction
        } else {
            $newDate = null;
        }
    }
    $newPOS = null;
    if ($UPDATE_POS && $col['supplier_state'] !== null) {
        $pos = trim((string)$oneSample[$col['supplier_state']]);
        if ($pos !== '' && $pos !== (string)$inv['place_of_supply']) {
            $newPOS = $pos;
        }
    }

    // Begin per-invoice transaction
    $pdo->beginTransaction();
    $changed = ['invoice_no'=>$invNo, 'invoice_id'=>$invoiceId, 'replaced_items'=>0, 'updated_date'=>false, 'updated_pos'=>false, 'errors'=>[]];

    try {
        if (!$KEEP_ITEMS) {
            // Replace strategy: delete all items first
            if ($APPLY) {
                $deleteItems->execute([':invoice_id' => $invoiceId]);
            }
        }

        // Insert items from CSV
        foreach ($rows as $r) {
            $name  = trim((string)$r[$col['canonical_name']]);
            if ($name === '') continue;

            // find item_id by canonical name, else alias
            $itemId = null;
            $getItemByCanonical->execute([':company_id'=>$COMPANY_ID, ':name'=>$name]);
            $rowItem = $getItemByCanonical->fetch();
            if ($rowItem) {
                $itemId = (int)$rowItem['id'];
            } else {
                // fallback: alias
                $getItemByAlias->execute([':company_id'=>$COMPANY_ID, ':alias'=>$name]);
                $rowAlias = $getItemByAlias->fetch();
                if ($rowAlias) $itemId = (int)$rowAlias['id'];
            }
            if (!$itemId) {
                $changed['errors'][] = "Item not found: {$name}";
                continue; // skip this line
            }

            $qty   = dec($col['received_qty'] !== null ? $r[$col['received_qty']] : 0);
            $rate  = dec($col['purchase_rate'] !== null ? $r[$col['purchase_rate']] : 0);
            $hsn   = $col['hsn'] !== null ? trim((string)$r[$col['hsn']]) : null;

            $c = dec($col['cgstrate'] !== null ? $r[$col['cgstrate']] : 0);
            $s = dec($col['sgstrate'] !== null ? $r[$col['sgstrate']] : 0);
            $i = dec($col['igstrate'] !== null ? $r[$col['igstrate']] : 0);

            // sanity on GST rates (cap at 28 total)
            $tot = $c + $s + $i;
            if ($tot < 0 || $tot > 28.0 + 1e-9) {
                $changed['errors'][] = "GST rate out of range ({$tot}%) for {$name} in invoice {$invNo}";
                continue;
            }

            if ($APPLY) {
                $insertItemLine->execute([
                    ':invoice_id' => $invoiceId,
                    ':item_id'    => $itemId,
                    ':description'=> $col['description'] !== null ? (string)$r[$col['description']] : $name,
                    ':hsn'        => $hsn,
                    ':quantity'   => $qty,
                    ':rate'       => $rate,
                    ':cgst_rate'  => $c,
                    ':sgst_rate'  => $s,
                    ':igst_rate'  => $i,
                ]);
            }
            $changed['replaced_items']++;
        }

        // header fixes
        if ($newDate && $APPLY) {
            $updateInvoiceDate->execute([':invoice_date'=>$newDate, ':invoice_id'=>$invoiceId]);
            $changed['updated_date'] = true;
        }
        if ($newPOS && $APPLY) {
            $updateInvoicePOS->execute([':pos'=>$newPOS, ':invoice_id'=>$invoiceId]);
            $changed['updated_pos'] = true;
        }

        // commit per invoice
        if ($APPLY) $pdo->commit(); else $pdo->rollBack();

        $audit['updated']++;
        $audit['details'][] = $changed;

    } catch (Throwable $e) {
        $pdo->rollBack();
        $changed['errors'][] = "Exception: " . $e->getMessage();
        $audit['skipped']++;
        $audit['details'][] = $changed;
    }
}

// ---- Summary ----
$summary = json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
echo $APPLY ? "✅ APPLY MODE complete.\n" : "🔎 DRY-RUN only; no changes were committed.\n";
echo $summary . "\n";

