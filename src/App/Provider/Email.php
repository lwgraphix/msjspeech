<?php

namespace App\Provider;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Type\EmailProviderType;
use App\Type\EmailType;
use App\Type\UserType;
use SimpleEmailService;
use SimpleEmailServiceMessage;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpFoundation\Session\Session;

class Email
{
    private static $instance = null;

    // [username, signature] - filled for all templates by default
    private $templates = [

        EmailType::MEMBERSHIP_REGISTRATION => [
            'form'
        ],

        EmailType::USER_ROLE_CHANGE => [
            'old_status',
            'new_status'
        ],

        EmailType::TOURNAMENT_JOIN => [
            'tournament_name',
            'form',
            'event_name',
            'event_cost',
            'old_balance',
            'new_balance',
            'drop_deadline',
            'link_to_history',
            'link_account_balance'
        ],

        EmailType::TOURNAMENT_DROP_BEFORE_DEADLINE => [
            'tournament_name',
            'event_name',
            'event_cost',
            'old_balance',
            'new_balance',
            'link_account_balance'
        ],

        EmailType::TOURNAMENT_DROP_AFTER_DEADLINE => [
            'tournament_name',
            'event_name',
            'event_cost',
            'old_balance',
            'new_balance',
            'link_account_balance',
            'drop_fee'
        ],

        EmailType::TOURNAMENT_PARTNER_DROP_BEFORE_DEADLINE => [
            'partner_name',
            'tournament_name',
            'event_name',
            'event_cost',
            'old_balance',
            'new_balance',
            'link_account_balance'
        ],

        EmailType::TOURNAMENT_PARTNER_DROP_AFTER_DEADLINE => [
            'partner_name',
            'tournament_name',
            'event_name'
        ],

        EmailType::TRANSACTION_CREATE => [
            'increased_or_decreased',
            'amount',
            'old_balance',
            'new_balance',
            'link_account_balance'
        ],

        EmailType::PARTNER_REQUEST => [
            'partner_name',
            'tournament_name',
            'event_name',
            'join_link'
        ],

        EmailType::PARTNER_REQUEST_DECLINE => [
            'partner_name',
            'tournament_name',
            'event_name',
            'event_cost',
            'old_balance',
            'new_balance',
            'link_account_balance'
        ],

        EmailType::PARTNER_REQUEST_ACCEPT => [
            'partner_name',
            'tournament_name',
            'event_name'
        ],

        EmailType::PARTNER_REQUEST_EXPIRED => [
            'partner_name',
            'tournament_name',
            'event_name',
            'event_cost',
            'old_balance',
            'new_balance',
            'link_account_balance'
        ],

        EmailType::TOURNAMENT_JUDGE => [
            'judge_name',
            'tournament_name',
            'event_name'
        ],

        EmailType::ACCOUNT_RESTORE_ACCESS => [
            'restore_link'
        ],

        EmailType::PARTNER_CANCELLED => [
            'partner_name',
            'tournament_name',
            'event_name'
        ],

        EmailType::TOURNAMENT_REGISTRATION_APPROVED => [
            'tournament_name',
            'event_name'
        ],

        EmailType::SYSTEM_SETTINGS_CHANGED => [
            'changed_fields'
        ],

        EmailType::TOURNAMENT_REGISTRATION_REJECTED => [
            'tournament_name',
            'event_name',
            'event_cost',
            'link_account_balance'
        ],

        EmailType::EMAIL_TEMPLATE_CHANGED => [
            'name',
            'old_subject',
            'old_body',
            'new_subject',
            'new_body'
        ],

        EmailType::PROFILE_EDIT => [
            'old_form',
            'new_form'
        ],

        EmailType::PROFILE_ADMIN_EDIT => [
            'officer_name',
            'old_form',
            'new_form'
        ]
    ];

    public static function getInstance()
    {
        if (self::$instance === null)
        {
            self::$instance = new Email();
        }

        return self::$instance;
    }

    public function __construct()
    {
        $this->_selfIntegrityCheck();
    }

    // restore settings where
    private function _selfIntegrityCheck()
    {
        $currentTemplates = $this->getAllTemplates();

        foreach(array_keys($this->templates) as $templateType)
        {
            if (!in_array($templateType, array_keys($currentTemplates)))
            {
                // create row
                $sql = 'INSERT INTO email_templates (`type`) VALUES (:t)';
                MySQL::get()->exec($sql, [
                    't' => $templateType,
                ]);
            }
        }
    }

    public function getAllTemplates()
    {
        $sql = 'SELECT * FROM email_templates';
        $data = MySQL::get()->fetchAll($sql);
        $result = [];
        foreach($data as $row)
        {
            $result[$row['type']] = $row;
        }
        return $data;
    }

    public function getTemplate($type)
    {
        $sql = 'SELECT * FROM email_templates WHERE `type` = :t';
        $data = MySQL::get()->fetchOne($sql, ['t' => $type]);
        $data['available_variables'] = $this->templates[$type];
        $data['available_variables'][] = 'username';
        $data['available_variables'][] = 'signature';
        $data['available_variables'][] = 'website_name';
        return $data;
    }

    public function updateTemplate($type, $subject, $content)
    {
        $sql = 'UPDATE email_templates SET subject = :s, content = :c WHERE `type` = :t';
        MySQL::get()->exec($sql, [
            's' => $subject,
            'c' => $content,
            't' => $type
        ]);
    }

    public function createMessage($type, $data, User $user, $anotherReceiver = null)
    {
        $template = $this->getTemplate($type);
        $replacement = [];
        foreach($data as $name => $value)
        {
            if (in_array($name, $template['available_variables']))
            {
                if ($name == 'old_balance' || $name == 'new_balance')
                {
                    $value = round($value, 2);
                }

                $replacement['[' . $name . ']'] = $value;
            }
        }
        $replacement['[username]'] = $user->getFullName();
        $replacement['[signature]'] = SystemSettings::getInstance()->get('email_signature');
        $replacement['[website_name]'] = SystemSettings::getInstance()->get('site_name');

        $message = str_replace(array_keys($replacement), array_values($replacement), $template['content']);
        $subject = str_replace(array_keys($replacement), array_values($replacement), $template['subject']);
        $sendEmail = ($anotherReceiver !== null) ? $anotherReceiver : $user->getEmail();
        $this->send($sendEmail, $subject, $message, $user, $anotherReceiver);
    }

    public function send($to, $subject, $message, User $user, $anotherReceiver = null)
    {
        if (SystemSettings::getInstance()->get('email_provider') == EmailProviderType::AMAZON)
        {
            $m = new SimpleEmailServiceMessage();
            $m->addTo($to);
            $m->setFrom(SystemSettings::getInstance()->get('send_email_from'));
            $m->setSubject($subject);

            if ($anotherReceiver === null)
            {
                if (!empty($user->getParentEmail()))
                {
                    $m->addCC($user->getParentEmail());
                }
            }

            $bccReceiver = SystemSettings::getInstance()->get('bcc_receiver');
            if (!empty($bccReceiver))
            {
                $m->addBCC($bccReceiver);
            }

            $m->setMessageFromString($message);

            $ses = new SimpleEmailService(
                SystemSettings::getInstance()->get('aws_access_key'),
                SystemSettings::getInstance()->get('aws_secret_key')
            );

            $ses->sendEmail($m);
        }
        elseif (SystemSettings::getInstance()->get('email_provider') == EmailProviderType::SENDGRID)
        {
            $from = new \SendGrid\Email(null, SystemSettings::getInstance()->get('send_email_from'));
            $to = new \SendGrid\Email(null, $to);
            $content = new \SendGrid\Content("text/plain", $message);
            $mail = new \SendGrid\Mail($from, $subject, $to, $content);
            // email generated, go to bcc/cc

            if ($anotherReceiver === null)
            {
                if (!empty($user->getParentEmail()))
                {
                    $mail->personalization[0]->addCc(new \SendGrid\Email(null, $user->getParentEmail()));
                }
            }

            $bccReceiver = SystemSettings::getInstance()->get('bcc_receiver');
            if (!empty($bccReceiver))
            {
                $mail->personalization[0]->addBcc(new \SendGrid\Email(null, $bccReceiver));
            }

            $sendgrid = new \SendGrid(SystemSettings::getInstance()->get('sendgrid_key'));
            $status = $sendgrid->client->mail()->send()->post($mail);
        }
        else
        {
            // unknown provider type
        }
    }
}


