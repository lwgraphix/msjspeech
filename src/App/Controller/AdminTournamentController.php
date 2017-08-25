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
use Silex\Application;
use DDesrosiers\SilexAnnotations\Annotations as SLX;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AlertController
 * @package App\Controller
 * @SLX\Controller(prefix="/admin")
 */
class AdminTournamentController extends BaseController {

    /**
     * @var TournamentsModel
     */
    private $tm;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::OFFICER);
        $this->tm = Model::get('tournaments');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/tournament/create")
     * )
     */
    public function tournamentCreateAction(Request $request)
    {
        return $this->out($this->twig->render('admin/tournament/create.twig'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/tournament/create")
     * )
     */
    public function tournamentCreatePersistAction(Request $request)
    {
        $tId = $this->tm->create(
            $request->get('name'),
            $request->get('reg_start'),
            $request->get('reg_deadline'),
            $request->get('drop_deadline'),
            $request->get('events')
        );

        return $this->out(json_encode(['id' => $tId]), true);
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/tournament/edit/{tournamentId}")
     * )
     */
    public function tournamentEditAction(Request $request, $tournamentId)
    {
        $tournament = $this->tm->getById($tournamentId);
        if (!$tournament)
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        return $this->out($this->twig->render('admin/tournament/edit.twig', [
            'data' => $tournament,
            'attributes' => [],
        ]));
    }
}