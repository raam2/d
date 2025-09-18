<?php
/* safe_sql_runner_bootstrap.php
 * Uses your bootstrap/db/session if present; supports routines without DELIMITER.
 * SECURITY: keep this admin-only (IP allowlist / HTTP auth), then delete after use.
 */

/* ---- include your app wiring (adjust paths if needed) ---- */
@require_once __DIR__ . '/session.php';
@require_once __DIR__ . '/bootstrap.php';
@require_once __DIR__ . '/db.php';

/* ---- detect a connection from your app ---- */
$pdo = null; $mysqli = null;
if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) $pdo = $GLOBALS['pdo'];
if (isset($GLOBALS['db'])  && $GLOBALS['db']  instanceof PDO) $pdo = $GLOBALS['db'];
if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) $mysqli = $GLOBALS['mysqli'];

/* fallback config (only used if your includes didn’t expose a connection) */
$DB_HOST='127.0.0.1'; $DB_NAME='gst_accounting'; $DB_USER='gstwork'; $DB_PASS='gstwork@123'; $DB_PORT=3306; $DB_CHARSET='utf8mb4';

if (!$pdo && !$mysqli) {
  // Try PDO first
  try {
    $pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=$DB_CHARSET",
                   $DB_USER,$DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                                      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                                      PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
  } catch (Throwable $e) {
    // Try mysqli as last resort
    mysqli_report(MYSQLI_REPORT_OFF);
    $mysqli = @new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME,$DB_PORT);
    if ($mysqli && !$mysqli->connect_errno) @$mysqli->set_charset($DB_CHARSET);
    if (!$mysqli || $mysqli->connect_errno) {
      http_response_code(500);
      echo "DB connect failed: ".htmlspecialchars($e->getMessage());
      exit;
    }
  }
}

/* ---------- helpers ---------- */
function read_uploaded_sql(): string {
  if (!isset($_FILES['sqlfile']) || $_FILES['sqlfile']['error']!==UPLOAD_ERR_OK) return '';
  $s = @file_get_contents($_FILES['sqlfile']['tmp_name']); return $s?:'';
}
function extract_routine_blocks($sql){
  $blocks=[]; $pat='/\bCREATE\s+(?:DEFINER\s*=\s*[^ ]+\s*)?(FUNCTION|PROCEDURE|TRIGGER|EVENT)\b.*?END\s*;/is';
  $off=0;
  while (preg_match($pat,$sql,$m,PREG_OFFSET_CAPTURE,$off)) {
    $start=$m[0][1]; $len=strlen($m[0][0]);
    $blocks[]=['start'=>$start,'end'=>$start+$len,'sql'=>substr($sql,$start,$len)];
    $off=$start+$len;
  }
  return $blocks;
}
function split_sql_statements($sql){
  $sql=preg_replace('/^\xEF\xBB\xBF/','',$sql);
  $sql=str_replace(["\r\n","\r"],"\n",$sql);
  $sql=preg_replace('/^\s*DELIMITER\s+.+$/im','',$sql);
  // extract routine blocks
  $blocks=extract_routine_blocks($sql); $phMap=[]; $repl=$sql;
  foreach ($blocks as $i=>$b){ $ph="__ROUTINE_BLOCK_$i__"; $phMap[$ph]=$b['sql'];
    $repl=substr_replace($repl,$ph,$b['start'],$b['end']-$b['start']);
    $delta=strlen($ph)-($b['end']-$b['start']);
    for($j=$i+1;$j<count($blocks);$j++){ $blocks[$j]['start']+=$delta; $blocks[$j]['end']+=$delta; }
  }
  // strip comments
  $repl=preg_replace('/--[ \t].*?(\n|$)/',"\n",$repl);
  $repl=preg_replace('/#[^\n]*\n/',"\n",$repl);
  $repl=preg_replace('/\/\*.*?\*\//s',' ',$repl);
  // split by ; outside quotes
  $stmts=[]; $buf=''; $sq=$dq=$bt=false; $n=strlen($repl);
  for($i=0;$i<$n;$i++){
    $ch=$repl[$i];
    if($ch=="'"&&!$dq&&!$bt){ $sq=!$sq; $buf.=$ch; continue; }
    if($ch=='"'&&!$sq&&!$bt){ $dq=!$dq; $buf.=$ch; continue; }
    if($ch=='`'&&!$sq&&!$dq){ $bt=!$bt; $buf.=$ch; continue; }
    if($ch==';'&&!$sq&&!$dq&&!$bt){ $t=trim($buf); if($t!=='') $stmts[]=$t.';'; $buf=''; }
    else $buf.=$ch;
  }
  $t=trim($buf); if($t!=='') $stmts[]=$t.(substr(rtrim($t),-1)===';'?'':';');
  // restore routines
  foreach($stmts as &$s){ foreach($phMap as $ph=>$blk){ if(strpos($s,$ph)!==false) $s=str_replace($ph,$blk,$s); } $s=trim($s); }
  $stmts=array_values(array_filter($stmts,fn($x)=>trim($x) !== ';' && trim($x) !== ''));
  return $stmts;
}
function run_sql_pdo(PDO $pdo,string $sql,bool $stopOnError=true){
  $out=[]; $n=0; $stmts=split_sql_statements($sql);
  foreach($stmts as $stmt){ $n++; $t0=microtime(true);
    try{
      if (preg_match('/^\s*SELECT\b/i',$stmt)) {
        $res=$pdo->query($stmt); $rows=$res?$res->fetchAll():[];
        $out[]=['no'=>$n,'status'=>'OK (SELECT)','time_ms'=>round((microtime(true)-$t0)*1000,2),'rows'=>count($rows),'data'=>$rows,'stmt'=>$stmt];
      } else {
        $aff=$pdo->exec($stmt); if($aff===false) throw new Exception(implode('; ',$pdo->errorInfo()));
        $out[]=['no'=>$n,'status'=>'OK','time_ms'=>round((microtime(true)-$t0)*1000,2),'affected'=>$aff,'stmt'=>$stmt];
      }
    }catch(Throwable $e){
      $out[]=['no'=>$n,'status'=>'ERROR','time_ms'=>round((microtime(true)-$t0)*1000,2),'error'=>$e->getMessage(),'stmt'=>$stmt];
      if($stopOnError) break;
    }
  }
  return $out;
}
function run_sql_mysqli(mysqli $m,string $sql,bool $stopOnError=true){
  $out=[]; $n=0; $stmts=split_sql_statements($sql);
  foreach($stmts as $stmt){ $n++; $t0=microtime(true);
    $ok=@$m->query($stmt);
    if($ok===false){ $out[]=['no'=>$n,'status'=>'ERROR','time_ms'=>round((microtime(true)-$t0)*1000,2),'error'=>$m->error,'stmt'=>$stmt]; if($stopOnError) break; }
    else{
      if($ok instanceof mysqli_result){ $rows=[]; while($r=$ok->fetch_assoc()) $rows[]=$r; $ok->free();
        $out[]=['no'=>$n,'status'=>'OK (SELECT)','time_ms'=>round((microtime(true)-$t0)*1000,2),'rows'=>count($rows),'data'=>$rows,'stmt'=>$stmt];
      } else {
        $out[]=['no'=>$n,'status'=>'OK','time_ms'=>round((microtime(true)-$t0)*1000,2),'affected'=>$m->affected_rows,'stmt'=>$stmt];
      }
    }
  }
  return $out;
}

/* ---------- handle request ---------- */
$results=null; $err=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $sql=trim($_POST['sql'] ?? '');
  if ($sql==='' && !empty($_FILES['sqlfile']['name'])) $sql=read_uploaded_sql();
  $stop=isset($_POST['stoponerror']);
  if ($sql==='') $err='No SQL provided.';
  else {
    if ($pdo) $results=run_sql_pdo($pdo,$sql,$stop);
    else       $results=run_sql_mysqli($mysqli,$sql,$stop);
  }
}
?><!doctype html>
<html><head><meta charset="utf-8"><title>Safe SQL Runner (bootstrap)</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b0b0f;color:#e6e6ea;margin:0;padding:24px}
h1{margin:0 0 16px;font-size:20px}
form{background:#14141c;border:1px solid #242438;border-radius:12px;padding:16px}
textarea{width:100%;min-height:220px;background:#0f0f15;color:#e6e6ea;border:1px solid #2a2a3a;border-radius:8px;padding:10px;font-family:ui-monospace,Consolas,monospace}
input[type=file]{color:#cfcfe6} button{background:#3a86ff;border:0;color:white;padding:10px 14px;border-radius:8px;cursor:pointer}
button:disabled{opacity:.6} label{display:inline-flex;gap:8px;align-items:center}
.card{background:#111117;border:1px solid #222235;border-radius:10px;padding:12px;margin-top:12px}
.ok{color:#86efac}.err{color:#fda4af} pre{white-space:pre-wrap;word-wrap:break-word}
table{width:100%;border-collapse:collapse;margin-top:8px} td,th{border:1px solid #2b2b3a;padding:6px 8px} th{background:#191926}
.small{opacity:.8;font-size:12px}
</style></head>
<body>
<h1>Safe SQL Runner (uses your bootstrap)</h1>
<form method="post" enctype="multipart/form-data">
  <p><b>Paste SQL</b> (or upload a .sql file)</p>
  <textarea name="sql" placeholder="Paste SQL here"></textarea>
  <div style="display:flex;gap:12px;align-items:center;margin:10px 0">
    <input type="file" name="sqlfile" accept=".sql">
    <label><input type="checkbox" name="stoponerror" checked> Stop on first error</label>
    <button type="submit">Run</button>
  </div>
  <div class="small">This console auto-detects your PDO/mysqli from bootstrap/db.</div>
</form>

<?php if($err): ?><div class="card err"><?php echo htmlspecialchars($err);?></div><?php endif; ?>
<?php if(is_array($results)): ?>
  <div class="card"><div class="small">Executed <?php echo count($results);?> statement(s).</div>
    <?php foreach($results as $r): ?>
      <div class="card">
        <div><b>#<?php echo (int)$r['no']; ?></b> —
          <?php if (strpos($r['status'],'OK')===0): ?>
            <span class="ok"><?php echo htmlspecialchars($r['status']); ?></span>
          <?php else: ?>
            <span class="err"><?php echo htmlspecialchars($r['status']); ?></span>
          <?php endif; ?>
          <span class="small">(<?php echo $r['time_ms']; ?> ms)</span>
        </div>
        <?php if(!empty($r['error'])): ?><div class="err"><b>ERROR:</b> <?php echo htmlspecialchars($r['error']); ?></div><?php endif; ?>
        <details><summary>SQL</summary><pre><?php echo htmlspecialchars($r['stmt']); ?></pre></details>
        <?php if(!empty($r['data'])): ?>
          <div style="overflow:auto;margin-top:6px">
            <table><thead><tr>
              <?php foreach(array_keys($r['data'][0]) as $c): ?><th><?php echo htmlspecialchars($c);?></th><?php endforeach; ?>
            </tr></thead><tbody>
              <?php foreach($r['data'] as $row): ?><tr>
                <?php foreach($row as $v): ?><td><?php echo htmlspecialchars((string)$v);?></td><?php endforeach; ?>
              </tr><?php endforeach; ?>
            </tbody></table>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</body></html>

