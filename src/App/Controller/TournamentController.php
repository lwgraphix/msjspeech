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
use App\Type\EventStatusType;
use App\Type\EventType;
use App\Type\TournamentType;
use App\Type\UserType;
use App\Util\DateUtil;
use App\Provider\SystemSettings;
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
        Security::setAccessLevel(UserType::SUSPENDED);
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/tournament/list")
     * )
     */
    public function tournamentListAction(Request $request)
    {
        $tournaments = $this->tm->getUserAvailableTournaments(Security::getUser()->getId());
        $history = $this->tm->getUserTournaments(Security::getUser()->getId());
        return $this->out($this->twig->render('user/tournament/list.twig', [
            'tournaments' => $tournaments,
            'history' => $history,
            'event_status' => EventStatusType::NAMES,
            'event_colors' => EventStatusType::COLORS
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/tournament/view/{eventId}")
     * )
     */
    public function tournamentViewAction(Request $request, $eventId)
    {
        if (Security::getUser()->getRole() == UserType::SUSPENDED)
        {
            FlashMessage::set(false, 'Your account is suspended');
            return new RedirectResponse($request->headers->get('referer'));
        }
        
        $eventInfo = $this->tm->getUserEventInfo($eventId);
        if (!$eventInfo)
        {
            FlashMessage::set(false, 'Record not found');
            return new RedirectResponse('/tournament/list');
        }
        else
        {
            if ($eventInfo['tournament_status'] == TournamentType::CANCELLED)
            {
                FlashMessage::set(false, 'Record not found');
                return new RedirectResponse('/tournament/list');
            }

            if ($request->get('user_id') !== null && Security::getUser()->getRole() >= UserType::OFFICER)
            {
                $adminMode = true;
            }
            else
            {
                $adminMode = false;
                // check access to tournament data by partner and owner
                if (Security::getUser()->getId() != $eventInfo['user_id'])
                {
                    if (Security::getUser()->getId() != $eventInfo['partner_id'])
                    {
                        FlashMessage::set(false, 'Record not found');
                        return new RedirectResponse('/tournament/list');
                    }
                }
            }
            
            $attributes = $this->am->getUserAttributes($eventInfo['user_id'], AttributeGroupType::TOURNAMENT, $eventInfo['user_event_id']);
            return $this->out($this->twig->render('user/tournament/view.twig', [
                'event' => $eventInfo,
                'attributes' => $attributes,
                'admin_mode' => $adminMode,
                'event_statuses' => EventStatusType::NAMES,
                'event_colors' => EventStatusType::COLORS
            ]));
        }
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/tournament/drop/{eventId}")
     * )
     */
    public function tournamentDropAction(Request $request, $eventId)
    {
        if (Security::getUser()->getRole() == UserType::SUSPENDED)
        {
            FlashMessage::set(false, 'Your account is suspended');
            return new RedirectResponse($request->headers->get('referer'));
        }

        $eventInfo = $this->tm->getUserEventInfo($eventId);
        if (!$eventInfo)
        {
            FlashMessage::set(false, 'Record not found');
            return $this->out('ok');
        }
        else
        {
            if ($eventInfo['tournament_status'] == TournamentType::CANCELLED)
            {
                FlashMessage::set(false, 'Record not found');
                return $this->out('ok');
            }

            // if event drop by another user
            if ($eventInfo['user_id'] != Security::getUser()->getId() && $eventInfo['partner_id'] != Security::getUser()->getId())
            {
                FlashMessage::set(false, 'You can\'t do that.');
                return $this->out('no');
            }

            $allowedStatusesToDrop = [
                EventStatusType::WAITING_FOR_APPROVE,
                EventStatusType::APPROVED,
                EventStatusType::WAITING_PARTNER_RESPONSE
            ];

            if (!in_array($eventInfo['event_status'], $allowedStatusesToDrop))
            {
                FlashMessage::set(false, 'Cant drop this tournament');
                return $this->out('no');
            }
            else
            {
                $this->tm->drop(Security::getUser(), $eventInfo);
                return $this->out('ok');
            }
        }
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/tournament/view/{eventId}")
     * )
     */
    public function tournamentPartnerDecisionAction(Request $request, $eventId)
    {
        if (Security::getUser()->getRole() == UserType::SUSPENDED)
        {
            FlashMessage::set(false, 'Your account is suspended');
            return new RedirectResponse($request->headers->get('referer'));
        }

        $eventInfo = $this->tm->getUserEventInfo($eventId);
        if (!$eventInfo)
        {
            FlashMessage::set(false, 'Record not found');
            return $this->out('ok');
        }
        else
        {
            if ($eventInfo['tournament_status'] == TournamentType::CANCELLED)
            {
                FlashMessage::set(false, 'Record not found');
                return $this->out('ok');
            }

            if ($eventInfo['partner_id'] != Security::getUser()->getId())
            {
                FlashMessage::set(false, 'Accept event failed');
                return $this->out('ok');
            }

            if ($eventInfo['event_status'] == EventStatusType::WAITING_PARTNER_RESPONSE && !DateUtil::isPassed($eventInfo['entry_deadline']))
            {
                $balance = Security::getUser()->getBalance();
                if (SystemSettings::getInstance()->get('negative_balance') == 0 && $balance < $eventInfo['cost'] && intval($request->get('decision')) == 1)
                {
                    FlashMessage::set(false, 'Not enough money for join this debate. Please <a target="_blank" href="/user/balance">deposit</a> money and try join again');
                    return $this->out('no');
                }
                else
                {
                    $userEntryCount = $this->tm->getUserEntryCount(Security::getUser()->getId(), $eventInfo['tournament_id']);
                    if ($eventInfo['double_entry'] == 0 && $userEntryCount > 0 && intval($request->get('decision')) == 1)
                    {
                        FlashMessage::set(false, 'Double entry not allowed at this tournament');
                        return $this->out('no');
                    }

                    $status = $this->tm->setPartnerDecision($eventId, $eventInfo, intval($request->get('decision')));
                    if (!$status)
                    {
                        FlashMessage::set(false, 'You have already registered for this event with another partner.');
                        return $this->out('no');
                    }
                    else
                    {
                        FlashMessage::set(true, 'Your decision applied');
                    }

                    return $this->out('ok');
                }
            }
            else
            {
                FlashMessage::set(false, 'Accept event failed');
                return $this->out('ok');
            }
        }
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/tournament/join/{tournamentId}")
     * )
     */
    public function tournamentJoinAction(Request $request, $tournamentId)
    {
        if (Security::getUser()->getRole() == UserType::SUSPENDED)
        {
            FlashMessage::set(false, 'Your account is suspended');
            return new RedirectResponse($request->headers->get('referer'));
        }

        $tournament = $this->tm->getById($tournamentId);

        if (!$tournament['tournament'])
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/tournament/list');
        }

        if ($tournament['tournament']['status'] == TournamentType::CANCELLED)
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/tournament/list');
        }

        if (!DateUtil::isPassed($tournament['tournament']['event_start']))
        {
            FlashMessage::set(false, 'Registration for this tournament is not open yet.');
            return new RedirectResponse('/tournament/list');
        }

        if (DateUtil::isPassed($tournament['tournament']['entry_deadline']))
        {
            FlashMessage::set(false, 'The registration deadline for this tournament has passed');
            return new RedirectResponse('/tournament/list');
        }

        if (!$this->tm->isUserAllowedToJoin($tournamentId, Security::getUser()->getId(), $tournament['tournament']['private']))
        {
            FlashMessage::set(false, 'You are not allowed to register for this tournament.');
            return new RedirectResponse('/tournament/list');
        }

        $users = ($tournament['tournament']['private'] == 0) ? Model::get('user')->getAllActive() : Model::get('user')->getAllAllowedToPrivateTournament($tournamentId);

        return $this->out($this->twig->render('user/tournament/join.twig', [
            'tournament' => $tournament['tournament'],
            'events' => $tournament['events'],
            'attributes' => $this->am->getAll(AttributeGroupType::TOURNAMENT, $tournamentId),
            'users' => $users
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/tournament/join/{tournamentId}")
     * )
     */
    public function tournamentJoinPersistAction(Request $request, $tournamentId)
    {
        if (Security::getUser()->getRole() == UserType::SUSPENDED)
        {
            FlashMessage::set(false, 'Your account is suspended');
            return new RedirectResponse($request->headers->get('referer'));
        }

        $tournament = $this->tm->getById($tournamentId);

        if (!$tournament['tournament'])
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/tournament/list');
        }

        if ($tournament['tournament']['status'] == TournamentType::CANCELLED)
        {
            FlashMessage::set(false, 'Tournament not found');
            return new RedirectResponse('/tournament/list');
        }

        if (!DateUtil::isPassed($tournament['tournament']['event_start']))
        {
            FlashMessage::set(false, 'Registration for this tournament is not open yet.');
            return new RedirectResponse('/tournament/list');
        }

        if (DateUtil::isPassed($tournament['tournament']['entry_deadline']))
        {
            FlashMessage::set(false, 'The registration deadline for this tournament has passed');
            return new RedirectResponse('/tournament/list');
        }

        if (!$this->tm->isUserAllowedToJoin($tournamentId, Security::getUser()->getId(), $tournament['tournament']['private']))
        {
            FlashMessage::set(false, 'You are not allowed to register for this tournament.');
            return new RedirectResponse('/tournament/list');
        }

        $userEntryCount = $this->tm->getUserEntryCount(Security::getUser()->getId(), $tournament['tournament']['id']);
        if ($tournament['tournament']['double_entry'] == 0 && $userEntryCount > 0)
        {
            FlashMessage::set(false, 'Double entry not allowed at this tournament');
            return new RedirectResponse('/tournament/list');
        }

        $requiredFields = [
            'debate_type' => AttributeType::TEXT
        ];

        // adding custom required attributes to check
        $attributes = Model::get('attribute')->getAll(AttributeGroupType::TOURNAMENT, $tournamentId);
        foreach($attributes as $attribute)
        {
            if ($attribute['required'])
            {
                $requiredFields['attr_' . $attribute['id']] = $attribute['type'];
            }
        }

        foreach($requiredFields as $field => $attributeType)
        {
            if ($attributeType == AttributeType::ATTACHMENT)
            {
                /**
                 * @var $fld UploadedFile
                 */
                $fld = $request->files->get($field);
                if ($fld->getClientMimeType() != 'application/pdf')
                {
                    FlashMessage::set(false, 'File types other than PDF are not allowed.');
                    return new RedirectResponse($request->headers->get('referer'));
                }
            }
            else
            {
                $fld = $request->get($field);
            }

            if ($fld === null)
            {
                FlashMessage::set(false, 'You forgot to fill out one or more of the required fields. Please fill it out and try again.');
                return new RedirectResponse($request->headers->get('referer'));
            }
        }

        // event info
        $eventInfo = null;
        foreach($tournament['events'] as $e)
        {
            if ($e['id'] == $request->get('debate_type'))
            {
                $eventInfo = $e;
            }
        }

        if ($eventInfo === null)
        {
            FlashMessage::set(false, 'Selected event not found.');
            return new RedirectResponse($request->headers->get('referer'));
        }

        if ($this->tm->isJoined(Security::getUser()->getId(), $request->get('debate_type')))
        {
            FlashMessage::set(false, 'You have already registered for this event or you have a pending partner request for this event.');
            return new RedirectResponse($request->headers->get('referer'));
        }

        // check if not selected partner
        $partnerUser = null;
        if ($eventInfo['type'] == EventType::WITH_PARTNER)
        {
            if ($request->get('partner_id') === null)
            {
                FlashMessage::set(false, 'You need to select a partner to register for this event.');
                return new RedirectResponse($request->headers->get('referer'));
            }
            else
            {
                if ($request->get('partner_id') == Security::getUser()->getId())
                {
                    FlashMessage::set(false, 'You are not allowed to select yourself as your own partner.');
                    return new RedirectResponse($request->headers->get('referer'));
                }
                else
                {
                    if (!$this->tm->isUserAllowedToJoin($tournamentId, $request->get('partner_id'), $tournament['tournament']['private']))
                    {
                        FlashMessage::set(false, 'The partner you selected is not allowed to register for this tournament.');
                        return new RedirectResponse($request->headers->get('referer'));
                    }
                    else
                    {
                        $partnerUser = Model::get('user')->getById($request->get('partner_id'));
                        if (!$partnerUser)
                        {
                            FlashMessage::set(false, 'Partner not found');
                            return new RedirectResponse($request->headers->get('referer'));
                        }
                    }
                }
            }
        }

        //check balance
        if (SystemSettings::getInstance()->get('negative_balance') == 0 && $eventInfo['cost'] > Security::getUser()->getBalance())
        {
            FlashMessage::set(false, 'Not enough money for join this debate. Please <a target="_blank" href="/user/balance">deposit</a> money and try join again');
            return new RedirectResponse($request->headers->get('referer'));
        }
        else
        {
            $this->tm->join(
                array_merge($request->request->all(), $request->files->all()),
                $tournament['tournament'],
                $eventInfo,
                Security::getUser(),
                $partnerUser
            );

            FlashMessage::set(true, 'You have successfully registered for this tournament.');
            return new RedirectResponse('/tournament/list');
        }
    }
}