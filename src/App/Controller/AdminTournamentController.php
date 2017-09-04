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
use App\Type\EventStatusType;
use App\Type\LinkType;
use App\Code\ProtocolCode;
use App\Type\TournamentType;
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
class AdminTournamentController extends BaseController {

    /**
     * @var TournamentsModel
     */
    private $tm;

    /**
     * @var AttributeModel
     */
    private $am;

    /**
     * @var GroupModel
     */
    private $gm;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::OFFICER);
        $this->tm = Model::get('tournaments');
        $this->am = Model::get('attribute');
        $this->gm = Model::get('group');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/tournament/create")
     * )
     */
    public function tournamentCreateAction(Request $request)
    {
        $userGroups = $this->gm->getAll();
        return $this->out($this->twig->render('admin/tournament/create.twig', ['groups' => $userGroups]));
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
            $request->get('approve_method'),
            $request->get('events'),
            $request->get('description'),
            $request->get('start_date'),
            $request->get('end_date'),
            $request->get('private'),
            $request->get('groups'),
            $request->get('double_entry')
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

        if (!$tournament['tournament'])
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        if ($tournament['tournament']['status'] == TournamentType::CANCELLED)
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        $isStarted = DateUtil::isPassed($tournament['tournament']['event_start']);
        $userGroups = $this->gm->getAll();

        return $this->out($this->twig->render('admin/tournament/edit.twig', [
            'data' => $tournament,
            'attributes' => $this->am->getAll(AttributeGroupType::TOURNAMENT, $tournamentId),
            'attribute_types' => AttributeType::NAMES,
            'is_started' => $isStarted,
            'groups' => $userGroups
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/tournament/edit/{tournamentId}/group")
     * )
     */
    public function tournamentGroupPersistAction(Request $request, $tournamentId)
    {
        $tournament = $this->tm->getById($tournamentId);
        if (!$tournament['tournament'])
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        if ($tournament['tournament']['status'] == TournamentType::CANCELLED)
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        $tournamentStarted = DateUtil::isPassed($tournament['tournament']['event_start']);
        if (!$tournamentStarted)
        {
            if ($request->get('check') == 0)
            {
                $this->tm->deleteGroupLink($tournamentId, $request->get('group_id'));
            }
            else
            {
                $this->tm->createGroupLink($tournamentId, $request->get('group_id'));
            }
        }
        else
        {
            return $this->out('no');
        }

        return $this->out('ok');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/tournament/edit/{tournamentId}")
     * )
     */
    public function tournamentEditPersistAction(Request $request, $tournamentId)
    {
        $tournament = $this->tm->getById($tournamentId);
        if (!$tournament['tournament'])
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        if ($tournament['tournament']['status'] == TournamentType::CANCELLED)
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        $tournamentStarted = DateUtil::isPassed($tournament['tournament']['event_start']);

        // persist debates
        if (!$tournamentStarted)
        {
            foreach($request->get('events') as $field)
            {
                if (!isset($field['id']))
                {
                    // create
                    $this->tm->createEvent($tournamentId, $field['dt_name'], $field['dt_type'], $field['dt_cost'], $field['dt_drop_cost']);
                }
                else
                {
                    // update
                    $this->tm->updateEvent($field['id'], $field['dt_name'], $field['dt_type'], $field['dt_cost'], $field['dt_drop_cost']);
                }
            }

            // persist fields
            foreach($request->get('fields') as $field)
            {
                if (!isset($field['id']))
                {
                    // create
                    $this->am->create(
                        AttributeGroupType::TOURNAMENT,
                        $field['label'],
                        $field['placeholder'],
                        $field['help_text'],
                        $field['type'],
                        (isset($field['dropdown_item'])) ? $field['dropdown_item'] : null,
                        $field['required'],
                        0,
                        $tournamentId
                    );
                }
                else
                {
                    // update
                    $this->am->update(
                        $field['id'],
                        $field['label'],
                        $field['placeholder'],
                        $field['help_text'],
                        (isset($field['dropdown_item'])) ? $field['dropdown_item'] : null,
                        $field['required'],
                        0
                    );
                }
            }
        }

        $regStart = ($tournamentStarted) ? $tournament['tournament']['event_start'] : $request->get('reg_start');

        $this->tm->update(
            $tournamentId,
            $request->get('name'),
            $regStart,
            $request->get('reg_deadline'),
            $request->get('drop_deadline'),
            $request->get('approve_method'),
            $request->get('description'),
            $request->get('start_date'),
            $request->get('end_date'),
            intval($request->get('private')),
            intval($request->get('double_entry'))
        );

        FlashMessage::set(true, 'Tournament data updated!');
        return $this->out('ok');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/tournament/delete/{tournamentId}")
     * )
     */
    public function tournamentDeleteAction(Request $request, $tournamentId)
    {
        $tournament = $this->tm->getById($tournamentId);
        if (!$tournament['tournament'])
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        if ($tournament['tournament']['status'] == TournamentType::CANCELLED)
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        $tournamentStarted = DateUtil::isPassed($tournament['tournament']['event_start']);
        if (!$tournamentStarted)
        {
            $this->tm->delete($tournamentId);
        }
        else
        {
            if (Security::getUser()->getRole() == UserType::ADMINISTRATOR)
            {
                $this->tm->delete($tournamentId, true);
            }
            else
            {
                FlashMessage::set(false, 'Access denied');
            }
        }

        return new Response('ok');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/tournament/members/{tournamentId}")
     * )
     */
    public function tournamentMembersList(Request $request, $tournamentId)
    {
        $requestedStatus = $request->get('event', 0);
        $list = $this->tm->getMembersList($tournamentId, null, $requestedStatus);
        $tournamentData = $this->tm->getById($tournamentId);

        if (!$tournamentData['tournament'])
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        if ($tournamentData['tournament']['status'] == TournamentType::CANCELLED)
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        return $this->out($this->twig->render('admin/tournament/members.twig', [
            'list' => $list,
            'event_statuses' => EventStatusType::NAMES,
            'tournament' => $tournamentData
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/tournament/approve/{tournamentId}/decision")
     * )
     */
    public function tournamentSetDecisionAction(Request $request, $tournamentId)
    {
        $tournamentData = $this->tm->getById($tournamentId);

        if (!$tournamentData['tournament'])
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        if ($tournamentData['tournament']['status'] == TournamentType::CANCELLED)
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        $this->tm->setDecision($request->get('id'), $request->get('state'));
        return $this->out('ok');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/tournament/edit/{tournamentId}/delete")
     * )
     */
    public function tournamentDeleteEntityAction(Request $request, $tournamentId)
    {
        $tournament = $this->tm->getById($tournamentId);

        if (!$tournament['tournament'])
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        if ($tournament['tournament']['status'] == TournamentType::CANCELLED)
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        if (DateUtil::isPassed($tournament['tournament']['event_start']))
        {
            FlashMessage::set(false, 'Tournament is started! You can\'t edit this tournament.');
            return new RedirectResponse('/');
        }

        $type = $request->get('type');
        if ($type == 'event')
        {
            $this->tm->deleteEvent($request->get('id'));
        }
        else
        {
            $this->am->delete($request->get('id'), AttributeGroupType::TOURNAMENT);
        }

        return new Response('ok');
    }
}