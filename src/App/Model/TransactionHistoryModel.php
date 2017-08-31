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
    public function getBalance($userId)
    {
        $sql = 'SELECT SUM(amount) FROM transaction_history WHERE user_id = :uid AND status = 1';
        $balance = MySQL::get()->fetchColumn($sql, ['uid' => $userId]);
        if (!$balance) $balance = 0;
        return floatval($balance);
    }

    public function getHistory($userId)
    {
        $sql = 'SELECT * FROM transaction_history WHERE user_id = :uid AND status = 1 ORDER BY id DESC';
        $data = MySQL::get()->fetchAll($sql, ['uid' => $userId]);
        return $data;
    }

    public function deleteTransaction($transactionId)
    {
        $sql = 'UPDATE transaction_history SET status = 0 WHERE id = :id';
        MySQL::get()->exec($sql, ['id' => $transactionId]);
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