<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\EventStatusType;
use App\Type\UserType;
use App\Util\DateUtil;

class GroupModel extends BaseModel
{

    public function getAll()
    {
        $sql = 'SELECT * FROM groups';
        $data = MySQL::get()->fetchAll($sql);
        $groups = [];
        foreach($data as $row)
        {
            $groups[$row['id']] = $row['name'];
        }
        return $groups;
    }

    public function create($name)
    {
        $sql = 'INSERT INTO groups (`name`) VALUES (:n)';
        $id = MySQL::get()->exec($sql, ['n' => trim($name)], true);
        return $id;
    }

    public function update($id, $name)
    {
        $sql = 'UPDATE groups SET `name` = :n WHERE `id` = :id';
        MySQL::get()->exec($sql, ['id' => $id, 'n' => trim($name)]);
    }

    public function delete($id)
    {
        $sql = 'DELETE FROM user_groups WHERE group_id = :id';
        MySQL::get()->exec($sql, ['id' => $id]);

        $sql = 'DELETE FROM groups WHERE id = :id';
        MySQL::get()->exec($sql, ['id' => $id]);
    }

    public function getUserGroups($userId)
    {
        $sql = 'SELECT g.id, g.name
                FROM user_groups ug
                INNER JOIN groups g ON g.id = ug.group_id
                WHERE ug.user_id = :uid';
        $data = MySQL::get()->fetchAll($sql, ['uid' => $userId]);
        $groups = [];
        foreach($data as $row)
        {
            $groups[$row['id']] = $row['name'];
        }
        return $groups;
    }

    public function getById($groupId)
    {
        $sql = 'SELECT * FROM groups WHERE id = :gid';
        $data = MySQL::get()->fetchOne($sql, ['gid' => $groupId]);
        return $data;
    }

    public function link($groupId, $userId)
    {
        $sql = 'INSERT INTO user_groups (user_id, group_id) VALUES (:uid, :gid)';
        MySQL::get()->exec($sql, ['uid' => $userId, 'gid' => $groupId]);
    }

    public function unlink($groupId, $userId)
    {
        $sql = 'DELETE FROM user_groups WHERE user_id = :uid AND group_id = :gid';
        MySQL::get()->exec($sql, ['uid' => $userId, 'gid' => $groupId]);
    }
}