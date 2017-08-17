<?php

namespace App\Util;

class Debug
{

    private static $debug = false;
    private static $trace = [];

    public static function enable()
    {
        self::$debug = true;
    }

    public static function enabled()
    {
        return self::$debug;
    }

    public static function fail($message)
    {
        if (self::$debug)
        {
            echo $message . PHP_EOL;
            self::showStackTrace();
        }
        else
        {
            echo 'Web service on maintenance';
        }

        die;
    }

    public static function showStackTrace()
    {
        if (count(self::$trace) > 0)
        {
            echo 'Stack trace:' . PHP_EOL;
            foreach(self::$trace as $k => $trace)
            {
                echo $k . '. ' . $trace . PHP_EOL;
            }
        }
    }

    public static function stop($message)
    {
        echo $message;
        die;
    }

    public static function trace($message)
    {
        self::$trace[] = $message;
    }

}