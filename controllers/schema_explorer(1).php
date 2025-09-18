<?php
require __DIR__.'/bootstrap.php';
// अब $pdo तैयार है; क्वेरी चलाएँ…


$pdo = (new Database())->getConnection();
$pdo->exec("SET NAMES utf8mb4");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function qrow(PDO $pdo, string $sql, array $p=[]): ?array {
  $st = $pdo->prepare($sql); $st->execute($p); $r = $st->fetch(PDO::FETCH_ASSOC); return $r === false ? null : $r;
}
function qall(PDO $pdo, string $sql, array $p=[]): array {
  $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC);
}

$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
$serverVersion = $pdo->query("SELECT VERSION()")->fetchColumn();
$now = (new DateTime('now'))->format('Y-m-d H:i:s');

// query params
$tableLike = isset($_GET['table_like']) ? trim((string)$_GET['table_like']) : '';
$showViews = isset($_GET['show_views']) ? (bool)$_GET['show_views'] : true;
$showRoutines = isset($_GET['show_routines']) ? (bool)$_GET['show_routines'] : true;
$showEvents = isset($_GET['show_events']) ? (bool)$_GET['show_events'] : true;
$section = $_GET['section'] ?? ''; // json|md|dot|sql_simple

// (1) collect tables
$where = "TABLE_SCHEMA = :db";
$params = [':db' => $dbName];
if ($tableLike !== '') { $where .= " AND TABLE_NAME LIKE :like"; $params[':like'] = $tableLike; }

$tables = qall($pdo, "SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_ROWS, CREATE_TIME, UPDATE_TIME, TABLE_COMMENT
  FROM INFORMATION_SCHEMA.TABLES WHERE $where ORDER BY TABLE_TYPE, TABLE_NAME", $params);

// (2) columns
$cols = qall($pdo, "SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_KEY, COLUMN_COMMENT, COLLATION_NAME, CHARACTER_SET_NAME
  FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db ORDER BY TABLE_NAME, ORDINAL_POSITION", [':db'=>$dbName]);

// (3) indexes
$indexes = qall($pdo, "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, INDEX_TYPE, COLLATION
  FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = :db ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX", [':db'=>$dbName]);

// (4) constraints + FKs
$kcu = qall($pdo, "SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = :db ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION", [':db'=>$dbName]);

$tcons = qall($pdo, "SELECT CONSTRAINT_NAME, TABLE_NAME, CONSTRAINT_TYPE
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = :db", [':db'=>$dbName]);

$ref = qall($pdo, "SELECT CONSTRAINT_NAME, UNIQUE_CONSTRAINT_NAME, MATCH_OPTION, UPDATE_RULE, DELETE_RULE, REFERENCED_TABLE_NAME, CONSTRAINT_SCHEMA
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = :db", [':db'=>$dbName]);

// (5) triggers
$triggers = qall($pdo, "SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE AS TABLE_NAME, ACTION_TIMING, ACTION_STATEMENT, SQL_MODE, CREATED
  FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = :db ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME", [':db'=>$dbName]);

// (6) views
$views = qall($pdo, "SELECT TABLE_NAME AS VIEW_NAME, VIEW_DEFINITION, CHECK_OPTION, IS_UPDATABLE, DEFINER, SECURITY_TYPE, CHARACTER_SET_CLIENT, COLLATION_CONNECTION
  FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = :db ORDER BY TABLE_NAME", [':db'=>$dbName]);

// (7) routines
$routines = qall($pdo, "SELECT ROUTINE_TYPE, ROUTINE_NAME, DTD_IDENTIFIER AS RETURNS, CREATED, LAST_ALTERED, SQL_DATA_ACCESS, SECURITY_TYPE, DEFINER
  FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = :db ORDER BY ROUTINE_TYPE, ROUTINE_NAME", [':db'=>$dbName]);

// (8) events
$events = qall($pdo, "SELECT EVENT_NAME, DEFINER, STATUS, ON_COMPLETION, EVENT_TYPE, EXECUTE_AT, INTERVAL_VALUE, INTERVAL_FIELD, STARTS, ENDS, EVENT_DEFINITION
  FROM INFORMATION_SCHEMA.EVENTS WHERE EVENT_SCHEMA = :db ORDER BY EVENT_NAME", [':db'=>$dbName]);

// map helpers
$colsByTable = []; foreach ($cols as $c) { $colsByTable[$c['TABLE_NAME']][] = $c; }
$idxByTable = []; foreach ($indexes as $ix) { $idxByTable[$ix['TABLE_NAME']][] = $ix; }
$consByTable = []; $fkByTable = []; $consType = [];
foreach ($tcons as $tc) { $consType[$tc['TABLE_NAME']][$tc['CONSTRAINT_NAME']] = $tc['CONSTRAINT_TYPE']; }
foreach ($kcu as $k) {
  $t = $k['TABLE_NAME']; $cn = $k['CONSTRAINT_NAME'];
  $consByTable[$t][$cn][] = $k;
  if ($k['REFERENCED_TABLE_NAME']) { $fkByTable[$t][$cn][] = $k; }
}
$refMeta = []; foreach ($ref as $r) { $refMeta[$r['CONSTRAINT_NAME']] = $r; }
$trgByTable = []; foreach ($triggers as $t) { $trgByTable[$t['TABLE_NAME']][] = $t; }
$viewsByName = []; foreach ($views as $v) { $viewsByName[$v['VIEW_NAME']] = $v; }

// Preload CREATE TABLE/VIEW DDL
$ddlByTable = [];
foreach ($tables as $t) {
  try {
    if ($t['TABLE_TYPE'] === 'BASE TABLE') {
      $row = qrow($pdo, "SHOW CREATE TABLE `{$t['TABLE_NAME']}`");
      if ($row) { $ddlByTable[$t['TABLE_NAME']] = $row['Create Table'] ?? ''; }
    } elseif ($t['TABLE_TYPE'] === 'VIEW') {
      $row = qrow($pdo, "SHOW CREATE VIEW `{$t['TABLE_NAME']}`");
      if ($row) { $ddlByTable[$t['TABLE_NAME']] = $row['Create View'] ?? ''; }
    }
  } catch (Throwable $e) { $ddlByTable[$t['TABLE_NAME']] = '-- (SHOW CREATE failed)'; }
}

// Build export bundle (used by JSON/MD)
$export = [
  'generated_at' => $now,
  'database' => $dbName,
  'server_version' => $serverVersion,
  'tables' => [],
  'views' => [],
  'routines' => $routines,
  'events' => $events,
];
foreach ($tables as $t) {
  $name = $t['TABLE_NAME'];
  $entry = $t;
  $entry['columns'] = $colsByTable[$name] ?? [];
  $entry['indexes'] = $idxByTable[$name] ?? [];
  $entry['constraints'] = [];
  if (isset($consByTable[$name])) {
    foreach ($consByTable[$name] as $cname => $items) {
      $meta = $refMeta[$cname] ?? null;
      $entry['constraints'][] = [
        'name' => $cname,
        'type' => $consType[$name][$cname] ?? null,
        'columns' => array_map(fn($x)=>$x['COLUMN_NAME'], $items),
        'fk' => $meta ? [
          'referenced_table' => $meta['REFERENCED_TABLE_NAME'],
          'update_rule' => $meta['UPDATE_RULE'],
          'delete_rule' => $meta['DELETE_RULE'],
        ] : null,
        'detail' => $items,
      ];
    }
  }
  $entry['triggers'] = $trgByTable[$name] ?? [];
  $entry['ddl'] = $ddlByTable[$name] ?? null;
  if ($t['TABLE_TYPE'] === 'VIEW') { $export['views'][$name] = $entry; } else { $export['tables'][$name] = $entry; }
}

// -------------------- Simple SQL Export helpers --------------------
function sql_split_items_inside_parens(string $s): array {
  // Split by commas at top-level, aware of quotes/backticks/parentheses/comments
  $items = []; $buf=''; $len=strlen($s);
  $state='code'; $depth=0;
  for ($i=0; $i<$len; $i++) {
    $ch = $s[$i]; $next = ($i+1<$len)?$s[$i+1]:'';
    if ($state==='code') {
      if ($ch==="'" || $ch==='"' || $ch==='`') { $state=$ch; $buf.=$ch; continue; }
      if ($ch==='/' && $next==='*') { $state='/*'; $buf.='/*'; $i++; continue; }
      if ($ch==='-' && $next==='-') { $state='--'; $buf.='--'; $i++; continue; }
      if ($ch==='#') { $state='#'; $buf.=$ch; continue; }
      if ($ch==='(') { $depth++; $buf.=$ch; continue; }
      if ($ch===')') { $depth=max(0,$depth-1); $buf.=$ch; continue; }
      if ($ch===',' && $depth===0) { $trim=trim($buf); if ($trim!=='') $items[]=$trim; $buf=''; continue; }
      $buf.=$ch;
    } elseif ($state==="'" || $state==='"' || $state==='`') {
      $buf.=$ch;
      if ($ch==='\\') { if ($i+1<$len) { $buf.=$s[++$i]; } continue; }
      if ($ch===$state) { $state='code'; }
    } elseif ($state==='/*') {
      $buf.=$ch;
      if ($ch==='*' && $next=== '/') { $buf.=$next; $i++; $state='code'; }
    } elseif ($state==='--') {
      $buf.=$ch; if ($ch=== "\n") { $state='code'; }
    } elseif ($state==='#') {
      $buf.=$ch; if ($ch=== "\n") { $state='code'; }
    }
  }
  $trim=trim($buf); if ($trim!=='') $items[]=$trim;
  return $items;
}

function simplify_table_ddl_text(string $ddl, bool $includePlainKeys=false): string {
  // Normalize CREATE TABLE line
  $ddl = preg_replace('/\r\n?/', "\n", $ddl);
  // Extract table name
  if (!preg_match('/^CREATE\s+TABLE\s+(`?[^`(]+`?)\s*\(/i', $ddl, $m)) {
    return $ddl; // fallback
  }
  $createLine = $m[0];
  $tableIdent = $m[1];
  // Find the opening '(' position and closing ')' before options
  $openPos = strpos($ddl, '(');
  $closePos = strrpos($ddl, ')');
  if ($openPos===false || $closePos===false || $closePos < $openPos) return $ddl;

  $itemsRaw = substr($ddl, $openPos+1, $closePos-$openPos-1);
  $items = sql_split_items_inside_parens($itemsRaw);

  $kept = [];
  foreach ($items as $it) {
    $raw = ltrim($it);
    $upper = strtoupper($raw);
    $isPlainKey = (bool)preg_match('/^KEY\s+`?[\w-]+`?\s*\(/i', $raw);
    $isUniqueKey = (bool)preg_match('/^UNIQUE\s+KEY\s+`?[\w-]+`?\s*\(/i', $raw);
    $isFulltextKey = (bool)preg_match('/^FULLTEXT\s+KEY\s+`?[\w-]+`?\s*\(/i', $raw);
    $isPrimary = (bool)preg_match('/^PRIMARY\s+KEY\b/i', $raw);
    $isConstraint = (bool)preg_match('/^CONSTRAINT\b/i', $raw);
    if ($isPlainKey && !$includePlainKeys) {
      continue; // drop non-unique keys by default
    }
    if ($isPrimary || $isUniqueKey || $isFulltextKey || $isConstraint || !$isPlainKey) {
      $kept[] = $it;
    }
  }

  // Rebuild CREATE with IF NOT EXISTS
  $header = preg_replace('/^CREATE\s+TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', "CREATE TABLE {$tableIdent} (");
  $body = "  " . implode(",\n  ", $kept) . "\n";
  $sql = $header . "\n" . $body . ");";

  return $sql;
}

function simplify_view_ddl_text(string $vname, string $ddl): string {
  // CREATE OR REPLACE VIEW `name` AS <select>;
  $ddl = preg_replace('/\r\n?/', "\n", $ddl);
  // Try to capture the SELECT after "VIEW `name` AS "
  $pattern = '/\bVIEW\s+`?'.preg_quote($vname, '/').'`?\s+AS\s+/i';
  if (preg_match($pattern, $ddl, $m, PREG_OFFSET_CAPTURE)) {
    $start = $m[0][1] + strlen($m[0][0]);
    $select = trim(substr($ddl, $start));
    if (substr($select, -1) !== ';') { $select .= ';'; }
    return "CREATE OR REPLACE VIEW `{$vname}` AS {$select}";
  }
  // Fallback: strip ALGORITHM/DEFINER/SECURITY
  $ddl = preg_replace('/^CREATE\s+.*?\bVIEW\b/i', 'CREATE OR REPLACE VIEW', $ddl);
  if (substr(trim($ddl), -1) !== ';') $ddl .= ';';
  return $ddl;
}

function topo_sort_tables(array $export): array {
  // Build dependency graph from FK meta
  $deps = []; $rev = []; $indeg = [];
  foreach ($export['tables'] as $tname => $t) {
    $deps[$tname] = [];
    $indeg[$tname] = 0;
  }
  foreach ($export['tables'] as $tname => $t) {
    foreach ($t['constraints'] as $c) {
      if (($c['type'] ?? null) === 'FOREIGN KEY' && !empty($c['fk']['referenced_table'])) {
        $p = $c['fk']['referenced_table'];
        if (!isset($deps[$tname][$p])) {
          $deps[$tname][$p] = true;
          $indeg[$tname]++;
          $rev[$p][$tname] = true;
        }
      }
    }
  }
  // Kahn's algorithm
  $ready = [];
  foreach ($indeg as $n=>$deg) if ($deg===0) $ready[] = $n;
  sort($ready, SORT_NATURAL);
  $order = [];
  while ($ready) {
    $n = array_shift($ready);
    $order[] = $n;
    foreach (array_keys($rev[$n] ?? []) as $child) {
      $indeg[$child]--;
      if ($indeg[$child]===0) {
        $ready[] = $child;
        sort($ready, SORT_NATURAL);
      }
    }
  }
  if (count($order) !== count($export['tables'])) {
    $order = array_keys($export['tables']);
    sort($order, SORT_NATURAL);
  }
  return $order;
}

// -------------------- Downloads --------------------
if ($section === 'json') {
  header('Content-Type: application/json; charset=utf-8');
  header('Content-Disposition: attachment; filename="'. $dbName . '_schema.json"');
  echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  exit;
}
if ($section === 'md') {
  header('Content-Type: text/markdown; charset=utf-8');
  header('Content-Disposition: attachment; filename="'. $dbName . '_schema.md"');
  echo "# Schema for `" . $dbName . "` (generated $now)\n\n";
  echo "Server version: `$serverVersion`\n\n";
  foreach ($export['tables'] as $name => $t) {
    echo "## Table `$name`\n\n";
    echo "- Engine: {$t['ENGINE']}, Rows: {$t['TABLE_ROWS']}, Created: {$t['CREATE_TIME']}\n\n";
    echo "### Columns\n\n| # | Name | Type | Null | Default | Extra | Key | Comment |\n|---:|---|---|---|---|---|---|---|\n";
    foreach ($t['columns'] as $c) {
      $def = (string)$c['COLUMN_DEFAULT']; $def = str_replace('|','\\|',$def);
      $com = (string)$c['COLUMN_COMMENT']; $com = str_replace("\n",' ', $com);
      echo "|" . $c['ORDINAL_POSITION'] . "|`" . $c['COLUMN_NAME'] . "`|`" . $c['COLUMN_TYPE'] . "`|" . $c['IS_NULLABLE'] . "|`" . $def . "`|`" . $c['EXTRA'] . "`|`" . $c['COLUMN_KEY'] . "`|" . $com . "|\n";
    }
    echo "\n### Indexes\n\n| Name | Unique | Seq | Column | Type |\n|---|---|---:|---|---|\n";
    foreach ($t['indexes'] as $ix) {
      echo "|" . $ix['INDEX_NAME'] . "|" . ($ix['NON_UNIQUE'] ? 'No':'Yes') . "|" . $ix['SEQ_IN_INDEX'] . "|`" . $ix['COLUMN_NAME'] . "`|" . $ix['INDEX_TYPE'] . "|\n";
    }
    echo "\n### Constraints\n\n";
    foreach ($t['constraints'] as $c) {
      echo "- **{$c['type']}** `{$c['name']}` on (" . implode(', ', array_map(fn($x)=>"`$x`",$c['columns'])) . ")";
      if ($c['type']==='FOREIGN KEY' && $c['fk']) { echo " → `{$c['fk']['referenced_table']}` (delete {$c['fk']['delete_rule']}, update {$c['fk']['update_rule']})"; }
      echo "\n";
    }
    if (!empty($t['triggers'])) {
      echo "\n### Triggers\n\n| Name | Timing | Event | Created |\n|---|---|---|---|\n";
      foreach ($t['triggers'] as $tr) {
        echo "|" . $tr['TRIGGER_NAME'] . "|" . $tr['ACTION_TIMING'] . "|" . $tr['EVENT_MANIPULATION'] . "|" . $tr['CREATED'] . "|\n";
      }
    }
    if (!empty($t['ddl'])) {
      echo "\n<details><summary>DDL</summary>\n\n```sql\n" . $t['ddl'] . "\n```\n\n</details>\n";
    }
    echo "\n";
  }
  if (!empty($export['views'])) {
    echo "\n# Views\n\n";
    foreach ($export['views'] as $name => $v) {
      echo "## View `$name`\n\n";
      echo "<details><summary>DDL</summary>\n\n```sql\n" . ($v['ddl'] ?? '') . "\n```\n\n</details>\n\n";
    }
  }
  if (!empty($routines)) {
    echo "\n# Routines\n\n| Type | Name | Returns | Created | Last Altered |\n|---|---|---|---|---|\n";
    foreach ($routines as $r) {
      echo "|" . $r['ROUTINE_TYPE'] . "|" . $r['ROUTINE_NAME'] . "|" . $r['RETURNS'] . "|" . $r['CREATED'] . "|" . $r['LAST_ALTERED'] . "|\n";
    }
  }
  if (!empty($events)) {
    echo "\n# Events\n\n| Name | Status | Type | Starts | Ends |\n|---|---|---|---|---|\n";
    foreach ($events as $e) {
      echo "|" . $e['EVENT_NAME'] . "|" . $e['STATUS'] . "|" . $e['EVENT_TYPE'] . "|" . $e['STARTS'] . "|" . $e['ENDS'] . "|\n";
    }
  }
  echo "\n"; exit;
}
if ($section === 'dot') {
  $dot = "digraph G {\n  rankdir=LR;\n  node [shape=box];\n";
  foreach ($export['tables'] as $tname => $_) { $dot .= "  \"" . addslashes($tname) . "\";\n"; }
  foreach ($export['tables'] as $tname => $t) {
    foreach ($t['constraints'] as $c) {
      if (($c['type'] ?? null) === 'FOREIGN KEY' && !empty($c['fk']['referenced_table'])) {
        $dot .= "  \"" . addslashes($tname) . "\" -> \"" . addslashes($c['fk']['referenced_table']) . "\" [label=\"" . addslashes($c['name']) . "\"];\n";
      }
    }
  }
  $dot .= "}\n";
  header('Content-Type: text/plain; charset=utf-8');
  header('Content-Disposition: attachment; filename="'. $dbName . '_fk_graph.dot"');
  echo $dot; exit;
}

// New: Simple SQL export (compact, full structure)
if ($section === 'sql_simple') {
  $includePlain = isset($_GET['include_plain']) ? (bool)$_GET['include_plain'] : false;
  $order = topo_sort_tables($export);

  $out = "-- Simple schema (tables + PK/UNIQUE/FK" . ($includePlain?"+plain indexes":"") . " + views)\n";
  $out .= "-- Generated {$now}\n-- DB: {$dbName}\n-- Server: {$serverVersion}\n\n";
  $out .= "CREATE DATABASE IF NOT EXISTS `{$dbName}`;\nUSE `{$dbName}`;\n\n";
  $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

  foreach ($order as $tname) {
    $ddl = $export['tables'][$tname]['ddl'] ?? '';
    if ($ddl) {
      $out .= simplify_table_ddl_text($ddl, $includePlain) . "\n\n";
    }
  }

  if (!empty($export['views'])) {
    $out .= "-- Views\n";
    foreach ($export['views'] as $vname => $v) {
      $vddl = $v['ddl'] ?? '';
      if ($vddl) { $out .= simplify_view_ddl_text($vname, $vddl) . "\n"; }
    }
    $out .= "\n";
  }

  $out .= "SET FOREIGN_KEY_CHECKS=1;\n";

  header('Content-Type: text/plain; charset=utf-8');
  $suffix = $includePlain ? '_schema_with_indexes.sql' : '_schema_simple.sql';
  header('Content-Disposition: attachment; filename="'. $dbName . $suffix . '"');
  echo $out; exit;
}

// -------------------- HTML render --------------------
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Schema Explorer — <?=h($dbName)?></title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 16px; }
    h1 { margin: 0 0 8px 0; font-size: 20px; }
    .meta { color:#444; margin-bottom: 12px; }
    .toolbar { position: sticky; top: 0; background: #fff; padding: 8px 0; border-bottom: 1px solid #ddd; z-index: 9; }
    .controls input[type=text] { padding:6px 8px; border:1px solid #bbb; border-radius:6px; min-width: 220px; }
    .controls label { margin-right: 10px; }
    .chip { display:inline-block; padding:2px 8px; border-radius:999px; border:1px solid #ccc; margin-right:6px; font-size:12px; }
    details { margin: 8px 0; }
    summary { cursor:pointer; font-weight:600; }
    table { border-collapse: collapse; width: 100%; margin: 8px 0; }
    th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; vertical-align: top; }
    th { background: #f7f7f7; }
    code { background:#f3f3f3; padding:1px 4px; border-radius:4px; }
    .muted { color:#666; }
    .row { display:flex; flex-wrap:wrap; gap: 12px; align-items: center; }
    .btn { display:inline-block; padding:6px 10px; border:1px solid #bbb; background:#fafafa; border-radius:8px; text-decoration:none; color:#000; }
    .btn:hover { background:#f0f0f0; }
    .section { border:1px solid #e5e5e5; border-radius:12px; padding: 12px; margin: 12px 0; }
    .hint { font-size:12px; color:#555; }
    .badge { background:#efefef; border-radius:6px; padding:2px 6px; font-size:12px; }
    .right { float:right; }
    .nowrap { white-space: nowrap; }
    .sticky-head { position: sticky; top: 40px; background: #fff; }
  </style>
</head>
<body>
  <h1>Schema Explorer — <?=h($dbName)?></h1>
  <div class="meta">
    Server: <span class="chip"><?=h($serverVersion)?></span>
    Generated: <span class="chip"><?=h($now)?></span>
    <a class="btn" href="?section=json">Download JSON</a>
    <a class="btn" href="?section=md">Download Markdown</a>
    <a class="btn" href="?section=dot" title="Graphviz DOT for FK graph">Download DOT</a>
    <a class="btn" href="?section=sql_simple" title="Compact sql.txt-style (PK/UNIQUE/FK + views)">Download Simple SQL</a>
    <a class="btn" href="?section=sql_simple&include_plain=1" title="Include non-unique indexes too">Simple SQL + Indexes</a>
  </div>
  <div class="toolbar">
    <form class="controls" method="get">
      <label>Table like:
        <input type="text" name="table_like" value="<?=h($tableLike)?>" placeholder="%invoice%">
      </label>
      <label><input type="checkbox" name="show_views" value="1" <?= $showViews ? 'checked':''; ?>> Views</label>
      <label><input type="checkbox" name="show_routines" value="1" <?= $showRoutines ? 'checked':''; ?>> Routines</label>
      <label><input type="checkbox" name="show_events" value="1" <?= $showEvents ? 'checked':''; ?>> Events</label>
      <button class="btn" type="submit">Apply</button>
      <a class="btn" href="#" id="expandAll">Expand all</a>
      <a class="btn" href="#" id="collapseAll">Collapse all</a>
    </form>
  </div>

  <div class="section">
    <div class="row">
      <div class="badge">Tables: <?=count(array_filter($tables, fn($t)=>$t['TABLE_TYPE']==='BASE TABLE'))?></div>
      <div class="badge">Views: <?=count(array_filter($tables, fn($t)=>$t['TABLE_TYPE']==='VIEW'))?></div>
      <div class="badge">Routines: <?=count($routines)?></div>
      <div class="badge">Events: <?=count($events)?></div>
    </div>
  </div>

  <?php foreach ($tables as $t): 
    $name = $t['TABLE_NAME']; 
    $isView = $t['TABLE_TYPE'] === 'VIEW';
    if ($isView && !$showViews) continue;
  ?>
  <details>
    <summary><?= $isView ? 'VIEW' : 'TABLE'; ?> <code><?=h($name)?></code>
      <span class="muted"> — <?=h($t['TABLE_COMMENT'] ?? '')?></span>
    </summary>
    <div class="section">
      <div class="row">
        <div class="badge"><?=h($t['TABLE_TYPE'])?></div>
        <?php if (!$isView): ?>
          <div class="badge">Engine: <?=h($t['ENGINE'])?></div>
          <div class="badge">Rows: <?=h((string)$t['TABLE_ROWS'])?></div>
        <?php endif; ?>
        <div class="badge">Created: <?=h((string)$t['CREATE_TIME'])?></div>
        <?php if ($t['UPDATE_TIME']) : ?>
          <div class="badge">Updated: <?=h((string)$t['UPDATE_TIME'])?></div>
        <?php endif; ?>
      </div>

      <details>
        <summary>Columns (<?= isset($colsByTable[$name]) ? count($colsByTable[$name]) : 0; ?>)</summary>
        <div style="overflow:auto;">
        <table>
          <thead class="sticky-head">
            <tr>
              <th class="nowrap">#</th>
              <th>Name</th>
              <th>Type</th>
              <th>Null</th>
              <th>Default</th>
              <th>Extra</th>
              <th>Key</th>
              <th class="nowrap">Charset/Collation</th>
              <th>Comment</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($colsByTable[$name] ?? [] as $c): ?>
            <tr>
              <td class="nowrap"><?=h((string)$c['ORDINAL_POSITION'])?></td>
              <td><code><?=h($c['COLUMN_NAME'])?></code></td>
              <td><code><?=h($c['COLUMN_TYPE'])?></code></td>
              <td><?=h($c['IS_NULLABLE'])?></td>
              <td><code><?=h((string)$c['COLUMN_DEFAULT'])?></code></td>
              <td><code><?=h($c['EXTRA'])?></code></td>
              <td><code><?=h($c['COLUMN_KEY'])?></code></td>
              <td class="nowrap"><?=h((string)$c['CHARACTER_SET_NAME'])?><?php if ($c['COLLATION_NAME']) echo '/'.h($c['COLLATION_NAME']); ?></td>
              <td><?=h($c['COLUMN_COMMENT'])?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </details>

      <?php if (!$isView): ?>
      <details>
        <summary>Indexes (<?= isset($idxByTable[$name]) ? count(array_unique(array_map(fn($x)=>$x['INDEX_NAME'], $idxByTable[$name]))) : 0; ?>)</summary>
        <table>
          <thead><tr><th>Name</th><th>Unique</th><th>Seq</th><th>Column</th><th>Prefix</th><th>Type</th></tr></thead>
          <tbody>
            <?php foreach ($idxByTable[$name] ?? [] as $ix): ?>
            <tr>
              <td><code><?=h($ix['INDEX_NAME'])?></code></td>
              <td><?= $ix['NON_UNIQUE'] ? 'No' : 'Yes' ?></td>
              <td class="nowrap"><?=h((string)$ix['SEQ_IN_INDEX'])?></td>
              <td><code><?=h($ix['COLUMN_NAME'])?></code></td>
              <td><?=h((string)$ix['SUB_PART'])?></td>
              <td><?=h($ix['INDEX_TYPE'])?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </details>

      <details>
        <summary>Constraints &amp; Foreign Keys</summary>
        <?php if (!empty($consByTable[$name])): ?>
          <?php foreach ($consByTable[$name] as $cname => $items): $ctype = $consType[$name][$cname] ?? ''; $meta = $refMeta[$cname] ?? null; ?>
            <div class="section">
              <div><strong><?=h($ctype)?></strong> <code><?=h($cname)?></code></div>
              <div class="hint">Columns: 
                <?php $colsx = array_map(fn($x)=>$x['COLUMN_NAME'], $items); echo implode(', ', array_map(fn($x)=>'<code>'.h($x).'</code>', $colsx)); ?>
              </div>
              <?php if ($ctype === 'FOREIGN KEY' && $meta): ?>
                <div class="hint">References: <code><?=h($meta['REFERENCED_TABLE_NAME'])?></code> 
                (ON DELETE <?=h($meta['DELETE_RULE'])?>, ON UPDATE <?=h($meta['UPDATE_RULE'])?>)</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="hint muted">No constraints</div>
        <?php endif; ?>
      </details>

      <details>
        <summary>Triggers (<?= isset($trgByTable[$name]) ? count($trgByTable[$name]) : 0; ?>)</summary>
        <?php if (!empty($trgByTable[$name])): ?>
          <table>
            <thead><tr><th>Name</th><th>Timing</th><th>Event</th><th>Created</th><th>Statement</th></tr></thead>
            <tbody>
              <?php foreach ($trgByTable[$name] as $tr): ?>
                <tr>
                  <td><code><?=h($tr['TRIGGER_NAME'])?></code></td>
                  <td><?=h($tr['ACTION_TIMING'])?></td>
                  <td><?=h($tr['EVENT_MANIPULATION'])?></td>
                  <td><?=h((string)$tr['CREATED'])?></td>
                  <td><code><?=h($tr['ACTION_STATEMENT'])?></code></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="hint muted">No triggers</div>
        <?php endif; ?>
      </details>
      <?php endif; ?>

      <?php if (!empty($ddlByTable[$name])): ?>
      <details>
        <summary>DDL (SHOW CREATE <?= $isView ? 'VIEW':'TABLE' ?>)</summary>
        <pre><code><?=h($ddlByTable[$name])?></code></pre>
      </details>
      <?php endif; ?>
    </div>
  </details>
  <?php endforeach; ?>

  <?php if ($showRoutines): ?>
  <details>
    <summary>Routines (<?=count($routines)?>)</summary>
    <?php if ($routines): ?>
      <table>
        <thead><tr><th>Type</th><th>Name</th><th>Returns</th><th>Created</th><th>Last Altered</th><th>Security</th><th>Data Access</th><th>Definer</th></tr></thead>
        <tbody>
        <?php foreach ($routines as $r): ?>
          <tr>
            <td><?=h($r['ROUTINE_TYPE'])?></td>
            <td><code><?=h($r['ROUTINE_NAME'])?></code></td>
            <td><code><?=h((string)$r['RETURNS'])?></code></td>
            <td><?=h((string)$r['CREATED'])?></td>
            <td><?=h((string)$r['LAST_ALTERED'])?></td>
            <td><?=h((string)$r['SECURITY_TYPE'])?></td>
            <td><?=h((string)$r['SQL_DATA_ACCESS'])?></td>
            <td><?=h((string)$r['DEFINER'])?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="hint muted">No routines found.</div>
    <?php endif; ?>
  </details>
  <?php endif; ?>

  <?php if ($showEvents): ?>
  <details>
    <summary>Events (<?=count($events)?>)</summary>
    <?php if ($events): ?>
      <table>
        <thead><tr><th>Name</th><th>Status</th><th>Type</th><th>Starts</th><th>Ends</th><th>Definer</th><th>Definition</th></tr></thead>
        <tbody>
        <?php foreach ($events as $e): ?>
          <tr>
            <td><code><?=h($e['EVENT_NAME'])?></code></td>
            <td><?=h($e['STATUS'])?></td>
            <td><?=h($e['EVENT_TYPE'])?></td>
            <td><?=h((string)$e['STARTS'])?></td>
            <td><?=h((string)$e['ENDS'])?></td>
            <td><?=h((string)$e['DEFINER'])?></td>
            <td><code><?=h((string)$e['EVENT_DEFINITION'])?></code></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="hint muted">No events found.</div>
    <?php endif; ?>
  </details>
  <?php endif; ?>

  <script>
  // Expand/Collapse helpers
  const expandAll = document.getElementById('expandAll');
  const collapseAll = document.getElementById('collapseAll');
  if (expandAll) expandAll.addEventListener('click', function(e){ e.preventDefault(); document.querySelectorAll('details').forEach(d => d.open = true); });
  if (collapseAll) collapseAll.addEventListener('click', function(e){ e.preventDefault(); document.querySelectorAll('details').forEach(d => d.open = false); });
  </script>
</body>
</html>
