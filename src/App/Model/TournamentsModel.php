<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\EventStatusType;
use App\Type\UserType;
use App\Util\DateUtil;

class TournamentsModel extends BaseModel
{

    public function getAll()
    {
        $sql = 'SELECT * FROM tournaments';
        $data = MySQL::get()->fetchAll($sql);
        foreach($data as &$row)
        {
            $row['reg_started'] = DateUtil::isPassed($row['event_start']);
            $row['reg_ended'] = DateUtil::isPassed($row['entry_deadline']);
            $row['drop_ended'] = DateUtil::isPassed($row['drop_deadline']);
        }
        return $data;
    }

    public function delete($tournamentId, $deleteAfterStart = false)
    {
        if ($deleteAfterStart)
        {
            // refund all members who registered on this tournament
            $sql = 'SELECT ut.*, e.cost, e.name as event_name, t.name as tournament_name
                    FROM user_tournaments ut
                    INNER JOIN events e ON e.id = ut.event_id
                    INNER JOIN tournaments t ON t.id = e.tournament_id
                    WHERE ut.status IN (0, 1, 3)';

            $applications = MySQL::get()->fetchAll($sql);
            $utIds = [];
            foreach($applications as $application)
            {
                // only user
                Model::get('transaction_history')->createTransaction(
                    $application['user_id'],
                    ($application['cost']),
                    'Refund for tournament "'.$application['tournament_name'].'", event: "'. $application['event_name'] .'" because administrator cancelled tournament'
                );

                if ($application['partner_id'] !== null && $application['status'] != EventStatusType::WAITING_PARTNER_RESPONSE)
                {
                    // only user
                    Model::get('transaction_history')->createTransaction(
                        $application['partner_id'],
                        ($application['cost']),
                        'Refund for tournament "'.$application['tournament_name'].'", event: "'. $application['event_name'] .'" because administrator cancelled tournament'
                    );
                }

                $utIds[] = $application['id'];
            }

            if (count($utIds) > 0)
            {
                // refunds done, go to delete user_tournaments
                $sql = 'DELETE FROM user_tournaments WHERE id IN ('. implode(',', $utIds) .')';
                MySQL::get()->exec($sql);

                foreach($utIds as $id)
                {
                    Model::get('attribute')->deleteAttributesByUserTournamentId($id);
                }
            }
        }

        $sql = 'DELETE FROM attributes WHERE tournament_id = :id';
        MySQL::get()->exec($sql, ['id' => $tournamentId]);

        $sql = 'DELETE FROM events WHERE tournament_id = :id';
        MySQL::get()->exec($sql, ['id' => $tournamentId]);

        $sql = 'DELETE FROM tournaments WHERE id = :id';
        MySQL::get()->exec($sql, ['id' => $tournamentId]);
    }

    public function create($name, $startDate, $deadlineDate, $dropDeadlineDate, $approveMethod, $events, $description = null, $pStartDate = null, $pEndDate = null)
    {
        $sql = 'INSERT INTO tournaments
                (`name`, `event_start`, `entry_deadline`, `drop_deadline`, `approve_method`, `description`, `date_start`, `date_end`)
                VALUES (:n, :es, :ed, :dd, :am, :d, :psd, :ped)';

        $tournamentId = MySQL::get()->exec($sql, [
            'n' => trim($name),
            'es' => $this->_convertDateToTimestamp($startDate),
            'ed' => $this->_convertDateToTimestamp($deadlineDate),
            'dd' => $this->_convertDateToTimestamp($dropDeadlineDate),
            'am' => $approveMethod,
            'd' => $description,
            'psd' => $pStartDate,
            'ped' => $pEndDate
        ], true);

        foreach($events as $event)
        {
            $this->createEvent($tournamentId, $event['dt_name'], $event['dt_type'], $event['dt_cost'], $event['dt_drop_cost']);
        }

        return $tournamentId;
    }

    public function setPartnerDecision($userTournamentId, $eventInfo, $status)
    {
        if ($status == 1)
        {
            // check for partner not joined with other
            $sql = 'SELECT count(*) FROM user_tournaments WHERE event_id = :eid AND partner_id = :pid AND status IN (0, 1)';
            $eventPartnerCount = MySQL::get()->fetchColumn($sql, [
                'eid' => $eventInfo['event_id'],
                'pid' => $eventInfo['partner_id']
            ]);

            if ($eventPartnerCount == 1) return false;

            // accepted
            $sql = 'UPDATE user_tournaments SET status = :s WHERE id = :id';
            $newStatus = ($eventInfo['approve_method'] == 0) ? EventStatusType::APPROVED : EventStatusType::WAITING_FOR_APPROVE;
            MySQL::get()->exec($sql, [
                's' => $newStatus,
                'id' => $userTournamentId
            ]);

            Model::get('transaction_history')->createTransaction(
                $eventInfo['partner_id'],
                -($eventInfo['cost']),
                'Joined the tournament "'. $eventInfo['tournament_name'] .'", event: "'. $eventInfo['event_name'] .'"'
            );
        }
        else
        {
            // declined
            $sql = 'UPDATE user_tournaments SET status = :s WHERE id = :id';
            MySQL::get()->exec($sql, [
                's' => EventStatusType::DECLINED_BY_PARTNER,
                'id' => $userTournamentId
            ]);

            Model::get('transaction_history')->createTransaction(
                $eventInfo['user_id'],
                ($eventInfo['cost']),
                'Refund for tournament "'.$eventInfo['tournament_name'].'", event: "'. $eventInfo['event_name'] .'" because partner declined your request'
            );
        }
        return true;
    }

    public function setDecision($userTournamentId, $status)
    {

        $sql = 'UPDATE user_tournaments SET status = :s WHERE id = :id';
        $status = ($status == 1) ? EventStatusType::APPROVED : EventStatusType::DECLINED;
        MySQL::get()->exec($sql, ['s' => $status, 'id' => $userTournamentId]);

        if ($status == EventStatusType::DECLINED)
        {
            // refund money
            $sql = 'SELECT user_id, partner_id, e.name as event_name, e.cost, t.name as tournament_name
                    FROM user_tournaments ut
                    INNER JOIN events e ON e.id = ut.event_id
                    INNER JOIN tournaments t ON t.id = e.tournament_id
                    WHERE ut.id = :id';
            $data = MySQL::get()->fetchOne($sql, ['id' => $userTournamentId]);
            $message = 'Refund for tournament "'.$data['tournament_name'].'", debate: "'. $data['event_name'] .'" because officer declined your application';
            Model::get('transaction_history')->createTransaction($data['user_id'], $data['cost'], $message);
            if ($data['partner_id'] !== null)
            {
                Model::get('transaction_history')->createTransaction($data['partner_id'], $data['cost'], $message);
            }
        }
    }

    public function getMembersList($tournamentId, $eventStatus)
    {
        $sql = 'SELECT
                  ut.id,
                  own.id as own_id,
                  CONCAT(own.first_name, \' \', own.last_name) as own_name,
                  own.email as own_email,
                  par.id as par_id,
                  CONCAT(par.first_name, \' \', par.last_name) as par_name,
                  par.email as par_email,
                  e.name
                FROM user_tournaments ut
                INNER JOIN users own ON own.id = ut.user_id
                LEFT JOIN users par ON par.id = ut.partner_id
                INNER JOIN events e ON e.id = ut.event_id
                WHERE ut.status = :s AND e.tournament_id = :tid';
        $data = MySQL::get()->fetchAll($sql, ['tid' => $tournamentId, 's' => $eventStatus]);
        return $data;
    }

    public function update($id, $name, $startDate, $deadlineDate, $dropDeadlineDate, $approveMethod, $description = null, $pStartDate = null, $pEndDate = null)
    {
        $sql = 'UPDATE tournaments SET
                `name` = :n,
                `event_start` = :es,
                `entry_deadline` = :ed,
                `drop_deadline` = :dd,
                `approve_method` = :am,
                `description` = :d,
                `date_start` = :psd,
                `date_end` = :ped
                WHERE id = :id';
        MySQL::get()->exec($sql, [
            'n' => $name,
            'es' => $this->_convertDateToTimestamp($startDate),
            'ed' => $this->_convertDateToTimestamp($deadlineDate),
            'dd' => $this->_convertDateToTimestamp($dropDeadlineDate),
            'am' => $approveMethod,
            'd' => $description,
            'psd' => str_replace('/', '-', $pStartDate),
            'ped' => str_replace('/', '-', $pEndDate),
            'id' => $id
        ]);
    }

    public function drop(User $user, $eventInfo)
    {
        $fee = DateUtil::isPassed($eventInfo['drop_deadline']);
        // wait approve, approve, partner response

        // drop tournament set status
        $sql = 'UPDATE user_tournaments SET status = :s WHERE id = :id';
        MySQL::get()->exec($sql, ['s' => EventStatusType::DROPPED, 'id' => $eventInfo['user_event_id']]);

        if (!$fee)
        {
            Model::get('transaction_history')->createTransaction(
                $user->getId(),
                $eventInfo['cost'],
                'Refund for tournament "'.$eventInfo['tournament_name'].'", event: "'. $eventInfo['event_name'] .'" because you drop event'
            );

            if ($user->getId() == $eventInfo['user_id'])
            {
                if ($eventInfo['partner_id'] !== null && $eventInfo['event_status'] != EventStatusType::WAITING_PARTNER_RESPONSE)
                {
                    Model::get('transaction_history')->createTransaction(
                        $eventInfo['partner_id'],
                        $eventInfo['cost'],
                        'Refund for tournament "'.$eventInfo['tournament_name'].'", event: "'. $eventInfo['event_name'] .'" because your partner drop event'
                    );
                }
            }
            else
            {
                // dropped by partner
                Model::get('transaction_history')->createTransaction(
                    $eventInfo['user_id'],
                    $eventInfo['cost'],
                    'Refund for tournament "'.$eventInfo['tournament_name'].'", event: "'. $eventInfo['event_name'] .'" because your partner drop event'
                );
            }
        }
        else
        {
            // not refund and get fee from user who drop
            Model::get('transaction_history')->createTransaction(
                $user->getId(),
                -($eventInfo['drop_fee_cost']),
                'Drop fee "'.$eventInfo['tournament_name'].'", event: "'. $eventInfo['event_name'] .'" because you drop tournament after drop deadline'
            );
        }
    }

    public function getUserTournaments($userId)
    {
        $sql = 'SELECT
                  ut.id,
                  t.name as tournament_name,
                  t.date_start,
                  t.date_end,
                  e.name as event_name,
                  own_user.id as owner_id,
                  own_user.username as owner_name,
                  partner_user.id as partner_id,
                  partner_user.username as partner_name,
                  t.entry_deadline,
                  t.drop_deadline,
                  ut.status
                FROM user_tournaments ut
                INNER JOIN events e ON e.id = ut.event_id
                INNER JOIN tournaments t ON t.id = e.tournament_id
                INNER JOIN users own_user ON own_user.id = ut.user_id
                LEFT JOIN users partner_user ON partner_user.id = ut.partner_id
                WHERE ut.user_id = :uid OR ut.partner_id = :uid
                ORDER BY ut.id DESC';
        $data = MySQL::get()->fetchAll($sql, ['uid' => $userId]);
        return $data;
    }

    public function getEvent($eventId)
    {
        $sql = 'SELECT * FROM events WHERE id = :id';
        $data = MySQL::get()->fetchOne($sql, ['id' => $eventId]);
        return $data;
    }

    public function getUserEventInfo($userEventId)
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
        $data = MySQL::get()->fetchOne($sql, ['id' => $userEventId]);
        return $data;
    }

    public function join($data, $tournament, $event, User $user, $partnerUser = null)
    {
        // create user-tournament row
        $sql = 'INSERT INTO user_tournaments (user_id, event_id, partner_id, status, judge_name, judge_email)
                VALUES (:uid, :eid, :pid, :s, :jn, :je)';
        $partnerId = ($partnerUser === null) ? null : $partnerUser['id'];

        if ($partnerId !== null)
        {
            $status = EventStatusType::WAITING_PARTNER_RESPONSE;
            // CREATE INVITE REQUEST HERE (EMAIL)
        }
        else
        {
            $status = ($tournament['approve_method'] == 0) ? EventStatusType::APPROVED : EventStatusType::WAITING_FOR_APPROVE;
        }

        $utid = MySQL::get()->exec($sql, [
            'uid' => $user->getId(),
            'eid' => $event['id'],
            'pid' => $partnerId,
            's' => $status,
            'jn' => isset($data['judge_name']) ? $data['judge_name'] : null,
            'je' => isset($data['judge_email']) ? $data['judge_email'] : null
        ], true);

        // update request partner timestamp (need for cron-task decline for 72 hours)
        if ($partnerId !== null)
        {
            MySQL::get()->exec('UPDATE user_tournaments SET partner_request_time = NOW() WHERE id = :id', [
                'id' => $utid
            ]);
        }

        // need for attachment create
        $userData = [
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'id' => $user->getId()
        ];

        // go create user_attributes
        $attributes = Model::get('attribute')->getAll(AttributeGroupType::TOURNAMENT, $tournament['id']);
        $attrSQL = 'INSERT INTO user_attributes (user_id, attribute_id, `value`, user_tournament_id) VALUES (:uid, :aid, :v, :utid)';
        foreach($attributes as $attribute)
        {
            if (!isset($data['attr_' . $attribute['id']]))
            {
                $data['attr_' . $attribute['id']] = null;
            }

            // multiple values insert
            if (is_array($data['attr_' . $attribute['id']]))
            {
                foreach($data['attr_' . $attribute['id']] as $dataItem)
                {
                    MySQL::get()->exec($attrSQL, [
                        'uid' => $user->getId(),
                        'aid' => $attribute['id'],
                        'v' => $dataItem,
                        'utid' => $utid
                    ]);
                }
            }
            else
            {
                if ($attribute['type'] == AttributeType::ATTACHMENT)
                {
                    $uaId = MySQL::get()->exec($attrSQL, [
                        'uid' => $user->getId(),
                        'aid' => $attribute['id'],
                        'v' => null,
                        'utid' => $utid
                    ], true);

                    $attachPath = Model::get('attachment')->createAttachment($uaId, $attribute['id'], $userData, $data['attr_' . $attribute['id']]);
                    MySQL::get()->exec('UPDATE user_attributes SET `value` = :v WHERE id = :id', [
                        'v' => $attachPath,
                        'id' => $uaId
                    ]);
                }
                else
                {
                    MySQL::get()->exec($attrSQL, [
                        'uid' => $user->getId(),
                        'aid' => $attribute['id'],
                        'v' => $data['attr_' . $attribute['id']],
                        'utid' => $utid
                    ]);
                }
            }
        }

        // get cash
        Model::get('transaction_history')->createTransaction(
            $user->getId(),
            -($event['cost']),
            'Joined the tournament "'. $tournament['name'] .'", debate: "'. $event['name'] .'"'
        );
    }

    public function createEvent($tournamentId, $name, $type, $cost, $dropFeeCost)
    {
        $sql = 'INSERT INTO events (tournament_id, `name`, `type`, `cost`, `drop_fee_cost`)
                VALUES (:tId, :n, :t, :c, :dfc)';
        MySQL::get()->exec($sql, [
            'tId' => $tournamentId,
            'n' => trim($name),
            't' => $type,
            'c' => $cost,
            'dfc' => $dropFeeCost
        ]);
    }

    public function updateEvent($id, $name, $type, $cost, $dropFeeCost)
    {
        $sql = 'UPDATE events SET `name` = :n, `type` = :t, `cost` = :c, `drop_fee_cost` = :dfc WHERE id = :id';
        MySQL::get()->exec($sql, [
            'n' => $name,
            't' => $type,
            'c' => $cost,
            'dfc' => $dropFeeCost,
            'id' => $id
        ]);
    }

    public function deleteEvent($id)
    {
        $sql = 'DELETE FROM events WHERE id = :id';
        MySQL::get()->exec($sql, ['id' => $id]);
    }

    public function isJoined($userId, $eventId)
    {
        $sql = 'SELECT * FROM user_tournaments WHERE event_id = :eid AND (user_id = :uid OR partner_id = :uid) AND status NOT IN (2, 4, 5)';
        $data = MySQL::get()->fetchOne($sql, ['eid' => $eventId, 'uid' => $userId]);
        return $data !== false;
    }

    public function getById($id)
    {
        $tournament = MySQL::get()->fetchOne('SELECT * FROM tournaments WHERE id = :id', ['id' => $id]);
        if (!$tournament) return false;

        $events = MySQL::get()->fetchAll('SELECT * FROM events WHERE tournament_id = :id', ['id' => $tournament['id']]);
        return ['tournament' => $tournament, 'events' => $events];
    }

    private function _convertDateToTimestamp($date)
    {
        // $date - yyyy/mm/dd
        // needed date yyyy-mm-dd hh:ii:ss
        return implode('-', (explode('/', $date))) . ' 00:00:00';
    }
}