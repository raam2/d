<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/session.php';
$db = (new Database())->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['canonical_name'])) {
    $stmt = $db->prepare("
        INSERT INTO items (company_id, sku_code, canonical_name, hsn, uqc_id, gst_rate_id, rate)
        VALUES (:company_id, :sku_code, :canonical_name, :hsn, :uqc_id, :gst_rate_id, :rate)
    ");
    $stmt->execute([
        ':company_id'    => $_POST['company_id'],
        ':sku_code'      => $_POST['sku_code'],
        ':canonical_name'=> $_POST['canonical_name'],
        ':hsn'          => $_POST['hsn'] ?? null,
        ':uqc_id'       => $_POST['uqc_id'] ?? null,
        ':gst_rate_id'  => $_POST['gst_rate_id'] ?? null,
        ':rate'         => $_POST['rate'] ?? 0,
    ]);

    if (!empty($_POST['alias_text'])) {
        $item_id = $db->lastInsertId();
        $stmtAlias = $db->prepare("
            INSERT INTO item_alias (company_id, item_id, alias_text, alias_type, is_primary)
            VALUES (:company_id, :item_id, :alias_text, :alias_type, :is_primary)
        ");
        $stmtAlias->execute([
            ':company_id' => $_POST['company_id'],
            ':item_id'    => $item_id,
            ':alias_text' => $_POST['alias_text'],
            ':alias_type' => $_POST['alias_type'] ?? 'name',
            ':is_primary' => isset($_POST['is_primary']) ? 1 : 0,
        ]);
    }
    echo "Item added successfully.";
}

$suggestions = [];
try {
  $suggestions = $db->query("SELECT label FROM v_product_suggestions ORDER BY priority, label")->fetchAll();
} catch (Throwable $e) { }
?>
<h2>Add Item</h2>
<form method="POST" action="">
    Company ID: <input type="number" name="company_id" required><br>
    SKU Code: <input type="text" name="sku_code" required><br>
    Name: <input list="item_suggestions" name="canonical_name" required>
    <datalist id="item_suggestions">
        <?php foreach($suggestions as $s): ?>
            <option value="<?= htmlspecialchars($s['label']) ?>"></option>
        <?php endforeach; ?>
    </datalist><br>
    HSN: <input type="text" name="hsn"><br>
    UQC ID: <input type="number" name="uqc_id"><br>
    GST Rate ID: <input type="number" name="gst_rate_id"><br>
    Rate: <input type="number" step="0.01" name="rate"><br>
    Alias Text: <input type="text" name="alias_text"><br>
    Alias Type:
    <select name="alias_type">
        <option value="name">Name</option>
        <option value="brand">Brand</option>
        <option value="code">Code</option>
        <option value="barcode">Barcode</option>
    </select><br>
    Primary Alias: <input type="checkbox" name="is_primary"><br>
    <button type="submit">Add Item</button>
</form>
