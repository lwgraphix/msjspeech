<?php

namespace App\Controller;

use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Code\UserType;
use Silex\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

use DDesrosiers\SilexAnnotations\Annotations as SLX;

/**
 * Class CallsController
 * @package App\Controller
 * @SLX\Controller()
 */
class IndexController extends BaseController
{
    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/")
     * )
     */
    public function indexAction(Request $request)
    {
        return $this->out($this->twig->render('index.twig'));
    }
}