<?php

namespace App\Controller;

use App\Code\StatusCode;
use App\Model\AttributeModel;
use App\Model\TournamentsModel;
use App\Model\UserModel;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\UserType;
use App\Util\DateUtil;
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

    /**
     * @var AttributeModel
     */
    private $am;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->tm = Model::get('tournaments');
        $this->am = Model::get('attribute');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/tournament/list")
     * )
     */
    public function tournamentListAction(Request $request)
    {
        $tournaments = $this->tm->getAll();
        return $this->out($this->twig->render('user/tournament/list.twig', [
            'tournaments' => $tournaments
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/tournament/join/{tournamentId}")
     * )
     */
    public function tournamentJoinAction(Request $request, $tournamentId)
    {
        $tournament = $this->tm->getById($tournamentId);

        // todo: check rejoin

        if (!$tournament['tournament'])
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/tournament/list');
        }

        if (!DateUtil::isPassed($tournament['tournament']['event_start']))
        {
            FlashMessage::set(false, 'Tournament registration is not started! You can\'t join this tournament.');
            return new RedirectResponse('/tournament/list');
        }

        if (DateUtil::isPassed($tournament['tournament']['entry_deadline']))
        {
            FlashMessage::set(false, 'Tournament registration is ended! You can\'t join this tournament.');
            return new RedirectResponse('/tournament/list');
        }

        return $this->out($this->twig->render('user/tournament/join.twig', [
            'tournament' => $tournament['tournament'],
            'events' => $tournament['events'],
            'attributes' => $this->am->getAll(AttributeGroupType::TOURNAMENT, $tournamentId),
            'users' => Model::get('user')->getAll()
        ]));
    }
}