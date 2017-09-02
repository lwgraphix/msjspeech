<?php

namespace App\Controller;
use App\Code\StatusCode;
use App\Model\AlertModel;
use App\Model\AttributeModel;
use App\Model\CategoriesModel;
use App\Model\GroupModel;
use App\Model\PagesModel;
use App\Model\TournamentsModel;
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
use App\Util\DateUtil;
use Silex\Application;
use DDesrosiers\SilexAnnotations\Annotations as SLX;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AlertController
 * @package App\Controller
 * @SLX\Controller(prefix="/admin")
 */
class AdminGroupsController extends BaseController {

    /**
     * @var GroupModel
     */
    private $gm;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::OFFICER);
        $this->gm = Model::get('group');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/groups/list")
     * )
     */
    public function groupsListAction(Request $request)
    {
        $list = $this->gm->getAll();
        return $this->out($this->twig->render('admin/groups.twig', [
            'list' => $list
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/groups/members/{groupId}")
     * )
     */
    public function groupsMembersListAction(Request $request, $groupId)
    {
        $name = $this->gm->getById($groupId)['name'];
        $list = $this->gm->getUsersByGroupId($groupId);
        $users = $this->gm->getUsersExceptGroupId($groupId);
        return $this->out($this->twig->render('admin/groups_members.twig', [
            'list' => $list,
            'users' => $users,
            'roles' => UserType::NAMES,
            'name' => $name
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/groups/members/{groupId}")
     * )
     */
    public function groupsMembersListLinkAction(Request $request, $groupId)
    {
        if ($request->get('type') == 0)
        {
            $this->gm->unlink($groupId, $request->get('id'));
        }
        else
        {
            $this->gm->link($groupId, $request->get('id'));
        }
        return $this->out('ok');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/groups/create")
     * )
     */
    public function groupsCreateAction(Request $request)
    {
        $this->gm->create($request->get('name'), $request->get('joinable'));
        FlashMessage::set(true, 'Group created.');
        return new RedirectResponse($request->headers->get('referer'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/groups/edit")
     * )
     */
    public function groupsEditAction(Request $request)
    {
        $this->gm->update($request->get('id'), $request->get('name'));
        FlashMessage::set(true, 'Group updated.');
        return new RedirectResponse($request->headers->get('referer'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/groups/delete")
     * )
     */
    public function groupsDeleteAction(Request $request)
    {
        $this->gm->delete($request->get('id'));
        FlashMessage::set(true, 'Group deleted.');
        return $this->out('ok');
    }
}