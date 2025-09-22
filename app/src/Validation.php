<?php
class Validation {
    public static function requireKeys(array $data,array $keys){
        foreach($keys as $k){
            if(!array_key_exists($k,$data) || $data[$k]==='') Util::err("Missing field: $k");
        }
    }
}
