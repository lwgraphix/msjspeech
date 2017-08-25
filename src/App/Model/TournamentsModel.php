<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\UserType;

class TournamentsModel extends BaseModel
{
    public function create($name, $startDate, $deadlineDate, $dropDeadlineDate, $events)
    {
        $sql = 'INSERT INTO tournaments (`name`, `event_start`, `entry_deadline`, `drop_deadline`)
                VALUES (:n, :es, :ed, :dd)';

        $tournamentId = MySQL::get()->exec($sql, [
            'n' => trim($name),
            'es' => $this->_convertDateToTimestamp($startDate),
            'ed' => $this->_convertDateToTimestamp($deadlineDate),
            'dd' => $this->_convertDateToTimestamp($dropDeadlineDate)
        ], true);

        $sql = 'INSERT INTO events (tournament_id, `name`, `type`, `cost`, `drop_fee_cost`)
                VALUES (:tId, :n, :t, :c, :dfc)';

        foreach($events as $event)
        {
            MySQL::get()->exec($sql, [
                'tId' => $tournamentId,
                'n' => trim($event['dt_name']),
                't' => $event['dt_type'],
                'c' => $event['dt_cost'],
                'dfc' => $event['dt_drop_cost']
            ]);
        }

        return $tournamentId;
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
        // $date - dd/mm/yyyy
        // needed date yyyy-mm-dd hh:ii:ss
        return implode('-', array_reverse(explode('/', $date))) . ' 00:00:00';
    }
}