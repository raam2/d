<?php
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
$table = $_GET['table'] ?? '';
$rows = [];
$columns = [];

if ($table) {
    try {
        $stmt = $pdo->query("SELECT * FROM `$table` LIMIT 500");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = array_keys($rows[0] ?? []);
    } catch (PDOException $e) {
        echo "<div class='alert'>❌ Error loading table: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<h2>🗂 Browse Table</h2>
<form method="get">
    <input type="hidden" name="tab" value="browse">
    <label for="table">Select Table:</label>
    <select name="table" id="table" onchange="this.form.submit()">
        <option value="">-- Choose --</option>
        <?php foreach ($tables as $tbl): ?>
            <option value="<?= htmlspecialchars($tbl) ?>" <?= $tbl === $table ? 'selected' : '' ?>><?= htmlspecialchars($tbl) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($table): ?>
    <h3>📄 Preview: <?= htmlspecialchars($table) ?> (max 500 rows)</h3>
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
                    <?php foreach ($columns as $col): ?>
                        <td><?= htmlspecialchars((string)$row[$col]) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

