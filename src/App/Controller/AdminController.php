<?php

namespace App\Controller;
use App\Model\AlertModel;
use App\Model\AttributeModel;
use App\Model\EmailModel;
use App\Model\UserModel;
use App\Provider\Email;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\SystemSettings;
use App\Type\AttributeGroupType;
use App\Type\AttributeType;
use App\Type\EmailType;
use App\Type\LinkType;
use App\Code\ProtocolCode;
use App\Type\TransactionType;
use App\Type\UserType;
use Silex\Application;
use DDesrosiers\SilexAnnotations\Annotations as SLX;
use SimpleEmailService;
use SimpleEmailServiceMessage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AlertController
 * @package App\Controller
 * @SLX\Controller(prefix="/admin")
 */
class AdminController extends BaseController {

    /**
     * @var UserModel
     */
    private $um;

    /**
     * @var AttributeModel
     */
    private $am;

    /**
     * @var EmailModel
     */
    private $em;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::OFFICER);
        $this->um = Model::get('user');
        $this->am = Model::get('attribute');
        $this->em = Model::get('email');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/signup/edit")
     * )
     */
    public function editSignupPageAction(Request $request)
    {
        $attributes = $this->am->getAll(AttributeGroupType::REGISTER);

        return $this->out($this->twig->render('admin/signup_edit.twig', [
            'attribute_types' => AttributeType::NAMES,
            'attributes' => $attributes
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/signup/delete")
     * )
     */
    public function deleteSignupAttributeAction(Request $request)
    {
        $attributeId = $request->get('id');
        $this->am->delete($attributeId, AttributeGroupType::REGISTER);
        return $this->out('ok');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/settings")
     * )
     */
    public function settingsListAction(Request $request)
    {
        Security::setAccessLevel(UserType::ADMINISTRATOR);
        return $this->out($this->twig->render('admin/settings.twig'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/settings")
     * )
     */
    public function settingsSaveListAction(Request $request)
    {
        Security::setAccessLevel(UserType::ADMINISTRATOR);
        $changedFields = null;

        $oldBcc = SystemSettings::getInstance()->get('bcc_receiver');

        foreach($request->request->all() as $name => $value)
        {
            // check equals
            $oldValue = SystemSettings::getInstance()->get($name);
            if ($oldValue == $value) continue;

            if ($name == 'private_stripe_key')
            {
                if (!empty($oldValue) && empty($request->get('private_stripe_key'))) continue;
            }

            if ($name == 'public_stripe_key')
            {
                if (!empty($oldValue) && empty($request->get('public_stripe_key'))) continue;
            }

            if (
                $name == 'payment_allowed'
                && $value == 1
                && (empty($request->get('private_stripe_key')) && empty(SystemSettings::getInstance()->get('private_stripe_key')))
                && (empty($request->get('public_stripe_key')) && empty(SystemSettings::getInstance()->get('public_stripe_key')))
            )
            {
                FlashMessage::set(false, 'Can\'t enable payment without stripe keys!');
                return new RedirectResponse('/admin/settings');
            }

            $changedFields .= $name . ': from ' . $oldValue . ' to ' . $value . PHP_EOL;

            SystemSettings::getInstance()->set($name, $value);
        }

        Email::getInstance()->createMessage(EmailType::SYSTEM_SETTINGS_CHANGED, [
            'changed_fields' => $changedFields,
        ], Security::getUser(), $oldBcc);

        FlashMessage::set(true, 'Settings updated');
        return new RedirectResponse('/admin/settings');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/attachment/download/{attrId}")
     * )
     */
    public function getAllAttachmentsAction(Request $request, $attrId)
    {
        if (Security::getUser()->getRole() < UserType::OFFICER)
        {
            FlashMessage::set(false, 'Access denied');
            return new RedirectResponse('/');
        }
        else
        {
            $binaryArchive = Model::get('attachment')->getAllAttachmentZip($attrId);
            if (!$binaryArchive)
            {
                FlashMessage::set(false, 'Did not find files for this field');
                return new RedirectResponse($request->headers->get('referer'));
            }
            else
            {
                $response = new Response();
                $response->headers->add([
                    'Content-Type' => 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename=attachment_'. $attrId .'.zip',
                    'Content-Description' => 'File Transfer',
                    'Expires' => 0,
                    'Cache-Control' => 'must-revalidate',
                    'Pragma' => 'public',
                    'Content-Length' => $binaryArchive['size']

                ]);
                $response->setContent($binaryArchive['data']);
                return $response;
            }
        }
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/email/list")
     * )
     */
    public function emailTemplateListAction(Request $request)
    {
        $templates = EmailType::NAMES;
        return $this->out($this->twig->render('admin/email/list.twig', [
            'templates' => $templates
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/transactions/list")
     * )
     */
    public function transactionsListAction(Request $request)
    {
        $type = $request->get('type', 0);
        $types = TransactionType::NAMES;

        if ($type > count($types) - 1)
        {
            FlashMessage::set(false, 'Transaction type not exists');
            return new RedirectResponse('/admin/transactions/list');
        }

        $list = Model::get('transaction_history')->getHistoryByType($type);

        return $this->out($this->twig->render('admin/transactions_list.twig', [
            'types' => $types,
            'list' => $list
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/email/send/{type}")
     * )
     */
    public function massEmailSendAction(Request $request, $type)
    {
        $list = $this->em->getUsersByEmailType(
            $type,
            $request->get('group_id'),
            $request->get('tournament_id'),
            $request->get('event_id')
        );

        if ($list === false)
        {
            FlashMessage::set(false, 'No members in group');
            return new RedirectResponse($request->headers->get('referer'));
        }

        // generate appendix
        switch($type)
        {
            case 1:
                $appendix = 'to all users';
            break;

            case 2:
                $appendix = 'to member group "'. Model::get('group')->getById($request->get('group_id'))['name'] .'"';
            break;

            case 3:
                $names = Model::get('tournaments')->getNamesByTournament(
                    $request->get('tournament_id'),
                    $request->get('event_id')
                );
                $eventAppendix = (!empty($names['event'])) ? ', event "'. $names['event'] .'"' : '';
                $appendix = 'to applicants of tournament "'. $names['tournament'] .'"' . $eventAppendix;
            break;
        }

        return $this->out($this->twig->render('admin/mass_email_send.twig', [
            'list' => $list,
            'appendix' => $appendix,
            'roles' => UserType::NAMES
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/email/send/{type}")
     * )
     */
    public function massEmailSendPersistAction(Request $request, $type)
    {

        $list = $this->em->getUsersByEmailType(
            $type,
            $request->get('group_id'),
            $request->get('tournament_id'),
            $request->get('event_id')
        );

        if ($list === false)
        {
            FlashMessage::set(false, 'No members in group');
            return new RedirectResponse($request->headers->get('referer'));
        }

        // generate appendix
        switch($type)
        {
            case 1:
                $appendix = 'to all users';
                break;

            case 2:
                $appendix = 'to member group "'. Model::get('group')->getById($request->get('group_id'))['name'] .'"';
                break;

            case 3:
                $names = Model::get('tournaments')->getNamesByTournament(
                    $request->get('tournament_id'),
                    $request->get('event_id')
                );
                $eventAppendix = (!empty($names['event'])) ? ', event "'. $names['event'] .'"' : '';
                $appendix = 'to applicants of tournament "'. $names['tournament'] .'"' . $eventAppendix;
                break;
        }

        $sendToParents = $request->get('parents_send') == 'on';
        $sendToStudents = $request->get('students_send') == 'on';

        if ($sendToParents && $sendToStudents)
        {
            FlashMessage::set(false, 'You can choose only one "Send to" parameter!');
            return new RedirectResponse($request->headers->get('referer'));
        }

        $this->em->sendMassEmail(
            $list,
            $sendToParents,
            $sendToStudents,
            $request->get('subject'),
            $request->get('content'),
            $appendix,
            Security::getUser()
        );

        FlashMessage::set(true, 'Message sent');
        return new RedirectResponse($request->headers->get('referer'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/email/edit/{type}")
     * )
     */
    public function emailTemplateEditAction(Request $request, $type)
    {
        $templates = EmailType::NAMES;

        if ($type > count($templates) - 1)
        {
            FlashMessage::set(false, 'Email type does not exist');
            return new RedirectResponse('/admin/email/list');
        }

        $template = Email::getInstance()->getTemplate($type);

        return $this->out($this->twig->render('admin/email/edit.twig', [
            'templates' => $templates,
            'template_data' => $template
        ]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/email/edit/{type}")
     * )
     */
    public function emailTemplateEditPersistAction(Request $request, $type)
    {
        $templates = EmailType::NAMES;

        if ($type > count($templates) - 1)
        {
            FlashMessage::set(false, 'Email type does not exist');
            return new RedirectResponse('/admin/email/list');
        }

        $oldData = Email::getInstance()->getTemplate(EmailType::EMAIL_TEMPLATE_CHANGED);
        Email::getInstance()->updateTemplate($type, $request->get('subject'), $request->get('content'));

        Email::getInstance()->createMessage(EmailType::EMAIL_TEMPLATE_CHANGED, [
            'name' => $templates[$type],
            'old_subject' => $oldData['subject'],
            'old_body' => $oldData['content'],
            'new_subject' => $request->get('subject'),
            'new_body' => $request->get('content')
        ], Security::getUser(), SystemSettings::getInstance()->get('bcc_receiver'));

        FlashMessage::set(true, 'Template updated');
        return new RedirectResponse($request->headers->get('referer'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/signup/edit")
     * )
     */
    public function saveSignupPageAction(Request $request)
    {
        foreach($request->get('data') as $field)
        {
            if (!isset($field['id']))
            {
                // create
                $this->am->create(
                    AttributeGroupType::REGISTER,
                    $field['label'],
                    $field['placeholder'],
                    $field['help_text'],
                    $field['type'],
                    (isset($field['dropdown_item'])) ? $field['dropdown_item'] : null,
                    $field['required'],
                    $field['editable'],
                    (isset($field['tournament_id'])) ? $field['tournament_id'] : null
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

        FlashMessage::set(true, 'Fields updated!');
        return $this->out('ok');
    }
}