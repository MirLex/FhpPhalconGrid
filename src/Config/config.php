<?php

defined('APP_PATH') || define('APP_PATH', realpath(__DIR__ . '/../../'));

return new \Phalcon\Config(array(
    'database' => array(
        'adapter' => 'Mysql',
        'host' => '192.168.33.10',
        'username' => 'root',
        'password' => 'root',
        'dbname' => 'auth',
        'charset' => 'utf8',
    ),
    'database2' => array(
        'adapter' => 'Mysql',
        'host' => '192.168.33.10',
        'username' => 'root',
        'password' => 'root',
        'dbname' => 'auth2',
        'charset' => 'utf8',
    ),
    'application' => array(
        'controllersDir' => APP_PATH . '/src/Controllers/',
        'entitiesDir' => APP_PATH . '/src/Entity/',
        'migrationsDir' => APP_PATH . '/src/Migrations/',
        'viewsDir' => APP_PATH . '/src/Views/',
        'pluginsDir' => APP_PATH . '/src/Plugins/',
        'libraryDir' => APP_PATH . '/src/Library/',
        'cacheDir' => APP_PATH . '/data/cache/',
        'baseUri' => '/',
        'sessionId' => 'FhpPhalconGrid',
        ''
    ),
    'beanstalk' => array(
        'host' => '127.0.0.1',
        'port' => '11300'
    ),
    'authentication' => array(
        'failedLoginsToGetLocked' => 10, //0 = never
        'lockedTime' => 'PT15M',
        'passwordSalt' => 'eEAfR|_&G&f,+vU]:jAS!!A&+71w1Ms9~8_4L!<@[N@DyaIP_2My|:+.u>/6m,$D',
        'bcryptCost' => 12,
        'guestRole' => 'guest',
        'redirect' => array(
            'login' => array('pattern' => '/login', 'controller' => 'auth', 'action' => 'login'),
            'logout' => array('pattern' => '/logout', 'controller' => 'auth', 'action' => 'logout'),
            'forbidden' => array('pattern' => '/forbidden', 'controller' => 'auth', 'action' => 'forbidden'),
            'pwreset' => array('pattern' => '/pwreset', 'controller' => 'auth', 'action' => 'pwreset'),
            'pwconfirm' => array('pattern' => '/pwconfirm/([a-zA-Z0-9\_\-]+)/([a-zA-Z0-9\_\-]+)', 'controller' => 'auth', 'action' => 'pwconfirm', 'login' => 1, 'confirm' => 2),
            'successLogin' => array('pattern' => '/success', 'controller' => 'auth', 'action' => 'index'),
        ),
        'guard' => array(
            'policy' => true,
            'rules' => array(
                'grid::*' => array('*'),
                'auth::index' => array('RoleOne'),
                'index::test,test2' => array('admin','policy'=> false,'ascher'),
                'index::*' => array('*'),
            )
        )
    ),
));
