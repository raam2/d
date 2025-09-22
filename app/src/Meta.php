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
