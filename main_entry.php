<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/db.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$tables = [];
$error = null;

try {
    $rows = fetchAll('SHOW TABLES');
    foreach ($rows as $row) {
        $values = array_values($row);
        if (!empty($values)) {
            $tables[] = (string)$values[0];
        }
    }
} catch (Throwable $t) {
    $error = $t->getMessage();
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Database Tables · Test View</title>
    <style>
        :root { color-scheme: dark; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { margin: 0; background: #0b0c10; color: #e5e7eb; }
        main { max-width: 720px; margin: 60px auto; padding: 32px; background: #0f172a; border: 1px solid #1f2937; border-radius: 12px; }
        h1 { margin-top: 0; font-size: 1.6rem; }
        p { color: #94a3b8; }
        ul { list-style: none; padding: 0; margin: 24px 0; }
        li { padding: 10px 12px; border: 1px solid #1f2937; border-radius: 8px; background: #0b1120; margin-bottom: 10px; font-family: 'JetBrains Mono', monospace; }
        .empty { color: #94a3b8; font-style: italic; }
        .error { background: #7f1d1d; border: 1px solid #ef4444; color: #fee2e2; padding: 12px; border-radius: 6px; }
    </style>
</head>
<body>
<main>
    <h1>Database Tables</h1>
    <p>This test page lists every table that the current database connection can see.</p>

    <?php if ($error !== null): ?>
        <div class="error"><?=h($error)?></div>
    <?php elseif (empty($tables)): ?>
        <p class="empty">No tables found in this schema.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($tables as $table): ?>
                <li><?=h($table)?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>
</body>
</html>
