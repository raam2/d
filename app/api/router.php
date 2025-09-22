<?php
// Ensure working directory is the app root so bootstrap's relative paths resolve.
chdir('..');
require 'bootstrap.php';

// Explicit requires (bootstrap already registered autoload if needed)
require_once 'src/Util.php';
require_once 'src/Meta.php';
require_once 'src/Validation.php';
require_once 'src/Posting.php';
require_once 'src/Reports.php';
require_once 'src/Invoices.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$raw = file_get_contents('php://input');
$body = [];
if ($method === 'POST' && $raw) {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) $body = $tmp;
}

$from = isset($_GET['from']) ? Util::ymd($_GET['from']) : null;
$to   = isset($_GET['to'])   ? Util::ymd($_GET['to'])   : null;

switch ($action) {
    case 'accounts':
        if (!Meta::tableExists('accounts')) Util::err("accounts table missing");
        $rows = db()->query("SELECT id,code,name,account_type FROM accounts ORDER BY code LIMIT 1000")->fetchAll();
        Util::ok(['accounts'=>$rows]);
        break;

    case 'journal_post':
        $res = Posting::post($body['header'] ?? [], $body['lines'] ?? []);
        Util::ok($res);
        break;

    case 'ledger':
        $aid = (int)($_GET['account_id'] ?? $body['account_id'] ?? 0);
        if ($aid <= 0) Util::err("account_id required");
        $rows = Reports::ledger($aid, $from, $to);
        Util::ok(['rows'=>$rows,'account_id'=>$aid,'from'=>$from,'to'=>$to]);
        break;

    case 'trial_balance':
        $end = Util::ymd($_GET['date'] ?? $body['date'] ?? $to);
        $tb  = Reports::trialBalance($end);
        Util::ok($tb + ['date'=>$end]);
        break;

    case 'gst_summary':
        $gs = Reports::gstSummary($from, $to);
        Util::ok($gs + ['from'=>$from,'to'=>$to]);
        break;

    case 'invoices':
        $rows = Invoices::list(['from'=>$from,'to'=>$to]);
        Util::ok(['invoices'=>$rows]);
        break;

    case 'invoice_create':
        $res = Invoices::create($body['header'] ?? [], $body['items'] ?? []);
        Util::ok($res);
        break;

    case 'invoice_view':
        $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
        if ($id <= 0) Util::err("id required");
        $res = Invoices::view($id);
        Util::ok($res);
        break;

    case 'schema':
        $tables = Meta::tables();
        $meta = [];
        foreach ($tables as $t) {
            $meta[$t] = array_column(Meta::columns($t), 'Type', 'Field');
        }
        Util::ok(['tables'=>$meta]);
        break;

    default:
        Util::err("Unknown action: $action", 404);
}
