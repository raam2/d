<?php
// table_view.php
declare(strict_types=1);
ini_set('display_errors', '1'); error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

$pdo = (new Database())->getConnection();
$pdo->exec("SET NAMES utf8mb4");

$table = $_GET['table'] ?? '';
$limit = isset($_GET['limit']) ? max(1, min(50000, (int)$_GET['limit'])) : 1000;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$format = $_GET['format'] ?? '';

if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    http_response_code(400);
    exit('Invalid table name.');
}

// Verify table exists in current DB
$chk = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
$chk->execute([':t' => $table]);
if (!$chk->fetchColumn()) {
    http_response_code(404);
    exit('Table not found in current database.');
}

// Total count (cheap for small tables; for very large, you can skip)
$total = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

// Columns
$cols = $pdo->query("SHOW FULL COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_map(fn($c) => $c['Field'], $cols);

// Data page
$stmt = $pdo->prepare("SELECT * FROM `{$table}` LIMIT :lim OFFSET :off");
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV download
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$table.'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $colNames);
    foreach ($rows as $r) {
        $line = [];
        foreach ($colNames as $c) { $line[] = isset($r[$c]) ? (string)$r[$c] : ''; }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$prev = max(0, $offset - $limit);
$next = $offset + $limit;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= h($table) ?> (<?= $offset+1 ?>–<?= min($offset+$limit, max($total, $offset+$limit)) ?> / <?= $total ?>)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 20px; }
  h1 { margin: 0 0 10px 0; }
  .toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin: 12px 0 18px; }
  table { border-collapse: collapse; width: 100%; table-layout: fixed; }
  th, td { border: 1px solid #ddd; padding: 6px; font-size: 13px; word-wrap: break-word; }
  th { background: #f7f7f7; text-align: left; position: sticky; top: 0; }
  .count { color:#666; font-size: 12px; }
  .btn { padding:6px 10px; border:1px solid #888; background:#fafafa; text-decoration:none; color:#000; }
  .btn:hover { background:#f0f0f0; }
</style>
<script>
// Try to focus the tab when opened
window.addEventListener('load', () => { try { window.focus(); } catch(e){} });
</script>
</head>
<body>
<h1>Table: <?= h($table) ?></h1>
<div class="toolbar">
  <span class="count">Rows: <?= number_format($total) ?> | Showing <?= $offset+1 ?>–<?= min($offset+$limit, max($total, $offset+$limit)) ?></span>
  <a class="btn" href="?table=<?= urlencode($table) ?>&limit=<?= $limit ?>&offset=<?= $prev ?>">« Prev</a>
  <a class="btn" href="?table=<?= urlencode($table) ?>&limit=<?= $limit ?>&offset=<?= $next ?>">Next »</a>
  <a class="btn" href="?table=<?= urlencode($table) ?>&limit=<?= $limit ?>&offset=<?= $offset ?>&format=csv">Download CSV</a>
  <form method="get" style="display:inline;">
    <input type="hidden" name="table" value="<?= h($table) ?>">
    <label>Limit <input type="number" name="limit" min="1" max="50000" value="<?= $limit ?>" style="width:90px;"></label>
    <label>Offset <input type="number" name="offset" min="0" value="<?= $offset ?>" style="width:100px;"></label>
    <button type="submit" class="btn">Go</button>
  </form>
</div>

<table>
  <thead>
    <tr>
      <?php foreach ($colNames as $c): ?>
        <th><?= h($c) ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <?php foreach ($colNames as $c): ?>
          <td><?= h(isset($r[$c]) ? $r[$c] : '') ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="<?= count($colNames) ?>">(No rows)</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</body>
</html>

