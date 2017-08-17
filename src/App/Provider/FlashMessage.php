<?php

namespace App\Provider;

use App\Code\UserType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class FlashMessage
{
    public static function set($status, $text)
    {
        Security::getUserSession()->set('flash_message_status', $status);
        Security::getUserSession()->set('flash_message_text', $text);
    }

    public static function get()
    {
        $data = [
            'status' => Security::getUserSession()->get('flash_message_status'),
            'text' => Security::getUserSession()->get('flash_message_text')
        ];

        Security::getUserSession()->remove('flash_message_status');
        Security::getUserSession()->remove('flash_message_text');

        return $data;
    }
}