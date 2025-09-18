<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = (new Database())->getConnection();

$stmt = $pdo->query("SELECT ii.id, ii.item_id FROM invoice_items ii LEFT JOIN items it ON ii.item_id = it.id WHERE it.id IS NULL LIMIT 500");
$rows = $stmt->fetchAll();

echo "<h3>🔗 Broken item_id references (" . count($rows) . " rows)</h3><table><tr><th>Invoice Item ID</th><th>item_id</th></tr>";
foreach ($rows as $row) {
    echo "<tr><td>{$row['id']}</td><td>{$row['item_id']}</td></tr>";
}
echo "</table>";

