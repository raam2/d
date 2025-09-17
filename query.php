<?php
require __DIR__.'/bootstrap.php';
// अब $pdo तैयार है; क्वेरी चलाएँ…

// Helpful for broad SQL (we execute statements individually anyway)
if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
    $pdo->setAttribute(PDO::MYSQL_ATTR_MULTI_STATEMENTS, true);
}
$DB = $pdo->query("SELECT DATABASE()")->fetchColumn();
$VERSION = $pdo->query("SELECT VERSION()")->fetchColumn();

// ---------------------- Helper utilities ----------------------
function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function micro_time(): float { return microtime(true); }

function starts_with_keyword(string $sql, array $kw): bool {
    $sql = ltrim($sql);
    if ($sql === '') return false;
    // handle WITH [RECURSIVE]
    if (preg_match('/^WITH(\s+RECURSIVE)?\b/i', $sql)) return in_array('WITH', $kw, true);
    $m = [];
    if (!preg_match('/^([A-Z_]+)/i', $sql, $m)) return false;
    $first = strtoupper($m[1]);
    return in_array($first, $kw, true);
}

function is_select_like(string $sql): bool {
    return starts_with_keyword($sql, ['SELECT','SHOW','DESCRIBE','DESC','EXPLAIN','WITH']);
}

function is_dangerous(string $sql): array {
    $s = strtoupper(preg_replace('/\s+/', ' ', trim($sql)));
    $danger = false; $reason = null;

    if (preg_match('/\b(DROP|TRUNCATE|ALTER)\b/i', $s)) { $danger = true; $reason = 'DDL (DROP/TRUNCATE/ALTER)'; }

    // UPDATE/DELETE without WHERE (rough check; ignores subqueries/CTE complexity)
    if (preg_match('/^\s*UPDATE\b/i', $s) && !preg_match('/\bWHERE\b/i', $s)) { $danger = true; $reason = 'UPDATE without WHERE'; }
    if (preg_match('/^\s*DELETE\b/i', $s) && !preg_match('/\bWHERE\b/i', $s)) { $danger = true; $reason = 'DELETE without WHERE'; }

    return [$danger, $reason];
}

/**
 * Split SQL into statements on semicolons that are NOT inside quotes/backticks/comments.
 * Handles: '...', "...", `...`, -- line comments, # line comments, /* ... * / block comments.
 */
function split_sql_statements(string $sql): array {
    $len = strlen($sql);
    $stmts = [];
    $buf = '';
    $state = 'code'; // code|squote|dquote|bquote|linecomment|blockcomment
    for ($i=0; $i<$len; $i++) {
        $ch = $sql[$i];
        $next = $i+1 < $len ? $sql[$i+1] : '';

        if ($state === 'code') {
            if ($ch === "'") { $state = 'squote'; $buf .= $ch; continue; }
            if ($ch === '"') { $state = 'dquote'; $buf .= $ch; continue; }
            if ($ch === '`'){ $state = 'bquote'; $buf .= $ch; continue; }
            if ($ch === '-' && $next === '-') { $state = 'linecomment'; $buf .= $ch.$next; $i++; continue; }
            if ($ch === '#') { $state = 'linecomment'; $buf .= $ch; continue; }
            if ($ch === '/' && $next === '*') { $state = 'blockcomment'; $buf .= $ch.$next; $i++; continue; }
            if ($ch === ';') {
                $trim = trim($buf);
                if ($trim !== '') { $stmts[] = $trim; }
                $buf = '';
                continue;
            }
            $buf .= $ch;
        } elseif ($state === 'squote') {
            $buf .= $ch;
            if ($ch === '\\') { if ($i+1<$len) { $buf .= $sql[++$i]; } continue; }
            if ($ch === "'") { $state = 'code'; }
        } elseif ($state === 'dquote') {
            $buf .= $ch;
            if ($ch === '\\') { if ($i+1<$len) { $buf .= $sql[++$i]; } continue; }
            if ($ch === '"') { $state = 'code'; }
        } elseif ($state === 'bquote') {
            $buf .= $ch;
            if ($ch === '`') { $state = 'code'; }
        } elseif ($state === 'linecomment') {
            $buf .= $ch;
            if ($ch === "\n") { $state = 'code'; }
        } elseif ($state === 'blockcomment') {
            $buf .= $ch;
            if ($ch === '*' && $next === '/') { $buf .= $next; $i++; $state = 'code'; }
        }
    }
    $trim = trim($buf);
    if ($trim !== '') { $stmts[] = $trim; }
    return $stmts;
}

// CSV download hook (from last run, any resultset)
if (isset($_GET['download_csv'])) {
    $rid = (string)($_GET['download_csv']);
    $all = $_SESSION['qc_last_results'] ?? [];
    foreach ($all as $run) {
        foreach ($run['resultsets'] as $rs) {
            if ($rs['id'] === $rid) {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="query_result_'.$rid.'.csv"');
                $out = fopen('php://output', 'w');
                if (!empty($rs['columns'])) { fputcsv($out, $rs['columns']); }
                foreach ($rs['rows'] as $row) { fputcsv($out, $row); }
                fclose($out);
                exit;
            }
        }
    }
    http_response_code(404); echo "Result not found."; exit;
}

// ---------------------- Run submitted SQL ----------------------
$defaultSQL = "SELECT NOW() AS now, DATABASE() AS db;\n-- Tip: You can run multiple statements; they will execute in order.\n-- Use the danger checkbox to allow DROP/TRUNCATE/ALTER or UPDATE/DELETE without WHERE.";

$sql = isset($_POST['sql']) ? trim((string)$_POST['sql']) : $defaultSQL;
$maxRows = isset($_POST['max_rows']) ? max(1, (int)$_POST['max_rows']) : 500;
$run = isset($_POST['run']) ? true : false;
$explain = isset($_POST['explain']) ? true : false;
$useTxn = isset($_POST['use_txn']) ? true : false;
$allowDanger = isset($_POST['allow_danger']) ? true : false;

$messages = [];          // status/info messages
$resultBlocks = [];      // Each block corresponds to one statement: either resultset or info/error
$resultsetsForCsv = [];  // SELECT-like for CSV storage
$errorOccurred = false;
$startedAt = micro_time();
$runId = bin2hex(random_bytes(6));

// Convert EXPLAIN mode (only applies to first SELECT-like statements)
if ($explain && $sql !== '') {
    $stmts = split_sql_statements($sql);
    foreach ($stmts as $i => &$st) {
        if (is_select_like($st)) { $st = "EXPLAIN " . $st; break; }
    }
    unset($st);
    $sql = implode(";\n", $stmts);
}

if ($run && $sql !== '') {
    $stmts = split_sql_statements($sql);

    // Danger check (basic)
    $dangerFound = [];
    foreach ($stmts as $s) {
        [$isDanger, $why] = is_dangerous($s);
        if ($isDanger) { $dangerFound[] = $why . ": " . mb_substr($s, 0, 80) . (mb_strlen($s)>80?'...':''); }
    }
    if (!empty($dangerFound) && !$allowDanger) {
        $messages[] = ['type'=>'warn','text'=>'Dangerous statements detected. Tick "Allow dangerous" to proceed.', 'list'=>$dangerFound];
    } else {
        try {
            if ($useTxn && !$pdo->inTransaction()) { $pdo->beginTransaction(); }
            foreach ($stmts as $idx => $stmtSql) {
                $stmtStart = micro_time();
                $kind = is_select_like($stmtSql) ? 'select' : 'other';
                if ($kind === 'select') {
                    $stmt = $pdo->query($stmtSql);
                    // Capture SELECT result
                    $cols = [];
                    $rows = [];
                    $colCount = $stmt->columnCount();
                    for ($c=0; $c<$colCount; $c++) {
                        $meta = $stmt->getColumnMeta($c);
                        $cols[] = $meta['name'] ?? ('col'.$c);
                    }
                    $fetched = 0;
                    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                        $rows[] = $row;
                        if (++$fetched >= $maxRows) break;
                    }
                    $elapsed = micro_time() - $stmtStart;
                    $rid = $runId . '-' . ($idx+1);
                    $resultBlocks[] = [
                        'type'=>'resultset','index'=>$idx+1,'time'=>$elapsed,'rows'=>count($rows),
                        'columns'=>$cols,'data'=>$rows,'rid'=>$rid
                    ];
                    $resultsetsForCsv[] = ['id'=>$rid, 'columns'=>$cols, 'rows'=>$rows];
                } else {
                    // Non-select: exec for affected rows; also support SHOW/EXPLAIN via query already handled
                    $affected = $pdo->exec($stmtSql);
                    $elapsed = micro_time() - $stmtStart;
                    $resultBlocks[] = [
                        'type'=>'info','index'=>$idx+1,'time'=>$elapsed,'text'=> (string)$affected . ' row(s) affected'
                    ];
                }
            }
            if ($useTxn && $pdo->inTransaction()) { $pdo->commit(); }
            $messages[] = ['type'=>'ok','text'=>'Execution completed successfully.'];
        } catch (Throwable $e) {
            $errorOccurred = true;
            if ($useTxn && $pdo->inTransaction()) { $pdo->rollBack(); }
            $messages[] = ['type'=>'error','text'=>'Error: '.$e->getMessage()];
        }
    }
}

$totalTime = micro_time() - $startedAt;

// Store CSV-able results in session (keep last 10 runs)
if (!empty($resultsetsForCsv)) {
    $_SESSION['qc_last_results'] = $_SESSION['qc_last_results'] ?? [];
    $_SESSION['qc_last_results'][] = ['ts'=>time(),'run_id'=>$runId,'resultsets'=>$resultsetsForCsv];
    if (count($_SESSION['qc_last_results']) > 10) { array_shift($_SESSION['qc_last_results']); }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Query Console — <?=h($DB)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin:16px; }
h1 { margin:0 0 8px 0; font-size:20px; }
.meta { color:#444; margin-bottom:12px; }
textarea { width:100%; min-height:220px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; font-size:13px; padding:10px; border:1px solid #bbb; border-radius:8px; }
.controls { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin:8px 0 12px; }
.btn { padding:8px 12px; border:1px solid #999; border-radius:8px; background:#fafafa; cursor:pointer; }
.btn:hover { background:#f0f0f0; }
.badge { display:inline-block; padding:2px 8px; border-radius:999px; border:1px solid #ccc; margin-right:6px; font-size:12px; }
.msg { padding:8px 10px; border-radius:8px; margin:6px 0; }
.msg.ok { background:#ecfff0; border:1px solid #b6e3c6; }
.msg.warn { background:#fff9e6; border:1px solid #f2d98b; }
.msg.error{ background:#ffecec; border:1px solid #f0a8a8; }
table { border-collapse: collapse; width:100%; margin:12px 0; }
th, td { border:1px solid #ddd; padding:6px 8px; text-align:left; vertical-align:top; }
th { background:#f7f7f7; position:sticky; top:0; }
pre { background:#f7f7f7; padding:10px; border-radius:8px; overflow:auto; }
small.muted { color:#666; }
.rowhdr { font-weight:600; margin-top:12px; }
</style>
</head>
<body>
  <h1>Query Console — <?=h($DB)?> <span class="badge"><?=h($VERSION)?></span></h1>
  <div class="meta">Write any SQL (CRUD/DDL). Multiple statements are allowed; they run sequentially. Optionally wrap in a transaction.</div>

  <?php foreach ($messages as $m): ?>
    <div class="msg <?=$m['type']?>">
      <?=h($m['text'])?>
      <?php if (!empty($m['list'])): ?>
        <ul><?php foreach ($m['list'] as $li): ?><li><?=h($li)?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <form method="post">
    <textarea name="sql" spellcheck="false" placeholder="Write SQL here..."><?=h($sql)?></textarea>
    <div class="controls">
      <label>Max rows (per SELECT):
        <input type="number" name="max_rows" value="<?=h((string)$maxRows)?>" min="1" max="100000" style="width:100px;">
      </label>
      <label><input type="checkbox" name="use_txn" value="1" <?= $useTxn ? 'checked':''; ?>> Run in transaction (BEGIN…COMMIT)</label>
      <label><input type="checkbox" name="allow_danger" value="1" <?= $allowDanger ? 'checked':''; ?>> Allow dangerous (DROP/TRUNCATE/ALTER; UPDATE/DELETE without WHERE)</label>
      <button class="btn" type="submit" name="run" value="1">Run (Ctrl+Enter)</button>
      <button class="btn" type="submit" name="explain" value="1" title="Adds EXPLAIN to first SELECT-like statement">Explain</button>
    </div>
  </form>

  <?php if (!empty($resultBlocks)): ?>
    <div class="meta">Total time: <?=number_format($totalTime, 3)?> s</div>
    <?php foreach ($resultBlocks as $blk): ?>
      <?php if ($blk['type']==='resultset'): ?>
        <div class="rowhdr">Statement #<?=$blk['index']?> — Result (<?=h((string)$blk['rows'])?> rows shown) — <?=number_format($blk['time'],3)?> s
          <a class="btn" style="margin-left:8px" href="?download_csv=<?=h($blk['rid'])?>">Download CSV</a>
        </div>
        <div style="overflow:auto; max-height:60vh;">
          <table>
            <thead><tr>
              <?php foreach ($blk['columns'] as $c): ?><th><?=h((string)$c)?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
              <?php foreach ($blk['data'] as $row): ?>
                <tr>
                <?php foreach ($blk['columns'] as $c): ?>
                  <td><?=h((string)($row[$c] ?? ''))?></td>
                <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php elseif ($blk['type']==='info'): ?>
        <div class="rowhdr">Statement #<?=$blk['index']?> — <?=h($blk['text'])?> — <?=number_format($blk['time'],3)?> s</div>
      <?php endif; ?>
    <?php endforeach; ?>
    <small class="muted">Note: Results truncated to max rows per SELECT. Run statements individually if you need full outputs.</small>
  <?php endif; ?>

<script>
document.addEventListener('keydown', function(e){
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
    const f = document.querySelector('form'); if (f) f.submit();
  }
});
</script>
</body>
</html>

