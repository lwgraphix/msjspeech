<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\UserType;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

class AttachmentModel extends BaseModel
{
    public function createAttachment($uaId, $attributeId, $userData, UploadedFile $file)
    {
        $uploadDir = __DIR__ . '/../../../uploads/' . $attributeId . '/';
        if (!file_exists($uploadDir))
        {
            mkdir($uploadDir, 0777, true);
        }

        $name = preg_replace('/\W/', '', $userData['first_name'] . ' ' .$userData['last_name']) . '_' . $userData['id'] . '_' . $uaId . '.' . $file->getClientOriginalExtension();
        $file->move($uploadDir, $name);
        return realpath($uploadDir . $name);
    }

    public function updateAttachment($uaId, $attributeId, $userData, UploadedFile $file)
    {
        $uploadDir = __DIR__ . '/../../../uploads/' . $attributeId . '/';
        if (!file_exists($uploadDir))
        {
            mkdir($uploadDir, 0777, true);
        }

        $name = preg_replace('/\W/', '', $userData['first_name'] . ' ' .$userData['last_name']) . '_' . $userData['id'] . '_' . $uaId . '.' . $file->getClientOriginalExtension();
        $fullPathFile = realpath($uploadDir . $name);

        @unlink($fullPathFile); // delete old copy

        $file->move($uploadDir, $name);
        return $fullPathFile;
    }

    public function getTournamentAttributeUsers($attributeId)
    {
        $sql = 'SELECT ut.user_id, ut.partner_id FROM user_tournaments ut
                LEFT JOIN user_attributes ua ON ua.user_tournament_id = ut.id
                WHERE ua.id = :aid';
        $data = MySQL::get()->fetchOne($sql, ['aid' => $attributeId]);
        return $data;
    }

    public function getAllAttachmentZip($attributeId)
    {
        $attachDirectory = realpath(__DIR__ . '/../../../uploads/' . $attributeId . '/');
        if (!file_exists($attachDirectory)) return false;

        $tmpFile = tempnam('/tmp', $attributeId);

        $za = new ZipArchive();
        $za->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($attachDirectory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file)
        {
            if (!$file->isDir())
            {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($attachDirectory) + 1);
                $za->addFile($filePath, $relativePath);
            }
        }

        $za->close();

        $archiveContent = file_get_contents($tmpFile);
        $size = filesize($tmpFile);
        unlink($tmpFile);

        return ['data' => $archiveContent, 'size' => $size];
    }

    public function getUserAttachment($userId, $userAttributeId)
    {
        $tournamentAttribute = $this->getTournamentAttributeUsers($userAttributeId);
        $sql = 'SELECT `value` FROM user_attributes WHERE user_id = :uid AND id = :aid';
        if (!$tournamentAttribute)
        {
            $path = MySQL::get()->fetchColumn($sql, [
                'uid' => $userId,
                'aid' => $userAttributeId
            ]);

            return $path;
        }
        else
        {
            if ($userId == $tournamentAttribute['partner_id'])
            {
                $path = MySQL::get()->fetchColumn($sql, [
                    'uid' => $tournamentAttribute['user_id'],
                    'aid' => $userAttributeId
                ]);
            }
            else
            {
                $path = MySQL::get()->fetchColumn($sql, [
                    'uid' => $userId,
                    'aid' => $userAttributeId
                ]);
            }
        }

        return $path;
    }
}