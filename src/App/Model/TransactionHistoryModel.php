<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\UserType;

class TransactionHistoryModel extends BaseModel
{

    private $balanceCache = [];

    public function getBalance($userId)
    {
        if (!isset($this->balanceCache[$userId]))
        {
            $sql = 'SELECT SUM(amount) FROM transaction_history WHERE user_id = :uid';
            $balance = MySQL::get()->fetchColumn($sql, ['uid' => $userId]);
            if (!$balance) $balance = 0;
            $this->balanceCache[$userId] = floatval($balance);
        }

        return $this->balanceCache[$userId];
    }

    public function getHistory($userId)
    {
        $sql = 'SELECT * FROM transaction_history WHERE user_id = :uid ORDER BY id DESC';
        $data = MySQL::get()->fetchAll($sql, ['uid' => $userId]);
        return $data;
    }

    public function createTransaction($userId, $amount, $type, $creatorId, $memo1, $memo2 = null, $memo3 = null, $memo4 = null, $memo5 = null, $eventId = null)
    {
        $sql = 'INSERT INTO transaction_history
                (user_id, amount, `type`, `creator_id`, `memo_1`, `memo_2`, `memo_3`, `memo_4`, `memo_5`, `event_id`)
                VALUES
                (:uid, :a, :t, :cid, :m1, :m2, :m3, :m4, :m5, :eid)';

        MySQL::get()->exec($sql, [
            'uid' => $userId,
            'a' => $amount,
            't' => $type,
            'cid' => $creatorId,
            'm1' => $memo1,
            'm2' => $memo2,
            'm3' => $memo3,
            'm4' => $memo4,
            'm5' => $memo5,
            'eid' => $eventId
        ]);
    }
}