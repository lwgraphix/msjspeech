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

        $username = $data['first_name'] . $data['last_name'] . '#' . str_pad($userId, 4, '0', STR_PAD_LEFT);

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

            // multiple values insert
            if (is_array($data['attr_' . $attribute['id']]))
            {
                foreach($data['attr_' . $attribute['id']] as $dataItem)
                {
                    MySQL::get()->exec($attrSQL, [
                        'uid' => $userId,
                        'aid' => $attribute['id'],
                        'v' => $dataItem
                    ]);
                }
            }
            else
            {
                MySQL::get()->exec($attrSQL, [
                    'uid' => $userId,
                    'aid' => $attribute['id'],
                    'v' => $data['attr_' . $attribute['id']]
                ]);
            }
        }

        // TODO: if stripe_token available -> charge user and create transaction
        return true;
    }

    public function update(User $user, $data, $adminMode = false)
    {
        if ($user->getEmail() != $data['email'])
        {
            // check if email is exists
            $checkUser = MySQL::get()->fetchColumn('SELECT email FROM users WHERE email = :e', [
                'e' => $data['email']
            ]);

            if ($checkUser !== false)
            {
                return StatusCode::USER_EMAIL_EXISTS;
            }
        }

        $sql = 'UPDATE users SET
                email = :e,
                first_name = :fn,
                last_name = :ln,
                parent_email = :pe,
                parent_first_name = :pfn,
                parent_last_name = :pln
                WHERE id = :id';

        MySQL::get()->exec($sql, [
            'id' => $user->getId(),
            'e' => $data['email'],
            'fn' => $data['first_name'],
            'ln' => $data['last_name'],
            'pe' => $data['parent_email'],
            'pfn' => $data['parent_first_name'],
            'pln' => $data['parent_last_name']
        ]);

        $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);

        // get existing user attributes
        $userAttributesIds = array_unique(array_map(function($row) {
            return $row['attribute_id'];
        }, MySQL::get()->fetchAll('SELECT attribute_id FROM user_attributes WHERE user_id = :uid', [
            'uid' => $user->getId()
        ])));


        foreach($attributes as $attribute)
        {
            // if attribute is editable OR attribute never filled before (admin can create new fields) OR it's admin
            if ($attribute['editable'] || !in_array($attribute['id'], $userAttributesIds) || $adminMode)
            {
                if (!isset($data['attr_' . $attribute['id']]))
                {
                    $data['attr_' . $attribute['id']] = null;
                }

                if (!in_array($attribute['id'], $userAttributesIds)) // if not exists - create new
                {
                    $sql = 'INSERT INTO user_attributes (user_id, attribute_id, `value`) VALUES (:uid, :aid, :v)';

                    if (is_array($data['attr_' . $attribute['id']])) // if multiple attribute field
                    {
                        // multiple values insert
                        foreach($data['attr_' . $attribute['id']] as $item)
                        {
                            MySQL::get()->exec($sql, [
                                'v' => $item,
                                'aid' => $attribute['id'],
                                'uid' => $user->getId()
                            ]);
                        }
                    }
                    else
                    {
                        MySQL::get()->exec($sql, [
                            'v' => $data['attr_' . $attribute['id']],
                            'aid' => $attribute['id'],
                            'uid' => $user->getId()
                        ]);
                    }
                }
                else
                {
                    if (is_array($data['attr_' . $attribute['id']]))
                    {
                        // multiple values insert, delete old attributes
                        MySQL::get()->exec('DELETE FROM user_attributes WHERE attribute_id = :aid AND user_id = :uid', [
                            'aid' => $attribute['id'],
                            'uid' => $user->getId()
                        ]);

                        foreach($data['attr_' . $attribute['id']] as $item)
                        {
                            $sql = 'INSERT INTO user_attributes (user_id, attribute_id, `value`) VALUES (:uid, :aid, :v)';
                            MySQL::get()->exec($sql, [
                                'v' => $item,
                                'aid' => $attribute['id'],
                                'uid' => $user->getId()
                            ]);
                        }
                    }
                    else
                    {
                        $sql = 'UPDATE user_attributes SET `value` = :v WHERE attribute_id = :aid AND user_id = :uid';
                        MySQL::get()->exec($sql, [
                            'v' => $data['attr_' . $attribute['id']],
                            'aid' => $attribute['id'],
                            'uid' => $user->getId()
                        ]);
                    }
                }
            }
        }

        return true;
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