<?php
// reports.php — Simple GST report viewer (Purchase Register & HSN Summary)
// Drop this into your web root (same folder as db.php). Open in browser.
// It reads from: invoices, invoice_items, parties. No schema changes required.
//
// Features:
// - Purchase Register (invoice-wise): date/party/search filters, totals, paging, CSV export
// - HSN Summary: date/party filters, totals, CSV export
// - Sticky headers, column chooser (basic), server-side totals (matching filters)

declare(strict_types=1);
ini_set('display_errors', '0');           // Turn off in prod UI (errors still go to logs)
error_reporting(E_ALL);

//// 0) DB CONNECTION ////
function get_pdo(): PDO {
    // Try to use your existing db.php if present
    if (file_exists(__DIR__.'/db.php')) {
        include_once __DIR__.'/db.php';
        // If db.php defines $pdo:
        if (isset($pdo) && $pdo instanceof PDO) return $pdo;
        // If db.php defines $db and it is PDO:
        if (isset($db) && $db instanceof PDO) return $db;
        // If db.php defines a Database class with getConnection():
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
    // Fallback minimal connector (edit to your creds if needed)
    $host = '127.0.0.1'; $port = '3306'; $dbname = 'gst_accounting';
    $user = 'root';      $pass = '';
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
}

$pdo = get_pdo();

//// 1) INPUTS ////
$report   = $_GET['report'] ?? 'purchase'; // 'purchase' | 'hsn'
$from     = trim($_GET['from'] ?? '');     // yyyy-mm-dd
$to       = trim($_GET['to'] ?? '');       // yyyy-mm-dd
$party_id = isset($_GET['party_id']) && $_GET['party_id'] !== '' ? (int)$_GET['party_id'] : null;
$q        = trim($_GET['q'] ?? '');        // invoice/party search (purchase report)
$page     = max(1, (int)($_GET['page'] ?? 1));
$pagesz   = min(200, max(10, (int)($_GET['pagesz'] ?? 50)));
$action   = $_GET['action'] ?? '';         // 'export_csv' to export filtered data

//// 2) PARTY OPTIONS ////
$parties = $pdo->query("SELECT id, name FROM parties ORDER BY name")->fetchAll();

//// 3) COMMON FILTER BUILDERS ////
function build_date_filters(&$sql, array &$bind, string $from, string $to): void {
    if ($from !== '') { $sql .= " AND i.invoice_date >= :from"; $bind[':from'] = $from; }
    if ($to   !== '') { $sql .= " AND i.invoice_date <= :to";   $bind[':to']   = $to;   }
}
function build_party_filter(&$sql, array &$bind, ?int $party_id): void {
    if ($party_id !== null) { $sql .= " AND i.party_id = :party"; $bind[':party'] = $party_id; }
}
function build_search_filter(&$sql, array &$bind, string $q): void {
    if ($q !== '') {
        $sql .= " AND (i.invoice_no LIKE :q OR p.name LIKE :q)";
        $bind[':q'] = "%{$q}%";
    }
}

//// 4) CSV EXPORT HELPER ////
function stream_csv(string $filename, array $headers, iterable $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $r) { fputcsv($out, $r); }
    fclose($out);
    exit;
}

//// 5) PURCHASE REGISTER ////
function fetch_purchase_register(PDO $pdo, string $from, string $to, ?int $party_id, string $q, int $page, int $pagesz, bool $for_export=false) {
    $bind = [];
    $base =
        " FROM invoices i
          JOIN parties p     ON p.id = i.party_id
          JOIN invoice_items ii ON ii.invoice_id = i.id
          WHERE i.inv_type = 'purchase' ";
    build_date_filters($base, $bind, $from, $to);
    build_party_filter($base, $bind, $party_id);
    build_search_filter($base, $bind, $q);

    // Count invoices (distinct)
    $sqlCount = "SELECT COUNT(DISTINCT i.id) AS cnt " . $base;
    $stmtC = $pdo->prepare($sqlCount);
    $stmtC->execute($bind);
    $total_rows = (int)$stmtC->fetchColumn();

    // Totals for filtered set (no limit)
    $sqlTotals =
        "SELECT
            SUM(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100))                          AS taxable,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.cgst_rate/100)     AS cgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.sgst_rate/100)     AS sgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.igst_rate/100)     AS igst
         " . $base;
    $stmtT = $pdo->prepare($sqlTotals);
    $stmtT->execute($bind);
    $tot = $stmtT->fetch() ?: ['taxable'=>0,'cgst'=>0,'sgst'=>0,'igst'=>0];
    foreach (['taxable','cgst','sgst','igst'] as $k) $tot[$k] = (float)($tot[$k] ?? 0);
    $tot['grand'] = $tot['taxable'] + $tot['cgst'] + $tot['sgst'] + $tot['igst'];

    // Data query (group per invoice)
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

    if (!$for_export) {
        $offset = ($page-1)*$pagesz;
        $sqlData .= " LIMIT :lim OFFSET :off";
    }
    $stmtD = $pdo->prepare($sqlData);
    foreach ($bind as $k=>$v) $stmtD->bindValue($k, $v);
    if (!$for_export) {
        $stmtD->bindValue(':lim', $pagesz, PDO::PARAM_INT);
        $stmtD->bindValue(':off', $offset, PDO::PARAM_INT);
    }
    $stmtD->execute();
    $rows = $stmtD->fetchAll();

    // Compute grand_total per row
    foreach ($rows as &$r) {
        $r['grand_total'] = (float)$r['taxable'] + (float)$r['cgst'] + (float)$r['sgst'] + (float)$r['igst'];
    }

    return [$rows, $total_rows, $tot];
}

//// 6) HSN SUMMARY ////
function fetch_hsn_summary(PDO $pdo, string $from, string $to, ?int $party_id, int $page, int $pagesz, bool $for_export=false) {
    $bind = [];
    $base =
        " FROM invoices i
          JOIN invoice_items ii ON ii.invoice_id = i.id
          WHERE i.inv_type = 'purchase' ";
    build_date_filters($base, $bind, $from, $to);
    if ($party_id !== null) { $base .= " AND i.party_id = :party"; $bind[':party'] = $party_id; }

    // Distinct HSN count
    $sqlCount = "SELECT COUNT(DISTINCT ii.hsn) AS cnt " . $base;
    $stmtC = $pdo->prepare($sqlCount);
    $stmtC->execute($bind);
    $total_rows = (int)$stmtC->fetchColumn();

    // Totals over all HSN (no limit)
    $sqlTotals =
        "SELECT
            SUM(ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100))                          AS taxable,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.cgst_rate/100)     AS cgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.sgst_rate/100)     AS sgst,
            SUM((ii.quantity * ii.rate * (1 - IFNULL(ii.discount_percent,0)/100)) * ii.igst_rate/100)     AS igst
          " . $base;
    $stmtT = $pdo->prepare($sqlTotals);
    $stmtT->execute($bind);
    $tot = $stmtT->fetch() ?: ['taxable'=>0,'cgst'=>0,'sgst'=>0,'igst'=>0];
    foreach (['taxable','cgst','sgst','igst'] as $k) $tot[$k] = (float)($tot[$k] ?? 0);
    $tot['grand'] = $tot['taxable'] + $tot['cgst'] + $tot['sgst'] + $tot['igst'];

    // Data by HSN
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
    if (!$for_export) {
        $offset = ($page-1)*$pagesz;
        $sqlData .= " LIMIT :lim OFFSET :off";
    }
    $stmtD = $pdo->prepare($sqlData);
    foreach ($bind as $k=>$v) $stmtD->bindValue($k, $v);
    if (!$for_export) {
        $stmtD->bindValue(':lim', $pagesz, PDO::PARAM_INT);
        $stmtD->bindValue(':off', $offset, PDO::PARAM_INT);
    }
    $stmtD->execute();
    $rows = $stmtD->fetchAll();
    foreach ($rows as &$r) {
        $r['grand_total'] = (float)$r['taxable'] + (float)$r['cgst'] + (float)$r['sgst'] + (float)$r['igst'];
    }
    return [$rows, $total_rows, $tot];
}

//// 7) EXPORT HANDLER ////
if ($action === 'export_csv') {
    if ($report === 'purchase') {
        [$rows, , $tot] = fetch_purchase_register($pdo, $from, $to, $party_id, $q, 1, 1000000, true);
        $csv = [];
        foreach ($rows as $r) {
            $csv[] = [
                $r['invoice_date'], $r['invoice_no'], $r['party'], $r['place_of_supply'],
                number_format((float)$r['taxable'],2,'.',''),
                number_format((float)$r['cgst'],2,'.',''),
                number_format((float)$r['sgst'],2,'.',''),
                number_format((float)$r['igst'],2,'.',''),
                number_format((float)$r['grand_total'],2,'.',''),
            ];
        }
        stream_csv('purchase_register.csv',
            ['Date','Invoice No','Party','Place of Supply','Taxable','CGST','SGST','IGST','Grand Total'],
            $csv
        );
    } else { // hsn
        [$rows, , $tot] = fetch_hsn_summary($pdo, $from, $to, $party_id, 1, 1000000, true);
        $csv = [];
        foreach ($rows as $r) {
            $csv[] = [
                $r['hsn'],
                number_format((float)$r['taxable'],2,'.',''),
                number_format((float)$r['cgst'],2,'.',''),
                number_format((float)$r['sgst'],2,'.',''),
                number_format((float)$r['igst'],2,'.',''),
                number_format((float)$r['grand_total'],2,'.',''),
            ];
        }
        stream_csv('hsn_summary.csv',
            ['HSN','Taxable','CGST','SGST','IGST','GST Total'],
            $csv
        );
    }
}

//// 8) FETCH CURRENT PAGE ////
if ($report === 'purchase') {
    [$rows, $total_rows, $tot] = fetch_purchase_register($pdo, $from, $to, $party_id, $q, $page, $pagesz);
} else {
    [$rows, $total_rows, $tot] = fetch_hsn_summary($pdo, $from, $to, $party_id, $page, $pagesz);
}
$total_pages = max(1, (int)ceil($total_rows / $pagesz));

//// 9) HTML ////
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Reports — GST Accounting</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root {
  --bg:#0b1020; --panel:#131a2a; --ink:#e9eef6; --muted:#9fb0c7; --accent:#4cc9f0; --ok:#22c55e; --warn:#f59e0b;
  --line:#23304a; --chip:#1e2740; --bad:#ef4444;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial}
a{color:var(--accent);text-decoration:none}
.wrap{max-width:1200px;margin:0 auto;padding:20px}
header{display:flex;gap:16px;align-items:center;margin-bottom:16px}
nav .tab{padding:10px 14px;border-radius:10px;background:var(--chip);display:inline-block;margin-right:8px}
nav .tab.active{background:var(--accent);color:#001b2a;font-weight:600}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:12px;margin-bottom:16px}
.filters{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.filters label{font-size:12px;color:var(--muted);display:block;margin-bottom:4px}
input,select,button{background:#0f1525;color:var(--ink);border:1px solid var(--line);border-radius:8px;padding:8px 10px}
button{cursor:pointer}
button.primary{background:var(--accent);color:#001b2a;font-weight:700;border-color:transparent}
.kpis{display:flex;gap:16px;flex-wrap:wrap}
.kpi{background:var(--chip);padding:10px 12px;border-radius:10px;border:1px solid var(--line)}
.kpi strong{display:block;font-size:14px}
.kpi span{font-size:12px;color:var(--muted)}
.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:12px}
table{width:100%;border-collapse:separate;border-spacing:0}
th,td{padding:10px 12px;border-bottom:1px solid var(--line);text-align:left;white-space:nowrap}
thead th{position:sticky;top:0;background:#0f1628;z-index:1}
tfoot td{background:#0f1628;font-weight:700}
.pager{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:10px}
.badge{padding:2px 8px;border-radius:999px;border:1px solid var(--line);background:var(--chip);font-size:12px}
.right{float:right}
</style>
</head>
<body>
<div class="wrap">
<header>
  <h2 style="margin:0">Reports</h2>
  <nav>
    <a class="tab <?php echo $report==='purchase'?'active':''; ?>" href="?report=purchase">Purchase Register</a>
    <a class="tab <?php echo $report==='hsn'?'active':''; ?>" href="?report=hsn">HSN Summary</a>
  </nav>
</header>

<form class="panel filters" method="get">
  <input type="hidden" name="report" value="<?php echo h($report); ?>">
  <div>
    <label>From</label>
    <input type="date" name="from" value="<?php echo h($from); ?>">
  </div>
  <div>
    <label>To</label>
    <input type="date" name="to" value="<?php echo h($to); ?>">
  </div>
  <div>
    <label>Party</label>
    <select name="party_id">
      <option value="">All Parties</option>
      <?php foreach ($parties as $p): ?>
        <option value="<?php echo (int)$p['id']; ?>" <?php echo $party_id===(int)$p['id']?'selected':''; ?>>
          <?php echo h($p['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($report==='purchase'): ?>
  <div style="flex:1 1 200px">
    <label>Search (Invoice/Party)</label>
    <input type="text" name="q" placeholder="e.g. INV123 / supplier name" value="<?php echo h($q); ?>">
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
  <div>
    <label>&nbsp;</label>
    <button type="submit" class="primary">Apply</button>
  </div>
  <div class="right">
    <label>&nbsp;</label>
    <a class="badge" href="<?php
      $qs = $_GET; $qs['action']='export_csv'; echo h($_SERVER['PHP_SELF'].'?'.http_build_query($qs));
    ?>">Export CSV</a>
  </div>
</form>

<div class="panel kpis">
  <div class="kpi">
    <strong>Rows</strong><span><?php echo number_format($total_rows); ?> (page <?php echo $page; ?>/<?php echo $total_pages; ?>)</span>
  </div>
  <div class="kpi">
    <strong>Taxable</strong><span>₹ <?php echo number_format((float)$tot['taxable'],2); ?></span>
  </div>
  <div class="kpi">
    <strong>CGST</strong><span>₹ <?php echo number_format((float)$tot['cgst'],2); ?></span>
  </div>
  <div class="kpi">
    <strong>SGST</strong><span>₹ <?php echo number_format((float)$tot['sgst'],2); ?></span>
  </div>
  <div class="kpi">
    <strong>IGST</strong><span>₹ <?php echo number_format((float)$tot['igst'],2); ?></span>
  </div>
  <div class="kpi">
    <strong>Grand Total</strong><span>₹ <?php echo number_format((float)$tot['grand'],2); ?></span>
  </div>
</div>

<div class="panel table-wrap">
<?php if ($report==='purchase'): ?>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Invoice No</th>
        <th>Party</th>
        <th>Place of Supply</th>
        <th style="text-align:right">Taxable</th>
        <th style="text-align:right">CGST</th>
        <th style="text-align:right">SGST</th>
        <th style="text-align:right">IGST</th>
        <th style="text-align:right">Grand Total</th>
      </tr>
    </thead>
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
    <tfoot>
      <tr>
        <td colspan="4" style="text-align:right"><strong>Totals</strong></td>
        <td style="text-align:right"><strong><?php echo number_format((float)$tot['taxable'],2); ?></strong></td>
        <td style="text-align:right"><strong><?php echo number_format((float)$tot['cgst'],2); ?></strong></td>
        <td style="text-align:right"><strong><?php echo number_format((float)$tot['sgst'],2); ?></strong></td>
        <td style="text-align:right"><strong><?php echo number_format((float)$tot['igst'],2); ?></strong></td>
        <td style="text-align:right"><strong><?php echo number_format((float)$tot['grand'],2); ?></strong></td>
      </tr>
    </tfoot>
  </table>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th>HSN</th>
        <th style="text-align:right">Taxable</th>
        <th style="text-align:right">CGST</th>
        <th style="text-align:right">SGST</th>
        <th style="text-align:right">IGST</th>
        <th style="text-align:right">GST Total</th>
      </tr>
    </thead>
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
    <tfoot>
      <tr>
        <td style="text-align:right"><strong>Totals</strong></td>
        <td style="text-align:right"><strong><?php echo number_format((float)$tot['taxable'],2); ?></strong></td>
        <td style="text-align:right"><strong><?php echo number_format((float)$tot['cgst'],2); ?></strong></td>
        <td style="text-align:right"><strong><?php echo number_format((float)$tot['sgst'],2); ?></strong></td>
        <td style="text-align:right"><strong><?php echo number_format((float)$tot['igst'],2); ?></strong></td>
        <td style="text-align:right"><strong><?php echo number_format((float)$tot['grand'],2); ?></strong></td>
      </tr>
    </tfoot>
  </table>
<?php endif; ?>
</div>

<div class="pager panel">
  <div>
    <span class="badge">Page <?php echo $page; ?> / <?php echo $total_pages; ?></span>
  </div>
  <div style="margin-left:auto">
    <?php
      $baseQS = $_GET;
      $baseQS['page'] = max(1, $page-1);
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

