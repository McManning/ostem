<?php

$app->get('/', function () use ($app) {

    $mailings = new OSTEM\Mailings(DATA_DIR . 'mailings');

    $editables = json_decode(file_get_contents(DATA_DIR . 'landing.json', 'r'));

    $app->render('landing.html.j2', [
        // Get last 5 emails created in the current term 
        'mailings' => array_slice(reset($mailings->terms), 0, 5),
        'editables' => $editables
    ]);
});

$app->get('/unsubscribe', function () use ($app) {

    //$editables = json_decode(file_get_contents(DATA_DIR . 'landing.json', 'r'));

    $app->render('unsubscribe.html.j2', [
        //'editables' => $editables
    ]);
});

/**
 * AJAX POST to subscribe to the mailing list
 */
$app->post('/subscribe', function () use ($app) {

    $email = $app->request->post('email');

    if (!$email) {
        $app->response->setStatus(400);
        $app->response->setBody(json_encode((object)[
                'error' => 'Could not parse update data'
        ]));
        return;
    }

    // TODO: Do the thing and add

    $app->response->setStatus(201);
});

$app->get('/mailings', function () use ($app) {

    $mailings = new OSTEM\Mailings(DATA_DIR . 'mailings');

    // Spit out JSON of previous mailings
    $app->response->setStatus(200);
    $app->response->setBody(json_encode($mailings->terms));
});

/**
 * POST action to update our landing page with new content.
 * 
 * Landing updating is done clientside, via contenteditable sections
 * of the site that are then merged into a single payload and 
 * pushed back to the server. 
 */
$app->post('/update', /*$authenticated,*/ function() use ($app) {

    // TODO: Validate payload JSON (fields, length, etc)

    $editables = json_decode(file_get_contents(DATA_DIR . 'landing.json', 'r'));

    foreach ($editables as $field => &$value) {
        if ($app->request->post($field)) {
            $value = $app->request->post($field);
        }
    }

    // Persist the updates
    file_put_contents(DATA_DIR . 'landing.json', json_encode($editables));

    $app->response->setStatus(200);
});
