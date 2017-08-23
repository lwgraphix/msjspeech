<?php

namespace App\Controller;
use App\Code\StatusCode;
use App\Model\AlertModel;
use App\Model\AttributeModel;
use App\Model\CategoriesModel;
use App\Model\PagesModel;
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

    /**
     * @var PagesModel
     */
    private $pm;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::OFFICER);
        $this->cm = Model::get('categories');
        $this->pm = Model::get('pages');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/pages/create")
     * )
     */
    public function pageCreateAction(Request $request)
    {
        $categories = $this->cm->getAll();
        return $this->out($this->twig->render('admin/pages/create.twig', [
            'categories' => $categories
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/pages/create")
     * )
     */
    public function pageCreatePersistAction(Request $request)
    {
        $categories = $this->cm->getAll();
        $status = $this->pm->create(
            $request->get('title'),
            $request->get('slug'),
            $request->get('category_id'),
            $request->get('is_public'),
            $request->get('content'),
            Security::getUser()->getId()
        );

        if ($status !== true && $status == StatusCode::PAGE_SLUG_EXISTS)
        {
            FlashMessage::set(false, 'Page with this slug exists! Choose another slug for page and try again.');
            return $this->out($this->twig->render('admin/pages/create.twig', [
                'categories' => $categories
            ]));
        }
        else
        {
            return new RedirectResponse('/pages/' . $request->get('slug'));
        }
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

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/pages/categories/create")
     * )
     */
    public function categoriesCreateAction(Request $request)
    {
        $status = $this->cm->create($request->get('name'), $request->get('parent_id'));
        if ($status !== true && $status == StatusCode::CATEGORY_MAX_DEPTH)
        {
            FlashMessage::set(false, 'Category max depth reached! Category don\'t created.');

        }
        else
        {
            FlashMessage::set(true, 'Category <b>'. $request->get('name') .'</b> created');
        }

        return new RedirectResponse('/admin/pages/categories');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/pages/categories/delete")
     * )
     */
    public function categoriesDeleteAction(Request $request)
    {
        $status = $this->cm->delete($request->get('id'));

        if ($status !== true && $status == StatusCode::CATEGORY_IS_PARENT)
        {
            FlashMessage::set(false, 'Category have childrens! Remove all childrens of category and try again.');
            return new RedirectResponse('/admin/pages/categories');
        }
        else
        {
            FlashMessage::set(true, 'Category deleted');
        }

        return new RedirectResponse('/admin/pages/categories');
    }
}