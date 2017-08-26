<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
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
            $row['is_passed'] = DateUtil::isPassed($row['event_start']);
        }
        return $data;
    }

    public function create($name, $startDate, $deadlineDate, $dropDeadlineDate, $approveMethod, $events)
    {
        $sql = 'INSERT INTO tournaments (`name`, `event_start`, `entry_deadline`, `drop_deadline`, `approve_method`)
                VALUES (:n, :es, :ed, :dd, :am)';

        $tournamentId = MySQL::get()->exec($sql, [
            'n' => trim($name),
            'es' => $this->_convertDateToTimestamp($startDate),
            'ed' => $this->_convertDateToTimestamp($deadlineDate),
            'dd' => $this->_convertDateToTimestamp($dropDeadlineDate),
            'am' => $approveMethod
        ], true);

        foreach($events as $event)
        {
            $this->createEvent($tournamentId, $event['dt_name'], $event['dt_type'], $event['dt_cost'], $event['dt_drop_cost']);
        }

        return $tournamentId;
    }

    public function update($id, $name, $startDate, $deadlineDate, $dropDeadlineDate, $approveMethod)
    {
        $sql = 'UPDATE tournaments SET `name` = :n, `event_start` = :es, `entry_deadline` = :ed, `drop_deadline` = :dd, `approve_method` = :am WHERE id = :id';
        MySQL::get()->exec($sql, [
            'n' => $name,
            'es' => $this->_convertDateToTimestamp($startDate),
            'ed' => $this->_convertDateToTimestamp($deadlineDate),
            'dd' => $this->_convertDateToTimestamp($dropDeadlineDate),
            'am' => $approveMethod,
            'id' => $id
        ]);
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