
<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/session.php';
$db = (new Database())->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_date'])) {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO payments (company_id, party_id, pay_date, amount, mode, ref_no)
            VALUES (:company_id, :party_id, :pay_date, :amount, :mode, :ref_no)
        ");
        $stmt->execute([
            ':company_id' => $_POST['company_id'],
            ':party_id'   => $_POST['party_id'],
            ':pay_date'   => $_POST['pay_date'],
            ':amount'     => $_POST['amount'],
            ':mode'       => $_POST['mode'],
            ':ref_no'     => $_POST['ref_no'] ?? null,
        ]);
        $payment_id = $db->lastInsertId();

        if (isset($_POST['allocations']) && is_array($_POST['allocations'])) {
            $stmt_alloc = $db->prepare("
                INSERT INTO payment_allocations (payment_id, invoice_id, alloc_amount)
                VALUES (:payment_id, :invoice_id, :alloc_amount)
            ");
            foreach ($_POST['allocations'] as $alloc) {
                $stmt_alloc->execute([
                    ':payment_id'  => $payment_id,
                    ':invoice_id'  => $alloc['invoice_id'],
                    ':alloc_amount'=> $alloc['alloc_amount'],
                ]);
            }
        }
        $db->commit();
        echo "Payment added successfully.";
    } catch (Exception $ex) {
        $db->rollBack();
        echo "Error: " . htmlspecialchars($ex->getMessage());
    }
}

$companies = $db->query("SELECT id, company_name FROM companies ORDER BY company_name")->fetchAll();
$parties   = $db->query("SELECT id, name FROM parties ORDER BY name")->fetchAll();
?>
<h2>Add Payment</h2>
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
            <option value="<?= htmlspecialchars($pa['id']) ?>"><?= htmlspecialchars($pa['name']) ?></option>
        <?php endforeach; ?>
    </select><br>
    Pay Date: <input type="date" name="pay_date" required><br>
    Amount: <input type="number" step="0.01" name="amount" required><br>
    Mode:
    <select name="mode">
        <option value="cash">Cash</option>
        <option value="bank">Bank</option>
        <option value="upi">UPI</option>
        <option value="card">Card</option>
        <option value="other">Other</option>
    </select><br>
    Ref No: <input type="text" name="ref_no"><br>

    <h3>Allocations (Optional)</h3>
    Invoice ID: <input type="number" name="allocations[0][invoice_id]"><br>
    Allocation Amount: <input type="number" step="0.01" name="allocations[0][alloc_amount]"><br>

    <button type="submit">Add Payment</button>
</form>
