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

    public function getById($transactionId)
    {
        $sql = 'SELECT * FROM transaction_history WHERE id = :id';
        $data = MySQL::get()->fetchOne($sql, ['id' => $transactionId]);
        return $data;
    }

    public function getHistory($userId)
    {
        $sql = 'SELECT * FROM transaction_history WHERE user_id = :uid AND status = 1 ORDER BY id DESC';
        $data = MySQL::get()->fetchAll($sql, ['uid' => $userId]);
        return $data;
    }

    public function getHistoryByType($type)
    {
        $where = ($type != -1) ? 'AND th.type = :t' : '';
        $selectData = [];
        $sql = 'SELECT
                  th.*,
                  e.name as event_name,
                  t.name as tournament_name,
                  user.id as user_id,
                  CONCAT(user.first_name, \' \', user.last_name) as user_fullname,
                  user.email as user_email,
                  creator.id as c_creator_id,
                  CONCAT(creator.first_name, \' \', creator.last_name) as creator_fullname,
                  creator.email as creator_email
                FROM transaction_history th
                LEFT JOIN events e ON e.id = th.event_id
                LEFT JOIN tournaments t ON t.id = e.tournament_id
                INNER JOIN users user ON user.id = th.user_id
                LEFT JOIN users creator ON creator.id = th.creator_id
                WHERE th.status = 1 '. $where .'
                ORDER BY th.id DESC';
        if ($type != -1) $selectData['t'] = $type;
        $data = MySQL::get()->fetchAll($sql, $selectData);
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