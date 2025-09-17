<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = (new Database())->getConnection();

$apply = $_POST['apply'] ?? 'preview';
$confirm = $_POST['confirm'] ?? null;

// Step 1: Handle file upload
if (!isset($_FILES['corrected_csv']) || !is_uploaded_file($_FILES['corrected_csv']['tmp_name'])) {
    die("❌ No valid CSV uploaded.");
}

$csv = array_map('str_getcsv', file($_FILES['corrected_csv']['tmp_name']));
$headers = $csv[0] ?? [];
$dataRows = array_slice($csv, 1);

// Step 2: Define expected DB columns
$expected = ['id', 'Hindi_Name', 'description']; // Add more if needed

// Step 3: If mapping not submitted, show mapping form
if (!isset($_POST['mapped'])) {
    echo "<h3>🧩 Map CSV Columns to DB Fields</h3>";
    echo "<form method='post' enctype='multipart/form-data'>";
    foreach ($expected as $dbCol) {
        echo "<label>$dbCol → <select name='mapped[$dbCol]'>";
        foreach ($headers as $csvCol) {
            echo "<option value='" . htmlspecialchars($csvCol) . "'>$csvCol</option>";
        }
        echo "</select></label><br>";
    }
    echo "<input type='hidden' name='apply' value='" . htmlspecialchars($apply) . "'>";
    echo "<input type='hidden' name='confirm' value='" . htmlspecialchars($confirm) . "'>";
    echo "<input type='hidden' name='csv_uploaded' value='1'>";
    echo "<input type='file' name='corrected_csv' accept='.csv' required><br>";
    echo "<button type='submit'>Preview Mapped Data</button></form>";
    exit;
}

// Step 4: Apply mapping
$map = $_POST['mapped'];
echo "<h3>🔎 Preview Master Corrections (" . count($dataRows) . " rows)</h3><table><tr>";
foreach ($expected as $col) echo "<th>" . htmlspecialchars($col) . "</th>";
echo "</tr>";

$updated = 0;
foreach ($dataRows as $row) {
    $rowAssoc = array_combine($headers, $row);
    $id = $rowAssoc[$map['id']] ?? null;
    $hindi = $rowAssoc[$map['Hindi_Name']] ?? null;
    $desc = $rowAssoc[$map['description']] ?? null;

    echo "<tr><td>" . htmlspecialchars($id) . "</td><td>" . htmlspecialchars($hindi) . "</td><td>" . htmlspecialchars($desc) . "</td></tr>";

    if ($apply === 'update' && $confirm) {
        $stmt = $pdo->prepare("UPDATE stg_purchase_invoice_hindi SET Hindi_Name = ?, description = ? WHERE id = ?");
        $stmt->execute([$hindi, $desc, $id]);
        $updated++;
    }
}
echo "</table>";

if ($apply === 'update' && $confirm) {
    echo "<div class='alert'>✅ Applied $updated master updates to database.</div>";
}

