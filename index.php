<?php

require __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

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
    define('LOG_DIR', dirname(__FILE__) . '/logs/');
    define('DEBUG_MODE', true);

    $extensions = array('css', 'map', 'eot', 'svg', 'ttf', 'woff', 'woff2', 'png', 'jpg', 'js', 'json');
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
    define('DEBUG_MODE', false);
    define('DATA_DIR', '/usr/local/webs/ostem/php-data/');
    define('LOG_DIR', '/usr/local/webs/ostem/php-data/logs/');
}

// App configurations
$app = new \Slim\Slim(array(
    'debug' => false, // Always false, let our custom handlers deal with error catching
    'view' => new \Slim\Views\Twig(),
    'log.enabled' => false
));

// Let our views know whether or not we're in debug mode
$app->view->appendData(array('debug' => DEBUG_MODE));

// Replace Slim's logger with Monolog
$app->container->singleton('log', function() {

    $logger = new Logger('OSTEM');
    $logger->pushHandler(new StreamHandler(
        LOG_DIR . date('Y-m-d') . '.log', 
        Logger::DEBUG
    ));
    
    return $logger;
});

// Configure shared PDO adapter
$app->container->singleton('db', function() {
    return new \PDO('sqlite:' . DATA_DIR . 'ostem.db', null, null, array(
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_EMULATE_PREPARES => true, // Required by Sqlite3
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    ));
});

$adapter = new PdoAdapter(
    $app->db,
    'users',
    'email',
    'password'
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
        $app->log->warning('403 (Forbidden) Attempt', array(
            'path' => $app->request->getPath(),
            'ip' => $app->request->getIp(),
            'headers' => $app->request->headers(),
            'exception' => (string)$e
        ));

        return $app->render('403.html.j2', array(
            'e' => $e
        ), 403);
    }

    if ($e instanceof HttpUnauthorizedException) {
        return $app->redirectTo('login');
    }

    // Zend ACL can't find a route, throw up a 404
    if ($e instanceof InvalidArgumentException) {
        $app->log->warning('404 Attempt', array(
            'path' => $app->request->getPath(),
            'ip' => $app->request->getIp(),
            'headers' => $app->request->headers(),
            'exception' => (string)$e
        ));

        return $app->render('404.html.j2', array(
            'e' => $e
        ), 404);
    }

    // Everything else, assume 500 level
    $app->log->warning('Server Error', array(
        'path' => $app->request->getPath(),
        'ip' => $app->request->getIp(),
        'headers' => $app->request->headers(),
        'exception' => (string)$e
    ));
        
    $app->render('500.html.j2', array(
        'e' => $e
    ), 500);
});

// Additional error handling for other 404's 
$app->notFound(function () use ($app) {
   
    // Just let the error handler deal with it,
    // since Zend will always throw on 404's anyway
    throw new InvalidArgumentException();
});

// Twig configurations
$view = $app->view();
$view->parserOptions = array(
    'debug' => true,
    'cache' => dirname(__FILE__) . '/cache',
    'auto_reload' => true,
    'autoescape' => true,
);

$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new \Twig_Extension_Debug(),
);

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
    
    $app->view->appendData(array(
        'user' => $user,
        'role' => $role
    ));
});

require __DIR__ . '/routes.php';

$app->run();
