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
        $data = MySQL::get()->fetchAll('SELECT * FROM attributes WHERE group = :g', [
            'g' => $group
        ]);

        return $data;
    }
}