<?php

namespace App\Provider;

use App\Code\StatusCode;
use App\Code\UserType;
use Phinx\Console\Command\Status;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class Security
{
    /**
     * @var $session Session
     */
    private static $session;
    /**
     * @var $user User
     */
    private static $user = null;

    public static function registerUser(Session $session)
    {
        self::$session = $session;
        if ($session->get('user') !== null)
        {
            self::$user = User::unserialize($session->get('user'));
        }
    }

    public static function reloadUser($email)
    {
        $user = User::load($email, null, true);
        self::$session->set('user', $user->serialize());
    }

    public static function getHost()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'].'/';
        return $protocol . $domainName;
    }

    public static function setAccessLevel($role)
    {
        if (self::$user === null)
        {
            self::redirectToAuth();
        }
        elseif (self::$user->getRole() < $role)
        {
            FlashMessage::set(false, 'No permission to allow that action');
            header('Location: /');
            exit;
        }
    }

    public static function getUser()
    {
        return self::$user;
    }

    public static function getUserSession()
    {
        return self::$session;
    }

    public static function createError($code, $message, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode(['status' => false, 'code' => $code, 'message' => $message]);
        die;
    }
    
    public static function redirectToAuth()
    {
        header('Location: /auth/login');
        die;
    }
}