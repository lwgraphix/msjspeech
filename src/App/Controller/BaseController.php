<?php

namespace App\Controller;
use App\Menu\MenuBuilder;
use App\Provider\FlashMessage;
use App\Provider\Security;
use App\Provider\User;
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

        $this->twig->addGlobal('menu', MenuBuilder::build($userRole));
        $this->twig->addGlobal('user', Security::getUser());

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