<?php

namespace App\Util;

class File
{

    public static function asBytes($iniValue)
    {
        $iniValue = trim($iniValue);
        $s = [ 'g'=> 1<<30, 'm' => 1<<20, 'k' => 1<<10 ];
        return intval($iniValue) * ($s[strtolower(substr($iniValue,-1))] ?: 1);
    }

}