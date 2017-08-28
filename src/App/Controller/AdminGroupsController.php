<?php

namespace App\Controller;
use App\Code\StatusCode;
use App\Model\AlertModel;
use App\Model\AttributeModel;
use App\Model\CategoriesModel;
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

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::OFFICER);
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/groups/list")
     * )
     */
    public function groupsListAction(Request $request)
    {

    }
}