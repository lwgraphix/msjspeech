<?php

namespace App\Executable;

use App\Connector\MySQL;
use App\Connector\Redis;
use App\Util\Email;
use App\Util\EmailType;
use App\Util\EventStatusType;
use App\Util\RigStatusType;
use App\Util\SystemSettings;
use App\Util\TelegramBot;
use App\Util\TransactionType;
use App\Util\User;

class DbMigrateExecutable extends BaseExecutable
{

    public function run()
    {
        $dbname = $this->getInput()->getArgument('dbname');
        $dbSettings = parse_ini_file(MySQL::SETTINGS_PATH);
        exec('mysqldump -u"'. $dbSettings['user'] .'" -p"'. $dbSettings['pass'] .'" "'. $dbSettings['db'] .'" > copy.sql');
        $sql = 'DROP DATABASE IF EXISTS '. $dbname .'; CREATE DATABASE IF NOT EXISTS '. $dbname .'; USE '. $dbname .';' . PHP_EOL;
        $copyPath = __DIR__ . '/../../../copy.sql';
        $resultSQL = $sql . file_get_contents($copyPath);
        file_put_contents('copy.sql', $resultSQL);
        exec('mysql -u"'. $dbSettings['user'] .'" -p"'. $dbSettings['pass'] .'" < copy.sql');
        unlink($copyPath);
        $this->log('Done copy to database: ' . $dbname);
    }
}
