<?php
declare(strict_types=1);

use Phalcon\Url;
use Phalcon\Di\FactoryDefault;

error_reporting(E_ALL);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

try {
    /**
     * The FactoryDefault Dependency Injector automatically registers
     * the services that provide a full stack framework.
     */
    $di = new FactoryDefault();

    /**
     * Read services
     */
    include APP_PATH . '/config/services.php';

    /**
     * Handle routes
     */
    include APP_PATH . '/config/router.php';

    /**
     * Get config service for use in inline setup below
     */
    $config = $di->getConfig();

    /**
     * Include Autoloader
     */
    include APP_PATH . '/config/loader.php';

    $di->set(
        'url',
        function () use ($config){
            $url = new Url();

            $url->setBaseUri($config->application->baseUri);

            return $url;
        }
    );

    /**
     * Handle the request
     */
    $application = new \Phalcon\Mvc\Application($di);
    // https://github.com/phalcon/cphalcon/issues/14559
    // echo $application->handle($_SERVER['REQUEST_URI'])->getContent();
    echo $application->handle($_GET['_url'] ?? '/')->getContent();
} catch (\Exception $e) {
    echo $e->getMessage() . '<br>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
}
