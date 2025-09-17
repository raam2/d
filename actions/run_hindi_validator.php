<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = (new Database())->getConnection();

$stmt = $pdo->query("SELECT id, Hindi_Name, description FROM stg_purchase_invoice_hindi WHERE Hindi_Name IS NULL OR Hindi_Name = '' LIMIT 500");
$rows = $stmt->fetchAll();

echo "<h3>🧠 Missing Hindi_Name (" . count($rows) . " rows)</h3><table><tr><th>ID</th><th>Description</th><th>Hindi_Name</th></tr>";
foreach ($rows as $row) {
    echo "<tr><td>{$row['id']}</td><td>" . htmlspecialchars($row['description']) . "</td><td>" . htmlspecialchars($row['Hindi_Name']) . "</td></tr>";
}
echo "</table>";

