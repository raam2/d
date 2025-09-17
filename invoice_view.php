<?php
// invoice_view.php
require_once __DIR__ . '/bootstrap.php';

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo "Bad request: missing invoice id";
  exit;
}

// header
$sqlH = "
SELECT i.*, c.company_name, c.state AS company_state,
       p.name AS party_name, p.gstin AS party_gstin, p.city AS party_city, p.state AS party_state
FROM invoices i
JOIN companies c ON c.id = i.company_id
LEFT JOIN parties p ON p.id = i.party_id
WHERE i.id = :id
";
$stH = $conn->prepare($sqlH);
$stH->execute([':id'=>$id]);
$inv = $stH->fetch(PDO::FETCH_ASSOC);
if (!$inv) { echo "Invoice not found."; exit; }

// lines
$sqlL = "
SELECT ii.*, it.canonical_name
FROM invoice_items ii
LEFT JOIN items it ON it.id = ii.item_id
WHERE ii.invoice_id = :id
ORDER BY ii.id
";
$stL = $conn->prepare($sqlL);
$stL->execute([':id'=>$id]);
$lines = $stL->fetchAll(PDO::FETCH_ASSOC);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function nf($n){ return number_format((float)$n, 2); }

// summarized totals from rows (generated columns already present)
$tot = [
  'taxable' => 0, 'cgst'=>0, 'sgst'=>0, 'igst'=>0, 'grand'=>0
];
foreach($lines as $ln){
  $tot['taxable'] += (float)$ln['taxable_amount'];
  $tot['cgst']    += (float)$ln['cgst_amount'];
  $tot['sgst']    += (float)$ln['sgst_amount'];
  $tot['igst']    += (float)$ln['igst_amount'];
  $tot['grand']   += (float)$ln['line_total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice <?=h($inv['invoice_no'])?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:16px;}
  .header{display:flex;justify-content:space-between;gap:16px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.05);margin-bottom:12px}
  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left}
  th{font-size:12px;color:#374151;text-transform:uppercase;letter-spacing:.03em}
  .right{text-align:right}
  .muted{color:#6b7280}
</style>
</head>
<body>
  <div class="header">
    <h2><?=h(ucfirst($inv['inv_type']))?> Invoice • <?=h($inv['invoice_no'])?></h2>
    <div><a href="purchase_register.php">← Back to register</a></div>
  </div>

  <div class="card">
    <strong><?=h($inv['company_name'])?></strong><br>
    POS: <?=h($inv['place_of_supply'])?> &nbsp;|&nbsp; Company State: <?=h($inv['company_state'])?><br>
    Date: <?=h($inv['invoice_date'])?> &nbsp;|&nbsp; Status: <?=h($inv['status'])?><br>
    Reverse charge: <?= $inv['reverse_charge'] ? 'Yes' : 'No' ?> &nbsp;|&nbsp;
    ITC eligible: <?= $inv['itc_eligible'] ? 'Yes' : 'No' ?>
  </div>

  <div class="card">
    <strong>Vendor / Party</strong><br>
    <?=h($inv['party_name'] ?: ('Party #'.$inv['party_id']))?><br>
    GSTIN: <?=h($inv['party_gstin'] ?: '—')?> &nbsp;|&nbsp; <?=h($inv['party_city'])?>, <?=h($inv['party_state'])?>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Description</th>
          <th>HSN</th>
          <th class="right">Qty</th>
          <th class="right">Rate</th>
          <th class="right">Taxable</th>
          <th class="right">CGST</th>
          <th class="right">SGST</th>
          <th class="right">IGST</th>
          <th class="right">Line Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($lines as $i=>$ln): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= h($ln['description'] ?: $ln['canonical_name']) ?></td>
          <td><?= h($ln['hsn']) ?></td>
          <td class="right"><?= nf($ln['quantity']) ?></td>
          <td class="right"><?= nf($ln['rate']) ?></td>
          <td class="right"><?= nf($ln['taxable_amount']) ?></td>
          <td class="right"><?= nf($ln['cgst_amount']) ?></td>
          <td class="right"><?= nf($ln['sgst_amount']) ?></td>
          <td class="right"><?= nf($ln['igst_amount']) ?></td>
          <td class="right"><strong><?= nf($ln['line_total']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="5" class="right">Totals</th>
          <th class="right"><?= nf($tot['taxable']) ?></th>
          <th class="right"><?= nf($tot['cgst']) ?></th>
          <th class="right"><?= nf($tot['sgst']) ?></th>
          <th class="right"><?= nf($tot['igst']) ?></th>
          <th class="right"><?= nf($tot['grand']) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</body>
</html>

