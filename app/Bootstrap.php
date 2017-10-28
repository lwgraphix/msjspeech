<?php

use App\Authorization\AccessDecision;
use App\Authorization\AuthenticationManager;
use App\Authorization\TokenStorage;
use App\Connector\DB;
use App\Connector\MSSQL;
use App\Connector\MySQL;
use App\Provider\Model;
use App\Util\Debug;
use Doctrine\Common\Annotations\AnnotationReader;

class Bootstrap
{
    public static function getApplication()
    {
        $configuration = self::getConfiguration();
        $app = new Silex\Application($configuration);
        self::register($app);
        return $app;
    }

    private static function getConfiguration()
    {
        $systemCfgPath = __DIR__ . '/config/system.ini';
        if (!file_exists($systemCfgPath)) {
            Debug::stop('Configuration file not found: ' . ($systemCfgPath));
        }

        $systemCfg = @parse_ini_file($systemCfgPath);
        if (!isset($systemCfg['debug'])) {
            Debug::stop('Configuration file is broken: ' . ($systemCfgPath));
        }

        $config = array(
            'debug' => $systemCfg['debug'] == 'on',
            'copy_mode' => $systemCfg['copy_mode'] == 'on'
        );

        if ($config['debug']) Debug::enable();

        return $config;
    }

    /**
     * @param \Silex\Application $app
     */
    private static function register(Silex\Application &$app)
    {
        $app->register(new Silex\Provider\SessionServiceProvider(), []);
        self::registerAnnotations($app);
        MySQL::loadApplication($app);
        self::registerTwig($app);
    }

    private static function registerTwig(Silex\Application &$app)
    {
        $app->register(new Silex\Provider\TwigServiceProvider(), [
            'twig.path' => __DIR__ . '/../src/App/views',
            'twig.options' => [
                'cache' => __DIR__ . '/../_cache',
            ],
        ]);
    }

    private static function registerAnnotations(Silex\Application &$app)
    {
        /** Annotation route service */
        $app->register(new DDesrosiers\SilexAnnotations\AnnotationServiceProvider(), array(
            "annot.cache" => new \Doctrine\Common\Cache\ApcuCache(),
            "annot.controllerDir" => __DIR__ . "/../src/App/Controller",
            "annot.controllerNamespace" => "App\\Controller\\"
        ));

        $ignoredAnnotations = ['apiVersion', 'apiName', 'apiGroup', 'apiSuccess', 'apiSuccessExample', 'apiParam', 'apiDescription'];
        foreach($ignoredAnnotations as $annotation) {
            AnnotationReader::addGlobalIgnoredName($annotation);
        }
    }
}