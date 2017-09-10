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

    public static function convertToTimestamp($usaDate)
    {
        // mm-dd-yyyy
        $exp = explode('/', $usaDate);
        return $exp[2] . '-' . $exp[0] . '-' . $exp[1] . ' 00:00:00';
    }

    public static function convertToUSATime($timestamp, $withoutHours = false)
    {
        $d = new \DateTime($timestamp);
        if ($withoutHours)
        {
            return $d->format('m/d/Y');
        }
        else
        {
            return $d->format('m/d/Y h:i A');
        }
    }
}