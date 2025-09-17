<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$pdo = $pdo ?? (new Database())->getConnection();
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
$table = $_GET['table'] ?? '';
$tab = $_GET['tab'] ?? 'browse';
$query = $_POST['query'] ?? '';
$uploadMessage = '';
$rows = [];
$columns = [];

// Handle CSV upload
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

// Handle custom query
$customResult = null;
if ($tab === 'query' && $query) {
    try {
        $stmt = $pdo->query($query);
        $customResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $customResult = ['error' => $e->getMessage()];
    }
}

// Handle table browsing
if ($tab === 'browse' && $table) {
    $stmt = $pdo->query("SELECT * FROM `$table` LIMIT 100");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_keys($rows[0] ?? []);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GST Admin Panel</title>
    <style>
        body { font-family: sans-serif; margin: 2em; background: #f9f9f9; }
        h1 { font-size: 1.5em; margin-bottom: 1em; }
        nav a { margin-right: 1em; text-decoration: none; font-weight: bold; }
        nav a.active { color: darkblue; }
        table { border-collapse: collapse; width: 100%; margin-top: 1em; }
        th, td { border: 1px solid #ccc; padding: 0.4em; text-align: left; }
        th { background-color: #eee; }
        textarea, input[type=file] { width: 100%; margin-top: 0.5em; }
        .alert { margin-top: 1em; padding: 0.8em; background: #fff3cd; border: 1px solid #ffeeba; }
    </style>
</head>
<body>
<h1>📊 GST Accounting – Admin Panel</h1>

<nav>
    <a href="?tab=browse" class="<?= $tab === 'browse' ? 'active' : '' ?>">Browse</a>
    <a href="?tab=query" class="<?= $tab === 'query' ? 'active' : '' ?>">Query</a>
    <a href="?tab=import" class="<?= $tab === 'import' ? 'active' : '' ?>">Import</a>
    <a href="?tab=tools" class="<?= $tab === 'tools' ? 'active' : '' ?>">Tools</a>
</nav>

<?php if ($tab === 'browse'): ?>
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
        <h2>🧾 Preview: <?= htmlspecialchars($table) ?> (first 100 rows)</h2>
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
<?php endif; ?>

<?php if ($tab === 'query'): ?>
    <h2>🧠 Run Custom SQL</h2>
    <form method="post">
        <textarea name="query" rows="6" placeholder="SELECT * FROM invoices LIMIT 10"><?= htmlspecialchars($query) ?></textarea><br>
        <button type="submit">Execute</button>
    </form>

    <?php if ($customResult): ?>
        <?php if (isset($customResult['error'])): ?>
            <div class="alert">❌ Error: <?= htmlspecialchars($customResult['error']) ?></div>
        <?php else: ?>
            <h3>✅ Result (<?= count($customResult) ?> rows)</h3>
            <table>
                <thead>
                <tr>
                    <?php foreach (array_keys($customResult[0] ?? []) as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($customResult as $row): ?>
                    <tr>
                        <?php foreach ($row as $val): ?>
                            <td><?= htmlspecialchars((string)$val) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php if ($tab === 'import'): ?>
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
<?php endif; ?>

<?php if ($tab === 'tools'): ?>
    <h2>🛠️ Workflow Tools</h2>
    <p>This section will include:</p>
    <ul>
        <li>✅ Hindi translation validator</li>
        <li>✅ Master list merger</li>
        <li>✅ Referential integrity checker</li>
        <li>✅ Normalization assistant</li>
    </ul>
    <p>Let me know which you'd like to activate first, and I’ll plug it in.</p>
<?php endif; ?>
</body>
</html>

