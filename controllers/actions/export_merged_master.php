<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = (new Database())->getConnection();

$stmt = $pdo->query("SELECT * FROM stg_purchase_invoice_hindi ORDER BY id LIMIT 500");
$rows = $stmt->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="merged_master.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, array_keys($rows[0] ?? []));
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;

