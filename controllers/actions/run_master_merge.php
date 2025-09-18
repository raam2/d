<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = (new Database())->getConnection();

$stmt = $pdo->query("SELECT * FROM stg_purchase_invoice_hindi ORDER BY id LIMIT 500");
$rows = $stmt->fetchAll();

echo "<h3>📦 Merged Master List Preview (" . count($rows) . " rows)</h3><table><tr>";
foreach (array_keys($rows[0] ?? []) as $col) {
    echo "<th>" . htmlspecialchars($col) . "</th>";
}
echo "</tr>";
foreach ($rows as $row) {
    echo "<tr>";
    foreach ($row as $val) {
        echo "<td>" . htmlspecialchars((string)$val) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

