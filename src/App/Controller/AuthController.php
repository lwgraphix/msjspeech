<?php

namespace App\Controller;

use App\Code\StatusCode;
use App\Model\UserModel;
use App\Provider\Email;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\SystemSettings;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\UserType;
use Silex\Application;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
            FlashMessage::set(false, 'Incorrect email and/or password. Please try again.');
        }
        else
        {
            if ($user->getRole() == UserType::PENDING)
            {
                FlashMessage::set(false, 'You are not allowed to perform this action because your account is still pending. You will receive an email when it is approved.');
            }
            elseif ($user->getRole() == UserType::FROZEN)
            {
                FlashMessage::set(false, 'Your account is frozen. Contact your administrator.');
            }
            else
            {
                $user->updateLastLogin();
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
        if (SystemSettings::getInstance()->get('register_allowed') == 0)
        {
            FlashMessage::set(false, 'Club registration is closed.');
            return new RedirectResponse('/');
        }

        $requiredFields = [
            'email' => AttributeType::TEXT,
            'password' => AttributeType::TEXT,
            'first_name' => AttributeType::TEXT,
            'last_name' => AttributeType::TEXT,
            'parent_email' => AttributeType::TEXT,
            'parent_first_name' => AttributeType::TEXT,
            'parent_last_name' => AttributeType::TEXT
        ];

        // adding custom required attributes to check
        $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
        foreach($attributes as $attribute)
        {
            if ($attribute['required'])
            {
                $requiredFields['attr_' . $attribute['id']] = $attribute['type'];
            }
        }

        foreach($requiredFields as $field => $attributeType)
        {
            if ($attributeType == AttributeType::ATTACHMENT)
            {
                /**
                 * @var $fld UploadedFile
                 */
                $fld = $request->files->get($field);
                if ($fld->getClientMimeType() != 'application/pdf')
                {
                    FlashMessage::set(false, 'File types other than PDF are not allowed.');
                    $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
                    return $this->out($this->twig->render('auth/register.twig', [
                        'attributes' => $attributes,
                        'flash_message' => FlashMessage::get()
                    ]));
                }
            }
            else
            {
                $fld = $request->get($field);
            }

            if ($fld === null)
            {
                FlashMessage::set(false, 'You forgot to fill out one or more of the required fields. Please fill it out and try again.');
                $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
                return $this->out($this->twig->render('auth/register.twig', [
                    'attributes' => $attributes,
                    'flash_message' => FlashMessage::get()
                ]));
            }
        }

        // email validation
        if (!filter_var($request->get('email'), FILTER_VALIDATE_EMAIL))
        {
            FlashMessage::set(false, 'Wrong format for student email address. Please check for typos and try again.');
            $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
            return $this->out($this->twig->render('auth/register.twig', [
                'attributes' => $attributes,
                'flash_message' => FlashMessage::get()
            ]));
        }

        if (!filter_var($request->get('parent_email'), FILTER_VALIDATE_EMAIL))
        {
            FlashMessage::set(false, 'Wrong format for parent email address. Please check for typos and try again.');
            $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
            return $this->out($this->twig->render('auth/register.twig', [
                'attributes' => $attributes,
                'flash_message' => FlashMessage::get()
            ]));
        }


        // register
        $status = $this->um->create(array_merge($request->request->all(), $request->files->all()));
        if ($status !== true)
        {
            if ($status == StatusCode::USER_EMAIL_EXISTS)
            {
                FlashMessage::set(false, 'This email address is already being used by another account. Try another email or <a href="/auth/restore">restore</a> your account access.');
                return new RedirectResponse('/auth/register');
            }
            elseif ($status == StatusCode::STRIPE_CHARGE_FAILED)
            {
                $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
                return $this->out($this->twig->render('auth/register.twig', [
                    'attributes' => $attributes,
                    'flash_message' => FlashMessage::get()
                ]));
            }
            else
            {
                FlashMessage::set(false, 'Internal error. Please contact your administrator.');
                $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
                return $this->out($this->twig->render('auth/register.twig', [
                    'attributes' => $attributes,
                    'flash_message' => FlashMessage::get()
                ]));
            }
        }
        else
        {
            FlashMessage::set(true, 'You are registered! Your account is currently pending. You will receive an email when it is approved.');
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
     *     @SLX\Request(method="GET", uri="/auth/restore")
     * )
     */
    public function authRestoreAction(Request $request)
    {
        return $this->out($this->twig->render('auth/forgot.twig'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/auth/restore/{hash}")
     * )
     */
    public function authRestoreByHashAction(Request $request, $hash)
    {
        $data = $this->um->getRestoreData($hash);
        if (!$data)
        {
            FlashMessage::set(false, 'Your link is expired');
            return new RedirectResponse('/');
        }

        return $this->out($this->twig->render('auth/reset.twig'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/auth/restore/{hash}")
     * )
     */
    public function authRestoreByHashPersistAction(Request $request, $hash)
    {
        $data = $this->um->getRestoreData($hash);
        if (!$data)
        {
            FlashMessage::set(false, 'Your link is expired');
            return new RedirectResponse('/');
        }

        if (empty($request->get('password')))
        {
            FlashMessage::set(false, 'Password is empty');
            return new RedirectResponse($request->headers->get('referer'));
        }

        $this->um->changePassword($data['user_id'], $request->get('password'), $hash);
        FlashMessage::set(true, 'Password was changed. Try to sign in now.');

        return new RedirectResponse('/auth/login');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/auth/restore")
     * )
     */
    public function authRestorePersistAction(Request $request)
    {
        $user = $this->um->getByEmail($request->get('email'));

        if ($user !== false)
        {
            $this->um->restore($user['id']);
        }

        FlashMessage::set(true, 'If the email you entered is tied to an existing account, you will be emailed a password reset link.');

        return new RedirectResponse($request->headers->get('referer'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/auth/register")
     * )
     */
    public function registerUserPageAction(Request $request)
    {
        if (SystemSettings::getInstance()->get('register_allowed') == 0)
        {
            FlashMessage::set(false, 'Club registration is closed.');
            return new RedirectResponse('/');
        }

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
        //Security::setAccessLevel(UserType::SUSPENDED);
        Security::getUserSession()->remove('user');
        return new RedirectResponse('/auth/login');
    }
}