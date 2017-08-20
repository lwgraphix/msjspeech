<?php

namespace App\Controller;

use App\Code\StatusCode;
use App\Model\UserModel;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
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
class UserController extends BaseController
{

    /**
     * @var UserModel
     */
    private $um;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::SUSPENDED);
        $this->um = Model::get('user');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/user/profile")
     * )
     */
    public function profileAction(Request $request)
    {

        $attributes = Model::get('attribute')->getUserAttributes(Security::getUser()->getId());
        return $this->out($this->twig->render('user/profile.twig', ['attributes' => $attributes]));
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="POST", uri="/user/profile/save")
     * )
     */
    public function profileSaveAction(Request $request)
    {
        Security::setAccessLevel(UserType::SUSPENDED);

        $requiredFields = ['email', 'first_name', 'last_name'];
        // adding custom required attributes to check
        $attributes = Model::get('attribute')->getAll(AttributeGroupType::REGISTER);
        foreach($attributes as $attribute)
        {
            if ($attribute['required'])
            {
                $requiredFields[] = 'attr_' . $attribute['id'];
            }
        }

        foreach($requiredFields as $field)
        {
            if ($request->get($field) === null || empty($request->get($field)))
            {
                return $this->out(json_encode(['status' => false, 'message' => 'One of field is empty. Please fill all required fields and try again.']), true);
            }
        }

        // email validation
        if (!filter_var($request->get('email'), FILTER_VALIDATE_EMAIL))
        {
            return $this->out(json_encode(['status' => false, 'message' => 'Wrong email address format. Check the typing of email correct and try again.']), true);
        }

        if (!empty($request->get('parent_email')) && !filter_var($request->get('parent_email'), FILTER_VALIDATE_EMAIL))
        {
            return $this->out(json_encode(['status' => false, 'message' => 'Wrong parent email address format. Check the typing of email correct and try again.']), true);
        }

        $status = $this->um->update(Security::getUser(), $request->request->all());
        if ($status !== true)
        {
            if ($status == StatusCode::USER_EMAIL_EXISTS)
            {
                return $this->out(json_encode(['status' => false, 'message' => 'User with this email exists! Try another email or <a href="/auth/restore">restore</a> your account access.']), true);
            }
            else
            {
                return $this->out(json_encode(['status' => false, 'message' => 'Internal error. Please contact with administrator.']), true);
            }
        }
        else
        {
            Security::reloadUser($request->get('email'));
            return $this->out(json_encode(['status' => true]));
        }
    }
}