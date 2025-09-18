<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = (new Database())->getConnection();

$stmt = $pdo->query("SELECT id, Hindi_Name, description FROM stg_purchase_invoice_hindi WHERE Hindi_Name IS NULL OR Hindi_Name = '' LIMIT 500");
$rows = $stmt->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="missing_hindi.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['id', 'Hindi_Name', 'description']);
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;

