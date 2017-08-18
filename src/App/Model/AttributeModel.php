<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Security;
use App\Type\UserType;

class AttributeModel extends BaseModel
{
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