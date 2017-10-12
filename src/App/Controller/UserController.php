<?php

namespace App\Controller;

use App\Code\StatusCode;
use App\Model\UserModel;
use App\Provider\Email;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\Stripe;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\EmailType;
use App\Type\TransactionType;
use App\Type\UserType;
use App\Util\File;
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
class UserController extends BaseController
{

    /**
     * @var UserModel
     */
    private $um;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::SUSPENDED);
        $this->um = Model::get('user');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/user/profile")
     * )
     */
    public function profileAction(Request $request)
    {
        // admin mode
        $groups = Model::get('group')->getAll();

        if ($request->get('user_id') !== null && Security::getUser()->getRole() >= UserType::OFFICER)
        {
            $user = User::loadById($request->get('user_id'));
            if (!$user)
            {
                FlashMessage::set(false, 'User not found');
                return new RedirectResponse('/user/profile');
            }

            $userGroups = Model::get('group')->getUserGroups($user->getId());
            $attributes = Model::get('attribute')->getUserAttributes($user->getId());
            return $this->out($this->twig->render('user/profile.twig', [
                'admin_mode' => true,
                'attributes' => $attributes,
                'view_user' => $user,
                'roles' => UserType::NAMES,
                'groups' => $groups,
                'user_groups' => $userGroups
            ]));
        }

        $attributes = Model::get('attribute')->getUserAttributes(Security::getUser()->getId());
        $userGroups = Model::get('group')->getUserGroups(Security::getUser()->getId());
        return $this->out($this->twig->render('user/profile.twig', [
            'attributes' => $attributes,
            'user_groups' => $userGroups,
            'groups' => $groups
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/user/group/save")
     * )
     */
    public function userGroupSaveAction(Request $request)
    {
        $adminMode = false;
        $groupInfo = Model::get('group')->getById($request->get('id'));
        if (!$groupInfo)
        {
            return $this->out('no');
        }

        if ($request->get('user_id') !== null && Security::getUser()->getRole() >= UserType::OFFICER)
        {
            $userId = $request->get('user_id');
            $adminMode = true;
        }
        else
        {
            $userId = Security::getUser()->getId();
        }

        if (intval($request->get('check')) == 0)
        {
            if ($groupInfo['joinable'] == 1 || $adminMode)
            {
                Model::get('group')->unlink($request->get('id'), $userId);
            }
        }
        else
        {
            if ($groupInfo['joinable'] == 1 || $adminMode)
            {
                Model::get('group')->link($request->get('id'), $userId);
            }
        }

        return $this->out('ok');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/user/balance/deposit")
     * )
     */
    public function depositCashAction(Request $request)
    {
        $token = $request->get('token');
        $amount = $request->get('amount');

        if (empty($token) || empty($amount))
        {
            FlashMessage::set(false, 'Internal error');
            return new RedirectResponse('/user/balance');
        }

        $amount = round(floatval($amount), 2);

        $description = Security::getUser()->getFullName() . ' - ' . Security::getUser()->getEmail() . ' (#'. Security::getUser()->getId() .')';
        $charge = Stripe::charge($token, $amount, $description);
        if ($charge !== true)
        {
            FlashMessage::set(false, $charge);
            return new RedirectResponse('/user/balance');
        }
        else
        {
            $oldBalance = Security::getUser()->getBalance();

            Model::get('transaction_history')->createTransaction(
                Security::getUser()->getId(),
                $amount,
                TransactionType::CARD_DEPOSIT,
                0,
                'Stripe deposit',
                $memo2 = null,
                $memo3 = null,
                $memo4 = null,
                $memo5 = null,
                $eventId = null
            );

            FlashMessage::set(true, 'Your account deposited ' . $amount . '$');

            Email::getInstance()->createMessage(EmailType::TRANSACTION_CREATE, [
                'increased_or_decreased' => 'increased',
                'amount' => floatval($amount),
                'old_balance' => $oldBalance,
                'new_balance' => Security::getUser()->getBalance(),
                'link_account_balance' => Security::getHost() . 'user/balance'
            ], Security::getUser());

            return new RedirectResponse('/user/balance');
        }
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/user/balance")
     * )
     */
    public function balanceAction(Request $request)
    {
        // admin mode
        if ($request->get('user_id') !== null && Security::getUser()->getRole() >= UserType::OFFICER)
        {
            $user = User::loadById($request->get('user_id'));
            if (!$user)
            {
                FlashMessage::set(false, 'User not found');
                return new RedirectResponse('/user/profile');
            }

            $history = Model::get('transaction_history')->getHistory($user->getId());
            return $this->out($this->twig->render('user/balance.twig', [
                'admin_mode' => true,
                'history' => $history,
                'view_user' => $user
            ]));
        }

        $history = Model::get('transaction_history')->getHistory(Security::getUser()->getId());
        return $this->out($this->twig->render('user/balance.twig', ['history' => $history]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/user/profile/save")
     * )
     */
    public function profileSaveAction(Request $request)
    {
        Security::setAccessLevel(UserType::SUSPENDED);

        if ($request->get('user_id') !== null && Security::getUser()->getRole() >= UserType::OFFICER)
        {
            $adminMode = true;
            $loadedUser = User::loadById($request->get('user_id'));
        }
        else
        {
            $adminMode = false;
        }

        // if stripe token - charge stripe on ready user
        $requiredFields = [
            'email' => AttributeType::TEXT,
            'first_name' => AttributeType::TEXT,
            'last_name' => AttributeType::TEXT,
            'parent_email' => AttributeType::TEXT,
            'parent_first_name' => AttributeType::TEXT,
            'parent_last_name' => AttributeType::TEXT
        ];

        // adding custom required attributes to check
        $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
        if ($adminMode)
        {
            $uAttributes = Model::get('attribute')->getUserAttributes($loadedUser->getId());
        }
        else
        {
            $uAttributes = Model::get('attribute')->getUserAttributes(Security::getUser()->getId());
        }

        foreach($attributes as $attribute)
        {
            if ($attribute['type'] == AttributeType::ATTACHMENT)
            {
                if ($uAttributes[$attribute['id']]['required'] == 0)
                {
                    continue;
                }
            }

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
                    return new RedirectResponse($request->headers->get('referer'));
                }
            }
            else
            {
                $fld = $request->get($field);
            }

            if ($fld === null)
            {
                FlashMessage::set(false, 'You forgot to fill out one or more of the required fields. Please fill it out and try again.');
                return new RedirectResponse($request->headers->get('referer'));
            }
        }

        // email validation
        if (!filter_var($request->get('email'), FILTER_VALIDATE_EMAIL))
        {
            FlashMessage::set(false, 'Wrong format for student email address. Please check for typos and try again.');
            return new RedirectResponse($request->headers->get('referer'));
        }

        if (!empty($request->get('parent_email')) && !filter_var($request->get('parent_email'), FILTER_VALIDATE_EMAIL))
        {
            FlashMessage::set(false, 'Wrong format for parent email address. Please check for typos and try again.');
            return new RedirectResponse($request->headers->get('referer'));
        }

        if ($adminMode)
        {
            $status = $this->um->update($loadedUser, array_merge($request->request->all(), $request->files->all()), true);
        }
        else
        {
            $status = $this->um->update(Security::getUser(), array_merge($request->request->all(), $request->files->all()));
        }

        if ($status !== true)
        {
            if ($status == StatusCode::USER_EMAIL_EXISTS)
            {
                FlashMessage::set(false, 'This email address is already being used by another account');
                return new RedirectResponse($request->headers->get('referer'));
            }
            else
            {
                FlashMessage::set(false, 'Internal error. Please contact your administrator.');
                return new RedirectResponse($request->headers->get('referer'));
            }
        }
        else
        {
            if (!$adminMode)
            {
                Security::reloadUser($request->get('email'));
            }
            FlashMessage::set(true, 'Member information changed');
            return new RedirectResponse($request->headers->get('referer'));
        }
    }
}