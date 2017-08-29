<?php

namespace App\Controller;

use App\Code\StatusCode;
use App\Model\UserModel;
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
            'last_name' => AttributeType::TEXT
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
                    FlashMessage::set(false, 'You can upload only PDF file!');
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
                FlashMessage::set(false, 'One of field is empty. Please fill all required fields and try again.');
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
            FlashMessage::set(false, 'Wrong email address format. Check the typing of email correct and try again');
            $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
            return $this->out($this->twig->render('auth/register.twig', [
                'attributes' => $attributes,
                'flash_message' => FlashMessage::get()
            ]));
        }

        if (!empty($request->get('parent_email')) && !filter_var($request->get('parent_email'), FILTER_VALIDATE_EMAIL))
        {
            FlashMessage::set(false, 'Wrong parent email address format. Check the typing of email correct and try again');
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
                FlashMessage::set(false, 'User with this email exists! Try another email or <a href="/auth/restore">restore</a> your account access.');
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
                FlashMessage::set(false, 'Internal error. Please contact with administrator.');
                $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
                return $this->out($this->twig->render('auth/register.twig', [
                    'attributes' => $attributes,
                    'flash_message' => FlashMessage::get()
                ]));
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
        if (!$user)
        {
            FlashMessage::set(false, 'User with this email not found');
        }
        else
        {
            $status = $this->um->restore($user['id']);
            if (!$status)
            {
                FlashMessage::set(false, 'You already requested account restore. Check your email');
            }
            else
            {
                FlashMessage::set(true, 'Check your email for further instructions');
            }

        }

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