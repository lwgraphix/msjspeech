<?php

namespace App\Controller;

use App\Model\PagesModel;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\UserType;
use Silex\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

use DDesrosiers\SilexAnnotations\Annotations as SLX;
use Symfony\Component\HttpFoundation\Response;

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
        $page = $this->pm->getBySlug('home');
        return $this->out($this->twig->render('page.twig', ['page' => $page]));
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
     *     @SLX\Request(method="GET", uri="/attachment/{userAttrId}")
     * )
     */
    public function attachmentShowAction(Request $request, $userAttrId)
    {
        if ($request->get('user_id') && Security::getUser()->getRole() >= UserType::OFFICER)
        {
            $userId = $request->get('user_id');
        }
        else
        {
            $userId = Security::getUser()->getId();
        }

        $attachmentPath = Model::get('attachment')->getUserAttachment($userId, $userAttrId);
        if (!$attachmentPath)
        {
            FlashMessage::set(false, 'Attachment not found');
            return new RedirectResponse('/');
        }
        else
        {
            $response = new Response();
            $response->headers->add([
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename=attachment.pdf',
                'Accept-Ranges' => 'bytes',
                'Content-Transfer-Encoding' => 'binary',
                'Expires' => 0,
                'Cache-Control' => 'must-revalidate',
                'Pragma' => 'public',
                'Content-Length' => filesize($attachmentPath)

            ]);
            $response->setContent(file_get_contents($attachmentPath));
            return $response;
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