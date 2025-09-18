<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
$db = (new Database())->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_name'])) {
    $stmt = $db->prepare("INSERT INTO companies (company_name, gstin, state) VALUES (:name, :gstin, :state)");
    $stmt->execute([
        ':name'  => $_POST['company_name'],
        ':gstin' => $_POST['gstin'] ?? null,
        ':state' => $_POST['state'] ?? null,
    ]);
    echo "Company added successfully.";
}

$stmt = $db->prepare("SELECT * FROM companies ORDER BY company_name ASC");
$stmt->execute();
$companies = $stmt->fetchAll();
?>
<h2>Add Company</h2>
<form method="POST" action="">
    Name: <input type="text" name="company_name" required><br>
    GSTIN: <input type="text" name="gstin"><br>
    State: <input type="text" name="state"><br>
    <button type="submit">Add</button>
</form>

<h2>Companies List</h2>
<ul>
<?php foreach ($companies as $company): ?>
    <li><?= htmlspecialchars($company['company_name']) ?>
        (GSTIN: <?= htmlspecialchars($company['gstin']) ?>) - <?= htmlspecialchars($company['state']) ?>
    </li>
<?php endforeach; ?>
</ul>
