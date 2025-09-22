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
