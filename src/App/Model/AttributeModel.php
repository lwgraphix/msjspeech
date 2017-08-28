<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Security;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\UserType;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AttributeModel extends BaseModel
{

    public function getAll($group, $tournamentId = null)
    {
        if ($tournamentId !== null)
        {
            $sql = 'SELECT * FROM attributes WHERE tournament_id = :tid';
            $data = MySQL::get()->fetchAll($sql, [
                'tid' => $tournamentId
            ]);
        }
        else
        {
            $sql = 'SELECT * FROM attributes WHERE `group` = :g';
            $data = MySQL::get()->fetchAll($sql, [
                'g' => $group
            ]);
        }

        foreach($data as &$row)
        {
            if (!empty($row['data']))
            {
                $row['data'] = json_decode($row['data'], true);
            }
        }

        return $data;
    }

    public function deleteAttributesByUserTournamentId($userTournamentId)
    {
        $sql = 'SELECT ua.id, a.type, ua.value
                FROM user_attributes ua
                INNER JOIN attributes a ON ua.attribute_id = a.id
                WHERE ua.user_tournament_id = :utid';
        $data = MySQL::get()->fetchAll($sql, ['utid' => $userTournamentId]);
        $ids = [];
        foreach($data as $row)
        {
            if ($row['type'] == AttributeType::ATTACHMENT)
            {
                // delete attachment from server
                @unlink($row['value']);
            }
            $ids[] = $row['id'];
        }

        if (count($ids) > 0)
        {
            $sql = 'DELETE FROM user_attributes WHERE id IN ('. implode(',', $ids) .')';
            MySQL::get()->exec($sql);
        }
    }

    public function getUserAttributes($userId, $group = AttributeGroupType::REGISTER, $userTournamentId = null)
    {
        if ($userTournamentId !== null && $group == AttributeGroupType::TOURNAMENT)
        {
            $eventWhere = 'AND user_tournament_id = ' . $userTournamentId;
        }
        else
        {
            $eventWhere = null;
        }

        $sql = 'SELECT a.label, a.data, a.type, a.required, a.editable, GROUP_CONCAT(ua.value) as value, a.id, ua.id as user_attr_id, ua.id as user_has_attribute, a.placeholder, a.help_text
                FROM attributes a
                LEFT JOIN user_attributes ua ON ua.attribute_id = a.id AND ua.user_id = :uid
                WHERE a.group = :g '. $eventWhere .'
                GROUP BY a.id';
        $data = MySQL::get()->fetchAll($sql, ['uid' => $userId, 'g' => $group]);
        $ua = [];
        foreach($data as &$row)
        {
            if ($row['value'] === null && $row['user_has_attribute'] === null)
            {
                // user dont have needed attribute - unlock it for first time
                $row['editable'] = 1;
            }

            if ($row['type'] == AttributeType::ATTACHMENT && $row['value'] !== null)
            {
                $row['required'] = 0; // dont need reupload the attachment
            }

            if ($row['type'] == AttributeType::DROPDOWN || $row['type'] == AttributeType::CHECKBOX)
            {
                $row['data'] = json_decode($row['data'], true);
                if ($row['value'] !== null)
                {
                    if ($row['type'] == AttributeType::DROPDOWN)
                    {
                        $row['value'] = $row['data'][$row['value']];
                    }
                    else
                    {
                        $values = explode(',', $row['value']);
                        $row['value'] = [];
                        foreach($values as $value)
                        {
                            $row['value'][] = $row['data'][$value];
                        }
                    }
                }
            }

            $ua[$row['id']] = $row;
        }

        return $ua;
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