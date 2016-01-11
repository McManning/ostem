<?php

require __DIR__ . '/vendor/autoload.php';

// Middleware inclusions (unfortunately not PSR-4 autoloaded)
require __DIR__ . '/middleware/authenticated.php';

/*  Import some legacy methods, used for authLevel() until I can replace it with
    something that's ACTUALLY secure opposed to just "lol they have a cookie".
    I don't even know if this'll work, due to the weird ob_start/end chunks 
 */ 
//include_once 'common.php';

date_default_timezone_set('America/New_York');

// Handle static files when running PHP locally
if (php_sapi_name() == 'cli-server') {
    define('DATA_DIR', dirname(__FILE__) . '/php-data/');

    $extensions = ['css', 'map', 'eot', 'svg', 'ttf', 'woff', 'woff2', 'png', 'jpg', 'js', 'json', ];
    $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (in_array($ext, $extensions)) {
        return false;  
    }
} else {
    // TODO: Not exposed in the script, jeeze. 
    define('DATA_DIR', '/usr/local/webs/ostem/php-data/');
}

// App configurations
$app = new \Slim\Slim([
    'debug' => true,
    'view' => new \Slim\Views\Twig()
]);

// Twig configurations
$view = $app->view();
$view->parserOptions = [
    'debug' => true,
    'cache' => dirname(__FILE__) . '/cache'
];

$view->parserExtensions = [
    new \Slim\Views\TwigExtension()
];

require __DIR__ . '/routes.php';

$app->run();
