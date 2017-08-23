<?php

namespace App\Controller;

use App\Model\PagesModel;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\UserType;
use Silex\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

use DDesrosiers\SilexAnnotations\Annotations as SLX;

/**
 * Class CallsController
 * @package App\Controller
 * @SLX\Controller()
 */
class IndexController extends BaseController
{

    /**
     * @var PagesModel
     */
    private $pm;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->pm = Model::get('pages');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/")
     * )
     */
    public function indexAction(Request $request)
    {
        return $this->out($this->twig->render('index.twig'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/pages/{slug}")
     * )
     */
    public function pageViewAction(Request $request, $slug)
    {
        $page = $this->pm->getBySlug($slug);
        if (!$page)
        {
            return new RedirectResponse('/');
        }
        else
        {
            if ($page['public'] || Security::getUser() !== null)
            {
                return $this->out($this->twig->render('page.twig', ['page' => $page]));
            }
            else
            {
                return new RedirectResponse('/');
            }
        }
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/pages/{slug}/edit")
     * )
     */
    public function pageEditAction(Request $request, $slug)
    {
        Security::setAccessLevel(UserType::OFFICER);
        $page = $this->pm->getBySlug($slug);
        if (!$page)
        {
            return new RedirectResponse('/');
        }
        else
        {
            return $this->out($this->twig->render('admin/pages/create.twig', [
                'page' => $page,
                'edit_mode' => true,
                'categories' => Model::get('categories')->getAll(),
                'history' => Model::get('pages')->getHistory($page['id'])
            ]));
        }
    }
}