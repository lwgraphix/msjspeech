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
class AdminTournamentController extends BaseController {

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
        Security::setAccessLevel(UserType::OFFICER);
        $this->tm = Model::get('tournaments');
        $this->am = Model::get('attribute');
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

        if (!$tournament['tournament'])
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/');
        }

        if (DateUtil::isPassed($tournament['tournament']['event_start']))
        {
            FlashMessage::set(false, 'Tournament is started! You can\'t edit this tournament.');
            return new RedirectResponse('/');
        }

        return $this->out($this->twig->render('admin/tournament/edit.twig', [
            'data' => $tournament,
            'attributes' => $this->am->getAll(AttributeGroupType::TOURNAMENT, $tournamentId),
            'attribute_types' => AttributeType::NAMES
        ]));
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

        if (DateUtil::isPassed($tournament['tournament']['event_start']))
        {
            FlashMessage::set(false, 'Tournament is started! You can\'t edit this tournament.');
            return new RedirectResponse('/');
        }

        // persist debates
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
                    $field['editable'],
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
                    $field['editable']
                );
            }
        }

        $this->tm->update(
            $tournamentId,
            $request->get('name'),
            $request->get('reg_start'),
            $request->get('reg_deadline'),
            $request->get('drop_deadline'),
            $request->get('approve_method')
        );

        FlashMessage::set(true, 'Tournament data updated!');
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