<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = (new Database())->getConnection();

$stmt = $pdo->query("SELECT ii.id, ii.item_id FROM invoice_items ii LEFT JOIN items it ON ii.item_id = it.id WHERE it.id IS NULL LIMIT 500");
$rows = $stmt->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="broken_refs.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Invoice_Item_ID', 'item_id']);
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;

