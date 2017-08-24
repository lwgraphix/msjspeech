<?php

namespace App\Controller;
use App\Code\StatusCode;
use App\Model\AlertModel;
use App\Model\AttributeModel;
use App\Model\CategoriesModel;
use App\Model\PagesModel;
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
     * @var AttributeModel
     */
    private $am;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        Security::setAccessLevel(UserType::OFFICER);
        $this->am = Model::get('attribute');
    }

    /**
     * @SLX\Route(
     *     @SLX\Request(method="GET", uri="/tournament/create")
     * )
     */
    public function tournamentCreateAction(Request $request)
    {
        $attributes = $this->am->getAll(AttributeGroupType::TOURNAMENT);

        return $this->out($this->twig->render('admin/tournament/create.twig', [
            'attribute_types' => AttributeType::NAMES,
            'attributes' => $attributes
        ]));
    }
}