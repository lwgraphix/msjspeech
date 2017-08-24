<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\UserType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AttachmentModel extends BaseModel
{
    public function createAttachment($attributeId, $userData, UploadedFile $file)
    {
        $uploadDir = __DIR__ . '/../../../uploads/' . $attributeId . '/';
        if (!file_exists($uploadDir))
        {
            mkdir($uploadDir, 0777, true);
        }

        $name = preg_replace('/\W/', '', $userData['first_name'] . ' ' .$userData['last_name']) . '_' . $userData['id'] . '_' . $attributeId . '.' . $file->getClientOriginalExtension();
        $file->move($uploadDir, $name);
        return realpath($uploadDir . $name);
    }

    public function updateAttachment($attributeId, $userData, UploadedFile $file)
    {
        $uploadDir = __DIR__ . '/../../../uploads/' . $attributeId . '/';
        if (!file_exists($uploadDir))
        {
            mkdir($uploadDir, 0777, true);
        }

        $name = preg_replace('/\W/', '', $userData['first_name'] . ' ' .$userData['last_name']) . '_' . $userData['id'] . '_' . $attributeId . '.' . $file->getClientOriginalExtension();
        $fullPathFile = realpath($uploadDir . $name);

        @unlink($fullPathFile); // delete old copy

        $file->move($uploadDir, $name);
        return $fullPathFile;
    }

    public function getUserAttachment($userId, $attributeId)
    {
        $sql = 'SELECT `value` FROM user_attributes WHERE user_id = :uid AND attribute_id = :aid';
        $path = MySQL::get()->fetchColumn($sql, [
            'uid' => $userId,
            'aid' => $attributeId
        ]);
        return $path;
    }
}