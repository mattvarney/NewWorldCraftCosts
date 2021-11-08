<?php

$loader = new \Phalcon\Loader();

/**
 * We're a registering a set of directories taken from the configuration file
 */
$loader->registerDirs(
    [
        $config->application->controllersDir,
        $config->application->modelsDir,
        $config->application->libraryDir
    ]
)->register();

$loader->registerNamespaces(
    [
        'Voting\App\Plugins' => $config->application->pluginsDir,
        'Voting\App\Library' => $config->application->libraryDir,
    ]
);

require_once __DIR__ . "/../../composer/vendor/autoload.php";