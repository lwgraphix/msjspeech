<?php

namespace App\Connector;
use App\Connection\MySQLConnection;
use App\Util\Debug;
use Silex\Application;

class MySQL
{

    const SETTINGS_PATH = __DIR__ . '/../../../../app/config/database_mysql.ini';

    /**
     * @var MySQLConnection
     */
    private static $connection;

    /**
     * @var Application
     */
    private static $application;

    /**
     * @var string Initial db name needed for fallback
     */
    private static $mainDb;

    /**
     * @var bool
     */

    private static $booted = false;

    public static function get()
    {
        if (!self::$booted)
        {
            self::boot();
        }

        return self::$connection;
    }

    public static function change($database)
    {
        self::get()->query('use `'. $database .'`');
        return self::$connection;
    }

    public static function reset()
    {
        self::change(self::$mainDb);
    }

    public static function loadApplication(Application &$app)
    {
        self::$application = $app;
    }

    private static function boot()
    {
        if (!file_exists(MySQL::SETTINGS_PATH)) {
            Debug::fail('Configuration file not found: ' . (MySQL::SETTINGS_PATH));
        }

        $dbCfg = parse_ini_file(MySQL::SETTINGS_PATH);

        self::$mainDb = $dbCfg['db'];
        self::$connection = new MySQLConnection($dbCfg['host'], $dbCfg['port'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['db']);
        self::$booted = true;
    }
}