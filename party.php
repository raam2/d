<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
$db = (new Database())->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $stmt = $db->prepare("
        INSERT INTO parties (company_id, name, gstin, party_type, city, state)
        VALUES (:company_id, :name, :gstin, :party_type, :city, :state)
    ");
    $stmt->execute([
        ':company_id' => $_POST['company_id'],
        ':name'       => $_POST['name'],
        ':gstin'      => $_POST['gstin'] ?? null,
        ':party_type' => $_POST['party_type'] ?? 'customer',
        ':city'       => $_POST['city'] ?? null,
        ':state'      => $_POST['state'] ?? null,
    ]);
    echo "Party added successfully.";
}

$suggestions = [];
try {
  $suggestions = $db->query("SELECT id, name FROM parties ORDER BY name")->fetchAll();
} catch (Throwable $e) { }
?>
<h2>Add Party</h2>
<form method="POST" action="">
    Company ID: <input type="number" name="company_id" required><br>
    Name: <input list="party_names" name="name" required>
    <datalist id="party_names">
        <?php foreach($suggestions as $s): ?>
            <option value="<?= htmlspecialchars($s['name']) ?>"></option>
        <?php endforeach; ?>
    </datalist><br>
    GSTIN: <input type="text" name="gstin"><br>
    Type:
    <select name="party_type">
        <option value="customer">Customer</option>
        <option value="supplier">Supplier</option>
        <option value="both">Both</option>
    </select><br>
    City: <input type="text" name="city"><br>
    State: <input type="text" name="state"><br>
    <button type="submit">Add</button>
</form>
