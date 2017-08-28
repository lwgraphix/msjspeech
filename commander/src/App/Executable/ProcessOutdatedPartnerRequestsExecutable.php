<?php

namespace App\Executable;

use App\Connector\MySQL;
use App\Connector\Redis;
use App\Util\EventStatusType;
use App\Util\RigStatusType;
use App\Util\SystemSettings;
use App\Util\TelegramBot;

class ProcessOutdatedPartnerRequestsExecutable extends BaseExecutable
{

    private function getOutdatedRequests()
    {
        $sql = 'SELECT *, time_to_sec(timediff(NOW(), partner_request_time)) / 3600 as diff
                FROM user_tournaments
                WHERE status = 3';
        $data = MySQL::get()->fetchAll($sql);
        return $data;
    }

    private function createTransaction($userId, $amount, $description)
    {
        $sql = 'INSERT INTO transaction_history (user_id, amount, description) VALUES (:uid, :a, :d)';
        MySQL::get()->exec($sql, [
            'uid' => $userId,
            'a' => $amount,
            'd' => $description
        ]);
    }

    private function decline($userTournamentId, $eventInfo)
    {
        // declined
        $sql = 'UPDATE user_tournaments SET status = :s WHERE id = :id';
        MySQL::get()->exec($sql, [
            's' => EventStatusType::DECLINED_BY_PARTNER,
            'id' => $userTournamentId
        ]);

        // TODO: send email?
        $this->createTransaction(
            $eventInfo['user_id'],
            ($eventInfo['cost']),
            'Refund for tournament "'.$eventInfo['tournament_name'].'", event: "'. $eventInfo['event_name'] .'" because partner request is timed out'
        );
    }

    public function getUserEventInfo($userTournamentId)
    {
        $sql = 'SELECT
                  e.id as event_id,
                  ut.id as user_event_id,
                  ut.status as event_status,
                  e.cost,
                  t.event_start,
                  e.drop_fee_cost,
                  t.entry_deadline,
                  t.drop_deadline,
                  t.approve_method,
                  e.name as event_name,
                  t.name as tournament_name,
                  t.description as tournament_description,
                  ut.user_id,
                  ut.partner_id,
                  own_u.username as owner_name,
                  par_u.username as partner_name,
                  e.tournament_id
                FROM user_tournaments ut
                INNER JOIN events e ON ut.event_id = e.id
                INNER JOIN tournaments t ON t.id = e.tournament_id
                INNER JOIN users own_u ON own_u.id = ut.user_id
                LEFT JOIN users par_u ON par_u.id = ut.partner_id
                WHERE ut.id = :id';
        $data = MySQL::get()->fetchOne($sql, ['id' => $userTournamentId]);
        return $data;
    }

    public function run()
    {
        $requests = $this->getOutdatedRequests();
        $timeoutHours = SystemSettings::getInstance()->get('auto_decline_timeout');
        foreach($requests as $request)
        {
            if (floatval($request['diff']) >= floatval($timeoutHours))
            {
                $eventInfo = $this->getUserEventInfo($request['id']);
                $this->decline($request['id'], $eventInfo);
                $this->log('Declined #' . $request['id']);
            }
        }
        $this->log('Done');
    }
}
