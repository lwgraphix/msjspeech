<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Security;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\UserType;

class AttributeModel extends BaseModel
{

    // TODO: deleting attributes!!!

    public function getAll($group)
    {
        $data = MySQL::get()->fetchAll('SELECT * FROM attributes WHERE `group` = :g', [
            'g' => $group
        ]);

        foreach($data as &$row)
        {
            if (!empty($row['data']))
            {
                $row['data'] = json_decode($row['data'], true);
            }
        }

        return $data;
    }

    public function getUserAttributes($userId)
    {
        $sql = 'SELECT a.label, a.data, a.type, a.required, a.editable, ua.value, a.id, ua.id as user_has_attribute, a.placeholder, a.help_text
                FROM attributes a
                LEFT JOIN user_attributes ua ON ua.attribute_id = a.id AND ua.user_id = :uid';
        $data = MySQL::get()->fetchAll($sql, ['uid' => $userId]);
        foreach($data as &$row)
        {

            if ($row['value'] === null && $row['user_has_attribute'] === null)
            {
                // user dont have needed attribute - unlock it for first time
                $row['editable'] = 1;
            }

            if ($row['type'] == AttributeType::DROPDOWN)
            {
                $row['data'] = json_decode($row['data'], true);
                if ($row['value'] !== null)
                {
                    $row['value'] = $row['data'][$row['value']];
                }
            }
        }

        return $data;
    }

    public function delete($id, $group)
    {
        switch($group)
        {
            case AttributeGroupType::REGISTER:
                // delete user_attributes
                MySQL::get()->exec('DELETE FROM user_attributes WHERE attribute_id = :aid', [
                    'aid' => $id
                ]);
            break;
        }

        MySQL::get()->exec('DELETE FROM attributes WHERE id = :id', ['id' => $id]);
    }

    public function create($group, $label, $placeholder, $helpText, $type, $data = null, $required, $editable, $tournamentId = null)
    {
        $sql = 'INSERT INTO attributes
                (`group`, label, placeholder, help_text, `type`, `data`, required, editable, tournament_id)
                VALUES
                (:g, :l, :p, :ht, :t, :d, :r, :e, :tId)';
        MySQL::get()->exec($sql, [
            'g' => $group,
            'l' => $label,
            'p' => $placeholder,
            'ht' => $helpText,
            't' => $type,
            'd' => json_encode($data),
            'r' => $required,
            'e' => $editable,
            'tId' => $tournamentId
        ]);
    }

    public function update($id, $label, $placeholder, $helpText, $data, $required, $editable)
    {
        $sql = 'UPDATE attributes SET
                label = :l,
                placeholder = :p,
                help_text = :ht,
                `data` = :d,
                required = :r,
                editable = :e
                WHERE id = :id
             ';

        MySQL::get()->exec($sql, [
            'l' => $label,
            'p' => $placeholder,
            'ht' => $helpText,
            'd' => json_encode($data),
            'r' => $required,
            'e' => $editable,
            'id' => $id
        ]);
    }
}