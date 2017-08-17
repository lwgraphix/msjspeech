<?php

namespace App\Connector;
use Doctrine\DBAL\Connection;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;

class Redis
{

    /**
     * @var \Redis
     */
    private static $connection;

    private static $booted = false;

    public static function get()
    {
        if (!self::$booted)
        {
            self::boot();
        }

        return self::$connection;
    }

    private static function boot()
    {
        $redis = new \Redis();
        $redis->pconnect('127.0.0.1', 6379);
        self::$connection = $redis;
        self::$booted = true;
    }
}