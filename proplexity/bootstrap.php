<?php
// bootstrap.php — common include to make all pages load reliably
declare(strict_types=1);

// --- error visibility (make 1 while debugging, 0 for prod) ---
@ini_set('display_errors', '1'); // set to '0' in prod
error_reporting(E_ALL);

// --- connect PDO (path-safe) ---
$__db_loaded = false;
if (file_exists(__DIR__ . '/db.php')) { require_once __DIR__ . '/db.php'; $__db_loaded = true; }
elseif (file_exists(dirname(__DIR__) . '/db.php')) { require_once dirname(__DIR__) . '/db.php'; $__db_loaded = true; }
elseif (file_exists(__DIR__ . '/include/db.php')) { require_once __DIR__ . '/include/db.php'; $__db_loaded = true; }

if (!$__db_loaded) {
    http_response_code(500);
    echo "db.php not found near " . __DIR__;
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (class_exists('Database')) {
        $db = new Database();
        $pdo = $db->getConnection();
    } else {
        http_response_code(500);
        echo "No \$pdo or Database class available in db.php";
        exit;
    }
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// --- optional: session (non-blocking) ---
if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    // release session lock so parallel requests don't block
    session_write_close();
}

// --- portable timeout / mysql session tuning ---
function sql_try(PDO $pdo, string $sql): void { try { $pdo->exec($sql); } catch (Throwable $e) {} }

$__ver = '';
try { $__ver = (string)$pdo->query("SELECT VERSION()")->fetchColumn(); } catch (Throwable $e) {}
$__isMaria = stripos($__ver, 'mariadb') !== false;

ignore_user_abort(true);
@ini_set('max_execution_time','0'); @set_time_limit(0);
@ini_set('default_socket_timeout','120');
@ini_set('zlib.output_compression','0');
if (!headers_sent()) {
  header('Content-Type: text/html; charset=utf-8');
  header('X-Accel-Buffering: no');
}
if (function_exists('apache_setenv')) @apache_setenv('no-gzip','1');

sql_try($pdo, "SET SESSION innodb_lock_wait_timeout = 10");
sql_try($pdo, "SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
if ($__isMaria) sql_try($pdo, "SET SESSION max_statement_time = 60");
else           sql_try($pdo, "SET SESSION max_execution_time = 60000");

function flush_now() { @ob_flush(); @flush(); }
