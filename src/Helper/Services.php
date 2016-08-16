<?php
/**
 * Services
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */
namespace FhpPhalconGrid\Helper;

use FhpPhalconAuth\Entity\Role;
use FhpPhalconGrid\Grid\Action;
use FhpPhalconAuth\Rbac\Guard\RouterGuard;
use FhpPhalconAuth\Rbac\Rbac;
use Phalcon\Acl;
use Phalcon\Cache\Backend\File;
use Phalcon\Cache\Frontend\Output;
use Phalcon\Di;
use Phalcon\Di\FactoryDefault;
use Phalcon\Flash\Session;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Url;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;
use Phalcon\Queue\Beanstalk;
use Phalcon\Security;
use Phalcon\Session\Adapter\Files;

class Services
{

    /** @var FactoryDefault */
    public static $di;

    /**
     * @param Di|null $di
     * @throws \FhpPhalconGrid\Helper\Exception
     */
    public function __construct(Di $di = null)
    {
        if (Loader::$config === null) {
            throw new Exception('The class Loader has to called before Service first!');
        }

        if ($di === null) {
            self::$di = new FactoryDefault();
        } else {
            self::$di = $di;
        }
    }

    /**
     * @return Services $this
     */
    public function registerAll()
    {
        $this->registerEventsManager();
        $this->registerRouter();
        $this->registerCache();
        $this->registerUrl();
        $this->registerDispatcher();
        $this->registerView();
        $this->registerDb();
        $this->registerModelsManager();
        $this->registerModelsMetadata();
        $this->registerSession();
        $this->registerFlash();
        $this->registerSecurity();
        $this->registerBeanstalk();
        $this->registerConfig();


        Di::setDefault(self::$di);
    }

    /**
     * @return Services $this
     */
    public function registerEventsManager()
    {
        self::$di->setShared(
            'eventsManager',
            function()
            {
                return new \Phalcon\Events\Manager();
            }
        );
    }


    /**
     * @return Services $this
     */
    public function registerModelsManager()
    {
        self::$di->setShared(
            'modelsManager',
            function()
            {
                return new \Phalcon\Mvc\Model\Manager();
            }
        );
    }

    /**
     * @return Services $this
     */
    public function registerModelsMetadata()
    {
        self::$di->setShared(
            'modelsMetadata',
            function()
            {
                return new \Phalcon\Mvc\Model\Metadata\Memory();
            }
        );
    }

    /**
     * @return Services $this
     */
    public function registerRouter()
    {
        self::$di->setShared(
            'router',
            function () {

                $router = new Router(false);

                $router->notFound(
                    array(
                        "controller" => "auth",
                        "action" => "forbidden"
                    )
                );

                $config = Loader::$config->authentication->redirect;
                foreach ($config as $name => $route) {
                    $options = $route->toArray();
                    $pattern = $options['pattern'];
                    unset($options['pattern']);
                    $router->add($pattern, $options)->setName($name);
                }

                $router->add('/grid', array('controller' => 'grid', 'action' => 'index'))->setName('Grid');
                $router->add('/grid/:params', array('controller' => 'grid', 'action' => 'index', 'params' => 1));
                return $router;
            }
        );
    }

    /**
     * @return Services $this
     */
    public function registerSecurity()
    {
        self::$di->setShared(
            'security',
            function () {
                $security = new Security();
                $security->setWorkFactor(Loader::$config->authentication->bcryptCost);
                return $security;
            }
        );
    }

    /**
     * @return Services $this
     */
    public function registerConfig()
    {
        self::$di->setShared(
            'config',
            function () {
                return Loader::$config;
            }
        );
    }

    /**
     * @return Services $this
     */
    public function registerCache()
    {
        self::$di->setShared(
            'cache',
            function () {
                $frontCache = new Output(
                    array(
                        "lifetime" => 172800
                    )
                );

                $cache = new File(
                    $frontCache,
                    array(
                        "cacheDir" => APP_PATH . "/data/"
                    )
                );
                return $cache;
            }
        );

        return $this;
    }

    /**
     * @return Services $this
     */
    public function registerUrl()
    {
        self::$di->setShared(
            'url',
            function () {
                $url = new Url();
                $url->setBaseUri(Loader::$config->application->baseUri);
                return $url;
            }
        );

        return $this;
    }

    /**
     * @return Services $this
     */
    public function registerDispatcher()
    {
        self::$di->setShared(
            'dispatcher',
            function () {
                $dispatch = new Dispatcher();

                $acl = new \Phalcon\Acl\Adapter\Memory();
                $rbac = new Rbac($acl, new Role(), Loader::$config);
                $routerGuard = new RouterGuard();
                $routerGuard->setDI(self::$di);
                $rbac->addGuard($routerGuard);
                $rbac->setDI(self::$di);

                $eventManager = Services::$di->getShared('eventsManager');
                $eventManager->attach('dispatch:beforeExecuteRoute', $rbac);

                $dispatch->setEventsManager($eventManager);
                return $dispatch;
            }
        );

        return $this;
    }

    /**
     * @return Services $this
     */
    public function registerView()
    {
        self::$di->setShared(
            'view',
            function () {
                $view = new View();

                $view->setViewsDir(Loader::$config->application->viewsDir);
                $view->setEventsManager(self::$di->getShared('eventsManager'));

                return $view;
            }
        );

        return $this;
    }

    /**
     * @return Services $this
     */
    public function registerFlash()
    {
        self::$di->set(
            'flash',
            function () {
                return new Session();
            }
        );

        return $this;
    }

    public function registerBeanstalk()
    {
        self::$di->setShared('beanstalk',
            function () {
                $bean = new Beanstalk(Loader::$config->beanstalk->toArray());
                $bean->connect();

                return $bean;
            });
    }


    /**
     * @return Services $this
     */
    public function registerDb()
    {
        self::$di->setShared(
            'db',
            function () {
                $dbConfig = Loader::$config->database->toArray();
                $adapter = $dbConfig['adapter'];
                unset($dbConfig['adapter']);
                $class = 'Phalcon\Db\Adapter\Pdo\\' . $adapter;
                /** @var \Phalcon\Db\Adapter $connection */
                $connection = new $class($dbConfig);
                $connection->setEventsManager(Services::$di->getShared('eventsManager'));
                return $connection;
            }
        );

        return $this;
    }


    /**
     * @return Services $this
     */
    public function registerSession()
    {
        self::$di->setShared(
            'session',
            function () {
                $session = new Files(
                    array('uniqueId' => Loader::$config->application->sessionId)
                );
                $session->start();
                return $session;
            }
        );

        return $this;
    }

}