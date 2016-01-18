<?php

require __DIR__ . '/vendor/autoload.php';

use JeremyKendall\Password\PasswordValidator;
use JeremyKendall\Slim\Auth\Adapter\Db\PdoAdapter;
use JeremyKendall\Slim\Auth\Bootstrap;
use JeremyKendall\Slim\Auth\Exception\HttpForbiddenException;
use JeremyKendall\Slim\Auth\Exception\HttpUnauthorizedException;
use Zend\Permissions\Acl\Exception\InvalidArgumentException;

/* Uncomment if I want more control over session storage
use Zend\Authentication\Storage\Session as SessionStorage;
use Zend\Session\Config\SessionConfig;
use Zend\Session\SessionManager;
*/

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

    // Show me errrything
    error_reporting(-1);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);

} else {
    // TODO: Not exposed in the script, jeeze. 
    define('DATA_DIR', '/usr/local/webs/ostem/php-data/');
}

// App configurations
$app = new \Slim\Slim([
    'debug' => false,
    'view' => new \Slim\Views\Twig()
]);

// Configure PDO Adapter for authentication
$app->db = new \PDO('sqlite:' . DATA_DIR . 'ostem.db', null, null, [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_EMULATE_PREPARES => true, // Required by Sqlite3
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
]);
$adapter = new PdoAdapter(
    $app->db,
    'users',
    'email',
    'password',
    new PasswordValidator()
);

/* Re-enable if I want more control over session storage.
// Zend session management for storing login credentials
$sessionConfig = new SessionConfig();
$sessionConfig->setOptions(array(
    'remember_me_seconds' => 60 * 60 * 24 * 7,
    'name' => 'ostem-auth',
));
$sessionManager = new SessionManager($sessionConfig);
$sessionManager->rememberMe();

$storage = new SessionStorage(null, null, $sessionManager);
*/

// Setup ACLs and boostrap Slim-Auth
$acl = new \OSTEM\Acl();
$authBootstrap = new Bootstrap($app, $adapter, $acl);
//$authBootstrap->setStorage($storage);
$authBootstrap->bootstrap();

// Custom error handling
$app->error(function (\Exception $e) use ($app) {

    // Handle the possible 403 the middleware can throw
    if ($e instanceof HttpForbiddenException) {
        return $app->render('403.html.j2', [
            'e' => $e
        ], 403);
    }

    if ($e instanceof HttpUnauthorizedException) {
        return $app->redirectTo('login');
    }

    // Zend ACL can't find a route, throw up a 404
    if ($e instanceof InvalidArgumentException) {
        return $app->render('404.html.j2', [
            'e' => $e
        ], 404);
    }

    // TODO: Handling other errors here...
    throw $e;
});

// Twig configurations
$view = $app->view();
$view->parserOptions = [
    'debug' => true,
    'cache' => dirname(__FILE__) . '/cache',
    'auto_reload' => true,
    'autoescape' => true,
];

$view->parserExtensions = [
    new \Slim\Views\TwigExtension(),
    new \Twig_Extension_Debug(),
];

// Add a before dispatch hook to ensure the view has user data, if set
$app->hook('slim.before.dispatch', function () use ($app) {
    $hasIdentity = $app->auth->hasIdentity();
    $identity = $app->auth->getIdentity();
    $role = ($hasIdentity) ?  : 'guest';

    $user = null;
    if ($hasIdentity) {
        $user = new OSTEM\User(
            $app->db, 
            $identity['email'], 
            $identity['role']
        );
    }
    
    $app->view->appendData([
        'user' => $user,
        'role' => $role
    ]);
});

require __DIR__ . '/routes.php';

$app->run();
