<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/session.php';
$db = (new Database())->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_no'])) {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO invoices (company_id, party_id, inv_type, invoice_no, invoice_date, place_of_supply, reverse_charge, status)
            VALUES (:company_id, :party_id, :inv_type, :invoice_no, :invoice_date, :place_of_supply, :reverse_charge, :status)
        ");
        $stmt->execute([
            ':company_id'     => $_POST['company_id'],
            ':party_id'       => $_POST['party_id'],
            ':inv_type'       => $_POST['inv_type'],
            ':invoice_no'     => $_POST['invoice_no'],
            ':invoice_date'   => $_POST['invoice_date'],
            ':place_of_supply'=> $_POST['place_of_supply'] ?? null,
            ':reverse_charge' => !empty($_POST['reverse_charge']) ? 1 : 0,
            ':status'         => $_POST['status'] ?? 'draft',
        ]);
        $invoice_id = $db->lastInsertId();

        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $stmt_item = $db->prepare("INSERT INTO invoice_items
                (invoice_id, item_id, description, hsn, quantity, rate, discount_percent, cgst_rate, sgst_rate, igst_rate)
                VALUES (:invoice_id, :item_id, :description, :hsn, :quantity, :rate, :discount_percent, :cgst_rate, :sgst_rate, :igst_rate)");
            foreach ($_POST['items'] as $item) {
                $stmt_item->execute([
                    ':invoice_id'      => $invoice_id,
                    ':item_id'         => $item['item_id'],
                    ':description'     => $item['description'] ?? null,
                    ':hsn'             => $item['hsn'] ?? null,
                    ':quantity'        => $item['quantity'],
                    ':rate'            => $item['rate'],
                    ':discount_percent'=> $item['discount_percent'] ?? 0,
                    ':cgst_rate'       => $item['cgst_rate'] ?? 0,
                    ':sgst_rate'       => $item['sgst_rate'] ?? 0,
                    ':igst_rate'       => $item['igst_rate'] ?? 0,
                ]);
            }
        }
        $db->commit();
        echo "Invoice added successfully.";
    } catch (Exception $ex) {
        $db->rollBack();
        echo "Error: " . htmlspecialchars($ex->getMessage());
    }
}

$companies = $db->query("SELECT id, company_name FROM companies ORDER BY company_name")->fetchAll();
$parties   = $db->query("SELECT id, name FROM parties ORDER BY name")->fetchAll();
?>
<h2>Add Invoice</h2>
<form method="POST" action="">
    Company:
    <select name="company_id" required>
        <?php foreach ($companies as $co): ?>
            <option value="<?= $co['id'] ?>"><?= htmlspecialchars($co['company_name']) ?></option>
        <?php endforeach; ?>
    </select><br>
    Party:
    <select name="party_id" required>
        <?php foreach ($parties as $pa): ?>
            <option value="<?= $pa['id'] ?>"><?= htmlspecialchars($pa['name']) ?></option>
        <?php endforeach; ?>
    </select><br>
    Invoice Type:
    <select name="inv_type" required>
        <option value="sale">Sale</option>
        <option value="purchase">Purchase</option>
        <option value="credit_note">Credit Note</option>
        <option value="debit_note">Debit Note</option>
    </select><br>
    Invoice Number: <input type="text" name="invoice_no" required><br>
    Invoice Date: <input type="date" name="invoice_date" required><br>
    Place of Supply: <input type="text" name="place_of_supply"><br>
    Reverse Charge: <input type="checkbox" name="reverse_charge"><br>
    Status:
    <select name="status">
        <option value="draft">Draft</option>
        <option value="final">Final</option>
        <option value="cancelled">Cancelled</option>
    </select><br>

    <h3>Invoice Item (Single Entry Example)</h3>
    Item ID: <input type="number" name="items[0][item_id]" required><br>
    Description: <input type="text" name="items[0][description]"><br>
    HSN: <input type="text" name="items[0][hsn]"><br>
    Quantity: <input type="number" step="0.001" name="items[0][quantity]" value="1" required><br>
    Rate: <input type="number" step="0.01" name="items[0][rate]" value="0" required><br>
    Discount %: <input type="number" step="0.01" name="items[0][discount_percent]" value="0"><br>
    CGST %: <input type="number" step="0.01" name="items[0][cgst_rate]" value="0"><br>
    SGST %: <input type="number" step="0.01" name="items[0][sgst_rate]" value="0"><br>
    IGST %: <input type="number" step="0.01" name="items[0][igst_rate]" value="0"><br>

    <button type="submit">Add Invoice</button>
</form>
