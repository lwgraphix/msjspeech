<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Email;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\Stripe;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\EmailType;
use App\Type\EventStatusType;
use App\Type\TransactionType;
use App\Type\UserType;
use App\Provider\SystemSettings;
use SimpleEmailService;
use SimpleEmailServiceMessage;

class EmailModel extends BaseModel
{
    public function getUsersByEmailType($type, $groupId = null, $tournamentId = null, $eventId = null)
    {
        switch ($type) {
            case 1:
                // all users
                $list = Model::get('user')->getAll();
                break;

            case 2:
                // user group (group_id)
                $list = Model::get('user')->getAllByGroupId($groupId);
                break;

            case 3:
                // tournament (tournament_id)
                // event (tournament_id, event_id)
                $list = Model::get('tournaments')->getMembersList($tournamentId, $eventId, EventStatusType::APPROVED);
                $list = $this->_transformTournamentMemberList($list);
                break;

            default:
                return false;
            break;
        }

        // delete pending & frozen from lsit
        foreach($list as $k => &$user)
        {
            if ($user['role'] == UserType::PENDING || $user['role'] == UserType::FROZEN)
            {
                unset($list[$k]);
            }

            if ($type != 3)
            {
                $user['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            }

        }

        if (count($list) == 0) return false;
        return $list;
    }

    private function _transformTournamentMemberList($members)
    {
        $tmp = $list = [];
        foreach($members as $member)
        {
            $tmp[] = [
                'id' => $member['own_id'],
                'email' => $member['own_email'],
                'full_name' => $member['own_name'],
                'role' => $member['own_role'],
                'parent_email' => $member['own_parent_email']
            ];

            if (!empty($member['par_id']))
            {
                $tmp[] = [
                    'id' => $member['par_id'],
                    'email' => $member['par_email'],
                    'full_name' => $member['par_name'],
                    'role' => $member['par_role'],
                    'parent_email' => $member['par_parent_email']
                ];
            }
        }

        foreach($tmp as $user)
        {
            $list[$user['id']] = $user;
        }

        return $list;
    }

    public function sendMassEmail($list, $sendToParents, $subject, $content, $appendix = null)
    {
        $messages = [];

        foreach ($list as $user) {
            $m = new SimpleEmailServiceMessage();

            if ($sendToParents) {
                if (empty($user['parent_email'])) continue; // skip message if parent not exists
                $m->addTo($user['parent_email']);
            } else {
                $m->addTo($user['email']);
                if (!empty($user['parent_email'])) {
                    $m->addCC($user['parent_email']);
                }
            }
            $m->setFrom(SystemSettings::getInstance()->get('aws_send_email_from'));
            $m->setSubject($subject);
            $m->setMessageFromString($content);
            $messages[] = $m;
        }

        $par = ($sendToParents) ? ' (to parents only)' : '';
        $adminMessageContent = 'This letter was sent by ' . Security::getUser()->getFullName() . ' ' . $appendix . $par . PHP_EOL;
        $adminMessageContent .= '===============================================' . PHP_EOL;
        $adminMessageContent .= 'Subject: ' . $subject . PHP_EOL;
        $adminMessageContent .= 'Content: ' . $content;

        $adminMessage = new SimpleEmailServiceMessage();
        $adminMessage->addTo(SystemSettings::getInstance()->get('bcc_receiver'));
        $adminMessage->setFrom(SystemSettings::getInstance()->get('aws_send_email_from'));
        $adminMessage->setSubject('Mass email started');
        $adminMessage->setMessageFromString($adminMessageContent);

        $messages[] = $adminMessage;

        $ses = new SimpleEmailService(
            SystemSettings::getInstance()->get('aws_access_key'),
            SystemSettings::getInstance()->get('aws_secret_key')
        );

        $ses->setBulkMode(true);
        foreach ($messages as $message)
        {
            $ses->sendEmail($message);
        }
        $ses->setBulkMode(false);
    }
}