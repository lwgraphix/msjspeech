<?php

namespace App\Controller;
use App\Model\AlertModel;
use App\Model\UserModel;
use App\Provider\Model;
use App\Provider\Security;
use App\Type\LinkType;
use App\Code\ProtocolCode;
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


    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(5);
        $this->um = Model::get('user');
    }
}