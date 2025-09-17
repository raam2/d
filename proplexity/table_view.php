<?php
require 'bootstrap.php';
$pdo->exec("SET NAMES utf8mb4");
$table  = $_GET['table'] ?? '';
$limit  = isset($_GET['limit']) ? max(1, min(50000, (int)$_GET['limit'])) : 1000;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$col_offset = isset($_GET['col_offset']) ? max(0,(int)$_GET['col_offset']) : 0;
$colsPerPage = 8; // change to 10/12 if you like!
if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) { http_response_code(400); exit('Invalid table name.'); }
$chk = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
$chk->execute([':t' => $table]);
if (!$chk->fetchColumn()) { http_response_code(404); exit('Table not found.'); }
$total = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
$cols = $pdo->query("SHOW FULL COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_map(fn($c) => $c['Field'], $cols);
$stmt = $pdo->prepare("SELECT * FROM `{$table}` LIMIT :lim OFFSET :off");
$stmt->bindValue(':lim',$limit,PDO::PARAM_INT);
$stmt->bindValue(':off',$offset,PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
function h($s){return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');}
$shownCols = array_slice($colNames,$col_offset,$colsPerPage);
$totalColPages = (int)ceil(count($colNames)/$colsPerPage);
$currentColPage = (int)($col_offset / $colsPerPage) + 1;
?>
<!DOCTYPE html>
<html><head>
<meta charset="utf-8">
<title>Table View: <?=h($table)?></title>
<style>
body{font-family:Arial,sans-serif;margin:20px;}
.details_box { transition: box-shadow 0.2s; }
.details_box:active { box-shadow: 0 0 20px #666; }
table {
  table-layout: auto;
}

table{border-collapse:collapse;width:100%;overflow-x:auto;display:block;}
th,td{border:1px solid #ccc;padding:6px;max-width:300px;word-break:break-all;}
th{background:#f2f2f2;position:sticky;top:0;}
.colpagebar{margin:0 0 15px 0;}
.colpagebar a,.colpagebar strong{display:inline-block;padding:4px 8px;margin:0 2px;border-radius:3px;text-decoration:none; background:#eee; color:#222;}
.colpagebar strong{background:#4CAF50; color:#fff;}
.details_box{background:#fff;border:1px solid #aaa;padding:12px;position:fixed;top:12%;left:50%;transform:translateX(-50%);z-index:1000;max-width:80vw;overflow:auto;display:none;}
.details_box table{width:100%;}
.details_box th, .details_box td{background:#fff;}
.details_close{float:right;padding:2px 8px;cursor:pointer;border:1px solid #ccc;background:#f8f8f8;}
</style>
</head><body>
<h2>Table: <?=h($table)?></h2>
<p>Total rows: <?=$total?> | Showing <?=$offset+1?>–<?=min($offset+$limit,$total)?></p>
<div class="colpagebar">
Columns:
<?php for($i=0;$i<$totalColPages;$i++): $start=$i*$colsPerPage;
  $label=$start+1 . "–" . min($start+$colsPerPage,count($colNames)); ?>
  <?php if($start==$col_offset):?><strong><?=$label?></strong>
  <?php else:?><a href="?table=<?=h($table)?>&limit=<?=$limit?>&offset=<?=$offset?>&col_offset=<?=$start?>"><?=$label?></a><?php endif;?>
<?php endfor;?>
</div>
<table>
<thead><tr>
<?php foreach ($shownCols as $c): ?>
<th><?=h($c)?></th>
<?php endforeach; ?><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr>
<?php foreach($shownCols as $c): ?>
<td><?=h($r[$c]??'')?></td>
<div id="details_box" class="details_box" style="display:none;">
  <div id="details_drag_handle" style="cursor:move;background:#eee;padding:5px;font-weight:bold;">Drag Here <span style="float:right;cursor:pointer;" onclick="document.getElementById('details_box').style.display='none'">Close</span></div>
  <div id="details_content"></div>
</div>

<?php endforeach; ?>
<td>
  <a href="#" onclick='return showDetails(<?=json_encode($r)?>)'>Details</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

  <!-- Details content rendered by JS -->
</div>
<script>
function makeDraggable(elem, handle) {
  let offsetX = 0, offsetY = 0, startX = 0, startY = 0, dragging = false;
  handle.onmousedown = function(e) {
    dragging = true;
    startX = e.clientX;
    startY = e.clientY;
    offsetX = elem.offsetLeft;
    offsetY = elem.offsetTop;
    elem.style.transform = ""; // Remove centering
    document.onmousemove = function(e) {
      if (!dragging) return;
      let dx = e.clientX - startX;
      let dy = e.clientY - startY;
      elem.style.left = (offsetX + dx) + "px";
      elem.style.top = (offsetY + dy) + "px";
    };
    document.onmouseup = function() {
      dragging = false;
      document.onmousemove = null;
      document.onmouseup = null;
    };
  };
}
document.addEventListener("DOMContentLoaded", function() {
  var box = document.getElementById('details_box');
  var handle = document.getElementById('details_drag_handle');
  if (box && handle) makeDraggable(box, handle);
});

function showDetails(row) {
  var html = '<table style="width:100%">';
  for (var k in row) {
    html += '<tr><th style="text-align:left">' + k + '</th><td>' + row[k] + '</td></tr>';
  }
  html += '</table>';
  var box = document.getElementById('details_box');
  document.getElementById('details_content').innerHTML = html;
  box.style.display = 'block';
  box.style.top = '12%';
  box.style.left = '50%';
  box.style.transform = 'translateX(-50%)';
  // Re-enable drag after content update
  var handle = document.getElementById('details_drag_handle');
  makeDraggable(box, handle);
  return false;
}

</script>
</body></html>

