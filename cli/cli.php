#!/usr/bin/php
<?php

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
//use IMNWork\Beanstalk\Pheanstalk\Wrapper as Pheanstalk;
//use MongoDB\Client as MongoClient;

/**
 * This makes our life easier when dealing with paths.
 * Everything is relative to the application root now.
 */
chdir(dirname(__DIR__));
$config = include __DIR__ . "/../app/config/config.php";
\Phalcon\Mvc\Model::setup(['castOnHydrate' => true]);
/**
 * Init loader
 */
$loader = new \Phalcon\Loader();
$loader->registerDirs(
    [
        $config->application->controllersDir,
        $config->application->modelsDir,
        $config->application->libraryDir,
        getcwd() . '/cli/tasks/',
    ]
)->register();

$loader->registerNamespaces(
    [
        'Voting\App\Library' => $config->application->libraryDir,
    ]
);

require_once __DIR__ . "/../composer/vendor/autoload.php";

/**
 * Setup dependency injection
 */
// $di = new Phalcon\DI();
$di = new Phalcon\DI\FactoryDefault\CLI();

// Router
$di->setShared('router', function() {
    return new Phalcon\CLI\Router();
});

// Dispatcher
$di->setShared('dispatcher', function() {
    $dispatcher =  new Phalcon\CLI\Dispatcher();

    //Set the default namespace for controllers
//    $dispatcher->setDefaultNamespace('LinkwebV2\Cli\Tasks');

    return $dispatcher;
});

$di->set('appRoot', function() use ($config) {
    return $config->application->appRoot;
});

/**
 * Database connection is created based in the parameters defined in the configuration file
 */

foreach (array('database', 'db') as $handle) {
    $di->set($handle, function () use ($config, $handle) {
        $adapter = array(
            'host' => $config->$handle->host,
            'username' => $config->$handle->username,
            'password' => $config->$handle->password,
            'dbname' => $config->$handle->dbname,
            'options' => array(
                PDO::ATTR_DEFAULT_FETCH_MODE  =>  PDO::FETCH_ASSOC
            ),
        );

        if (isset($config->$handle->port)) {
            $adapter['port'] = $config->$handle->port;
        }

        return new DbAdapter($adapter);
    });
}

// Models manager for PHQL queries
$di->set('modelsManager', function() {
    return new Phalcon\Mvc\Model\Manager();
});

$di->set('collectionManager', function(){
    return new Phalcon\Mvc\Collection\Manager();
}, true);

/**
* Process the console arguments
*/
$arguments = array();
foreach($argv as $k => $arg) {
    if($k == 1) {
        $arguments['task'] = $arg;
    } elseif($k == 2) {
        $arguments['action'] = $arg;
    } elseif($k >= 3) {
       $arguments['params'][] = $arg;
    }
}

/**
 * Run application
 */
$app = new Phalcon\CLI\Console();
$app->setDI($di);
$app->handle($arguments);
