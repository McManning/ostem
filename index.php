<?php

require __DIR__ . '/vendor/autoload.php';

/*  Import some legacy methods, used for authLevel() until I can replace it with
    something that's ACTUALLY secure opposed to just "lol they have a cookie".
    I don't even know if this'll work, due to the weird ob_start/end chunks 
 */ 
//include_once 'common.php';

date_default_timezone_set('America/New_York');

// Handle static files when running PHP locally
if (php_sapi_name() == 'cli-server') {
    $extensions = ['css', 'map', 'eot', 'svg', 'ttf', 'woff', 'woff2', 'png', 'jpg', 'js', 'json', ];
    $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (in_array($ext, $extensions)) {
        return false;  
    }
}

/**
 * Middleware to check if a user can access an authorized route.
 * This is to access block page updating, sending of emails, etc
 */
$authenticated = function (\Slim\Route $route) {
    if (authLevel() < 2) {
        $app = \Slim\Slim::getInstance();
        $app->response
            ->setStatus(401)
            ->setBody(json_encode((object)[
                'error' => 'Unauthorized'
            ]));

        $app->stop();
    }
};

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

$app->get('/', function () use ($app) {

    $mailings = new OSTEM\Mailings('./mailings');

    $editables = json_decode(file_get_contents('landing.json', 'r'));

    $app->render('landing.html.j2', [
        // Get last 5 emails created in the current term 
        'mailings' => array_slice(reset($mailings->terms), 0, 5),
        'editables' => $editables
    ]);
});

$app->get('/mailings', function () use ($app) {

    $mailings = new OSTEM\Mailings('./mailings');

    // Spit out JSON of previous mailings
    $app->response
        ->setStatus(200)
        ->write(json_encode($mailings->terms));
});

/**
 * POST action to update our landing page with new content.
 * 
 * Landing updating is done clientside, via contenteditable sections
 * of the site that are then merged into a single JSON payload and 
 * pushed back to the server. 
 */
$app->post('/update', $authenticated, function() use ($app) {
    $body = json_decode($app->request->getBody());
    if (!$body) {
        $app->response
            ->setStatus(400)
            ->write(json_encode((object)[
                'error' => 'Could not parse update data'
            ]));
        return;
    }

    // TODO: Validate payload JSON (fields, length, etc)

    // Persist the updates
    file_put_contents('landing.json', json_encode($body));

    $app->response
        ->setStatus(200)
        ->write(json_encode((object)[
            'message' => 'Updates have been saved'
        ]));
});

$app->run();
