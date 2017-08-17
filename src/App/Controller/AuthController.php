<?php

namespace App\Controller;

use App\Code\StatusCode;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\UserType;
use Silex\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

use DDesrosiers\SilexAnnotations\Annotations as SLX;

/**
 * Class CallsController
 * @package App\Controller
 * @SLX\Controller()
 */
class AuthController extends BaseController
{
    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/auth/login")
     * )
     */
    public function authUserAction(Request $request)
    {
        $user = User::load($request->get('email'), $request->get('password'));

        if ($user == StatusCode::USER_BAD_CREDENTIALS)
        {
            FlashMessage::set(false, 'Bad credentials. Check your email/password and try again.');
        }
        else
        {
            if ($user['role'] == UserType::PENDING)
            {
                FlashMessage::set(false, 'Your account in pending approval status. Wait until administrator approve your account.');
            }
            elseif ($user['role'] == UserType::FROZEN)
            {
                FlashMessage::set(false, 'Your account is frozen. Contact with administrator.');
            }
            else
            {
                $this->session->set('user', $user->serialize());
                return new RedirectResponse('/');
            }
        }

        return new RedirectResponse('/auth/login');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/auth/login")
     * )
     */
    public function authUserPageAction(Request $request)
    {
        return $this->out($this->twig->render('auth/login.twig'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/auth/register")
     * )
     */
    public function registerUserPageAction(Request $request)
    {
        return $this->out($this->twig->render('auth/register.twig'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/auth/logout")
     * )
     */
    public function authLogoutAction(Request $request)
    {
        Security::getUserSession()->remove('user');
        return new RedirectResponse('/auth/login');
    }
}