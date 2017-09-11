<?php

namespace App\Provider;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Type\UserType;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpFoundation\Session\Session;

class SystemSettings
{

    private $settingsRepository = [];

    private $defaults = [
        [
            'label' => '[System] Site name (max 20 chars)',
            'name' => 'site_name',
            'value' => 'Speech & Debate',
            'boolean' => 0
        ],

        [
            'label' => '[System] Site URL',
            'name' => 'site_url',
            'value' => null,
            'boolean' => 0
        ],

        [
            'label' => '[System] Member registration allowed',
            'name' => 'register_allowed',
            'value' => 0,
            'boolean' => 1
        ],

        [
            'label' => '[System] Allow members with a negative balance to register for tournaments',
            'name' => 'negative_balance',
            'value' => 0,
            'boolean' => 1,
        ],

        [
            'label' => '[System] Auto-decline partner request by timeout (in hours)',
            'name' => 'auto_decline_timeout',
            'value' => 72,
            'boolean' => 0
        ],

        [
            'label' => '[System] Membership contribution',
            'name' => 'membership_fee',
            'value' => 0,
            'boolean' => 0
        ],

        [
            'label' => '[System] Membership contribution text',
            'name' => 'membership_text',
            'value' => null,
            'boolean' => 0
        ],

        [
            'label' => '[System] Member registration description text (html tags supported)',
            'name' => 'membership_registration_description',
            'value' => null,
            'boolean' => 0
        ],

        [
            'label' => '[Payments] Credit card payment enabled',
            'name' => 'payment_allowed',
            'value' => 0,
            'boolean' => 1,
        ],

        [
            'label' => '[Payments] Public stripe key for credit card payment',
            'name' => 'public_stripe_key',
            'value' => null,
            'boolean' => 0,
        ],

        [
            'label' => '[Payments] Private stripe key for credit card payment',
            'name' => 'private_stripe_key',
            'value' => null,
            'boolean' => 0,
        ],

        [
            'label' => '[Email] Email provider',
            'name' => 'email_provider',
            'value' => 0,
            'boolean' => 0,
            'dropdown' => [
                'Amazon SES',
                'Sendgrid'
            ]
        ],

        [
            'label' => '[Email] Send email from',
            'name' => 'send_email_from',
            'value' => null,
            'boolean' => 0,
        ],

        [
            'label' => '[Email] BCC sent to',
            'name' => 'bcc_receiver',
            'value' => null,
            'boolean' => 0,
        ],

        [
            'label' => '[Email] Email signature',
            'name' => 'email_signature',
            'value' => null,
            'boolean' => 0
        ],


        [
            'label' => '[Email] [AWS] Access key ID',
            'name' => 'aws_access_key',
            'value' => null,
            'boolean' => 0,
        ],

        [
            'label' => '[Email] [AWS] Secret Key',
            'name' => 'aws_secret_key',
            'value' => null,
            'boolean' => 0,
        ],

        [
            'label' => '[Email] [Sendgrid] Secret key',
            'name' => 'sendgrid_key',
            'value' => null,
            'boolean' => 0
        ],

        [
            'label' => '[Other] Google Analytics code',
            'name' => 'google_code',
            'value' => null,
            'boolean' => 0
        ]
    ];

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null)
        {
            self::$instance = new SystemSettings();
        }

        return self::$instance;
    }

    public function __construct()
    {
        $this->_selfIntegrityCheck();
        Email::getInstance(); // init email types too
    }

    // restore settings where
    private function _selfIntegrityCheck()
    {
        $currentSettings = $this->getAll();
        $settingsName = [];
        foreach($currentSettings as $setting)
        {
            $settingsName[] = $setting['name'];
        }

        foreach($this->defaults as $defaultSetting)
        {
            if (!in_array($defaultSetting['name'], $settingsName))
            {
                $dropdown = (isset($defaultSetting['dropdown'])) ? json_encode($defaultSetting['dropdown']) : null;
                // create row
                $sql = 'INSERT INTO system_settings (label, `name`, `value`, boolean, dropdown) VALUES (:l, :n, :v, :b, :d)';
                MySQL::get()->exec($sql, [
                    'l' => $defaultSetting['label'],
                    'n' => $defaultSetting['name'],
                    'v' => $defaultSetting['value'],
                    'b' => $defaultSetting['boolean'],
                    'd' => $dropdown
                ]);
            }
        }
    }

    public function get($name)
    {
        if (!$this->settingsRepository[$name])
        {
            $value = MySQL::get()->fetchColumn('SELECT `value` FROM system_settings WHERE `name` = :n', [
                'n' => $name
            ]);

            $this->settingsRepository[$name] = $value;
        }

        return $this->settingsRepository[$name]; // cache get
    }

    public function set($name, $value)
    {
        $sql = 'UPDATE system_settings SET `value` = :v WHERE `name` = :n';
        MySQL::get()->exec($sql, [
            'v' => $value,
            'n' => $name
        ]);

        $this->settingsRepository[$name] = $value; // cache set
    }

    public function getAll()
    {
        $sql = 'SELECT * FROM system_settings';
        $data = MySQL::get()->fetchAll($sql);

        foreach($data as &$row)
        {
            // caching all settings
            if (!empty($row['dropdown']))
            {
                $row['dropdown'] = json_decode($row['dropdown'], true);
            }

            $this->settingsRepository[$row['name']] = $row['value'];
        }

        return $data;
    }
}


