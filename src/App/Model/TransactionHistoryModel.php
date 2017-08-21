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

    private $balanceCache = null;

    public function getBalance($userId)
    {
        if ($this->balanceCache === null)
        {
            $sql = 'SELECT SUM(amount) FROM transaction_history WHERE user_id = :uid';
            $balance = MySQL::get()->fetchColumn($sql, ['uid' => $userId]);
            if (!$balance) $balance = 0;
            $this->balanceCache = floatval($balance);
        }

        return $this->balanceCache;
    }

    public function getHistory($userId)
    {
        $sql = 'SELECT * FROM transaction_history WHERE user_id = :uid ORDER BY id DESC';
        $data = MySQL::get()->fetchAll($sql, ['uid' => $userId]);
        return $data;
    }

    public function createTransaction($userId, $amount, $description)
    {
        $sql = 'INSERT INTO transaction_history (user_id, amount, description) VALUES (:uid, :a, :d)';
        MySQL::get()->exec($sql, [
            'user_id' => $userId,
            'amount' => $amount,
            'description' => $description
        ]);
    }
}