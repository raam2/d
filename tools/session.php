<?php
$oneYear = 60*60*24*365;
ini_set('session.gc_maxlifetime', (string)$oneYear);
session_set_cookie_params([
  'lifetime'=>$oneYear, 'path'=>'/', 'httponly'=>true, 'samesite'=>'Lax'
]);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['__init'])) { session_regenerate_id(true); $_SESSION['__init']=1; }
?>
