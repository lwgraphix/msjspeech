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

    public static function convertToUSATime($timestamp)
    {
        $d = new \DateTime($timestamp);
        return $d->format('m/d/Y h:i A');
    }
}