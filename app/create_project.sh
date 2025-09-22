#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(pwd)"
APP_DIR="$BASE_DIR/app"

echo "== Creating directory structure =="
mkdir -p "$APP_DIR"/{config,src,api,assets/css,assets/js,logs,tmp}

echo "== Writing files =="

# ---------------- CONFIG ----------------
cat > "$APP_DIR/config/config.php" <<'EOF'
<?php
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'gst_accounting',
    'db_user' => 'gstwork',
    'db_pass' => 'gstwork@123',

    'app_name' => 'GST Accounting',
    'single_admin_mode' => true,
    'admin_fixed_user' => 'admin',
    'session_lifetime_seconds' => 15552000,

    'debug' => false,
    'error_log' => 'logs/php-error.log',

    'meta_cache_ttl' => 30
];
EOF

# ---------------- BOOTSTRAP ----------------
cat > "$APP_DIR/bootstrap.php" <<'EOF'
<?php
define('APP_ROOT','.');
spl_autoload_register(function($c){
    $p='src/'.$c.'.php';
    if(is_file($p)) require $p;
});
$CONFIG=null;
if(is_file('config/config.php')) $CONFIG = require 'config/config.php';
if(!$CONFIG){ header('HTTP/1.1 500'); echo "Config missing"; exit; }

if(!is_dir('logs')) @mkdir('logs',0775,true);
ini_set('log_errors','1');
ini_set('error_log',$CONFIG['error_log'] ?? 'logs/php-error.log');

if(!empty($CONFIG['debug'])){ ini_set('display_errors','1'); error_reporting(E_ALL); }
else { ini_set('display_errors','0'); error_reporting(E_ALL); }

register_shutdown_function(function(){
    $e=error_get_last();
    if($e && in_array($e['type'],[E_ERROR,E_PARSE,E_COMPILE_ERROR,E_CORE_ERROR])){
        @file_put_contents('logs/last-fatal.log','['.date('c')."] {$e['type']} {$e['message']} {$e['file']}:{$e['line']}\n",FILE_APPEND);
    }
});

if(session_status()===PHP_SESSION_NONE){
    $life=(int)($CONFIG['session_lifetime_seconds']??0);
    if($life>0){ session_set_cookie_params($life,'/','',false,true); ini_set('session.gc_maxlifetime',$life); }
    @session_start();
}
if(!empty($CONFIG['single_admin_mode']) && empty($_SESSION['uid'])){
    $_SESSION['uid']=1; $_SESSION['user']=$CONFIG['admin_fixed_user']??'admin';
}

function db(){
    static $pdo=null; global $CONFIG;
    if($pdo) return $pdo;
    try{
        $pdo=new PDO(
            "mysql:host={$CONFIG['db_host']};port={$CONFIG['db_port']};dbname={$CONFIG['db_name']};charset=utf8mb4",
            $CONFIG['db_user'],$CONFIG['db_pass'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES=>false]
        );
    }catch(Throwable $e){
        @file_put_contents('logs/last-fatal.log','['.date('c')."] DB ERROR ".$e->getMessage()."\n",FILE_APPEND);
        header('HTTP/1.1 500'); echo "DB connect error"; exit;
    }
    return $pdo;
}
EOF

# ---------------- INDEX (simple front shell) ----------------
cat > "$APP_DIR/index.php" <<'EOF'
<?php
require 'bootstrap.php';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?=htmlspecialchars($CONFIG['app_name'])?></title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="app-header">
  <h1><?=htmlspecialchars($CONFIG['app_name'])?></h1>
  <nav>
    <a href="#" data-view="dashboard">Dashboard</a>
    <a href="#" data-view="invoices">Invoices (API)</a>
    <a href="#" data-view="journal">Journal</a>
    <a href="#" data-view="reports">Reports</a>
    <a href="api/test.html" target="_blank">API Test</a>
  </nav>
</header>
<main id="app-main">
  <p>Use the navigation. Data loads via API endpoints under <code>app/api/router.php</code>.</p>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
EOF

# ---------------- SRC CLASSES ----------------
cat > "$APP_DIR/src/Util.php" <<'EOF'
<?php
class Util {
    public static function esc($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
    public static function json($data,int $code=200){
        if(!headers_sent()){
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data,JSON_UNESCAPED_UNICODE);
        exit;
    }
    public static function ok($data=[]){ self::json(['ok'=>true]+$data); }
    public static function err($msg,$code=400,$extra=[]){ self::json(['ok'=>false,'error'=>$msg]+$extra,$code); }
    public static function ymd($v){
        if(!$v)return null;
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)) return $v;
        $ts=strtotime($v); return $ts?date('Y-m-d',$ts):null;
    }
    public static function num($v){ return is_numeric($v)?0+$v:0; }
}
EOF

cat > "$APP_DIR/src/Meta.php" <<'EOF'
<?php
class Meta {
    private static $cache=[];
    private static function ttl(){ global $CONFIG; return (int)($CONFIG['meta_cache_ttl']??30); }
    public static function tables(){
        $k='tables'; $now=time();
        if(isset(self::$cache[$k]) && self::$cache[$k]['exp']>$now) return self::$cache[$k]['data'];
        $pdo=db(); $t=$pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        self::$cache[$k]=['data'=>$t,'exp'=>$now+self::ttl()];
        return $t;
    }
    public static function tableExists($t){ return in_array($t,self::tables(),true); }
    public static function columns($t){
        $k='c_'.$t; $now=time();
        if(isset(self::$cache[$k]) && self::$cache[$k]['exp']>$now) return self::$cache[$k]['data'];
        if(!self::tableExists($t)) return [];
        $pdo=db(); $c=$pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll();
        self::$cache[$k]=['data'=>$c,'exp'=>$now+self::ttl()];
        return $c;
    }
}
EOF

cat > "$APP_DIR/src/Validation.php" <<'EOF'
<?php
class Validation {
    public static function requireKeys(array $data,array $keys){
        foreach($keys as $k){
            if(!array_key_exists($k,$data) || $data[$k]==='') Util::err("Missing field: $k");
        }
    }
}
EOF

cat > "$APP_DIR/src/Posting.php" <<'EOF'
<?php
class Posting {
    public static function post(array $header,array $lines): array {
        if(!Meta::tableExists('journals') || !Meta::tableExists('journal_lines')){
            Util::err("Journal tables missing");
        }
        Validation::requireKeys($header,['voucher_no','voucher_date']);
        if(!$lines) Util::err("No lines");
        $date=Util::ymd($header['voucher_date']); if(!$date) Util::err("Invalid voucher_date");
        $td=0; $tc=0;
        foreach($lines as $i=>$ln){
            Validation::requireKeys($ln,['account_id']);
            $d=Util::num($ln['debit']??0); $c=Util::num($ln['credit']??0);
            if($d>0 && $c>0) Util::err("Line $i both debit and credit");
            if($d==0 && $c==0) Util::err("Line $i missing amount");
            $td+=$d; $tc+=$c;
        }
        if(round($td,2)!==round($tc,2)) Util::err("Unbalanced: $td vs $tc");
        $pdo=db(); $pdo->beginTransaction();
        try{
            $pdo->prepare("INSERT INTO journals (voucher_no,voucher_date,narration,created_at) VALUES (?,?,?,NOW())")
                ->execute([$header['voucher_no'],$date,$header['narration']??null]);
            $jid=$pdo->lastInsertId();
            $ins=$pdo->prepare("INSERT INTO journal_lines (journal_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)");
            foreach($lines as $ln){
                $ins->execute([$jid,$ln['account_id'],Util::num($ln['debit']??0),Util::num($ln['credit']??0),$ln['description']??null]);
            }
            $pdo->commit();
            return ['journal_id'=>$jid,'total_debit'=>$td,'total_credit'=>$tc];
        }catch(Throwable $e){
            $pdo->rollBack(); Util::err("Post failed: ".$e->getMessage(),500);
        }
    }
}
EOF

cat > "$APP_DIR/src/Reports.php" <<'EOF'
<?php
class Reports {
    public static function ledger(int $accountId,?string $start,?string $end): array {
        if(!Meta::tableExists('journal_lines')||!Meta::tableExists('journals')) Util::err("Ledger tables missing");
        $pdo=db();
        $p=['aid'=>$accountId]; $f='';
        if($start){ $f.=" AND j.voucher_date>=:ds"; $p['ds']=$start; }
        if($end){ $f.=" AND j.voucher_date<=:de"; $p['de']=$end; }
        $sql="SELECT j.id journal_id,j.voucher_no,j.voucher_date,j.narration,l.debit,l.credit,l.description
              FROM journal_lines l
              JOIN journals j ON j.id=l.journal_id
              WHERE l.account_id=:aid $f
              ORDER BY j.voucher_date,j.id,l.id";
        $st=$pdo->prepare($sql); $st->execute($p);
        $rows=$st->fetchAll();
        $run=0; foreach($rows as &$r){ $run+= (float)$r['debit']-(float)$r['credit']; $r['balance']=$run; }
        return $rows;
    }
    public static function trialBalance(?string $endDate=null): array {
        if(!Meta::tableExists('accounts')||!Meta::tableExists('journal_lines')||!Meta::tableExists('journals'))
            Util::err("Trial balance tables missing");
        $pdo=db(); $p=[]; $f=$endDate?" AND j.voucher_date<=:d":"";
        if($endDate) $p['d']=$endDate;
        $sql="SELECT a.id,a.code,a.name,a.account_type,
                     SUM(l.debit) debit_sum,SUM(l.credit) credit_sum
              FROM accounts a
              LEFT JOIN journal_lines l ON l.account_id=a.id
              LEFT JOIN journals j ON j.id=l.journal_id
              WHERE 1=1 $f
              GROUP BY a.id,a.code,a.name,a.account_type
              ORDER BY a.code";
        $st=$pdo->prepare($sql); $st->execute($p);
        $rows=$st->fetchAll();
        $td=$tc=0;
        foreach($rows as &$r){
            $r['debit_sum']=(float)$r['debit_sum'];
            $r['credit_sum']=(float)$r['credit_sum'];
            $td+=$r['debit_sum']; $tc+=$r['credit_sum'];
        }
        return ['accounts'=>$rows,'total_debit'=>$td,'total_credit'=>$tc,'balanced'=>round($td,2)===round($tc,2)];
    }
    public static function gstSummary(?string $start,?string $end): array {
        if(!Meta::tableExists('invoices')||!Meta::tableExists('invoice_items')) Util::err("GST tables missing");
        $pdo=db(); $p=[]; $w="WHERE 1=1";
        if($start){ $w.=" AND i.invoice_date>=:s"; $p['s']=$start; }
        if($end){ $w.=" AND i.invoice_date<=:e"; $p['e']=$end; }

        $itemCols=array_column(Meta::columns('invoice_items'),'Field');
        // Try flexible naming:
        $taxableCol = in_array('taxable_value',$itemCols)?'taxable_value':(in_array('line_total',$itemCols)?'line_total':null);
        if(!$taxableCol) Util::err("Cannot determine taxable base column");
        $hasTaxRate=in_array('tax_rate',$itemCols);
        $hasHSN=in_array('hsn_code',$itemCols);
        $hasCGST=in_array('cgst',$itemCols);
        $hasSGST=in_array('sgst',$itemCols);
        $hasIGST=in_array('igst',$itemCols);
        $hasTaxAmount=in_array('tax_amount',$itemCols);

        $sel=["SUM(it.$taxableCol) taxable_sum"];
        if($hasTaxRate) $sel[]="it.tax_rate";
        if($hasHSN) $sel[]="it.hsn_code";
        if($hasCGST) $sel[]="SUM(it.cgst) cgst_sum";
        if($hasSGST) $sel[]="SUM(it.sgst) sgst_sum";
        if($hasIGST) $sel[]="SUM(it.igst) igst_sum";
        if(!$hasCGST && !$hasSGST && !$hasIGST && $hasTaxAmount) $sel[]="SUM(it.tax_amount) tax_sum";

        $group=[];
        if($hasHSN) $group[]="it.hsn_code";
        if($hasTaxRate)$group[]="it.tax_rate";

        $sql="SELECT ".implode(',',$sel)." FROM invoice_items it JOIN invoices i ON i.id=it.invoice_id $w";
        if($group) $sql.=" GROUP BY ".implode(',',$group)." ORDER BY ".implode(',',$group);

        $st=$pdo->prepare($sql); $st->execute($p);
        return ['rows'=>$st->fetchAll(),'grouping'=>$group,'basis'=>$taxableCol];
    }
}
EOF

cat > "$APP_DIR/src/Invoices.php" <<'EOF'
<?php
class Invoices {
    public static function list(array $opts=[]): array {
        if(!Meta::tableExists('invoices')) Util::err("Invoices table missing");
        $pdo=db(); $p=[]; $w="WHERE 1=1";
        if(!empty($opts['from'])){ $w.=" AND invoice_date>=:f"; $p['f']=$opts['from']; }
        if(!empty($opts['to'])){ $w.=" AND invoice_date<=:t"; $p['t']=$opts['to']; }
        $sql="SELECT * FROM invoices $w ORDER BY invoice_date DESC,id DESC LIMIT 500";
        $st=$pdo->prepare($sql); $st->execute($p);
        return $st->fetchAll();
    }

    public static function create(array $header,array $items): array {
        if(!Meta::tableExists('invoices')||!Meta::tableExists('invoice_items')) Util::err("Invoice tables missing");
        Validation::requireKeys($header,['invoice_number','invoice_date']);
        if(!$items) Util::err("No items");
        $date=Util::ymd($header['invoice_date']); if(!$date) Util::err("Bad invoice_date");
        $pdo=db(); $pdo->beginTransaction();
        try{
            $pdo->prepare("INSERT INTO invoices (invoice_number,invoice_date,narration,created_at) VALUES (?,?,?,NOW())")
                ->execute([$header['invoice_number'],$date,$header['narration']??null]);
            $iid=$pdo->lastInsertId();
            $itemCols=array_column(Meta::columns('invoice_items'),'Field');
            $hasQty=in_array('qty',$itemCols);
            $hasRate=in_array('rate',$itemCols);
            $hasTaxable=in_array('taxable_value',$itemCols);
            $hasTaxRate=in_array('tax_rate',$itemCols);
            $hasHSN=in_array('hsn_code',$itemCols);
            $hasCGST=in_array('cgst',$itemCols);
            $hasSGST=in_array('sgst',$itemCols);
            $hasIGST=in_array('igst',$itemCols);
            $hasLineTotal=in_array('line_total',$itemCols);
            $hasTaxAmount=in_array('tax_amount',$itemCols);

            $cols=['invoice_id','description'];
            if($hasQty)$cols[]='qty';
            if($hasRate)$cols[]='rate';
            if($hasTaxable)$cols[]='taxable_value';
            if($hasTaxRate)$cols[]='tax_rate';
            if($hasHSN)$cols[]='hsn_code';
            if($hasCGST)$cols[]='cgst';
            if($hasSGST)$cols[]='sgst';
            if($hasIGST)$cols[]='igst';
            if($hasLineTotal)$cols[]='line_total';
            if($hasTaxAmount)$cols[]='tax_amount';

            $ph='('.implode(',',array_fill(0,count($cols),'?')).')';
            $ins=$pdo->prepare("INSERT INTO invoice_items (".implode(',',$cols).") VALUES $ph");

            $totalTaxable=0; $totalTax=0;
            foreach($items as $it){
                $desc=$it['description']??'';
                $qty=$hasQty?(float)($it['qty']??1):1;
                $rate=$hasRate?(float)($it['rate']??0):0;
                $taxable=$hasTaxable?(float)($it['taxable_value']??($qty*$rate)):$qty*$rate;
                $taxRate=$hasTaxRate?(float)($it['tax_rate']??0):0;
                $cgst=$hasCGST?(float)($it['cgst']??0):0;
                $sgst=$hasSGST?(float)($it['sgst']??0):0;
                $igst=$hasIGST?(float)($it['igst']??0):0;
                $taxAmount=0;
                if($hasTaxAmount){
                    if(isset($it['tax_amount'])) $taxAmount=(float)$it['tax_amount'];
                    else if($hasCGST||$hasSGST||$hasIGST) $taxAmount=$cgst+$sgst+$igst;
                    else if($hasTaxRate) $taxAmount=round($taxable*$taxRate/100,2);
                }
                $lineTotal = $taxable + ($taxAmount?:($cgst+$sgst+$igst));

                $row=[$iid,$desc];
                if($hasQty)$row[]=$qty;
                if($hasRate)$row[]=$rate;
                if($hasTaxable)$row[]=$taxable;
                if($hasTaxRate)$row[]=$taxRate;
                if($hasHSN)$row[]=$it['hsn_code']??null;
                if($hasCGST)$row[]=$cgst;
                if($hasSGST)$row[]=$sgst;
                if($hasIGST)$row[]=$igst;
                if($hasLineTotal)$row[]=$lineTotal;
                if($hasTaxAmount)$row[]=$taxAmount;
                $ins->execute($row);

                $totalTaxable+=$taxable;
                $totalTax+= $taxAmount?:($cgst+$sgst+$igst);
            }

            // Aggregate update if columns exist
            $invCols=array_column(Meta::columns('invoices'),'Field');
            $parts=[];$bind=[]; 
            if(in_array('total_taxable',$invCols)){$parts[]="total_taxable=?";$bind[]=$totalTaxable;}
            if(in_array('total_tax',$invCols)){$parts[]="total_tax=?";$bind[]=$totalTax;}
            if(in_array('grand_total',$invCols)){$parts[]="grand_total=?";$bind[]=$totalTaxable+$totalTax;}
            if($parts){
                $bind[]=$iid;
                $pdo->prepare("UPDATE invoices SET ".implode(',',$parts)." WHERE id=? LIMIT 1")->execute($bind);
            }
            $pdo->commit();
            return ['invoice_id'=>$iid,'total_taxable'=>$totalTaxable,'total_tax'=>$totalTax,'grand_total'=>$totalTaxable+$totalTax];
        }catch(Throwable $e){
            $pdo->rollBack(); Util::err("Invoice create failed: ".$e->getMessage(),500);
        }
    }

    public static function view(int $id): array {
        if(!Meta::tableExists('invoices')||!Meta::tableExists('invoice_items')) Util::err("Invoice tables missing");
        $pdo=db();
        $h=$pdo->prepare("SELECT * FROM invoices WHERE id=?"); $h->execute([$id]);
        $head=$h->fetch(); if(!$head) Util::err("Not found",404);
        $it=$pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id"); $it->execute([$id]);
        return ['invoice'=>$head,'items'=>$it->fetchAll()];
    }
}
EOF

# ---------------- API ROUTER ----------------
mkdir -p "$APP_DIR/api"
cat > "$APP_DIR/api/router.php" <<'EOF'
<?php
require '../bootstrap.php';
require_once '../src/Util.php';
require_once '../src/Meta.php';
require_once '../src/Validation.php';
require_once '../src/Posting.php';
require_once '../src/Reports.php';
require_once '../src/Invoices.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$raw = file_get_contents('php://input');
$body = [];
if($method==='POST' && $raw){
    $j=json_decode($raw,true);
    if(is_array($j)) $body=$j;
}

$from = isset($_GET['from']) ? Util::ymd($_GET['from']) : null;
$to   = isset($_GET['to'])   ? Util::ymd($_GET['to'])   : null;

switch($action){
    case 'accounts':
        if(!Meta::tableExists('accounts')) Util::err("accounts table missing");
        $rows=db()->query("SELECT id,code,name,account_type FROM accounts ORDER BY code LIMIT 1000")->fetchAll();
        Util::ok(['accounts'=>$rows]);
        break;

    case 'journal_post':
        $res=Posting::post($body['header']??[],$body['lines']??[]);
        Util::ok($res);
        break;

    case 'ledger':
        $aid=(int)($_GET['account_id'] ?? $body['account_id'] ?? 0);
        if($aid<=0) Util::err("account_id required");
        $rows=Reports::ledger($aid,$from,$to);
        Util::ok(['rows'=>$rows,'account_id'=>$aid,'from'=>$from,'to'=>$to]);
        break;

    case 'trial_balance':
        $end=Util::ymd($_GET['date'] ?? $body['date'] ?? $to);
        $tb=Reports::trialBalance($end);
        Util::ok($tb+['date'=>$end]);
        break;

    case 'gst_summary':
        $gs=Reports::gstSummary($from,$to);
        Util::ok($gs+['from'=>$from,'to'=>$to]);
        break;

    case 'invoices':
        $rows=Invoices::list(['from'=>$from,'to'=>$to]);
        Util::ok(['invoices'=>$rows]);
        break;

    case 'invoice_create':
        $res=Invoices::create($body['header']??[],$body['items']??[]);
        Util::ok($res);
        break;

    case 'invoice_view':
        $id=(int)($_GET['id'] ?? $body['id'] ?? 0);
        if($id<=0) Util::err("id required");
        $res=Invoices::view($id);
        Util::ok($res);
        break;

    case 'schema':
        $tables=Meta::tables(); $meta=[];
        foreach($tables as $t){ $meta[$t]=array_column(Meta::columns($t),'Type','Field'); }
        Util::ok(['tables'=>$meta]);
        break;

    default:
        Util::err("Unknown action: $action",404);
}
EOF

# ---------------- API Test Page ----------------
cat > "$APP_DIR/api/test.html" <<'EOF'
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>API Test</title>
<style>
body{font-family:system-ui;margin:1rem;background:#111;color:#eee;}
label{display:block;margin:.5rem 0;}
textarea,input,select{width:100%;max-width:800px;background:#1d1d1d;color:#eee;border:1px solid #333;padding:.5rem;}
button{padding:.55rem 1.1rem;background:#2d7cc6;color:#fff;border:none;cursor:pointer;margin-top:.5rem;}
pre{background:#1d1d1d;padding:.75rem;max-width:900px;overflow:auto;}
</style>
</head>
<body>
<h1>Accounting API Test</h1>
<form id="f">
  <label>Action
    <input name="action" value="trial_balance">
  </label>
  <label>Method
    <select name="method">
      <option>GET</option>
      <option>POST</option>
    </select>
  </label>
  <label>Query Params (e.g. from=2025-04-01&to=2025-04-30)
    <input name="query">
  </label>
  <label>JSON Body (POST only)
    <textarea name="body" rows="6">{}</textarea>
  </label>
  <button>Send</button>
</form>
<pre id="out"></pre>
<script>
document.getElementById('f').addEventListener('submit', async e=>{
  e.preventDefault();
  const fd=new FormData(e.target);
  const action=fd.get('action');
  const method=fd.get('method');
  const query=fd.get('query')||'';
  let url='router.php?action='+encodeURIComponent(action);
  if(query.trim()) url+='&'+query.trim();
  const opt={method};
  if(method==='POST'){
    opt.headers={'Content-Type':'application/json'};
    opt.body=fd.get('body')||'{}';
  }
  const r=await fetch(url,opt);
  const t=await r.text();
  document.getElementById('out').textContent=t;
});
</script>
</body>
</html>
EOF

# ---------------- ASSETS ----------------
cat > "$APP_DIR/assets/css/style.css" <<'EOF'
body{margin:0;font-family:system-ui,Arial,sans-serif;background:#101010;color:#e6e6e6;}
.app-header{background:#181818;padding:.8rem 1rem;display:flex;flex-wrap:wrap;align-items:center;gap:1rem;}
.app-header h1{margin:0;font-size:1.1rem;font-weight:500;}
.app-header nav a{color:#4fb3ff;text-decoration:none;margin-right:.75rem;font-size:.9rem;}
.app-header nav a:hover{text-decoration:underline;}
#app-main{padding:1rem;}
code{background:#222;padding:2px 5px;border-radius:3px;}
EOF

cat > "$APP_DIR/assets/js/app.js" <<'EOF'
document.addEventListener('DOMContentLoaded',()=>{
  const main=document.getElementById('app-main');
  document.querySelectorAll('[data-view]').forEach(a=>{
    a.addEventListener('click',e=>{
      e.preventDefault();
      loadView(a.getAttribute('data-view'));
    });
  });
  function loadView(v){
    switch(v){
      case 'invoices':
        main.innerHTML='<h2>Invoices</h2><p>Fetching latest …</p>';
        fetch('api/router.php?action=invoices')
          .then(r=>r.json())
          .then(j=>{
            if(!j.ok) { main.innerHTML='Error: '+j.error; return;}
            const rows=j.invoices.map(i=>`<tr><td>${i.id}</td><td>${i.invoice_number||''}</td><td>${i.invoice_date||''}</td><td>${i.grand_total||''}</td></tr>`).join('');
            main.innerHTML='<h2>Invoices</h2><table><thead><tr><th>ID</th><th>No</th><th>Date</th><th>Total</th></tr></thead><tbody>'+rows+'</tbody></table>';
          }).catch(err=> main.innerHTML='Fetch error '+err);
        break;
      case 'journal':
        main.innerHTML='<h2>Journal</h2><p>Use API: POST journal_post.</p>';
        break;
      case 'reports':
        main.innerHTML='<h2>Reports</h2><ul><li>Trial Balance: <code>api/router.php?action=trial_balance</code></li><li>GST Summary: <code>api/router.php?action=gst_summary&from=YYYY-MM-DD&to=YYYY-MM-DD</code></li></ul>';
        break;
      default:
        main.innerHTML='<h2>Dashboard</h2><p>Welcome. Explore navigation links.</p>';
    }
  }
});
EOF

# ---------------- GITIGNORE ----------------
cat > "$APP_DIR/.gitignore" <<'EOF'
/logs/*
/tmp/*
!/.gitignore
EOF

echo "== Done =="
echo "Project created at: $APP_DIR"
echo "Test endpoints: app/api/router.php?action=trial_balance"
