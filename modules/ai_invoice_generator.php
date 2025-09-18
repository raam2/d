<?php
require_once __DIR__ . '/../config/db.php';
$db = new Database();
$conn = $db->getConnection();

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$preview = $_GET['preview'] ?? 'true';

$stmt = $conn->prepare("SELECT * FROM gst_payment WHERE payment_date BETWEEN :from AND :to");
$stmt->execute([':from' => $from, ':to' => $to]);
$receipts = $stmt->fetchAll();

foreach ($receipts as $r) {
    $party_id = matchParty($conn, $r['reference_no']);
    $gst_type = inferGSTType($conn, $party_id);
    $items = generateItems($r['amount']);

    if ($preview === 'true') {
        echo "Preview Invoice for ₹{$r['amount']} → Party #$party_id\n";
        foreach ($items as $item) {
            echo "- {$item['description']} ₹{$item['total']} (GST: ₹{$item['gst_amount']})\n";
        }
        echo "\n";
    } else {
        $stmt = $conn->prepare("INSERT INTO gst_invoice (...) VALUES (...)");
        $stmt->execute([...]);
        $invoice_id = $conn->lastInsertId();

        foreach ($items as $item) {
            $stmt = $conn->prepare("INSERT INTO gst_invoice_items (...) VALUES (...)");
            $stmt->execute([...]);
        }
    }
}



function matchParty($conn, $ref) {
    $stmt = $conn->prepare("SELECT id FROM gst_party WHERE gstin LIKE :ref OR name LIKE :ref");
    $stmt->execute([':ref' => "%$ref%"]);
    return $stmt->fetchColumn() ?: 1; // fallback to default party
}

function inferGSTType($conn, $party_id) {
    $stmt = $conn->prepare("SELECT state_code FROM gst_party WHERE id = :id");
    $stmt->execute([':id' => $party_id]);
    $state = $stmt->fetchColumn();
    return ($state === '09') ? 'CGST+SGST' : 'IGST'; // assuming your state is UP (code 09)
}

function generateItems($amount) {
    $base = $amount / 1.18;
    $gst = $amount - $base;

    $item = array(
        'description' => 'Auto-generated service',
        'hsn_code' => '9983',
        'quantity' => 1,
        'rate' => round($base, 2),
        'gst_rate' => 18,
        'gst_amount' => round($gst, 2),
        'total' => round($amount, 2)
    );

    return array($item);
}

?>
