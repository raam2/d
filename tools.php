<h2>🛠️ Workflow Tools</h2>

<!-- Hindi Translation Validator -->
<form method="post">
    <button type="submit" name="run_hindi_validator">Validate Hindi_Name</button>
</form>
<?php
if (isset($_POST['run_hindi_validator'])) {
    $stmt = $pdo->query("SELECT id, Hindi_Name, description FROM stg_purchase_invoice_hindi WHERE Hindi_Name IS NULL OR Hindi_Name = '' LIMIT 500");
    $missingHindi = $stmt->fetchAll();
    echo "<h3>🧠 Missing Hindi_Name (" . count($missingHindi) . " rows)</h3><table><tr><th>ID</th><th>Description</th><th>Hindi_Name</th></tr>";
    foreach ($missingHindi as $row) {
        echo "<tr><td>{$row['id']}</td><td>" . htmlspecialchars($row['description']) . "</td><td>" . htmlspecialchars($row['Hindi_Name']) . "</td></tr>";
    }
    echo "</table>";
}
?>
<!-- Export Button -->
<form method="post" action="actions/export_missing_hindi.php">
    <button type="submit">Export Missing Hindi_Name</button>
</form>

<!-- Upload + Toggle -->
<h3>📥 Upload Corrected CSV</h3>
<form method="post" action="actions/apply_hindi_corrections.php" enctype="multipart/form-data">
    <input type="file" name="corrected_csv" accept=".csv">
    <select name="apply">
        <option value="preview">Preview Only</option>
        <option value="update">Apply to DB</option>
    </select>
    <button type="submit">Run</button>
</form>


<h3>📥 Upload Corrected Hindi CSV</h3>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="corrected_hindi_csv" accept=".csv">
    <button type="submit" name="apply_hindi_corrections">Preview & Apply</button>
</form>
<?php
if (isset($_POST['apply_hindi_corrections']) && isset($_FILES['corrected_hindi_csv'])) {
    $file = $_FILES['corrected_hindi_csv']['tmp_name'];
    if (is_uploaded_file($file)) {
        $csv = array_map('str_getcsv', file($file));
        $columns = $csv[0] ?? [];
        $rows = array_slice($csv, 1);

        echo "<h4>🔎 Preview Corrections (" . count($rows) . " rows)</h4><table><tr>";
        foreach ($columns as $col) echo "<th>" . htmlspecialchars($col) . "</th>";
        echo "</tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $val) echo "<td>" . htmlspecialchars($val) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Optional: Apply updates to DB
        // foreach ($rows as $row) {
        //     $stmt = $pdo->prepare("UPDATE stg_purchase_invoice_hindi SET Hindi_Name = ? WHERE id = ?");
        //     $stmt->execute([$row[1], $row[0]]);
        // }
    } else {
        echo "<div class='alert'>❌ Upload failed.</div>";
    }
}
?>

<!-- Referential Integrity Checker -->
<form method="post">
    <button type="submit" name="run_integrity_check">Check Referential Integrity</button>
</form>
<?php
if (isset($_POST['run_integrity_check'])) {
    $stmt = $pdo->query("SELECT ii.id, ii.item_id FROM invoice_items ii LEFT JOIN items it ON ii.item_id = it.id WHERE it.id IS NULL LIMIT 500");
    $brokenRefs = $stmt->fetchAll();
    echo "<h3>🔗 Broken item_id references (" . count($brokenRefs) . " rows)</h3><table><tr><th>Invoice Item ID</th><th>item_id</th></tr>";
    foreach ($brokenRefs as $row) {
        echo "<tr><td>{$row['id']}</td><td>{$row['item_id']}</td></tr>";
    }
    echo "</table>";
}
?>

<!-- Normalization Assistant -->
<form method="post">
    <button type="submit" name="run_normalization_check">Check Normalization</button>
</form>
<?php
if (isset($_POST['run_normalization_check'])) {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>🧹 Normalization Hints</h3><ul>";
    foreach ($tables as $tbl) {
        $cols = $pdo->query("SHOW COLUMNS FROM `$tbl`")->fetchAll();
        $colNames = array_column($cols, 'Field');
        $redundant = array_filter($colNames, fn($c) => stripos($c, 'name') !== false || stripos($c, 'desc') !== false);
        if (count($redundant) > 1) {
            echo "<li><strong>$tbl</strong>: Possible redundancy in columns " . implode(', ', $redundant) . "</li>";
        }
    }
    echo "</ul>";
}
?>

<!-- Master List Merger -->
<form method="post">
    <button type="submit" name="run_master_merge">Merge Corrected Batches</button>
</form>
<?php
if (isset($_POST['run_master_merge'])) {
    $stmt = $pdo->query("SELECT * FROM stg_purchase_invoice_hindi ORDER BY id LIMIT 500");
    $merged = $stmt->fetchAll();
    echo "<h3>📦 Merged Master List Preview (" . count($merged) . " rows)</h3><table><tr>";
    foreach (array_keys($merged[0] ?? []) as $col) {
        echo "<th>" . htmlspecialchars($col) . "</th>";
    }
    echo "</tr>";
    foreach ($merged as $row) {
        echo "<tr>";
        foreach ($row as $val) {
            echo "<td>" . htmlspecialchars((string)$val) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}
?>

<h3>📥 Upload Corrected CSV</h3>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="corrected_csv" accept=".csv">
    <button type="submit" name="apply_corrections">Apply Corrections</button>
</form>
<?php
if (isset($_POST['apply_corrections']) && isset($_FILES['corrected_csv'])) {
    $file = $_FILES['corrected_csv']['tmp_name'];
    if (is_uploaded_file($file)) {
        $csv = array_map('str_getcsv', file($file));
        $columns = $csv[0] ?? [];
        $rows = array_slice($csv, 1);

        echo "<h4>🔎 Preview Corrections (" . count($rows) . " rows)</h4><table><tr>";
        foreach ($columns as $col) echo "<th>" . htmlspecialchars($col) . "</th>";
        echo "</tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $val) echo "<td>" . htmlspecialchars($val) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Optional: Apply updates to DB here
        // foreach ($rows as $row) {
        //     $stmt = $pdo->prepare("UPDATE stg_purchase_invoice_hindi SET Hindi_Name = ? WHERE id = ?");
        //     $stmt->execute([$row[1], $row[0]]);
        // }
    } else {
        echo "<div class='alert'>❌ Upload failed.</div>";
    }
}
?>


