<?php

namespace App\Util;
use Exception;
use PDO;
use PDOException;

class MySQL
{
    public $dbh;

    public function __construct($host, $port, $username, $password, $db)
    {
        $this->db_username = $username;
        $this->db_password = $password;
        $this->db_dbname = $db;
        $this->db_host = $host;
        $this->db_port = $port;

        if (!is_null($db)) $this->db_dbname = $db;

        try {
            $this->dbh = new PDO('mysql:host='.$this->db_host.';port='.$this->db_port.';dbname=' . $this->db_dbname, $this->db_username, $this->db_password);
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw $e;
        }

        $this->PDO_Execute('SET NAMES "utf8"');
        return true;
    }

    public function PDO_GetAll($sql, $data = array())
    {
        try {
            $statement = $this->dbh->prepare($sql);
            $statement->execute($data);
            $row = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e){
            var_dump($sql);
            throw $e;
        }

        return $row;
    }

    public function PDO_GetOne($sql, $data = array())
    {
        try {
            $statement = $this->dbh->prepare($sql);
            $statement->execute($data);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            var_dump($sql);
            echo $e;
        }

        return $row;
    }

    public function PDO_GetValue($sql, $data = array())
    {
        $statement = $this->dbh->prepare($sql);
        try {
            $statement->execute($data);
        } catch (PDOException $e) {
            var_dump($sql, $data);
            throw $e;
        }

        $row = $statement->fetchColumn();
        return $row;
    }

    public function PDO_Execute($sql, $data = array(), $returnID = false)
    {
        try {
            $statement = $this->dbh->prepare($sql);
            $statement->execute($data);
        } catch (PDOException $e) {
            var_dump($sql);
            throw $e;
        }

        if ($returnID) return $this->dbh->lastInsertId();
        else return 0;
    }

    public function PDO_ChangeDB($database) {
        $this->PDO_Execute('use `'.$database.'`');
    }

    public function reconnect_pdo() {
        try {
            $dbh = new PDO('mysql:host='.$this->db_host.';port='.$this->db_port.';dbname=' . $this->db_dbname, $this->db_username, $this->db_password);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $dbh->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
        }
        catch (Exception $e) {
            var_dump($sql);
            echo $e;
        }

        $this->dbh = $dbh;
    }
}