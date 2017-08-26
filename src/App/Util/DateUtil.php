<?php

namespace App\Util;

class DateUtil
{
    public static function isPassed($date)
    {
        $point = new \DateTime($date);
        $now = new \DateTime();
        return $now > $point;
    }
}