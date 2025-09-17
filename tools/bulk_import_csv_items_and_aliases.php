<?php
/**
 * bulk_import_csv_items_and_aliases.php
 * - final_hindi_name -> items.canonical_name
 * - other columns -> item_alias (name)
 * - idempotent, transactional, UTF-8 safe
 */
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

$db = (new Database())->getConnection(); // PDO, as in your working script
$db->exec("SET NAMES utf8mb4");

/** ===== Config / CLI ===== */
$CSV        = $argv[1] ?? 'more_products.csv';
$COMPANY_ID = isset($argv[2]) ? (int)$argv[2] : 1;
$DEFAULT_RATE = 0;
$DEFAULT_HSN  = null;
$DEFAULT_UQC  = null;  // keep NULL if unknown
$DEFAULT_GST  = null;  // keep NULL if unknown

if (!is_file($CSV)) {
    fwrite(STDERR, "CSV not found: {$CSV}\n");
    exit(1);
}

/** ===== Helpers ===== */
function norm(?string $s): string {
    if ($s === null) return '';
    $s = trim($s);
    // Optionally normalize spaces:
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}
function skuFromName(string $name): string {
    // deterministic, avoids collisions with uq_item_sku across re-runs
    return 'SKU-' . strtoupper(substr(md5($name), 0, 10));
}

/** ===== Prepare SQL =====
 * items unique keys: (company_id, sku_code) and (company_id, canonical_name)
 * item_alias unique keys: (item_id, alias_text) and (company_id, alias_text)
 */
$pdo = $db;
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$insItem = $pdo->prepare("
    INSERT INTO items
        (company_id, sku_code, canonical_name, hsn, uqc_id, gst_rate_id, rate, is_active)
    VALUES
        (:company_id, :sku, :name, :hsn, :uqc, :gst, :rate, 1)
    ON DUPLICATE KEY UPDATE
        canonical_name = VALUES(canonical_name)
");

$getItemId = $pdo->prepare("
    SELECT id FROM items
    WHERE company_id = :company_id AND canonical_name = :name
    LIMIT 1
");

$insAlias = $pdo->prepare("
    INSERT IGNORE INTO item_alias
        (company_id, item_id, alias_text, alias_type, is_primary)
    VALUES
        (:company_id, :item_id, :alias, 'name', 0)
");

/** ===== Read CSV (header-flex) ===== */
$fh = new SplFileObject($CSV);
$fh->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
$fh->setCsvControl(',', '"', '\\');

$header = $fh->fgetcsv();
if ($header === false) {
    fwrite(STDERR, "CSV appears empty: {$CSV}\n");
    exit(1);
}
$map = [];
foreach ($header as $i => $h) {
    $map[strtolower(trim((string)$h))] = $i;
}
$idxFinal  = $map['final_hindi_name'] ?? null;
$idxOrig   = $map['original'] ?? null;
$idxEng    = $map['very_short_english_suggestions'] ?? ($map['very_short_english_suggestions'] ?? $map['very_short_english_suggestion'] ?? $map['very_short_english'] ?? null);
$idxHinSh  = $map['very_short_simple_hindi_suggestion'] ?? $map['hindi_short'] ?? null;

if ($idxFinal === null) {
    fwrite(STDERR, "Required column 'final_hindi_name' not found in CSV header.\n");
    exit(1);
}

$insertedItems = 0;
$existingItems = 0;
$aliasAttempts = 0;
$aliasAdded    = 0;

$pdo->beginTransaction();

try {
    foreach ($fh as $row) {
        if (!is_array($row)) continue;
        if (count($row) === 1 && $row[0] === null) continue; // skip blank line

        $finalName = norm($row[$idxFinal] ?? '');
        if ($finalName === '') continue;

        // Build unique, deterministic SKU from the canonical name:
        $sku = skuFromName($finalName);

        // Insert/Upsert item
        $insItem->execute([
            ':company_id' => $COMPANY_ID,
            ':sku'        => $sku,
            ':name'       => $finalName,
            ':hsn'        => $DEFAULT_HSN,
            ':uqc'        => $DEFAULT_UQC,
            ':gst'        => $DEFAULT_GST,
            ':rate'       => $DEFAULT_RATE,
        ]);

        // Get item id (works both for new & duplicate)
        $getItemId->execute([
            ':company_id' => $COMPANY_ID,
            ':name'       => $finalName,
        ]);
        $itemId = (int)$getItemId->fetchColumn();

        if ($insItem->rowCount() > 0 && $itemId > 0) {
            $insertedItems++;
        } else {
            $existingItems++;
        }

        // Collect aliases (skip blank & same-as-canonical)
        $aliases = [];
        if ($idxOrig !== null)  $aliases[] = norm($row[$idxOrig] ?? '');
        if ($idxEng  !== null)  $aliases[] = norm($row[$idxEng] ?? '');
        if ($idxHinSh !== null) $aliases[] = norm($row[$idxHinSh] ?? '');

        // de-dup within row
        $aliases = array_values(array_unique(array_filter($aliases, function ($a) use ($finalName) {
            return $a !== '' && $a !== $finalName;
        })));

        foreach ($aliases as $aliasText) {
            $aliasAttempts++;
            $insAlias->execute([
                ':company_id' => $COMPANY_ID,
                ':item_id'    => $itemId,
                ':alias'      => $aliasText,
            ]);
            if ($insAlias->rowCount() > 0) {
                $aliasAdded++;
            }
        }
    }

    $pdo->commit();

    echo "✅ Import complete\n";
    echo "Company ID: {$COMPANY_ID}\n";
    echo "Items inserted (new): {$insertedItems}\n";
    echo "Items existing (skipped via upsert): {$existingItems}\n";
    echo "Alias rows attempted: {$aliasAttempts}\n";
    echo "Aliases actually added: {$aliasAdded}\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "❌ Error: " . $e->getMessage() . "\n");
    exit(1);
}

