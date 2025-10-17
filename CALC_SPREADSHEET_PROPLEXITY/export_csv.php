<?php
// export_csv.php

// Simulate fetching spreadsheet data; replace with your real data source
$data = $_SESSION['spreadsheet_data'] ?? [
    ['Row', 'A', 'B', 'C', 'D', 'E'],
    ['1 (Value)', '10', '20', '30', '40', '50']
];

// send headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="spreadsheet_export_' . date('Ymd_His') . '.csv"');

// output buffer
$output = fopen('php://output', 'w');

// write data rows properly with fputcsv for escaping
foreach ($data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>

