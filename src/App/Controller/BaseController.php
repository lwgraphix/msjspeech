<?php

namespace App\Controller;
use App\Menu\MenuBuilder;
use App\Menu\MenuBuilderNew;
use App\Provider\FlashMessage;
use App\Provider\Security;
use App\Provider\SystemSettings;
use App\Provider\User;
use App\Type\UserType;
use App\Util\File;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BaseController {

    protected $session;
    protected $twig;

    public function __construct(Application $app)
    {
        Security::registerUser($app['session']);
        $this->session = $app['session'];
        $this->twig = $app['twig'];

        $userRole = (Security::getUser() === null) ? 0 : Security::getUser()->getRole();
        $user = Security::getUser();

        if ($user !== null && $userRole == UserType::FROZEN)
        {
            $this->session->remove('user');
            FlashMessage::set(false, 'Your account has been frozen');
            header('Location: /auth/login');
            die;
        }

        $this->twig->addGlobal('menu', MenuBuilder::generate($userRole));
        $this->twig->addGlobal('user', $user);
        $this->twig->addGlobal('system_settings', SystemSettings::getInstance());
        $this->twig->addGlobal('max_filesize', File::asBytes(ini_get('post_max_size')));
        $this->twig->addGlobal('max_filesize_php', ini_get('post_max_size'));

        $flash = FlashMessage::get();
        if ($flash['status'] !== null)
        {
            $this->twig->addGlobal('flash_message', $flash);
        }
    }

    public function outJSON($code, $data = [])
    {
        $response['code'] = intval($code);
        if (count($data) > 0)
        {
            $response['data'] = $data;
        }

        return $this->out(json_encode($response), true);
    }

    public function out($data, $json = false)
    {
        $response = new Response();
        if ($json)
        {
            $response->headers->set('Content-Type', 'application/json');
        }
        $response->setContent($data);
        return $response;
    }
}