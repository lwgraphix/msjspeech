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
     *     @SLX\Request(method="GET", uri="/pages/list")
     * )
     */
    public function pagesListAction(Request $request)
    {
        $pages = $this->pm->getAll();
        return $this->out($this->twig->render('admin/pages/list.twig', [
            'pages' => $pages
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/pages/delete")
     * )
     */
    public function deletePageAction(Request $request)
    {
        $page = $this->pm->getById($request->get('id'));
        if ($page['slug'] == 'terms' || $page['slug'] == 'home')
        {
            FlashMessage::set(false, 'Access denied');
            return new RedirectResponse('/admin/pages/list');
        }

        $this->pm->delete($request->get('id'));
        FlashMessage::set(true, 'Page deleted');
        return new RedirectResponse('/admin/pages/list');
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
            trim($request->get('title')),
            trim($request->get('slug')),
            $request->get('category_id'),
            $request->get('is_public'),
            $request->get('content'),
            Security::getUser()->getId()
        );

        if ($status !== true && $status == StatusCode::PAGE_SLUG_EXISTS)
        {
            FlashMessage::set(false, 'This slug is already used by another page.');
            return $this->out($this->twig->render('admin/pages/create.twig', [
                'flash_message' => FlashMessage::get(),
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
     *     @SLX\Request(method="POST", uri="/pages/update")
     * )
     */
    public function pagesUpdateAction(Request $request)
    {
        $page = $this->pm->getById($request->get('id'));
        $categories = $this->cm->getAll();
        $status = $this->pm->update(
            $request->get('id'),
            trim($request->get('title')),
            trim($request->get('slug')),
            $request->get('category_id'),
            $request->get('is_public'),
            $request->get('content'),
            $request->get('reason'),
            Security::getUser()->getId(),
            $page
        );

        if ($status !== true && $status == StatusCode::PAGE_SLUG_EXISTS)
        {
            FlashMessage::set(false, 'This slug is already used by another page.');
            return $this->out($this->twig->render('admin/pages/create.twig', [
                'flash_message' => FlashMessage::get(),
                'bad_submit' => true,
                'edit_mode' => true,
                'page' => $page,
                'categories' => $categories,
                'history' => $this->pm->getHistory($request->get('id'))
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
        $tree = $this->cm->buildTree($categories);
        return $this->out($this->twig->render('admin/pages/categories.twig', [
            'categories' => $categories,
            'tree' => $tree
        ]));
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
            FlashMessage::set(false, 'Category max depth reached! Category not created.');

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
            FlashMessage::set(false, 'Category have children! Remove all children of category and try again.');
            return new RedirectResponse('/admin/pages/categories');
        }
        else
        {
            FlashMessage::set(true, 'Category deleted');
        }

        return new RedirectResponse('/admin/pages/categories');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/pages/categories/change")
     * )
     */
    public function categoriesChangeAction(Request $request)
    {
        $this->cm->update($request->get('id'), $request->get('name'));
        FlashMessage::set(true, 'Category name changed');
        return new RedirectResponse('/admin/pages/categories');
    }
}