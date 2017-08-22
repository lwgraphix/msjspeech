<?php

namespace App\Controller;
use App\Model\AlertModel;
use App\Model\AttributeModel;
use App\Model\TransactionHistoryModel;
use App\Model\UserModel;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\LinkType;
use App\Code\ProtocolCode;
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
        $users = $this->um->getAll();
        $roles = UserType::NAMES;
        return $this->out($this->twig->render('admin/users/list.twig', ['users' => $users, 'roles' => $roles]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/users/transactions/add")
     * )
     */
    public function addUserTransactionAction(Request $request)
    {
        $this->thm->createTransaction(
            $request->get('user_id'),
            floatval($request->get('amount')),
            $request->get('description')
        );

        FlashMessage::set(true, 'Transaction successfully created');
        return new RedirectResponse($request->headers->get('referer'));
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
            $this->um->setRole($request->get('user_id'), $newRole);
            FlashMessage::set(true, 'Role changed');
        }

        return new RedirectResponse($request->headers->get('referer'));
    }
}