<?php

namespace App\Connection;
use PDO;
use PDOException;

class MySQLConnection
{
    public $dbh;

    public function __construct($host, $port, $user, $pass, $db)
    {
        try {
            $this->dbh = new PDO('mysql:host='.$host.';dbname=' . $db, $user, $pass);
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->exec('SET NAMES utf8');
        } catch
        (\PDOException $e) {
            echo $e;
            die;
        }
    }

    public function fetchAll($sql, $data = array())
    {
        $statement = $this->dbh->prepare($sql);
        $statement->execute($data);
        $row = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    public function fetchOne($sql, $data = array())
    {
        try {
            $statement = $this->dbh->prepare($sql);
            $statement->execute($data);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            //$this->reconnect_pdo();
            echo $e;
            die;
        }

        return $row;
    }

    public function fetchColumn($sql, $data = array())
    {
        $statement = $this->dbh->prepare($sql);
        $statement->execute($data);
        $row = $statement->fetchColumn();
        return $row;
    }

    public function exec($sql, $data = array(), $returnID = false)
    {
        try {
            $statement = $this->dbh->prepare($sql);
            $statement->execute($data);
        } catch (PDOException $e) {
            return false;
        }

        if ($returnID) return $this->dbh->lastInsertId();
        else return true;
    }
}