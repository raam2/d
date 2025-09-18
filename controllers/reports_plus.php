<?php
// reports_plus.php — Extended GST reports: Purchase, HSN, GST Rate, Place of Supply, Party Ledger & Aging
// Drop next to db.php and open in browser: /reports_plus.php
declare(strict_types=1);
ini_set('display_errors', '0'); error_reporting(E_ALL);

function get_pdo(): PDO {
    if (file_exists(__DIR__.'/db.php')) {
        require_once __DIR__.'/db.php';
        if (isset($pdo) && $pdo instanceof PDO) return $pdo;
        if (isset($db) && $db instanceof PDO) return $db;
        if (class_exists('Database')) {
            try {
                $inst = new Database();
                if (method_exists($inst, 'getConnection')) {
                    $p = $inst->getConnection();
                    if ($p instanceof PDO) return $p;
                }
            } catch (Throwable $e) {}
        }
    }
    // Fallback (edit if needed)
    $dsn = "mysql:host=127.0.0.1;port=3306;dbname=gst_accounting;charset=utf8mb4";
    $user = "root"; $pass = "";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
}
$pdo = get_pdo();

// Inputs
$report   = $_GET['report'] ?? 'purchase';   // purchase | hsn | gst_rate | pos | party_aging
$from     = trim($_GET['from'] ?? '');
$to       = trim($_GET['to'] ?? '');
$party_id = isset($_GET['party_id']) && $_GET['party_id'] !== '' ? (int)$_GET['party_id'] : null;
$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$pagesz   = min(200, max(10, (int)($_GET['pagesz'] ?? 50)));
$action   = $_GET['action'] ?? '';

// Party options
$parties = $pdo->query("SELECT id, name FROM parties ORDER BY name")->fetchAll();

// Helpers
function build_date_filters(&$sql, array &$bind, string $from, string $to): void {
    if ($from !== '') { $sql .= " AND i.invoice_date >= :from"; $bind[':from'] = $from; }
    if ($to   !== '') { $sql .= " AND i.invoice_date <= :to";   $bind[':to']   = $to;   }
}
function build_party_filter(&$sql, array &$bind, ?int $party_id): void {
    if ($party_id !== null) { $sql .= " AND i.party_id = :party"; $bind[':party'] = $party_id; }
}
function build_search_invoice_party(&$sql, array &$bind, string $q): void {
    if ($q !== '') { $sql .= " AND (i.invoice_no LIKE :q OR p.name LIKE :q)"; $bind[':q'] = "%{$q}%"; }
}
function build_search_party(&$sql, array &$bind, string $q): void {
    if ($q !== '') { $sql .= " AND p.name LIKE :q"; $bind[':q'] = "%{$q}%"; }
}
function stream_csv(string $filename, array $headers, iterable $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out); exit;
}

// PURCHASE REGISTER
function fetch_purchase_register(PDO $pdo, string $from, string $to, ?int $party_id, string $q, int $page, int $pagesz, bool $for_export=false) {
    $bind = [];
    $base =
        " FROM invoices i
          JOIN parties p     ON p.id = i.party_id
          JOIN invoice_items ii ON ii.invoice_id = i.id
          WHERE i.inv_type = 'purchase' ";
    build_date_filters($base, $bind, $from, $to);
    build_party_filter($base, $bind, $party_id);
    build_search_invoice_party($base, $bind, $q);

    $sqlCount = "SELECT COUNT(DISTINCT i.id) AS cnt " . $base;
    $stmtC = $pdo->prepare($sqlCount); $stmtC->execute($bind);
    $total_rows = (int)$stmtC->fetchColumn();

    $sqlTotals =
        "SELECT
            SUM(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100))                          AS taxable,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.cgst_rate/100)     AS cgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.sgst_rate/100)     AS sgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.igst_rate/100)     AS igst
         " . $base;
    $stmtT = $pdo->prepare($sqlTotals); $stmtT->execute($bind);
    $tot = $stmtT->fetch() ?: ['taxable'=>0,'cgst'=>0,'sgst'=>0,'igst'=>0];
    foreach (['taxable','cgst','sgst','igst'] as $k) $tot[$k] = (float)($tot[$k] ?? 0);
    $tot['grand'] = $tot['taxable'] + $tot['cgst'] + $tot['sgst'] + $tot['igst'];

    $sqlData =
        "SELECT
            i.id,
            i.invoice_date,
            i.invoice_no,
            p.name AS party,
            i.place_of_supply,
            ROUND(SUM(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)), 2) AS taxable,
            ROUND(SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.cgst_rate/100), 2) AS cgst,
            ROUND(SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.sgst_rate/100), 2) AS sgst,
            ROUND(SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.igst_rate/100), 2) AS igst
          " . $base .
        " GROUP BY i.id
          ORDER BY i.invoice_date DESC, i.invoice_no DESC";
    if (!$for_export) { $offset = ($page-1)*$pagesz; $sqlData .= " LIMIT :lim OFFSET :off"; }
    $stmtD = $pdo->prepare($sqlData);
    foreach ($bind as $k=>$v) $stmtD->bindValue($k, $v);
    if (!$for_export) { $stmtD->bindValue(':lim',$pagesz,PDO::PARAM_INT); $stmtD->bindValue(':off',$offset,PDO::PARAM_INT); }
    $stmtD->execute();
    $rows = $stmtD->fetchAll();
    foreach ($rows as &$r) { $r['grand_total'] = (float)$r['taxable'] + (float)$r['cgst'] + (float)$r['sgst'] + (float)$r['igst']; }
    return [$rows, $total_rows, $tot];
}

// HSN SUMMARY
function fetch_hsn_summary(PDO $pdo, string $from, string $to, ?int $party_id, int $page, int $pagesz, bool $for_export=false) {
    $bind = [];
    $base =
        " FROM invoices i
          JOIN invoice_items ii ON ii.invoice_id = i.id
          WHERE i.inv_type = 'purchase' ";
    build_date_filters($base, $bind, $from, $to);
    if ($party_id !== null) { $base .= " AND i.party_id = :party"; $bind[':party'] = $party_id; }

    $sqlCount = "SELECT COUNT(DISTINCT ii.hsn) AS cnt " . $base;
    $stmtC = $pdo->prepare($sqlCount); $stmtC->execute($bind);
    $total_rows = (int)$stmtC->fetchColumn();

    $sqlTotals =
        "SELECT
            SUM(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100))                          AS taxable,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.cgst_rate/100)     AS cgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.sgst_rate/100)     AS sgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.igst_rate/100)     AS igst
          " . $base;
    $stmtT = $pdo->prepare($sqlTotals); $stmtT->execute($bind);
    $tot = $stmtT->fetch() ?: ['taxable'=>0,'cgst'=>0,'sgst'=>0,'igst'=>0];
    foreach (['taxable','cgst','sgst','igst'] as $k) $tot[$k] = (float)($tot[$k] ?? 0);
    $tot['grand'] = $tot['taxable'] + $tot['cgst'] + $tot['sgst'] + $tot['igst'];

    $sqlData =
        "SELECT
            IFNULL(NULLIF(TRIM(ii.hsn),''), '(unset)') AS hsn,
            ROUND(SUM(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)), 2) AS taxable,
            ROUND(SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.cgst_rate/100), 2) AS cgst,
            ROUND(SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.sgst_rate/100), 2) AS sgst,
            ROUND(SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.igst_rate/100), 2) AS igst
          " . $base .
        " GROUP BY hsn
          ORDER BY hsn ASC";
    if (!$for_export) { $offset = ($page-1)*$pagesz; $sqlData .= " LIMIT :lim OFFSET :off"; }
    $stmtD = $pdo->prepare($sqlData);
    foreach ($bind as $k=>$v) $stmtD->bindValue($k, $v);
    if (!$for_export) { $stmtD->bindValue(':lim',$pagesz,PDO::PARAM_INT); $stmtD->bindValue(':off',$offset,PDO::PARAM_INT); }
    $stmtD->execute();
    $rows = $stmtD->fetchAll();
    foreach ($rows as &$r) { $r['grand_total'] = (float)$r['taxable'] + (float)$r['cgst'] + (float)$r['sgst'] + (float)$r['igst']; }
    return [$rows, $total_rows, $tot];
}

// GST RATE SUMMARY
function fetch_gst_rate_summary(PDO $pdo, string $from, string $to, ?int $party_id, int $page, int $pagesz, bool $for_export=false) {
    $bind = [];
    $base =
        " FROM invoices i
          JOIN invoice_items ii ON ii.invoice_id = i.id
          WHERE i.inv_type = 'purchase' ";
    build_date_filters($base, $bind, $from, $to);
    if ($party_id !== null) { $base .= " AND i.party_id = :party"; $bind[':party'] = $party_id; }

    $sqlCount = "SELECT COUNT(DISTINCT ROUND(ii.cgst_rate+ii.sgst_rate+ii.igst_rate,2)) AS cnt " . $base;
    $stmtC = $pdo->prepare($sqlCount); $stmtC->execute($bind);
    $total_rows = (int)$stmtC->fetchColumn();

    $sqlTotals =
        "SELECT
            SUM(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100))                          AS taxable,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * (ii.cgst_rate+ii.sgst_rate+ii.igst_rate)/100) AS gst
          " . $base;
    $stmtT = $pdo->prepare($sqlTotals); $stmtT->execute($bind);
    $tot = $stmtT->fetch() ?: ['taxable'=>0,'gst'=>0];

    $sqlData =
        "SELECT
           ROUND(ii.cgst_rate+ii.sgst_rate+ii.igst_rate,2) AS rate_percent,
           ROUND(SUM(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)), 2) AS taxable,
           ROUND(SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * (ii.cgst_rate+ii.sgst_rate+ii.igst_rate)/100), 2) AS gst,
           COUNT(DISTINCT i.id) AS invoices
         " . $base .
        " GROUP BY rate_percent
          ORDER BY rate_percent";
    if (!$for_export) { $offset = ($page-1)*$pagesz; $sqlData .= " LIMIT :lim OFFSET :off"; }
    $stmtD = $pdo->prepare($sqlData);
    foreach ($bind as $k=>$v) $stmtD->bindValue($k, $v);
    if (!$for_export) { $stmtD->bindValue(':lim',$pagesz,PDO::PARAM_INT); $stmtD->bindValue(':off',$offset,PDO::PARAM_INT); }
    $stmtD->execute();
    $rows = $stmtD->fetchAll();
    return [$rows, $total_rows, $tot];
}

// PLACE OF SUPPLY SUMMARY
function fetch_pos_summary(PDO $pdo, string $from, string $to, ?int $party_id, int $page, int $pagesz, bool $for_export=false) {
    $bind = [];
    $base =
        " FROM invoices i
          JOIN invoice_items ii ON ii.invoice_id = i.id
          WHERE i.inv_type = 'purchase' ";
    build_date_filters($base, $bind, $from, $to);
    if ($party_id !== null) { $base .= " AND i.party_id = :party"; $bind[':party'] = $party_id; }

    $sqlCount = "SELECT COUNT(DISTINCT IFNULL(NULLIF(TRIM(i.place_of_supply),''),'(unset)')) AS cnt " . $base;
    $stmtC = $pdo->prepare($sqlCount); $stmtC->execute($bind);
    $total_rows = (int)$stmtC->fetchColumn();

    $sqlTotals =
        "SELECT
            SUM(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100))                          AS taxable,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.cgst_rate/100)     AS cgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.sgst_rate/100)     AS sgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.igst_rate/100)     AS igst
          " . $base;
    $stmtT = $pdo->prepare($sqlTotals); $stmtT->execute($bind);
    $tot = $stmtT->fetch() ?: ['taxable'=>0,'cgst'=>0,'sgst'=>0,'igst'=>0];
    foreach (['taxable','cgst','sgst','igst'] as $k) $tot[$k] = (float)($tot[$k] ?? 0);
    $tot['grand'] = $tot['taxable'] + $tot['cgst'] + $tot['sgst'] + $tot['igst'];

    $sqlData =
        "SELECT
            IFNULL(NULLIF(TRIM(i.place_of_supply),''), '(unset)') AS place_of_supply,
            COUNT(DISTINCT i.id) AS invoices,
            ROUND(SUM(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)), 2) AS taxable,
            ROUND(SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.cgst_rate/100), 2) AS cgst,
            ROUND(SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.sgst_rate/100), 2) AS sgst,
            ROUND(SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.igst_rate/100), 2) AS igst
          " . $base .
        " GROUP BY place_of_supply
          ORDER BY place_of_supply";
    if (!$for_export) { $offset = ($page-1)*$pagesz; $sqlData .= " LIMIT :lim OFFSET :off"; }
    $stmtD = $pdo->prepare($sqlData);
    foreach ($bind as $k=>$v) $stmtD->bindValue($k, $v);
    if (!$for_export) { $stmtD->bindValue(':lim',$pagesz,PDO::PARAM_INT); $stmtD->bindValue(':off',$offset,PDO::PARAM_INT); }
    $stmtD->execute();
    $rows = $stmtD->fetchAll();
    foreach ($rows as &$r) { $r['grand_total'] = (float)$r['taxable'] + (float)$r['cgst'] + (float)$r['sgst'] + (float)$r['igst']; }
    return [$rows, $total_rows, $tot];
}

// PARTY LEDGER & AGING
function fetch_party_aging(PDO $pdo, string $from, string $to, ?int $party_id, string $q, int $page, int $pagesz, bool $for_export=false) {
    $bind = [];
    // Invoice-level totals subquery
    $sub =
        " SELECT
            i.id AS invoice_id,
            i.party_id,
            i.invoice_date,
            SUM(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100))                          AS taxable,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.cgst_rate/100)     AS cgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.sgst_rate/100)     AS sgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.igst_rate/100)     AS igst
          FROM invoices i
          JOIN invoice_items ii ON ii.invoice_id = i.id
          WHERE i.inv_type = 'purchase' ";
    build_date_filters($sub, $bind, $from, $to);
    build_party_filter($sub, $bind, $party_id);
    $sub .= " GROUP BY i.id ";

    // payments allocated per invoice
    $sql =
        " FROM ( $sub ) inv
          LEFT JOIN (
            SELECT invoice_id, SUM(amount) AS allocated
            FROM payment_allocations
            GROUP BY invoice_id
          ) pa ON pa.invoice_id = inv.invoice_id
          JOIN parties p ON p.id = inv.party_id
          WHERE 1=1 ";
    build_search_party($sql, $bind, $q);

    // Count parties
    $sqlCount = "SELECT COUNT(DISTINCT inv.party_id) AS cnt " . $sql;
    $stmtC = $pdo->prepare($sqlCount); $stmtC->execute($bind);
    $total_rows = (int)$stmtC->fetchColumn();

    // Totals across parties
    $sqlTotals =
        "SELECT
           SUM((inv.taxable+inv.cgst+inv.sgst+inv.igst)) AS invoice_total,
           SUM(IFNULL(pa.allocated,0)) AS allocated,
           SUM((inv.taxable+inv.cgst+inv.sgst+inv.igst) - IFNULL(pa.allocated,0)) AS due
         " . $sql;
    $stmtT = $pdo->prepare($sqlTotals); $stmtT->execute($bind);
    $tot = $stmtT->fetch() ?: ['invoice_total'=>0,'allocated'=>0,'due'=>0];

    // Party aggregates with aging buckets
    $sqlData =
        "SELECT
           p.id AS party_id,
           p.name AS party,
           ROUND(SUM(inv.taxable+inv.cgst+inv.sgst+inv.igst),2) AS invoice_total,
           ROUND(SUM(IFNULL(pa.allocated,0)),2) AS allocated,
           ROUND(SUM((inv.taxable+inv.cgst+inv.sgst+inv.igst) - IFNULL(pa.allocated,0)),2) AS due,
           ROUND(SUM(CASE WHEN DATEDIFF(CURDATE(), inv.invoice_date) <= 30 THEN (inv.taxable+inv.cgst+inv.sgst+inv.igst) - IFNULL(pa.allocated,0) ELSE 0 END),2) AS d0_30,
           ROUND(SUM(CASE WHEN DATEDIFF(CURDATE(), inv.invoice_date) BETWEEN 31 AND 60 THEN (inv.taxable+inv.cgst+inv.sgst+inv.igst) - IFNULL(pa.allocated,0) ELSE 0 END),2) AS d31_60,
           ROUND(SUM(CASE WHEN DATEDIFF(CURDATE(), inv.invoice_date) BETWEEN 61 AND 90 THEN (inv.taxable+inv.cgst+inv.sgst+inv.igst) - IFNULL(pa.allocated,0) ELSE 0 END),2) AS d61_90,
           ROUND(SUM(CASE WHEN DATEDIFF(CURDATE(), inv.invoice_date) > 90 THEN (inv.taxable+inv.cgst+inv.sgst+inv.igst) - IFNULL(pa.allocated,0) ELSE 0 END),2) AS d90p
         " . $sql .
        " GROUP BY p.id, p.name
          ORDER BY due DESC, party ASC ";
    if (!$for_export) { $offset = ($page-1)*$pagesz; $sqlData .= " LIMIT :lim OFFSET :off"; }
    $stmtD = $pdo->prepare($sqlData);
    foreach ($bind as $k=>$v) $stmtD->bindValue($k, $v);
    if (!$for_export) { $stmtD->bindValue(':lim',$pagesz,PDO::PARAM_INT); $stmtD->bindValue(':off',$offset,PDO::PARAM_INT); }
    $stmtD->execute();
    $rows = $stmtD->fetchAll();

    return [$rows, $total_rows, $tot];
}

// Export handler
if ($action === 'export_csv') {
    if ($report === 'purchase') {
        [$rows,, $tot] = fetch_purchase_register($pdo, $from, $to, $party_id, $q, 1, 1000000, true);
        $csv=[]; foreach ($rows as $r){ $csv[] = [$r['invoice_date'],$r['invoice_no'],$r['party'],$r['place_of_supply'],$r['taxable'],$r['cgst'],$r['sgst'],$r['igst'],$r['grand_total']]; }
        stream_csv('purchase_register.csv',['Date','Invoice No','Party','Place of Supply','Taxable','CGST','SGST','IGST','Grand Total'],$csv);
    } elseif ($report === 'hsn') {
        [$rows,, $tot] = fetch_hsn_summary($pdo, $from, $to, $party_id, 1, 1000000, true);
        $csv=[]; foreach ($rows as $r){ $csv[] = [$r['hsn'],$r['taxable'],$r['cgst'],$r['sgst'],$r['igst'],$r['grand_total']]; }
        stream_csv('hsn_summary.csv',['HSN','Taxable','CGST','SGST','IGST','GST Total'],$csv);
    } elseif ($report === 'gst_rate') {
        [$rows,, $tot] = fetch_gst_rate_summary($pdo, $from, $to, $party_id, 1, 1000000, true);
        $csv=[]; foreach ($rows as $r){ $csv[] = [$r['rate_percent'],$r['taxable'],$r['gst'],$r['invoices']]; }
        stream_csv('gst_rate_summary.csv',['GST %','Taxable','GST','Invoices'],$csv);
    } elseif ($report === 'pos') {
        [$rows,, $tot] = fetch_pos_summary($pdo, $from, $to, $party_id, 1, 1000000, true);
        $csv=[]; foreach ($rows as $r){ $csv[] = [$r['place_of_supply'],$r['invoices'],$r['taxable'],$r['cgst'],$r['sgst'],$r['igst'],$r['grand_total']]; }
        stream_csv('place_of_supply_summary.csv',['Place of Supply','Invoices','Taxable','CGST','SGST','IGST','Grand Total'],$csv);
    } else { // party_aging
        [$rows,, $tot] = fetch_party_aging($pdo, $from, $to, $party_id, $q, 1, 1000000, true);
        $csv=[]; foreach ($rows as $r){ $csv[] = [$r['party'],$r['invoice_total'],$r['allocated'],$r['due'],$r['d0_30'],$r['d31_60'],$r['d61_90'],$r['d90p']]; }
        stream_csv('party_aging.csv',['Party','Invoiced','Allocated','Due','0-30','31-60','61-90','>90'],$csv);
    }
}

// Fetch current
if ($report === 'purchase') {
    [$rows, $total_rows, $tot] = fetch_purchase_register($pdo, $from, $to, $party_id, $q, $page, $pagesz);
} elseif ($report === 'hsn') {
    [$rows, $total_rows, $tot] = fetch_hsn_summary($pdo, $from, $to, $party_id, $page, $pagesz);
} elseif ($report === 'gst_rate') {
    [$rows, $total_rows, $tot] = fetch_gst_rate_summary($pdo, $from, $to, $party_id, $page, $pagesz);
} elseif ($report === 'pos') {
    [$rows, $total_rows, $tot] = fetch_pos_summary($pdo, $from, $to, $party_id, $page, $pagesz);
} else { // party_aging
    [$rows, $total_rows, $tot] = fetch_party_aging($pdo, $from, $to, $party_id, $q, $page, $pagesz);
}
$total_pages = max(1, (int)ceil($total_rows / $pagesz));

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reports+ — GST Accounting</title>
<style>
:root { --bg:#0b1020; --panel:#131a2a; --ink:#e9eef6; --muted:#9fb0c7; --accent:#4cc9f0; --line:#23304a; --chip:#1e2740; }
body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial}
a{color:var(--accent);text-decoration:none}
.wrap{max-width:1200px;margin:0 auto;padding:20px}
nav .tab{padding:10px 14px;border-radius:10px;background:var(--chip);display:inline-block;margin-right:8px}
nav .tab.active{background:var(--accent);color:#001b2a;font-weight:600}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:12px;margin-bottom:16px}
.filters{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.filters label{font-size:12px;color:var(--muted);display:block;margin-bottom:4px}
input,select,button{background:#0f1525;color:var(--ink);border:1px solid var(--line);border-radius:8px;padding:8px 10px}
button.primary{background:var(--accent);color:#001b2a;font-weight:700;border-color:transparent;cursor:pointer}
.kpis{display:flex;gap:16px;flex-wrap:wrap}
.kpi{background:var(--chip);padding:10px 12px;border-radius:10px;border:1px solid var(--line)}
.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:12px}
table{width:100%;border-collapse:separate;border-spacing:0}
th,td{padding:10px 12px;border-bottom:1px solid var(--line);text-align:left;white-space:nowrap}
thead th{position:sticky;top:0;background:#0f1628;z-index:1}
tfoot td{background:#0f1628;font-weight:700}
.pager{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:10px}
.badge{padding:2px 8px;border-radius:999px;border:1px solid var(--line);background:var(--chip);font-size:12px}
.right{margin-left:auto}
</style>
</head>
<body>
<div class="wrap">
  <header style="display:flex;gap:16px;align-items:center;margin-bottom:16px">
    <h2 style="margin:0">Reports+</h2>
    <nav>
      <a class="tab <?php echo $report==='purchase'?'active':''; ?>" href="?report=purchase">Purchase Register</a>
      <a class="tab <?php echo $report==='hsn'?'active':''; ?>" href="?report=hsn">HSN Summary</a>
      <a class="tab <?php echo $report==='gst_rate'?'active':''; ?>" href="?report=gst_rate">GST Rate Summary</a>
      <a class="tab <?php echo $report==='pos'?'active':''; ?>" href="?report=pos">Place of Supply</a>
      <a class="tab <?php echo $report==='party_aging'?'active':''; ?>" href="?report=party_aging">Party Ledger & Aging</a>
    </nav>
  </header>

  <form class="panel filters" method="get">
    <input type="hidden" name="report" value="<?php echo h($report); ?>">
    <div><label>From</label><input type="date" name="from" value="<?php echo h($from); ?>"></div>
    <div><label>To</label><input type="date" name="to" value="<?php echo h($to); ?>"></div>
    <div>
      <label>Party</label>
      <select name="party_id">
        <option value="">All Parties</option>
        <?php foreach ($parties as $p): ?>
          <option value="<?php echo (int)$p['id']; ?>" <?php echo $party_id===(int)$p['id']?'selected':''; ?>><?php echo h($p['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($report==='purchase' || $report==='party_aging'): ?>
    <div style="flex:1 1 200px">
      <label><?php echo $report==='purchase'?'Search (Invoice/Party)':'Search (Party)'; ?></label>
      <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="<?php echo $report==='purchase'?'INV123 / supplier':''; ?>">
    </div>
    <?php endif; ?>
    <div>
      <label>Page size</label>
      <select name="pagesz">
        <?php foreach ([25,50,100,200] as $pz): ?>
          <option value="<?php echo $pz; ?>" <?php echo $pagesz===$pz?'selected':''; ?>><?php echo $pz; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>&nbsp;</label><button class="primary">Apply</button></div>
    <div class="right"><label>&nbsp;</label>
      <a class="badge" href="<?php $qs=$_GET; $qs['action']='export_csv'; echo h($_SERVER['PHP_SELF'].'?'.http_build_query($qs)); ?>">Export CSV</a>
    </div>
  </form>

  <?php
    // KPIs by report
    if ($report==='purchase' || $report==='hsn' || $report==='pos') {
      function kpi($label,$val){ echo '<div class="kpi"><div><strong>'.htmlspecialchars($label).'</strong><div>₹ '.number_format((float)$val,2).'</div></div></div>'; }
    } elseif ($report==='gst_rate') {
      function kpi($label,$val){ echo '<div class="kpi"><div><strong>'.htmlspecialchars($label).'</strong><div>'.number_format((float)$val,2).'</div></div></div>'; }
    } else { // party_aging
      function kpi($label,$val){ echo '<div class="kpi"><div><strong>'.htmlspecialchars($label).'</strong><div>₹ '.number_format((float)$val,2).'</div></div></div>'; }
    }
  ?>
  <div class="panel kpis">
    <?php if ($report==='gst_rate'): ?>
      <div class="kpi"><div><strong>Rows</strong><div><?php echo number_format($total_rows); ?> (page <?php echo $page; ?>/<?php echo $total_pages; ?>)</div></div></div>
      <?php kpi('Total Taxable', $tot['taxable'] ?? 0); kpi('Total GST', $tot['gst'] ?? 0); ?>
    <?php elseif ($report==='party_aging'): ?>
      <div class="kpi"><div><strong>Rows</strong><div><?php echo number_format($total_rows); ?> (page <?php echo $page; ?>/<?php echo $total_pages; ?>)</div></div></div>
      <?php kpi('Invoiced', $tot['invoice_total'] ?? 0); kpi('Allocated', $tot['allocated'] ?? 0); kpi('Total Due', $tot['due'] ?? 0); ?>
    <?php else: ?>
      <div class="kpi"><div><strong>Rows</strong><div><?php echo number_format($total_rows); ?> (page <?php echo $page; ?>/<?php echo $total_pages; ?>)</div></div></div>
      <?php kpi('Taxable',$tot['taxable']); kpi('CGST',$tot['cgst'] ?? 0); kpi('SGST',$tot['sgst'] ?? 0); kpi('IGST',$tot['igst'] ?? 0); kpi('Grand Total', ($tot['grand'] ?? ($tot['taxable'] + ($tot['cgst']??0) + ($tot['sgst']??0) + ($tot['igst']??0)))); ?>
    <?php endif; ?>
  </div>

  <div class="panel table-wrap">
    <?php if ($report==='purchase'): ?>
      <table>
        <thead><tr>
          <th>Date</th><th>Invoice No</th><th>Party</th><th>Place of Supply</th>
          <th style="text-align:right">Taxable</th><th style="text-align:right">CGST</th>
          <th style="text-align:right">SGST</th><th style="text-align:right">IGST</th><th style="text-align:right">Grand Total</th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo h($r['invoice_date']); ?></td>
              <td><a href="table_view.php?table=invoices&id=<?php echo (int)$r['id']; ?>"><?php echo h($r['invoice_no']); ?></a></td>
              <td><?php echo h($r['party']); ?></td>
              <td><?php echo h($r['place_of_supply']); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['taxable'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['cgst'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['sgst'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['igst'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['grand_total'],2); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    <?php elseif ($report==='hsn'): ?>
      <table>
        <thead><tr>
          <th>HSN</th><th style="text-align:right">Taxable</th><th style="text-align:right">CGST</th>
          <th style="text-align:right">SGST</th><th style="text-align:right">IGST</th><th style="text-align:right">GST Total</th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo h($r['hsn']); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['taxable'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['cgst'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['sgst'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['igst'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['grand_total'],2); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    <?php elseif ($report==='gst_rate'): ?>
      <table>
        <thead><tr>
          <th style="text-align:right">GST %</th>
          <th style="text-align:right">Taxable</th>
          <th style="text-align:right">GST</th>
          <th style="text-align:right">Invoices</th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td style="text-align:right"><?php echo number_format((float)$r['rate_percent'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['taxable'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['gst'],2); ?></td>
              <td style="text-align:right"><?php echo (int)$r['invoices']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    <?php elseif ($report==='pos'): ?>
      <table>
        <thead><tr>
          <th>Place of Supply</th>
          <th style="text-align:right">Invoices</th>
          <th style="text-align:right">Taxable</th>
          <th style="text-align:right">CGST</th>
          <th style="text-align:right">SGST</th>
          <th style="text-align:right">IGST</th>
          <th style="text-align:right">Grand Total</th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo h($r['place_of_supply']); ?></td>
              <td style="text-align:right"><?php echo (int)$r['invoices']; ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['taxable'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['cgst'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['sgst'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['igst'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['grand_total'],2); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    <?php else: // party_aging ?>
      <table>
        <thead><tr>
          <th>Party</th>
          <th style="text-align:right">Invoiced</th>
          <th style="text-align:right">Allocated</th>
          <th style="text-align:right">Due</th>
          <th style="text-align:right">0–30</th>
          <th style="text-align:right">31–60</th>
          <th style="text-align:right">61–90</th>
          <th style="text-align:right">&gt;90</th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><a href="party.php?id=<?php echo (int)$r['party_id']; ?>"><?php echo h($r['party']); ?></a></td>
              <td style="text-align:right"><?php echo number_format((float)$r['invoice_total'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['allocated'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['due'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['d0_30'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['d31_60'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['d61_90'],2); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$r['d90p'],2); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="pager panel">
    <div><span class="badge">Page <?php echo $page; ?> / <?php echo $total_pages; ?></span></div>
    <div style="margin-left:auto">
      <?php
        $baseQS = $_GET; $baseQS['page'] = max(1, $page-1);
        $prevURL = $_SERVER['PHP_SELF'].'?'.http_build_query($baseQS);
        $baseQS['page'] = min($total_pages, $page+1);
        $nextURL = $_SERVER['PHP_SELF'].'?'.http_build_query($baseQS);
      ?>
      <a class="tab" href="<?php echo h($prevURL); ?>">&larr; Prev</a>
      <a class="tab" href="<?php echo h($nextURL); ?>">Next &rarr;</a>
    </div>
  </div>

</div>
</body>
</html>
