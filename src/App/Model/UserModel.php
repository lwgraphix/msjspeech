<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Email;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\Stripe;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\EmailType;
use App\Type\TransactionType;
use App\Type\UserType;
use App\Provider\SystemSettings;

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

        $stripeStatus = false;
        if (!empty($data['stripe_token']))
        {
            $description = $data['first_name'] . ' ' . $data['last_name'] . ' ('. $data['email'] .') membership contribution';
            $stripeStatus = Stripe::charge($data['stripe_token'], SystemSettings::getInstance()->get('membership_fee'), $description);
            if ($stripeStatus !== true)
            {
                FlashMessage::set(false, $stripeStatus);
                return StatusCode::STRIPE_CHARGE_FAILED;
            }
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

        if ($stripeStatus === true)
        {
            Model::get('transaction_history')->createTransaction(
                $userId,
                SystemSettings::getInstance()->get('membership_fee'),
                TransactionType::MEMBERSHIP_FEE,
                0,
                'Stripe deposit',
                $memo2 = null,
                $memo3 = null,
                $memo4 = null,
                $memo5 = null,
                $eventId = null
            );

            Model::get('transaction_history')->createTransaction(
                $userId,
                -(SystemSettings::getInstance()->get('membership_fee')),
                TransactionType::MEMBERSHIP_FEE,
                0,
                'Membership contribution',
                $memo2 = null,
                $memo3 = null,
                $memo4 = null,
                $memo5 = null,
                $eventId = null
            );

            $sql = 'UPDATE users SET payed_fee = :pf WHERE id = :id';
            MySQL::get()->exec($sql, [
                'pf' => SystemSettings::getInstance()->get('membership_fee'),
                'id' => $userId
            ]);
        }

        // need for attachment create
        $userData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'id' => $userId
        ];

        $username = $data['first_name'] . $data['last_name'] . '#' . str_pad($userId, 4, '0', STR_PAD_LEFT);

        MySQL::get()->exec('UPDATE users SET username = :u WHERE id = :i', [
            'u' => preg_replace('/\s+/', '', $username),
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
                if ($attribute['type'] == AttributeType::ATTACHMENT)
                {
                    $uaId = MySQL::get()->exec($attrSQL, [
                        'uid' => $userId,
                        'aid' => $attribute['id'],
                        'v' => null
                    ], true);

                    $attachPath = Model::get('attachment')->createAttachment($uaId, $attribute['id'], $userData, $data['attr_' . $attribute['id']]);
                    MySQL::get()->exec('UPDATE user_attributes SET `value` = :v WHERE id = :id', [
                        'v' => $attachPath,
                        'id' => $uaId
                    ]);
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
        }

        // Generate email
        $userObject = User::loadById($userId);
        $form = 'Email: ' . $userObject->getEmail() . PHP_EOL;
        $form .= 'First name: ' . $userObject->getFirstName() . PHP_EOL;
        $form .= 'Last name: ' . $userObject->getLastName() . PHP_EOL;
        $form .= 'Parent email: ' . $userObject->getParentEmail() . PHP_EOL;
        $form .= 'Parent first name: ' . $userObject->getParentFirstName() . PHP_EOL;
        $form .= 'Parent last name: ' . $userObject->getParentLastName() . PHP_EOL;

        $attrs = Model::get('attribute')->getUserAttributes($userObject->getId());

        foreach($attrs as $attr)
        {
            $form .= $attr['label'] . ': ';

            if ($attr['type'] == AttributeType::TEXT || $attr['type'] == AttributeType::DROPDOWN)
            {
                $value = $attr['value'];
            }
            elseif ($attr['type'] == AttributeType::CHECKBOX)
            {
                $value = implode(',', $attr['value']);
            }
            elseif ($attr['type'] == AttributeType::ATTACHMENT)
            {
                $value = Security::getHost() . 'attachment/' . $attr['user_attr_id'];
            }

            if (empty($value)) $value = 'Not specified';

            $form .= $value . PHP_EOL;
        }

        Email::getInstance()->createMessage(EmailType::MEMBERSHIP_REGISTRATION, [
            'form' => $form
        ], $userObject);

        return true;
    }

    public function getByEmail($email)
    {
        $sql = 'SELECT * FROM users WHERE email = :e';
        $data = MySQL::get()->fetchOne($sql, ['e' => $email]);
        return $data;
    }

    public function restore($userId, $email)
    {
        $exists = MySQL::get()->fetchColumn('SELECT hash FROM restore_users WHERE user_id = :uid AND status = 0', [
            'uid' => $userId
        ]);

        if ($exists) return false;

        $hash = md5(time() . $userId . rand(0, 99999));
        $sql = 'INSERT INTO restore_users (hash, user_id, status) VALUES (:h, :uid, 0)';
        MySQL::get()->exec($sql, ['h' => $hash, 'uid' => $userId]);

        $user = User::loadById($userId);
        Email::getInstance()->createMessage(EmailType::ACCOUNT_RESTORE_ACCESS, [
            'restore_link' => Security::getHost() . 'auth/restore/' . $hash
        ], $user);

        return true;
    }

    public function getRestoreData($hash)
    {
        $sql = 'SELECT * FROM restore_users WHERE hash = :h AND status = 0';
        $data = MySQL::get()->fetchOne($sql, ['h' => $hash]);
        return $data;
    }

    public function changePassword($userId, $newPassword, $hash)
    {
        $pHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $sql = 'UPDATE users SET password = :p WHERE id = :uid';
        MySQL::get()->exec($sql, ['p' => $pHash, 'uid' => $userId]);

        $sql = 'UPDATE restore_users SET status = 1 WHERE hash = :h';
        MySQL::get()->exec($sql, ['h' => $hash]);
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

        // get current attributes
        $uAttributes = Model::get('attribute')->getUserAttributes($user->getId());

        $userData = [
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'id' => $user->getId()
        ];

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
                        if ($attribute['type'] == AttributeType::ATTACHMENT)
                        {
                            $uaId = MySQL::get()->exec($sql, [
                                'uid' => $user->getId(),
                                'aid' => $attribute['id'],
                                'v' => null
                            ], true);

                            $attachPath = Model::get('attachment')->createAttachment($uaId, $attribute['id'], $userData, $data['attr_' . $attribute['id']]);
                            MySQL::get()->exec('UPDATE user_attributes SET `value` = :v WHERE id = :id', [
                                'v' => $attachPath,
                                'id' => $uaId
                            ]);
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
                        if ($attribute['type'] == AttributeType::ATTACHMENT)
                        {
                            if ($uAttributes[$attribute['id']]['required'] != 0 || $data['attr_' . $attribute['id']] !== null)
                            {
                                // prevent "needed" reupload attachment
                                $attachPath = Model::get('attachment')->updateAttachment($uAttributes[$attribute['id']]['user_attr_id'], $attribute['id'], $userData, $data['attr_' . $attribute['id']]);
                                MySQL::get()->exec($sql, [
                                    'v' => $attachPath,
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
                }
            }
        }

        return true;
    }

    public function setRole($userId, $role, $adminRole)
    {
        $user = $this->getById($userId);
        if ($adminRole == UserType::OFFICER && ($user['role'] == UserType::OFFICER || $user['role'] == UserType::ADMINISTRATOR)) return false;
        $sql = 'UPDATE users SET role = :r WHERE id = :id';
        MySQL::get()->exec($sql, [
            'r' => $role,
            'id' => $userId
        ]);
        return true;
    }

    public function getAll()
    {
        $sql = 'SELECT * FROM users';
        $data = MySQL::get()->fetchAll($sql);
        return $data;
    }

    public function getAllByGroupId($groupId)
    {
        $sql = 'SELECT u.*
                FROM user_groups ug
                INNER JOIN users u ON u.id = ug.user_id
                WHERE ug.group_id = :gid';
        $data = MySQL::get()->fetchAll($sql, ['gid' => $groupId]);
        return $data;
    }

    public function getAllByTournament($tournamentId, $eventId = null)
    {
        //$sql = 'SE'
    }

    public function getAllActive()
    {
        $sql = 'SELECT * FROM users WHERE role NOT IN (0, 1, 2)';
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