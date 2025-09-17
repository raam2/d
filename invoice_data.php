<?php
require 'bootstrap.php';

// Assuming $pdo is your PDO database connection from bootstrap.php

$sql = "SELECT i.id, i.invoice_no, i.inv_type, i.status, i.itc_eligible, i.place_of_supply, i.external_supplier_invoice_no, i.party_id, i.external_sales_ref_no, i.reverse_charge, i.seq_no, i.series_code, i.invoice_date 
        FROM invoices i
        ORDER BY i.invoice_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Invoices</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
<h1>Invoice Data</h1>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Invoice No</th>
            <th>Type</th>
            <th>Status</th>
            <th>ITC Eligible</th>
            <th>Place of Supply</th>
            <th>External Supplier Invoice No</th>
            <th>Party ID</th>
            <th>External Sales Ref No</th>
            <th>Reverse Charge</th>
            <th>Sequence No</th>
            <th>Series Code</th>
            <th>Invoice Date</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($invoices as $inv): ?>
            <tr>
                <td><?= htmlspecialchars($inv['id']) ?></td>
                <td><?= htmlspecialchars($inv['invoice_no']) ?></td>
                <td><?= htmlspecialchars($inv['inv_type']) ?></td>
                <td><?= htmlspecialchars($inv['status']) ?></td>
                <td><?= htmlspecialchars($inv['itc_eligible']) ?></td>
                <td><?= htmlspecialchars($inv['place_of_supply']) ?></td>
                <td><?= htmlspecialchars($inv['external_supplier_invoice_no']) ?></td>
                <td><?= htmlspecialchars($inv['party_id']) ?></td>
                <td><?= htmlspecialchars($inv['external_sales_ref_no']) ?></td>
                <td><?= htmlspecialchars($inv['reverse_charge']) ?></td>
                <td><?= htmlspecialchars($inv['seq_no']) ?></td>
                <td><?= htmlspecialchars($inv['series_code']) ?></td>
                <td><?= htmlspecialchars($inv['invoice_date']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
