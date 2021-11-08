<?php
declare(strict_types=1);

use Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Php as PhpEngine;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Session\Adapter\Stream as SessionAdapter;
use Phalcon\Session\Manager as SessionManager;
use Phalcon\Url as UrlResolver;
use Phalcon\Mvc\Dispatcher;
use Voting\App\Plugins\Security;

use Phalcon\Escaper;
use Phalcon\Session\Adapter\Stream;
use Phalcon\Session\Manager;
use Phalcon\Flash\Session as FlashSession;
use Phalcon\Flash\Direct as FlashDirect;



/**
 * Shared configuration service
 */
$di->setShared('config', function () {
    return include APP_PATH . "/config/config.php";
});

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->setShared('url', function () {

    $url = new UrlResolver();
    $url->setBaseUri($config->application->baseUri);

    return $url;
});

$di->set('logger', function () use ($di) {
    return new ErrorLog();
}, true);

/**
 * Setting up the view component
 */
$di->setShared('view', function () {
    $config = $this->getConfig();

    $view = new View();
    $view->setDI($this);
    $view->setViewsDir($config->application->viewsDir);

    $view->registerEngines([
        '.volt' => function ($view) {
            $config = $this->getConfig();

            $volt = new VoltEngine($view, $this);

            $volt->setOptions([
                'path' => $config->application->cacheDir,
                'separator' => '_',
                'always'     => true,
            ]);

            return $volt;
        },
        '.phtml' => PhpEngine::class

    ]);

    return $view;
});

$di->set('dispatcher', function () use ($di) {
    //Obtain the standard eventsManager from the DI
    $eventsManager = $di->getShared('eventsManager');

    // Jiggle the params so that instead of
    // Array ( [0] => mailRunID [1] => 390540 )
    // we get
    // Array ( [mailRunID] => 390540 )
    $eventsManager->attach(
        "dispatch:beforeDispatchLoop",
        function ($event, $dispatcher) {
            $keyParams = array();
            $params = $dispatcher->getParams();

            //Use odd parameters as keys and even as values
            foreach ($params as $number => $value) {
                if ($number & 1) {
                    $keyParams[$params[$number - 1]] = $value;
                }
            }

            //Override parameters
            $dispatcher->setParams($keyParams);

            // $dispatcher->setDefaultNamespace('app\controllers');
        }
    ); // end jiggle


    //Instantiate the Security plugin
    $security = new Security($di);

    //Listen for events produced in the dispatcher using the Security plugin
    $eventsManager->attach('dispatch', $security);

    $dispatcher = new Dispatcher();

    //Bind the EventsManager to the Dispatcher
    $dispatcher->setEventsManager($eventsManager);

    //Set the default namespace for controllers
#    $dispatcher->setDefaultNamespace('LinkwebV2\App\Controllers');

    return $dispatcher;
});

\Phalcon\Mvc\Model::setup(['castOnHydrate' => true]);
/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$di->setShared('db', function () {
    $config = $this->getConfig();

    $class = 'Phalcon\Db\Adapter\Pdo\\' . $config->database->adapter;
    $params = [
        'host'     => $config->database->host,
        'username' => $config->database->username,
        'password' => $config->database->password,
        'dbname'   => $config->database->dbname,
        'charset'  => $config->database->charset,
        'options' => array(
            PDO::ATTR_DEFAULT_FETCH_MODE  =>  PDO::FETCH_ASSOC
        ),
    ];

    if ($config->database->adapter == 'Postgresql') {
        unset($params['charset']);
    }

    return new $class($params);
});


/**
 * If the configuration specify the use of metadata adapter use it or use memory otherwise
 */
$di->setShared('modelsMetadata', function () {
    return new MetaDataAdapter();
});

/**
 * Set up the flash service
 */
$di->set('flash', function () {
    $escaper = new Escaper();
    $flash = new FlashDirect($escaper);
    $cssClasses = [
        'error'   => 'alert alert-danger',
        'success' => 'alert alert-success',
        'notice'  => 'alert alert-info',
        'warning' => 'alert alert-warning',
    ];

    $flash->setCssClasses($cssClasses);
    return $flash;
});

$di->set('flashSession', function () {
    $escaper = new Escaper();
    $session = new Manager();
    $flash = new FlashSession($escaper, $session);
    $cssClasses = [
        'error'   => 'alert alert-danger',
        'success' => 'alert alert-success',
        'notice'  => 'alert alert-info',
        'warning' => 'alert alert-warning',
    ];

    $flash->setCssClasses($cssClasses);
#    $flash->setAutoescape(false);

    return $flash;
});

/**
 * Start the session the first time some component request the session service
 */
$di->setShared('session', function () {
    $session = new SessionManager();
    $files = new SessionAdapter([
        'savePath' => sys_get_temp_dir(),
    ]);
    $session->setAdapter($files);
    $session->start();

    return $session;
});
