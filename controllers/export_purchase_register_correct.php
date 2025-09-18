<?php
/**
 * export_purchase_register_correct.php
 *
 * Rebuild Purchase Register **to match Purchase_Summary.csv**:
 * - Select invoices ONLY by invoice numbers (inv=... or csv=...)
 * - Derive GST amounts from taxable and rates (do NOT trust stored sgst/cgst columns)
 * - Optional rounding mode: rounding=per_line | aggregate (default: per_line)
 *
 * Usage:
 *   /export_purchase_register_correct.php?inv=INV1,INV2
 *   /export_purchase_register_correct.php?csv=Purchase-Invoice-Detail-Enhanced.csv
 *   /export_purchase_register_correct.php?format=html&csv=Purchase-Invoice-Detail-Enhanced.csv&rounding=per_line
 */

declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL);

function get_pdo(): PDO {
    $dbfile = __DIR__ . '/db.php';
    if (file_exists($dbfile)) {
        require_once $dbfile;
        if (isset($pdo) && $pdo instanceof PDO) return $pdo;
        if (isset($db) && $db instanceof PDO) return $db;
        if (class_exists('Database')) {
            try {
                $obj = new Database();
                if (method_exists($obj,'getConnection')) {
                    $p = $obj->getConnection();
                    if ($p instanceof PDO) return $p;
                }
            } catch (Throwable $e) {}
        }
    }
    $dsn = "mysql:host=127.0.0.1;port=3306;dbname=gst_accounting;charset=utf8mb4";
    $user = "root"; $pass = "";
    return new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND=>"SET NAMES utf8mb4",
    ]);
}
$pdo = get_pdo();

$format   = $_GET['format']  ?? 'csv'; // csv | html
$rounding = $_GET['rounding']?? 'per_line'; // per_line | aggregate
$invParam = $_GET['inv']     ?? '';
$fromCsv  = $_GET['csv']     ?? '';

// Parse invoice numbers
$invoiceNos = array_values(array_filter(array_map('trim', preg_split('/[,\r\n;\t]+/', $invParam))));

if (empty($invoiceNos)) {
    // Try to parse from CSV file on server
    $csvPath = '';
    if ($fromCsv !== '') {
        $test = realpath(__DIR__ . '/' . basename($fromCsv));
        if ($test && is_file($test)) $csvPath = $test;
    } else {
        $guess = __DIR__ . '/Purchase-Invoice-Detail-Enhanced.csv';
        if (is_file($guess)) $csvPath = $guess;
    }
    if ($csvPath !== '') {
        if (($fh = fopen($csvPath,'r')) !== false) {
            $header = fgetcsv($fh);
            $map = [];
            if (is_array($header)) {
                foreach ($header as $i=>$h) $map[strtolower(trim((string)$h))] = $i;
            }
            $idx = null;
            foreach ($map as $k=>$i) {
                if (strpos($k,'invoice') !== false && strpos($k,'no') !== false) { $idx=$i; break; }
                if (strpos($k,'bill no') !== false) { $idx=$i; break; }
            }
            if ($idx === null) $idx = 0;
            $set = [];
            while (($row = fgetcsv($fh)) !== false) {
                if (isset($row[$idx])) {
                    $v = trim((string)$row[$idx]);
                    if ($v !== '') $set[$v] = true;
                }
            }
            fclose($fh);
            $invoiceNos = array_keys($set);
        }
    }
}
if (empty($invoiceNos)) {
    header('Content-Type: text/html; charset=utf-8'); ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Export Purchase Register (Correct)</title>
    <style>body{font-family:system-ui;padding:20px} textarea{width:100%;height:160px}</style></head><body>
    <h3>Export Purchase Register (Correct)</h3>
    <form method="get">
      <p><label>Invoice numbers (comma/newline separated)</label><br>
      <textarea name="inv" placeholder="INV001, INV002, ..."></textarea></p>
      <p>or CSV filename on server (e.g. <code>Purchase-Invoice-Detail-Enhanced.csv</code>):<br>
      <input type="text" name="csv" style="width:420px"></p>
      <p><label>Rounding</label>
      <select name="rounding"><option value="per_line">Per line (match printed bills)</option><option value="aggregate">Aggregate (mathematical)</option></select></p>
      <p><button type="submit">Generate CSV</button> <small>Use <code>&format=html</code> to preview.</small></p>
    </form></body></html>
    <?php exit;
}

// Build IN list bind params
$bind = []; $ph = [];
foreach ($invoiceNos as $i => $no) { $k=':inv'.$i; $ph[]=$k; $bind[$k]=$no; }

// Build SQL derived amounts
$expr_taxable = "(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100))";
$expr_cgst = "($expr_taxable * ii.cgst_rate/100)";
$expr_sgst = "($expr_taxable * ii.sgst_rate/100)";
$expr_igst = "($expr_taxable * ii.igst_rate/100)";

if ($rounding === 'per_line') {
    $expr_taxable = "ROUND($expr_taxable, 2)";
    $expr_cgst    = "ROUND($expr_cgst, 2)";
    $expr_sgst    = "ROUND($expr_sgst, 2)";
    $expr_igst    = "ROUND($expr_igst, 2)";
}

// Query
$sql = "
SELECT
  i.id,
  i.invoice_date,
  i.invoice_no,
  p.name AS party,
  i.place_of_supply,
  ROUND(SUM($expr_taxable), 2) AS taxable,
  ROUND(SUM($expr_cgst), 2)    AS cgst,
  ROUND(SUM($expr_sgst), 2)    AS sgst,
  ROUND(SUM($expr_igst), 2)    AS igst
FROM invoices i
JOIN parties p       ON p.id = i.party_id
JOIN invoice_items ii ON ii.invoice_id = i.id
WHERE i.inv_type = 'purchase'
  AND i.invoice_no IN (".implode(',', $ph).")
GROUP BY i.id, i.invoice_date, i.invoice_no, p.name, i.place_of_supply
ORDER BY i.invoice_date, i.invoice_no";
$stmt = $pdo->prepare($sql);
foreach ($bind as $k=>$v) $stmt->bindValue($k,$v);
$stmt->execute();
$rows = $stmt->fetchAll();

// Totals
$tot = ['taxable'=>0.0,'cgst'=>0.0,'sgst'=>0.0,'igst'=>0.0,'grand'=>0.0];
foreach ($rows as &$r) {
    $r['grand_total'] = (float)$r['taxable'] + (float)$r['cgst'] + (float)$r['sgst'] + (float)$r['igst'];
    $tot['taxable'] += (float)$r['taxable'];
    $tot['cgst']    += (float)$r['cgst'];
    $tot['sgst']    += (float)$r['sgst'];
    $tot['igst']    += (float)$r['igst'];
    $tot['grand']   += (float)$r['grand_total'];
}

if ($format === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Purchase Register (Correct)</title>";
    echo "<style>body{font-family:system-ui;padding:20px} table{border-collapse:collapse} th,td{padding:6px 10px;border:1px solid #ddd} th{text-align:left;position:sticky;top:0;background:#f8f8f8} tfoot td{font-weight:bold;background:#f0f0f0}</style></head><body>";
    echo "<h3>Purchase Register — Selected Invoices</h3>";
    echo "<p><strong>Invoices:</strong> ".htmlspecialchars(implode(', ', $invoiceNos), ENT_QUOTES, 'UTF-8')."</p>";
    echo "<p><a href='?".http_build_query(['inv'=>implode(',', $invoiceNos)])."'>Download CSV</a></p>";
    echo "<table><thead><tr>
            <th>Date</th><th>Invoice No</th><th>Party</th><th>Place of Supply</th>
            <th style='text-align:right'>Taxable</th><th style='text-align:right'>CGST</th>
            <th style='text-align:right'>SGST</th><th style='text-align:right'>IGST</th><th style='text-align:right'>Grand</th>
          </tr></thead><tbody>";
    foreach ($rows as $r) {
        printf(
            "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td style='text-align:right'>%.2f</td><td style='text-align:right'>%.2f</td><td style='text-align:right'>%.2f</td><td style='text-align:right'>%.2f</td><td style='text-align:right'>%.2f</td></tr>",
            htmlspecialchars($r['invoice_date'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($r['invoice_no'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($r['party'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($r['place_of_supply'], ENT_QUOTES, 'UTF-8'),
            $r['taxable'], $r['cgst'], $r['sgst'], $r['igst'], $r['grand_total']
        );
    }
    printf(
        "</tbody><tfoot><tr><td colspan='4' style='text-align:right'>Totals</td><td style='text-align:right'>%.2f</td><td style='text-align:right'>%.2f</td><td style='text-align:right'>%.2f</td><td style='text-align:right'>%.2f</td><td style='text-align:right'>%.2f</td></tr></tfoot></table>",
        $tot['taxable'], $tot['cgst'], $tot['sgst'], $tot['igst'], $tot['grand']
    );
    echo "</body></html>";
    exit;
}

// CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="purchase_register.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Date','Invoice No','Party','Place of Supply','Taxable','CGST','SGST','IGST','Grand Total']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['invoice_date'], $r['invoice_no'], $r['party'], $r['place_of_supply'],
        number_format((float)$r['taxable'],2,'.',''),
        number_format((float)$r['cgst'],2,'.',''),
        number_format((float)$r['sgst'],2,'.',''),
        number_format((float)$r['igst'],2,'.',''),
        number_format((float)$r['grand_total'],2,'.',''),
    ]);
}
fputcsv($out, ['', '', 'Totals', '',
    number_format((float)$tot['taxable'],2,'.',''),
    number_format((float)$tot['cgst'],2,'.',''),
    number_format((float)$tot['sgst'],2,'.',''),
    number_format((float)$tot['igst'],2,'.',''),
    number_format((float)$tot['grand'],2,'.',''),
]);
fclose($out); exit;
