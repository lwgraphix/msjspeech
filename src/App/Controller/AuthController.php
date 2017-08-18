<?php

namespace App\Controller;

use App\Code\StatusCode;
use App\Model\UserModel;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
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
     * @var $um UserModel
     */
    private $um;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->um = Model::get('user');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/auth/login")
     * )
     */
    public function authUserAction(Request $request)
    {
        $user = User::load($request->get('email'), $request->get('password'));

        if ($user === StatusCode::USER_BAD_CREDENTIALS)
        {
            FlashMessage::set(false, 'Bad credentials. Check your email/password and try again.');
        }
        else
        {
            if ($user->getRole() == UserType::PENDING)
            {
                FlashMessage::set(false, 'Your account in pending approval status. Wait until administrator approve your account.');
            }
            elseif ($user->getRole() == UserType::FROZEN)
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
     *     @SLX\Request(method="POST", uri="/auth/register")
     * )
     */
    public function registerUserAction(Request $request)
    {
        // if stripe token - charge stripe on ready user
        $requiredFields = ['email', 'password', 'first_name', 'last_name']; // todo: fill from custom attributes
        foreach($requiredFields as $field)
        {
            if ($request->get($field) === null || empty($request->get($field)))
            {
                FlashMessage::set(false, 'One of field is empty. Please fill all required fields and try again.');
                return new RedirectResponse('/auth/register');
            }
        }

        // register
        $status = $this->um->create($request->request->all());
        if ($status !== true)
        {
            if ($status == StatusCode::USER_EMAIL_EXISTS)
            {
                FlashMessage::set(false, 'User with this email exists! Try another email or <a href="/auth/restore">restore</a> your account access.');
                return new RedirectResponse('/auth/register');
            }
            else
            {
                FlashMessage::set(false, 'Internal error. Please contact with administrator.');
                return new RedirectResponse('/auth/register');
            }
        }
        else
        {
            // TODO: send email
            FlashMessage::set(true, 'You are registered! You can access your profile after administrator approvement. Wait for email message with approve status.');
            return new RedirectResponse('/');
        }
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
        $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
        return $this->out($this->twig->render('auth/register.twig', ['attributes' => $attributes]));
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