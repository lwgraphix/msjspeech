<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\UserType;

class UserModel extends BaseModel
{
    public function create($data)
    {
        // check if email is exists
        $user = MySQL::get()->fetchColumn('SELECT email FROM users WHERE email = :e', [
            'e' => $data['email']
        ]);

        if ($user !== false)
        {
            return StatusCode::USER_EMAIL_EXISTS;
        }

        $pass = password_hash($data['password'], PASSWORD_BCRYPT);

        $insert = 'INSERT INTO users
                   (email, password, first_name, last_name, parent_first_name, parent_last_name, parent_email, role)
                   VALUES
                   (:e, :p, :fn, :ln, :pfn, :pln, :pe, :r)';

        $userId = MySQL::get()->exec($insert, [
            'e' => $data['email'],
            'p' => $pass,
            'fn' => $data['first_name'],
            'ln' => $data['last_name'],
            'pfn' => $data['parent_first_name'],
            'pln' => $data['parent_last_name'],
            'pe' => $data['parent_email'],
            'r' => UserType::PENDING
        ], true);

        // ex: DmitBezm#0001
        $username = substr($data['first_name'], 0,4) .
                    substr($data['last_name'], 0,4) .
                    '#' .
                    str_pad($userId, 4, '0', STR_PAD_LEFT)
        ;

        MySQL::get()->exec('UPDATE users SET username = :u WHERE id = :i', [
            'u' => $username,
            'i' => $userId
        ]);

        $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
        $attrSQL = 'INSERT INTO user_attributes (user_id, attribute_id, `value`) VALUES (:uid, :aid, :v)';
        foreach($attributes as $attribute)
        {
            if (!isset($data['attr_' . $attribute['id']]))
            {
                $data['attr_' . $attribute['id']] = null;
            }

            MySQL::get()->exec($attrSQL, [
                'uid' => $userId,
                'aid' => $attribute['id'],
                'v' => $data['attr_' . $attribute['id']]
            ]);
        }

        // TODO: if stripe_token available -> charge user and create transaction
        return true;
    }

    public function update(User $user, $data)
    {
        if ($user->getEmail() != $data['email'])
        {
            // check if email is exists
            $user = MySQL::get()->fetchColumn('SELECT email FROM users WHERE email = :e', [
                'e' => $data['email']
            ]);

            if ($user !== false)
            {
                return StatusCode::USER_EMAIL_EXISTS;
            }
        }

        $sql = 'UPDATE users SET
                first_name = :fn,
                last_name = :ln,
                parent_email = :pe,
                parent_first_name = :pfn,
                parent_last_name = :pln
                WHERE id = :id';

        MySQL::get()->exec($sql, [
            'id' => $user->getId(),
            'fn' => $data['first_name'],
            'ln' => $data['last_name'],
            'pe' => $data['parent_email'],
            'pfn' => $data['parent_first_name'],
            'pln' => $data['parent_last_name']
        ]);

        $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
        foreach($attributes as $attribute)
        {
            if ($attribute['editable'])
            {
                if (!isset($data['attr_' . $attribute['id']]))
                {
                    $data['attr_' . $attribute['id']] = null;
                }

                MySQL::get()->exec('UPDATE user_attributes SET `value` = :v WHERE id = :id AND user_id = :uid', [
                    'v' => $data['attr_' . $attribute['id']],
                    'id' => $attribute['id'],
                    'uid' => $user->getId()
                ]);
            }
        }

        return true;
    }

    public function getBalance($userId)
    {
        return MySQL::get()->fetchColumn('SELECT balance FROM users WHERE id = :id', [
            'id' => $userId
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