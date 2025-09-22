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
