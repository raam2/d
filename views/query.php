<?php
$query = $_POST['query'] ?? '';
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $query) {
    try {
        $stmt = $pdo->query($query);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}
?>

<h2>🧠 Run Custom SQL</h2>
<form method="post">
    <textarea name="query" rows="6" placeholder="SELECT * FROM invoices LIMIT 10"><?= htmlspecialchars($query) ?></textarea><br>
    <button type="submit">Execute</button>
</form>

<?php if ($error): ?>
    <div class="alert">❌ Error: <?= htmlspecialchars($error) ?></div>
<?php elseif ($result !== null): ?>
    <h3>✅ Result (<?= count($result) ?> rows)</h3>
    <table>
        <thead>
            <tr>
                <?php foreach (array_keys($result[0] ?? []) as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($result as $row): ?>
                <tr>
                    <?php foreach ($row as $val): ?>
                        <td><?= htmlspecialchars((string)$val) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

