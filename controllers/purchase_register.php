<?php
// purchase_register.php
// Requirements: db.php with class Database (host, db_name, username, password)

require_once __DIR__ . '/bootstrap.php';

$db = new Database();
$conn = $db->getConnection();

// ---- inputs (GET) ----
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to   = isset($_GET['to'])   ? $_GET['to']   : '';
$party_id = isset($_GET['party_id']) ? trim($_GET['party_id']) : '';
$invno = isset($_GET['invno']) ? trim($_GET['invno']) : '';
$itc   = isset($_GET['itc']) ? $_GET['itc'] : ''; // '', '1', '0'
$page  = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// ---- base SQL ----
$where = ["i.inv_type = 'purchase'"];
$params = [];

if ($from !== '') { $where[] = "i.invoice_date >= :from"; $params[':from'] = $from; }
if ($to   !== '') { $where[] = "i.invoice_date <= :to";   $params[':to']   = $to;   }
if ($party_id !== '') { $where[] = "i.party_id = :party_id"; $params[':party_id'] = $party_id; }
if ($invno !== '') { $where[] = "i.invoice_no LIKE :invno"; $params[':invno'] = "%$invno%"; }
if ($itc === '0' || $itc === '1') { $where[] = "i.itc_eligible = :itc"; $params[':itc'] = $itc; }

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---- count ----
$sql_count = "
  SELECT COUNT(*) AS cnt
  FROM invoices i
  $where_sql
";
$stmt = $conn->prepare($sql_count);
$stmt->execute($params);
$total_rows = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// ---- data query (with totals) ----
$sql = "
  SELECT
    i.id,
    i.invoice_no,
    i.invoice_date,
    i.party_id,
    p.name AS party_name,
    i.place_of_supply,
    i.reverse_charge,
    i.itc_eligible,
    i.status,
    COALESCE(SUM(ii.line_total), 0) AS grand_total,
    COALESCE(SUM(ii.taxable_amount), 0) AS taxable,
    COALESCE(SUM(ii.cgst_amount), 0) AS cgst,
    COALESCE(SUM(ii.sgst_amount), 0) AS sgst,
    COALESCE(SUM(ii.igst_amount), 0) AS igst
  FROM invoices i
  LEFT JOIN parties p ON p.id = i.party_id
  LEFT JOIN invoice_items ii ON ii.invoice_id = i.id
  $where_sql
  GROUP BY i.id
  ORDER BY i.invoice_date DESC, i.id DESC
  LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($sql);
// bind normal params
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v);
}
// bind limit/offset as integers
$stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- vendors dropdown (optional) ----
$partySql = "SELECT id, name FROM parties WHERE party_type='supplier' ORDER BY name";
$partyStmt = $conn->query($partySql);
$vendors = $partyStmt->fetchAll(PDO::FETCH_ASSOC);

// helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Purchase Register</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:16px;}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.05);}
  .row{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
  label{font-size:12px;color:#374151;display:block;margin-bottom:4px}
  input,select{padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;min-width:180px}
  button{padding:8px 12px;border-radius:8px;border:1px solid #111827;background:#111827;color:#fff;cursor:pointer}
  table{width:100%;border-collapse:collapse;margin-top:14px}
  th,td{border-bottom:1px solid #e5e7eb;padding:10px;text-align:left}
  th{font-size:12px;color:#374151;text-transform:uppercase;letter-spacing:.03em}
  .badge{font-size:12px;padding:2px 6px;border-radius:6px;border:1px solid #d1d5db;display:inline-block}
  .muted{color:#6b7280}
  .right{text-align:right}
  nav{display:flex;gap:8px;align-items:center;margin-top:12px}
  a{color:#0f62fe;text-decoration:none}
  a:hover{text-decoration:underline}
</style>
</head>
<body>
  <h2>Purchase Register</h2>

  <form method="get" class="card">
    <div class="row">
      <div>
        <label>From date</label>
        <input type="date" name="from" value="<?=h($from)?>">
      </div>
      <div>
        <label>To date</label>
        <input type="date" name="to" value="<?=h($to)?>">
      </div>
      <div>
        <label>Vendor</label>
        <select name="party_id">
          <option value="">— All —</option>
          <?php foreach($vendors as $v): ?>
            <option value="<?=h($v['id'])?>" <?=($party_id!=='' && (int)$party_id===(int)$v['id']?'selected':'')?>><?=h($v['name'])?> (<?=h($v['id'])?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Invoice No</label>
        <input type="text" name="invno" placeholder="URD-2024..." value="<?=h($invno)?>">
      </div>
      <div>
        <label>ITC Eligible</label>
        <select name="itc">
          <option value="">— Any —</option>
          <option value="1" <?=($itc==='1'?'selected':'')?>>Yes</option>
          <option value="0" <?=($itc==='0'?'selected':'')?>>No</option>
        </select>
      </div>
      <div>
        <button type="submit">Filter</button>
        <a class="badge" href="purchase_register.php">Reset</a>
      </div>
    </div>
  </form>

  <div class="muted" style="margin:8px 0;">Total rows: <?=h($total_rows)?> | Page <?=h($page)?> / <?=h($total_pages)?></div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Invoice No</th>
          <th>Date</th>
          <th>Vendor</th>
          <th>Place of Supply</th>
          <th class="right">Taxable</th>
          <th class="right">CGST</th>
          <th class="right">SGST</th>
          <th class="right">IGST</th>
          <th class="right">Grand Total</th>
          <th>ITC</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php if(!$rows): ?>
        <tr><td colspan="12" class="muted">No purchases found.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?=h($r['id'])?></td>
          <td>
            <a href="invoice_view.php?id=<?=h($r['id'])?>" target="_blank">
              <?=h($r['invoice_no'])?>
            </a>
          </td>
          <td><?=h($r['invoice_date'])?></td>
          <td><?=h($r['party_name'] ?: ('Party #'.$r['party_id']))?></td>
          <td><?=h($r['place_of_supply'])?></td>
          <td class="right"><?=number_format((float)$r['taxable'],2)?></td>
          <td class="right"><?=number_format((float)$r['cgst'],2)?></td>
          <td class="right"><?=number_format((float)$r['sgst'],2)?></td>
          <td class="right"><?=number_format((float)$r['igst'],2)?></td>
          <td class="right"><strong><?=number_format((float)$r['grand_total'],2)?></strong></td>
          <td><span class="badge"><?= $r['itc_eligible'] ? 'Yes' : 'No' ?></span></td>
          <td><span class="badge"><?=h($r['status'])?></span></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>

    <nav>
      <?php if ($page>1): ?>
        <a href="?<?=http_build_query(array_merge($_GET,['page'=>1]))?>">« First</a>
        <a href="?<?=http_build_query(array_merge($_GET,['page'=>$page-1]))?>">‹ Prev</a>
      <?php endif; ?>
      <span class="badge">Page <?=h($page)?> / <?=h($total_pages)?></span>
      <?php if ($page<$total_pages): ?>
        <a href="?<?=http_build_query(array_merge($_GET,['page'=>$page+1]))?>">Next ›</a>
        <a href="?<?=http_build_query(array_merge($_GET,['page'=>$total_pages]))?>">Last »</a>
      <?php endif; ?>
    </nav>
  </div>
</body>
</html>

