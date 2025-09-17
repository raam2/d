<?php
require_once __DIR__ . '/includes/db.php';
$pdo = (new Database())->getConnection();
$tab = $_GET['tab'] ?? 'browse';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GST Admin Panel</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<h1>📊 GST Accounting – Admin Panel</h1>
<nav>
    <a href="?tab=browse" class="<?= $tab === 'browse' ? 'active' : '' ?>">Browse</a>
    <a href="?tab=query" class="<?= $tab === 'query' ? 'active' : '' ?>">Query</a>
    <a href="?tab=import" class="<?= $tab === 'import' ? 'active' : '' ?>">Import</a>
    <a href="?tab=tools" class="<?= $tab === 'tools' ? 'active' : '' ?>">Tools</a>
</nav>

<?php
$viewFile = __DIR__ . "/views/{$tab}.php";
if (file_exists($viewFile)) {
    include $viewFile;
} else {
    echo "<p class='alert'>Invalid tab selected.</p>";
}
?>

<hr>
<hr>
<h2>📁 Directory Listing</h2>
<p>Sort by:
    <a href="?tab=<?= $tab ?>&sort=size">Size</a> |
    <a href="?tab=<?= $tab ?>&sort=date">Date</a> |
    <a href="?tab=<?= $tab ?>">Name</a>
</p>
<table>
    <thead>
        <tr><th>Name</th><th>Type</th><th>Size</th><th>Modified</th></tr>
    </thead>
    <tbody>
        <?php
        $sort = $_GET['sort'] ?? 'name';
        $items = array_filter(scandir(__DIR__), fn($i) => $i !== '.' && $i !== '..' && !str_starts_with($i, '.'));

        $files = [];
        foreach ($items as $item) {
            $path = __DIR__ . '/' . $item;
            $files[] = [
                'name' => $item,
                'type' => is_dir($path) ? 'Folder' : 'File',
                'size' => is_file($path) ? filesize($path) : 0,
                'date' => filemtime($path),
                'link' => $item
            ];
        }

        if ($sort === 'size') {
            usort($files, fn($a, $b) => $b['size'] <=> $a['size']);
        } elseif ($sort === 'date') {
            usort($files, fn($a, $b) => $b['date'] <=> $a['date']);
        } else {
            usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));
        }

        foreach ($files as $file) {
            echo "<tr>
                <td><a href=\"{$file['link']}\" style=\"color:#90caf9\">{$file['name']}</a></td>
                <td>{$file['type']}</td>
                <td>" . ($file['type'] === 'File' ? $file['size'] : '-') . "</td>
                <td>" . date('Y-m-d H:i', $file['date']) . "</td>
            </tr>";
        }
        ?>
    </tbody>
</table>
</body>
</html>

