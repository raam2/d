<?php
require 'bootstrap.php';    // अब यह आपकी DB कनेक्शन और सेटिंग्स को लोड करेगा

$pdo->exec("SET NAMES utf8mb4");

// … बाकी आपका कोड …


$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();

$sql = "
SELECT
  TABLE_NAME   AS name,
  TABLE_TYPE   AS type,
  ENGINE       AS engine,
  TABLE_ROWS   AS approx_rows
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME
";
$tables = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Tables in <?= htmlspecialchars($dbName) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 20px; }
  h1 { margin: 0 0 10px 0; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #ddd; padding: 8px; font-size: 14px; }
  th { background: #f4f4f4; text-align: left; }
  .toolbar { margin: 12px 0 16px; display: flex; gap: 8px; align-items: center; }
  .btn { padding: 6px 10px; border: 1px solid #888; background: #fafafa; cursor: pointer; }
  .btn:hover { background: #f0f0f0; }
  .small { color: #666; font-size: 12px; }
</style>
</head>
<body>
<h1>Database: <?= htmlspecialchars($dbName) ?></h1>

<div class="toolbar">
  <label>Rows per tab:
    <input id="limit" type="number" value="1000" min="1" max="50000" style="width:100px;">
  </label>
  <button class="btn" id="openAll">Open All (new tabs)</button>
  <span class="small">Note: Your browser may block mass pop-ups; use the button once to allow.</span>
</div>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Table</th>
      <th>Type</th>
      <th>Engine</th>
      <th>Approx Rows</th>
      <th>Open</th>
    </tr>
  </thead>
  <tbody id="tblBody">
    <?php foreach ($tables as $i => $t): ?>
      <?php
        $name = $t['name'];
        $url  = "table_view.php?table=" . urlencode($name) . "&limit=1000";
      ?>
      <tr data-url="<?= htmlspecialchars($url) ?>">
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($name) ?></td>
        <td><?= htmlspecialchars($t['type']) ?></td>
        <td><?= htmlspecialchars((string)$t['engine']) ?></td>
        <td><?= htmlspecialchars((string)$t['approx_rows']) ?></td>
        <td>
          <a class="openLink" href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener">Open</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
(function(){
  const limitEl = document.getElementById('limit');
  const rows = Array.from(document.querySelectorAll('#tblBody tr'));
  const openAllBtn = document.getElementById('openAll');

  // Update the limit in the link when user changes it
  function rebuildUrl(url, limit) {
    const u = new URL(url, window.location.href);
    u.searchParams.set('limit', String(limit || 1000));
    return u.toString();
  }

  document.querySelectorAll('.openLink').forEach(a => {
    a.addEventListener('click', (e) => {
      e.preventDefault();
      const limit = parseInt(limitEl.value || '1000', 10);
      const url = rebuildUrl(a.href, limit);
      const w = window.open(url, '_blank');
      if (w) w.focus();
    });
  });

  // Try to open each table in a new tab (sequential with small delay)
  openAllBtn.addEventListener('click', async () => {
    const limit = parseInt(limitEl.value || '1000', 10);
    for (let i = 0; i < rows.length; i++) {
      const base = rows[i].getAttribute('data-url');
      const url = rebuildUrl(base, limit);
      const w = window.open(url, '_blank');
      if (w) { try { w.focus(); } catch(e){} }
      // small delay to reduce popup blocking likelihood
      await new Promise(r => setTimeout(r, 120));
    }
  });
})();
</script>
</body>
</html>

