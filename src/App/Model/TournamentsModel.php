<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Email;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\EmailType;
use App\Type\EventStatusType;
use App\Type\TournamentType;
use App\Type\TransactionType;
use App\Type\UserType;
use App\Util\DateUtil;

class TournamentsModel extends BaseModel
{

    /**
     * @var TransactionHistoryModel
     */
    private $thm;

    public function __construct()
    {
        $this->thm = Model::get('transaction_history');
    }

    public function getAll()
    {
        $sql = 'SELECT * FROM tournaments WHERE status != :s';
        $data = MySQL::get()->fetchAll($sql, [
            's' => TournamentType::CANCELLED
        ]);

        foreach($data as &$row)
        {
            $row['reg_started'] = DateUtil::isPassed($row['event_start']);
            $row['reg_ended'] = DateUtil::isPassed($row['entry_deadline']);
            $row['drop_ended'] = DateUtil::isPassed($row['drop_deadline']);
        }
        return $data;
    }

    public function isUserAllowedToJoin($tournamentId, $userId, $private = 0)
    {
        if ($private == 0) return true; // public tournament
        $available = $this->getPrivateTournamentsForUser($userId);
        $ids = [];
        foreach($available as $row)
        {
            $ids[] = $row['id'];
        }

        return in_array($tournamentId, $ids);
    }

    public function getUserAvailableTournaments($userId)
    {
        $sql = 'SELECT * FROM tournaments WHERE status != :s AND NOW() > event_start AND private = 0';
        $data = MySQL::get()->fetchAll($sql, ['s' => TournamentType::CANCELLED]);

        // get private tournaments
        $private = $this->getPrivateTournamentsForUser($userId);
        foreach($private as $privateTournament)
        {
            $data[] = $privateTournament;
        }

        foreach($data as &$row)
        {
            $row['reg_started'] = DateUtil::isPassed($row['event_start']);
            $row['reg_ended'] = DateUtil::isPassed($row['entry_deadline']);
            $row['drop_ended'] = DateUtil::isPassed($row['drop_deadline']);
        }

        return $data;
    }

    public function getPrivateTournamentsForUser($userId)
    {
        $sql = 'SELECT t.*
                FROM tournaments t
                LEFT JOIN user_groups ug ON ug.user_id = :uid
                LEFT JOIN tournament_groups tg ON tg.tournament_id = t.id AND tg.group_id = ug.group_id
                WHERE tg.id IS NOT NULL AND t.private = 1
                GROUP BY t.id';
        $data = MySQL::get()->fetchAll($sql, ['uid' => $userId]);
        return $data;
    }

    public function delete($tournamentId, $deleteAfterStart = false)
    {
        if ($deleteAfterStart)
        {
            // refund all members who registered on this tournament
            $sql = 'SELECT ut.*, e.id as event_id, e.cost, e.name as event_name, t.name as tournament_name
                    FROM user_tournaments ut
                    INNER JOIN events e ON e.id = ut.event_id
                    INNER JOIN tournaments t ON t.id = e.tournament_id
                    WHERE ut.status IN (0, 1, 3) AND t.id = :tid';

            $applications = MySQL::get()->fetchAll($sql, ['tid' => $tournamentId]);
            $utIds = [];
            foreach($applications as $application)
            {

                $this->thm->createTransaction(
                    $application['user_id'],
                    ($application['cost']),
                    TransactionType::TOURNAMENT_REFUND,
                    0,
                    'Refund for "'.$application['tournament_name'].'" in "'. $application['event_name'] .'" because the tournament was cancelled',
                    null,
                    null,
                    null,
                    null,
                    $application['event_id']
                );

                if ($application['partner_id'] !== null && $application['status'] != EventStatusType::WAITING_PARTNER_RESPONSE)
                {
                    // only user
                    $this->thm->createTransaction(
                        $application['partner_id'],
                        ($application['cost']),
                        TransactionType::TOURNAMENT_REFUND,
                        0,
                        'Refund for "'.$application['tournament_name'].'" in "'. $application['event_name'] .'" because the tournament was cancelled',
                        null,
                        null,
                        null,
                        null,
                        $application['event_id']
                    );
                }

                $utIds[] = $application['id'];
            }

            if (count($utIds) > 0)
            {

                // refunds done, go to delete user_tournaments
                $sql = 'UPDATE user_tournaments SET status = '. EventStatusType::CANCELLED .' WHERE id IN ('. implode(',', $utIds) .')';
                MySQL::get()->exec($sql);
            }

            $sql = 'UPDATE tournaments SET status = :s WHERE id = :id';
            MySQL::get()->exec($sql, [
                's' => TournamentType::CANCELLED,
                'id' => $tournamentId
            ]);
        }
        else
        {
            $sql = 'DELETE FROM attributes WHERE tournament_id = :id';
            MySQL::get()->exec($sql, ['id' => $tournamentId]);

            $sql = 'DELETE FROM events WHERE tournament_id = :id';
            MySQL::get()->exec($sql, ['id' => $tournamentId]);

            $sql = 'DELETE FROM tournament_groups WHERE tournament_id = :id';
            MySQL::get()->exec($sql, ['id' => $tournamentId]);

            $sql = 'DELETE FROM tournaments WHERE id = :id';
            MySQL::get()->exec($sql, ['id' => $tournamentId]);
        }
    }

    public function create($name, $startDate, $deadlineDate, $dropDeadlineDate, $approveMethod, $events, $description = null, $pStartDate = null, $pEndDate = null, $private, $groups = [], $doubleEntry)
    {
        $sql = 'INSERT INTO tournaments
                (`name`, `event_start`, `entry_deadline`, `drop_deadline`, `approve_method`, `description`, `date_start`, `date_end`, `private`, `double_entry`)
                VALUES (:n, :es, :ed, :dd, :am, :d, :psd, :ped, :p, :de)';

        $tournamentId = MySQL::get()->exec($sql, [
            'n' => trim($name),
            'es' => $startDate,
            'ed' => $deadlineDate,
            'dd' => $dropDeadlineDate,
            'am' => $approveMethod,
            'd' => $description,
            'psd' => $pStartDate,
            'ped' => $pEndDate,
            'p' => $private,
            'de' => $doubleEntry
        ], true);

        foreach($events as $event)
        {
            $this->createEvent($tournamentId, $event['dt_name'], $event['dt_type'], $event['dt_cost'], $event['dt_drop_cost']);
        }

        if ($private == 1 && count($groups) > 0)
        {
            foreach($groups as $groupId)
            {
                $this->createGroupLink($tournamentId, $groupId);
            }
        }

        return $tournamentId;
    }

    public function createGroupLink($tournamentId, $groupId)
    {
        $sql = 'INSERT INTO tournament_groups (tournament_id, group_id) VALUES (:tId, :gId)';
        MySQL::get()->exec($sql, [
            'tId' => $tournamentId,
            'gId' => $groupId
        ]);
    }

    public function deleteGroupLink($tournamentId, $groupId)
    {
        $sql = 'DELETE FROM tournament_groups WHERE tournament_id = :tid AND group_id = :gid';
        MySQL::get()->exec($sql, ['tid' => $tournamentId, 'gid' => $groupId]);
    }

    public function setPartnerDecision($userTournamentId, $eventInfo, $status)
    {
        $decisioner = User::loadById($eventInfo['partner_id']);

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
            $sql = 'UPDATE user_tournaments SET status = :s, join_timestamp = NOW() WHERE id = :id';
            $newStatus = ($eventInfo['approve_method'] == 0) ? EventStatusType::APPROVED : EventStatusType::WAITING_FOR_APPROVE;
            MySQL::get()->exec($sql, [
                's' => $newStatus,
                'id' => $userTournamentId
            ]);


            // send email accept
            Email::getInstance()->createMessage(EmailType::PARTNER_REQUEST_ACCEPT, [
                'partner_name' => $decisioner->getFullName(),
                'tournament_name' => $eventInfo['tournament_name'],
                'event_name' => $eventInfo['event_name']
            ], User::loadById($eventInfo['user_id']));

            $oldBalance = $decisioner->getBalance();
            $this->thm->createTransaction(
                $eventInfo['partner_id'],
                -($eventInfo['cost']),
                TransactionType::TOURNAMENT_JOIN,
                0,
                'Registered for "'. $eventInfo['tournament_name'] .'" in "'. $eventInfo['event_name'] .'"',
                null,
                null,
                null,
                null,
                $eventInfo['event_id']
            );

            $partnerUserObject = User::loadById($eventInfo['user_id']);
            $formPartner = $partnerUserObject->getFullName() . ' ('. $partnerUserObject->getUsername() .')';
            $userEventAttributes = Model::get('attribute')->getUserAttributes($partnerUserObject->getId(), AttributeGroupType::TOURNAMENT, $eventInfo['user_event_id']);

            $form = 'Partner: ' . $formPartner . PHP_EOL;
            $form .= 'Judge name: ' . (!empty($eventInfo['judge_name']) ? $eventInfo['judge_name'] : 'Not specified') . PHP_EOL;
            $form .= 'Judge email: ' . (!empty($eventInfo['judge_email']) ? $eventInfo['judge_email'] : 'Not specified') . PHP_EOL;

            foreach($userEventAttributes as $attr)
            {
                $form .= $attr['label'] . ': ';

                if ($attr['type'] == AttributeType::TEXT || $attr['type'] == AttributeType::DROPDOWN)
                {
                    $value = $attr['value'];
                }
                elseif ($attr['type'] == AttributeType::CHECKBOX)
                {
                    $value = implode(',', $attr['value']);
                }
                elseif ($attr['type'] == AttributeType::ATTACHMENT)
                {
                    $value = Security::getHost() . 'attachment/' . $attr['user_attr_id'];
                }

                if (empty($value)) $value = 'Not specified';

                $form .= $value . PHP_EOL;
            }

            //  [form]
            $emailData = [
                'tournament_name' => $eventInfo['tournament_name'],
                'event_name' => $eventInfo['event_name'],
                'event_cost' => $eventInfo['cost'],
                'drop_deadline' => DateUtil::convertToUSATime($eventInfo['drop_deadline']),
                'link_to_history' => Security::getHost() . 'tournament/list',
                'link_account_balance' => Security::getHost() . 'user/balance',
                'old_balance' => $oldBalance,
                'new_balance' => $decisioner->getBalance(),
                'form' => $form
            ];

            Email::getInstance()->createMessage(EmailType::TOURNAMENT_JOIN, $emailData, $decisioner);

        }
        else
        {
            // declined
            $sql = 'UPDATE user_tournaments SET status = :s WHERE id = :id';
            MySQL::get()->exec($sql, [
                's' => EventStatusType::DECLINED_BY_PARTNER,
                'id' => $userTournamentId
            ]);

            $ownerUser = User::loadById($eventInfo['user_id']);
            $oldBalance = $ownerUser->getBalance();

            $this->thm->createTransaction(
                $eventInfo['user_id'],
                ($eventInfo['cost']),
                TransactionType::TOURNAMENT_REFUND,
                0,
                'Refund for "'.$eventInfo['tournament_name'].'" in "'. $eventInfo['event_name'] .'" because your partner declined your request',
                null,
                null,
                null,
                null,
                $eventInfo['event_id']
            );

            Email::getInstance()->createMessage(EmailType::PARTNER_REQUEST_DECLINE, [
                'partner_name' => $decisioner->getFullName(),
                'tournament_name' => $eventInfo['tournament_name'],
                'event_name' => $eventInfo['event_name'],
                'event_cost' => $eventInfo['cost'],
                'old_balance' => $oldBalance,
                'new_balance' => $ownerUser->getBalance(),
                'link_account_balance' => Security::getHost() . 'user/balance'
            ], $ownerUser);
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
            $sql = 'SELECT user_id, partner_id, e.id as event_id, e.name as event_name, e.cost, t.name as tournament_name
                    FROM user_tournaments ut
                    INNER JOIN events e ON e.id = ut.event_id
                    INNER JOIN tournaments t ON t.id = e.tournament_id
                    WHERE ut.id = :id';
            $data = MySQL::get()->fetchOne($sql, ['id' => $userTournamentId]);
            $message = 'Refund for "'.$data['tournament_name'].'" in "'. $data['event_name'] .'" because your application was rejected';

            $this->thm->createTransaction(
                $data['user_id'],
                $data['cost'],
                TransactionType::TOURNAMENT_REFUND,
                0,
                $message,
                null,
                null,
                null,
                null,
                $data['event_id']
            );

            $ownerUser = User::loadById($data['user_id']);
            Email::getInstance()->createMessage(EmailType::TOURNAMENT_REGISTRATION_REJECTED, [
                'tournament_name' => $data['tournament_name'],
                'event_name' => $data['event_name'],
                'event_cost' => $data['cost'],
                'link_account_balance' => Security::getHost() . 'user/balance'
            ], $ownerUser);

            if ($data['partner_id'] !== null)
            {
                $this->thm->createTransaction(
                    $data['partner_id'],
                    $data['cost'],
                    TransactionType::TOURNAMENT_REFUND,
                    0,
                    $message,
                    null,
                    null,
                    null,
                    null,
                    $data['event_id']
                );

                $partnerUser = User::loadById($data['partner_id']);
                Email::getInstance()->createMessage(EmailType::TOURNAMENT_REGISTRATION_REJECTED, [
                    'tournament_name' => $data['tournament_name'],
                    'event_name' => $data['event_name'],
                    'event_cost' => $data['cost'],
                    'link_account_balance' => Security::getHost() . 'user/balance'
                ], $partnerUser);
            }
        }
        else
        {
            $tournamentData = $this->getUserEventInfo($userTournamentId);
            $ownerUser = User::loadById($tournamentData['user_id']);
            // send emails
            Email::getInstance()->createMessage(EmailType::TOURNAMENT_REGISTRATION_APPROVED, [
                'tournament_name' => $tournamentData['tournament_name'],
                'event_name' => $tournamentData['event_name']
            ], $ownerUser);

            if (!empty($tournamentData['partner_id']))
            {
                $partnerUser = User::loadById($tournamentData['partner_id']);
                // send emails
                Email::getInstance()->createMessage(EmailType::TOURNAMENT_REGISTRATION_APPROVED, [
                    'tournament_name' => $tournamentData['tournament_name'],
                    'event_name' => $tournamentData['event_name']
                ], $partnerUser);
            }
        }
    }

    public function getMembersList($tournamentId, $eventId = null, $eventStatus = null)
    {
        $statusWhere = ($eventStatus === null) ? '' : 'AND ut.status = ' . $eventStatus;
        $eventWhere = ($eventId === null) ? '' : 'AND e.id = ' . $eventId;
        $sql = 'SELECT
                  ut.id,
                  ut.join_timestamp,
                  ut.judge_name,
                  ut.judge_email,
                  ut.status as event_status,
                  own.id as own_id,
                  CONCAT(own.first_name, \' \', own.last_name) as own_name,
                  own.email as own_email,
                  own.parent_email as own_parent_email,
                  own.role as own_role,
                  par.id as par_id,
                  CONCAT(par.first_name, \' \', par.last_name) as par_name,
                  par.email as par_email,
                  par.parent_email as par_parent_email,
                  par.role as par_role,
                  e.name
                FROM user_tournaments ut
                INNER JOIN users own ON own.id = ut.user_id
                LEFT JOIN users par ON par.id = ut.partner_id
                INNER JOIN events e ON e.id = ut.event_id '. $eventWhere .'
                WHERE e.tournament_id = :tid ' . $statusWhere;
        $data = MySQL::get()->fetchAll($sql, ['tid' => $tournamentId]);
        foreach($data as &$row)
        {
            $attributes = Model::get('attribute')->getUserAttributes($row['own_id'], AttributeGroupType::TOURNAMENT, $row['id']);
            $row['attrs'] = $attributes;
        }
        return $data;
    }

    public function getTournamentMembersList($tournamentId)
    {
        // get user ids in tournament
        $sql = 'SELECT ut.user_id, ut.partner_id, e.name
                FROM user_tournaments ut
                INNER JOIN events e ON e.id = ut.event_id
                WHERE e.tournament_id = :tid AND ut.status IN (0, 1)';
        $data = MySQL::get()->fetchAll($sql, ['tid' => $tournamentId]);
        $userData = [];
        foreach($data as $row)
        {
            $userData[$row['user_id']] = $row['name'];
            if (!empty($row['partner_id']))
            {
                $userData[$row['partner_id']] = $row['name'];
            }
        }
        $userIds = array_unique(array_keys($userData));
        $list = Model::get('user')->getByIds($userIds);
        foreach($list as &$row)
        {
            $row['event_name'] = $userData[$row['id']];
        }
        return $list;
    }

    public function update($id, $name, $startDate, $deadlineDate, $dropDeadlineDate, $approveMethod, $description = null, $pStartDate = null, $pEndDate = null, $private, $doubleEntry)
    {
        $sql = 'UPDATE tournaments SET
                `name` = :n,
                `event_start` = :es,
                `entry_deadline` = :ed,
                `drop_deadline` = :dd,
                `approve_method` = :am,
                `description` = :d,
                `date_start` = :psd,
                `date_end` = :ped,
                `private` = :p,
                `double_entry` = :de
                WHERE id = :id';
        MySQL::get()->exec($sql, [
            'n' => $name,
            'es' => $startDate,
            'ed' => $deadlineDate,
            'dd' => $dropDeadlineDate,
            'am' => $approveMethod,
            'd' => $description,
            'psd' => str_replace('/', '-', $pStartDate),
            'ped' => str_replace('/', '-', $pEndDate),
            'p' => $private,
            'de' => $doubleEntry,
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

        $ownerUser = User::loadById($eventInfo['user_id']);
        $partnerUser = User::loadById($eventInfo['partner_id']);

        if (!$fee)
        {
            if ($user->getId() == $eventInfo['user_id'])
            {
                // refund user
                $oldBalance = $ownerUser->getBalance();

                $this->thm->createTransaction(
                    $eventInfo['user_id'],
                    $eventInfo['cost'],
                    TransactionType::TOURNAMENT_REFUND,
                    0,
                    'Refund for "'.$eventInfo['tournament_name'].'" in "'. $eventInfo['event_name'] .'" because you dropped',
                    null,
                    null,
                    null,
                    null,
                    $eventInfo['event_id']
                );

                Email::getInstance()->createMessage(EmailType::TOURNAMENT_DROP_BEFORE_DEADLINE, [
                    'tournament_name' => $eventInfo['tournament_name'],
                    'event_name' => $eventInfo['event_name'],
                    'event_cost' => $eventInfo['cost'],
                    'old_balance' => $oldBalance,
                    'new_balance' => $ownerUser->getBalance(),
                    'link_account_balance' => Security::getHost() . 'user/balance'
                ], $ownerUser);

                if (!empty($eventInfo['partner_id']))
                {
                    if ($eventInfo['event_status'] == EventStatusType::WAITING_PARTNER_RESPONSE)
                    {
                        // send cancel request
                        Email::getInstance()->createMessage(EmailType::PARTNER_CANCELLED, [
                            'partner_name' => $ownerUser->getFullName(),
                            'tournament_name' => $eventInfo['tournament_name'],
                            'event_name' => $eventInfo['event_name']
                        ], $partnerUser);
                    }
                    else
                    {
                        $oldBalance = $partnerUser->getBalance();
                        // refund partner
                        $this->thm->createTransaction(
                            $eventInfo['partner_id'],
                            $eventInfo['cost'],
                            TransactionType::TOURNAMENT_REFUND,
                            0,
                            'Refund for "'.$eventInfo['tournament_name'].'" in "'. $eventInfo['event_name'] .'" because your partner dropped',
                            null,
                            null,
                            null,
                            null,
                            $eventInfo['event_id']
                        );

                        Email::getInstance()->createMessage(EmailType::TOURNAMENT_PARTNER_DROP_BEFORE_DEADLINE, [
                            'partner_name' => $ownerUser->getFullName(),
                            'tournament_name' => $eventInfo['tournament_name'],
                            'event_name' => $eventInfo['event_name'],
                            'event_cost' => $eventInfo['cost'],
                            'old_balance' => $oldBalance,
                            'new_balance' => $partnerUser->getBalance(),
                            'link_account_balance' => Security::getHost() . 'user/balance'
                        ], $partnerUser);
                    }
                }
            }
            else
            {
                // dropped by partner
                $oldBalance = $partnerUser->getBalance();
                $this->thm->createTransaction(
                    $eventInfo['partner_id'],
                    $eventInfo['cost'],
                    TransactionType::TOURNAMENT_REFUND,
                    0,
                    'Refund for "'.$eventInfo['tournament_name'].'" in "'. $eventInfo['event_name'] .'" because you dropped',
                    null,
                    null,
                    null,
                    null,
                    $eventInfo['event_id']
                );

                Email::getInstance()->createMessage(EmailType::TOURNAMENT_DROP_BEFORE_DEADLINE, [
                    'tournament_name' => $eventInfo['tournament_name'],
                    'event_name' => $eventInfo['event_name'],
                    'event_cost' => $eventInfo['cost'],
                    'old_balance' => $oldBalance,
                    'new_balance' => $partnerUser->getBalance(),
                    'link_account_balance' => Security::getHost() . 'user/balance'
                ], $partnerUser);

                $oldBalance = $ownerUser->getBalance();
                $this->thm->createTransaction(
                    $eventInfo['user_id'],
                    $eventInfo['cost'],
                    TransactionType::TOURNAMENT_REFUND,
                    0,
                    'Refund for "'.$eventInfo['tournament_name'].'" in "'. $eventInfo['event_name'] .'" because your partner dropped',
                    null,
                    null,
                    null,
                    null,
                    $eventInfo['event_id']
                );

                Email::getInstance()->createMessage(EmailType::TOURNAMENT_PARTNER_DROP_BEFORE_DEADLINE, [
                    'partner_name' => $partnerUser->getFullName(),
                    'tournament_name' => $eventInfo['tournament_name'],
                    'event_name' => $eventInfo['event_name'],
                    'event_cost' => $eventInfo['cost'],
                    'old_balance' => $oldBalance,
                    'new_balance' => $ownerUser->getBalance(),
                    'link_account_balance' => Security::getHost() . 'user/balance'
                ], $ownerUser);
            }
        }
        else
        {
            if ($user->getId() == $eventInfo['user_id'])
            {
                $oldBalance = $ownerUser->getBalance();

                $this->thm->createTransaction(
                    $ownerUser->getId(),
                    -($eventInfo['drop_fee_cost']),
                    TransactionType::TOURNAMENT_FEE,
                    0,
                    'Drop fine "'.$eventInfo['tournament_name'].'" in "'. $eventInfo['event_name'] .'" because you dropped after the deadline',
                    null,
                    null,
                    null,
                    null,
                    $eventInfo['event_id']
                );

                Email::getInstance()->createMessage(EmailType::TOURNAMENT_DROP_AFTER_DEADLINE, [
                    'tournament_name' => $eventInfo['tournament_name'],
                    'event_name' => $eventInfo['event_name'],
                    'event_cost' => $eventInfo['cost'],
                    'old_balance' => $oldBalance,
                    'new_balance' => $ownerUser->getBalance(),
                    'link_account_balance' => Security::getHost() . 'user/balance',
                    'drop_fee' => $eventInfo['drop_fee_cost']
                ], $ownerUser);

                if (!empty($eventInfo['partner_id']))
                {
                    // send message to partner
                    Email::getInstance()->createMessage(EmailType::TOURNAMENT_PARTNER_DROP_AFTER_DEADLINE, [
                        'partner_name' => $ownerUser->getFullName(),
                        'tournament_name' => $eventInfo['tournament_name'],
                        'event_name' => $eventInfo['event_name']
                    ], $partnerUser);
                }
            }
            else
            {
                $oldBalance = $partnerUser->getBalance();
                $this->thm->createTransaction(
                    $partnerUser->getId(),
                    -($eventInfo['drop_fee_cost']),
                    TransactionType::TOURNAMENT_FEE,
                    0,
                    'Drop fine "'.$eventInfo['tournament_name'].'" in "'. $eventInfo['event_name'] .'" because you dropped after the deadline',
                    null,
                    null,
                    null,
                    null,
                    $eventInfo['event_id']
                );

                Email::getInstance()->createMessage(EmailType::TOURNAMENT_DROP_AFTER_DEADLINE, [
                    'tournament_name' => $eventInfo['tournament_name'],
                    'event_name' => $eventInfo['event_name'],
                    'event_cost' => $eventInfo['cost'],
                    'old_balance' => $oldBalance,
                    'new_balance' => $partnerUser->getBalance(),
                    'link_account_balance' => Security::getHost() . 'user/balance',
                    'drop_fee' => $eventInfo['drop_fee_cost']
                ], $partnerUser);

                Email::getInstance()->createMessage(EmailType::TOURNAMENT_PARTNER_DROP_AFTER_DEADLINE, [
                    'partner_name' => $partnerUser->getFullName(),
                    'tournament_name' => $eventInfo['tournament_name'],
                    'event_name' => $eventInfo['event_name']
                ], $ownerUser);
            }

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
                WHERE (ut.user_id = :uid OR ut.partner_id = :uid) AND t.status != :s
                ORDER BY ut.id DESC';
        $data = MySQL::get()->fetchAll($sql, [
            'uid' => $userId,
            's' => TournamentType::CANCELLED
        ]);
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
                  t.status as tournament_status,
                  t.description as tournament_description,
                  ut.user_id,
                  ut.partner_id,
                  own_u.username as owner_name,
                  par_u.username as partner_name,
                  e.tournament_id,
                  t.double_entry,
                  ut.judge_name,
                  ut.judge_email
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
        $sql = 'INSERT INTO user_tournaments (user_id, event_id, partner_id, status, judge_name, judge_email, join_timestamp)
                VALUES (:uid, :eid, :pid, :s, :jn, :je, NOW())';
        $partnerId = ($partnerUser === null) ? null : $partnerUser['id'];

        if (isset($data['judge_name'], $data['judge_email']))
        {
            Email::getInstance()->createMessage(EmailType::TOURNAMENT_JUDGE, [
                'judge_name' => $data['judge_name'],
                'tournament_name' => $tournament['name'],
                'event_name' => $event['name'],
            ], $user, $data['judge_email']);
        }

        if ($partnerId !== null)
        {
            $status = EventStatusType::WAITING_PARTNER_RESPONSE;
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

            // send email
            Email::getInstance()->createMessage(EmailType::PARTNER_REQUEST, [
                'partner_name' => $user->getFullName() . ' ('. $user->getUsername() .')',
                'tournament_name' => $tournament['name'],
                'event_name' => $event['name'],
                'join_link' => Security::getHost() . 'tournament/view/' . $utid
            ], User::loadById($partnerId));
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

        // save for email data
        $userOldBalance = $user->getBalance();

        // get cash
        $this->thm->createTransaction(
            $user->getId(),
            -($event['cost']),
            TransactionType::TOURNAMENT_JOIN,
            0,
            'Registered for "'. $tournament['name'] .'" in "'. $event['name'] .'"',
            null,
            null,
            null,
            null,
            $event['id']
        );

        // email data generate
        if ($partnerId === null)
        {
            $formPartner = 'No';
        }
        else
        {
            $partnerUserObject = User::loadById($partnerId);
            $formPartner = $partnerUserObject->getFullName() . ' ('. $partnerUserObject->getUsername() .')';
        }

        $eventInfo = $this->getUserEventInfo($utid);
        $userEventAttributes = Model::get('attribute')->getUserAttributes($user->getId(), AttributeGroupType::TOURNAMENT, $utid);

        $form = 'Partner: ' . $formPartner . PHP_EOL;
        $form .= 'Judge name: ' . (!empty($eventInfo['judge_name']) ? $eventInfo['judge_name'] : 'Not specified') . PHP_EOL;
        $form .= 'Judge email: ' . (!empty($eventInfo['judge_email']) ? $eventInfo['judge_email'] : 'Not specified') . PHP_EOL;

        foreach($userEventAttributes as $attr)
        {
            $form .= $attr['label'] . ': ';

            if ($attr['type'] == AttributeType::TEXT || $attr['type'] == AttributeType::DROPDOWN)
            {
                $value = $attr['value'];
            }
            elseif ($attr['type'] == AttributeType::CHECKBOX)
            {
                $value = implode(',', $attr['value']);
            }
            elseif ($attr['type'] == AttributeType::ATTACHMENT)
            {
                $value = Security::getHost() . 'attachment/' . $attr['user_attr_id'];
            }

            if (empty($value)) $value = 'Not specified';

            $form .= $value . PHP_EOL;
        }

        $emailData = [
            'tournament_name' => $tournament['name'],
            'event_name' => $event['name'],
            'event_cost' => $event['cost'],
            'drop_deadline' => DateUtil::convertToUSATime($tournament['drop_deadline']),
            'link_to_history' => Security::getHost() . 'tournament/list',
            'link_account_balance' => Security::getHost() . 'user/balance',
            'old_balance' => $userOldBalance,
            'new_balance' => $user->getBalance(),
            'form' => $form
        ];

        Email::getInstance()->createMessage(EmailType::TOURNAMENT_JOIN, $emailData, $user);
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

    public function getUserEntryCount($userId, $tournamentId)
    {
        $sql = 'SELECT count(*)
                FROM user_tournaments ut
                INNER JOIN events e ON e.id = ut.event_id
                WHERE (ut.user_id = :uid OR ut.partner_id = :uid) AND e.tournament_id = :tid AND ut.status IN (0, 1, 3)';
        $data = MySQL::get()->fetchColumn($sql, ['uid' => $userId, 'tid' => $tournamentId]);
        return $data;
    }

    public function getPartnerEntryCount($userId, $tournamentId)
    {
        $sql = 'SELECT count(*)
                FROM user_tournaments ut
                INNER JOIN events e ON e.id = ut.event_id
                WHERE (ut.user_id = :uid OR ut.partner_id = :uid) AND e.tournament_id = :tid AND ut.status IN (0, 1)';
        $data = MySQL::get()->fetchColumn($sql, ['uid' => $userId, 'tid' => $tournamentId]);
        return $data;
    }

    public function getById($id)
    {
        $tournament = MySQL::get()->fetchOne('SELECT * FROM tournaments WHERE id = :id', ['id' => $id]);
        if (!$tournament) return false;

        $events = MySQL::get()->fetchAll('SELECT * FROM events WHERE tournament_id = :id', ['id' => $tournament['id']]);

        $groupsRaw = 'SELECT g.id, g.name FROM tournament_groups tg INNER JOIN groups g ON g.id = tg.group_id WHERE tg.tournament_id = :tid';
        $groupsRaw = MySQL::get()->fetchAll($groupsRaw, ['tid' => $id]);
        $groups = [];
        foreach($groupsRaw as $group)
        {
            $groups[$group['id']] = $group;
        }

        return ['tournament' => $tournament, 'events' => $events, 'groups' => $groups];
    }

    public function getNamesByTournament($tournamentId, $eventId = null)
    {
        $t = $this->getById($tournamentId);
        $names['tournament'] = $t['tournament']['name'];

        if ($eventId !== null)
        {
            foreach($t['events'] as $event)
            {
                if ($event['id'] == $eventId) $names['event'] = $event['name'];
            }
        }

        return $names;
    }
}