<?php
$uploadMessage = '';
$columns = [];
$rows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (is_uploaded_file($file)) {
        $csv = array_map('str_getcsv', file($file));
        $columns = $csv[0] ?? [];
        $rows = array_slice($csv, 1, 100);
        $uploadMessage = "✅ Previewing first 100 rows of uploaded CSV.";
    } else {
        $uploadMessage = "❌ Failed to upload file.";
    }
}
?>

<h2>📤 Upload CSV</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="csv_file" accept=".csv">
    <button type="submit">Preview</button>
</form>

<?php if ($uploadMessage): ?>
    <div class="alert"><?= $uploadMessage ?></div>
<?php endif; ?>

<?php if ($rows && $columns): ?>
    <h3>🔎 CSV Preview (first 100 rows)</h3>
    <table>
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($row as $val): ?>
                        <td><?= htmlspecialchars((string)$val) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

