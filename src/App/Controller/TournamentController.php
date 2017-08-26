<?php

namespace App\Controller;

use App\Code\StatusCode;
use App\Model\TournamentsModel;
use App\Model\UserModel;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\UserType;
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
class TournamentController extends BaseController
{

    /**
     * @var TournamentsModel
     */
    private $tm;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->tm = Model::get('tournaments');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/tournament/list")
     * )
     */
    public function tournamentListAction(Request $request)
    {
        $tournaments = $this->tm->getAll();
        return $this->out($this->twig->render('admin/tournament/list.twig', [
            'tournaments' => $tournaments
        ]));
    }
}