<?php

namespace App\Controller;
use App\Model\AlertModel;
use App\Model\AttributeModel;
use App\Model\TransactionHistoryModel;
use App\Model\UserModel;
use App\Provider\Email;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\EmailType;
use App\Type\LinkType;
use App\Code\ProtocolCode;
use App\Type\TransactionType;
use App\Type\UserType;
use Silex\Application;
use DDesrosiers\SilexAnnotations\Annotations as SLX;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AlertController
 * @package App\Controller
 * @SLX\Controller(prefix="/admin")
 */
class AdminUsersController extends BaseController {

    /**
     * @var UserModel
     */
    private $um;

    /**
     * @var AttributeModel
     */
    private $am;

    /**
     * @var TransactionHistoryModel
     */
    private $thm;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::OFFICER);
        $this->um = Model::get('user');
        $this->am = Model::get('attribute');
        $this->thm = Model::get('transaction_history');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/users/list")
     * )
     */
    public function usersListAction(Request $request)
    {
        $role = $request->get('role', 0);
        $users = ($role == -1) ? $this->um->getAll() : $this->um->getAllByRole($role);
        $roles = UserType::NAMES;
        if ($role > count($roles) - 1 || $role < -1)
        {
            FlashMessage::set(false, 'Role not found');
            return new RedirectResponse('/admin/users/list');
        }

        $uaNames = [];
        $attrs = $this->am->getAll(AttributeGroupType::REGISTER);
        foreach($attrs as $attr)
        {
            $uaNames[$attr['id']] = $attr['label'];
        }

        return $this->out($this->twig->render('admin/users/list.twig', [
            'users' => $users,
            'roles' => $roles,
            'attr_names' => $uaNames
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/users/transactions/add")
     * )
     */
    public function addUserTransactionAction(Request $request)
    {
        $receiverUser = User::loadById($request->get('user_id'));
        $oldBalance = $receiverUser->getBalance();

        $this->thm->createTransaction(
            $request->get('user_id'),
            floatval($request->get('amount')),
            TransactionType::MANUAL,
            Security::getUser()->getId(),
            $request->get('memo1'),
            $request->get('memo2'),
            $request->get('memo3'),
            $request->get('memo4'),
            $request->get('memo5')
        );

        $newBalance = $receiverUser->getBalance();

        Email::getInstance()->createMessage(EmailType::TRANSACTION_CREATE, [
            'increased_or_decreased' => ($oldBalance > $newBalance) ? 'decreased' : 'increased',
            'amount' => floatval($request->get('amount')),
            'old_balance' => $oldBalance,
            'new_balance' => $newBalance,
            'link_account_balance' => Security::getHost() . 'user/balance'
        ], $receiverUser);

        FlashMessage::set(true, 'Transaction successfully created');
        return new RedirectResponse($request->headers->get('referer'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/users/transactions/delete")
     * )
     */
    public function deleteTransactionAction(Request $request)
    {
        if (Security::getUser()->getRole() != UserType::ADMINISTRATOR)
        {
            FlashMessage::set(false, 'Access denied');
            return $this->out('no');
        }
        else
        {
            $transaction = Model::get('transaction_history')->getById($request->get('id'));
            if ($transaction['type'] == TransactionType::CARD_DEPOSIT)
            {
                FlashMessage::set(false, 'You are not allowed to delete card deposit transactions');
                return $this->out('no');
            }
            else
            {
                FlashMessage::set(true, 'Transaction successfully deleted');
                Model::get('transaction_history')->deleteTransaction($request->get('id'));
                return $this->out('ok');
            }
        }
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/users/role/set")
     * )
     */
    public function setUserRoleAction(Request $request)
    {
        $newRole = $request->get('new_role');
        $adminRole = Security::getUser()->getRole();

        if ($adminRole == UserType::OFFICER && ($newRole == UserType::OFFICER || $newRole == UserType::ADMINISTRATOR))
        {
            FlashMessage::set(false, 'Access denied. Your action reported.');
        }
        else
        {
            $user = User::loadById($request->get('user_id'));
            $oldRole = $user->getRole();
            $status = $this->um->setRole($request->get('user_id'), $newRole, $adminRole);
            if ($status)
            {
                Email::getInstance()->createMessage(EmailType::USER_ROLE_CHANGE, [
                    'old_status' => UserType::NAMES[$oldRole],
                    'new_status' => UserType::NAMES[$newRole]
                ], $user);

                FlashMessage::set(true, 'Role changed');
            }
            else
            {
                FlashMessage::set(false, 'Access denied. Your action reported.');
            }

        }

        return new RedirectResponse($request->headers->get('referer'));
    }
}