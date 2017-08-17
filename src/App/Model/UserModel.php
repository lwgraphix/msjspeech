<?php
namespace App\Model;

use App\Connector\MySQL;
use App\Provider\Security;
use App\Type\RigStatusType;

class UserModel extends BaseModel
{
    public function create($username, $password)
    {
        $pass = password_hash($password, PASSWORD_BCRYPT);
        $secretKey = md5($username . $pass . time());
        //$sql = 'INSERT INTO users (email, password, role, secret_key) VALUES (:u, :p, :r, :sk)';
        MySQL::get()->exec($sql, [
            'u' => $username,
            'p' => $pass,
            'r' => 1,
            'sk' => $secretKey
        ]);
    }

    public function getAll()
    {
        $sql = 'SELECT * FROM users';
        $data = MySQL::get()->fetchAll($sql);
        return $data;
    }

    public function getById($id)
    {
        $sql = 'SELECT * FROM users WHERE id = :id';
        $data = MySQL::get()->fetchOne($sql, ['id' => $id]);
        return $data;
    }
}