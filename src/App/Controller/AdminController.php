<?php

namespace App\Controller;
use App\Model\AlertModel;
use App\Model\AttributeModel;
use App\Model\UserModel;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\SystemSettings;
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
class AdminController extends BaseController {

    /**
     * @var UserModel
     */
    private $um;

    /**
     * @var AttributeModel
     */
    private $am;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::OFFICER);
        $this->um = Model::get('user');
        $this->am = Model::get('attribute');
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
        return $this->out($this->twig->render('admin/settings.twig'));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/settings")
     * )
     */
    public function settingsSaveListAction(Request $request)
    {
        foreach($request->request->all() as $name => $value)
        {
            // check equals
            $oldValue = SystemSettings::getInstance()->get($name);
            if ($oldValue == $value) continue;

            if ($name == 'payment_allowed' && $value == 1 && empty($request->get('stripe_key')))
            {
                FlashMessage::set(false, 'Can\'t enable payment without stripe key!');
                return new RedirectResponse('/admin/settings');
            }

            SystemSettings::getInstance()->set($name, $value);
        }

        FlashMessage::set(true, 'Settings updated');
        return new RedirectResponse('/admin/settings');
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