<?php

namespace App\Controller;
use App\Model\AlertModel;
use App\Model\AttributeModel;
use App\Model\CategoriesModel;
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
class AdminPagesController extends BaseController {

    /**
     * @var CategoriesModel
     */
    private $cm;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::OFFICER);
        $this->cm = Model::get('categories');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/pages/create")
     * )
     */
    public function pageCreateAction(Request $request)
    {
        return $this->out($this->twig->render('admin/pages/create.twig'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/pages/categories")
     * )
     */
    public function categoriesAction(Request $request)
    {
        $categories = $this->cm->getAll();
        return $this->out($this->twig->render('admin/pages/categories.twig', ['categories' => $categories]));
    }
}