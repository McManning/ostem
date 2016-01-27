<?php

// Middleware inclusions (unfortunately not PSR-4 autoloaded)
// $authenticate = require __DIR__ . '/middleware/authenticate.php';

$app->get('/', function () use ($app) {

    $editables = new OSTEM\Editables();
    $newsletters = OSTEM\Newsletter::getRecent($app->db);

    $app->render('landing.html.j2', array(
        // Get last 5 emails created in the current term 
        //'mailings' => array_slice(reset($mailings->terms), 0, 5),
        'newsletters' => $newsletters,
        'editables' => $editables
    ));
});

/**
 * Unsubscribe a user from the mailing list :(
 *
 * Note this accepts UUIDs instead of emails so we don't have
 * to deal with people unsubscribing other people's email addresses
 */
$app->map('/unsubscribe/:uuid', function ($uuid) use ($app) {

    $listserv = new OSTEM\Listserv($app->db);
    $email = $listserv->getEmail($uuid);

    // If they posted, do actual unsubscribe.
    // Otherwise, we show a form to confirm
    if ($app->request->isPost()) {
        $listserv->unsubscribe($uuid);
        
        $app->log->info('Removed from listserv', array(
            'email' => $email
        ));
    }

    $app->render('unsubscribe.html.j2', array(
        'email' => $email,
        'unsubscribed' => $app->request->isPost()
    ));
})->via('GET', 'POST')->name('unsubscribe');

/**
 * AJAX POST: Subscribe to the mailing list
 */
$app->post('/subscribe', function () use ($app) {
    $email = $app->request->post('email');

    if (!$email) {
        $app->contentType('application/json');
        $app->halt(400, json_encode((object)array(
            'error' => 'Expected email'
        )));
    }

    $listserv = new OSTEM\Listserv($app->db);
    $listserv->subscribe($email);

    $app->log->info('Added to listserv', array(
        'email' => $email
    ));

    $app->response->setStatus(201); // CREATED
});

/**
 * Login action, both the GET for the form and POST for the action
 */
$app->map('/login', function () use ($app) {
    $email = null;

    if ($app->request()->isPost()) {
        $email = $app->request->post('email');
        $password = $app->request->post('password');

        $result = $app->authenticator->authenticate($email, $password);

        // If login is successful, take them to the admin dashboard
        if ($result->isValid()) {
            $app->redirect('/admin');
        } else {
            $messages = $result->getMessages();
            $app->flashNow('error', $messages[0]);

            $app->log->warning('Failed login attempt', array(
                'email' => $email,
                'ip' => $app->request->getIp(),
                'headers' => $app->request->headers()
            ));
        }
    }

    $app->render('login.html.j2', array(
        'email' => $email
    ));

})->via('GET', 'POST')->name('login');

/**
 * Logout action
 */
$app->get('/logout', function () use ($app) {
    // TODO: Technically shouldn't be GET, since it should be idempotent.
    //$app->authenticator->logout();
    if ($app->auth->hasIdentity()) {
        $app->auth->clearIdentity();
    }    

    $app->redirect('/');
});

$app->group('/admin', function () use ($app) {

    /**
     * Display the admin dashboard
     */
    $app->get('', function () use ($app) {

        $listserv = new OSTEM\Listserv($app->db);
        $newsletter = new OSTEM\Newsletter($app->db);
        $newsletter->loadDraft();

        $app->render('admin/dashboard.html.j2', array(
            'listserv' => $listserv,
            'newsletter' => $newsletter
        ));
    });

    /**
     * AJAX POST: Update our landing page with new content.
     * 
     * Landing updating is done clientside, via contenteditable sections
     * of the site that are then merged into a single payload and 
     * pushed back to the server. 
     */
    $app->post('/update', function() use ($app) {

        // TODO: Validate payload JSON (fields, length, etc)

        $editables = new OSTEM\Editables();

        // Copy entries from our POST to the editables object
        foreach ($editables->keys() as $key) {
            if ($app->request->post($key)) {
                $editables->{$key} = $app->request->post($key);
            }
        }

        $editables->save();
        $app->response->setStatus(200);
    });

    /**
     * AJAX POST: Update profile data 
     */
    $app->post('/profile', function () use ($app) {
        $user = $app->view->getData('user');

        $user->updatePassword($app->request->post('password'));

        $app->log->info('User updated password', array(
            'user' => $user->email,
            'ip' => $app->request->getIp(),
            'headers' => $app->request->headers()
        ));
    });

    /**
     * AJAX POST: Add a new admin user to the system
     */
    $app->post('/add', function () use ($app) {
        throw new \Exception('TODO!');
    });

    /**
     * Newsletter routes for saving/sending/etc
     */
    $app->group('/newsletter', function () use ($app) {

        /**
         * AJAX POST: Update the saved draft of the current newsletter
         */
        $app->post('/draft', function () use ($app) {
            $user = $app->view->getData('user');

            try {
                    
                $newsletter = new OSTEM\Newsletter($app->db);
                $newsletter->loadDraft();

                $newsletter->message = $app->request->post('newsletter-html');
                $newsletter->subject = $app->request->post('newsletter-subject');
                $newsletter->sender = $user->email;
                $newsletter->save();

                $app->response->setStatus(201);
            } 
            catch (\Exception $e) {

                $app->log->error('Failed to save newsletter draft', array(
                    'user' => $user->email,
                    'ip' => $app->request->getIp(),
                    'headers' => $app->request->headers(),
                    'exception' => (string)$e
                ));

                $app->contentType('application/json');
                $app->halt(400, json_encode((object)array(
                    'error' => $e->getMessage()
                )));
            }
        });

        /**
         * AJAX POST: Test send an email to a specific user (usually the author)
         */
        $app->post('/test', function () use ($app) {
            $user = $app->view->getData('user');

            try {
                // Prepare newsletter
                $newsletter = new OSTEM\Newsletter($app->db);
                $newsletter->loadDraft();
                $newsletter->message = $app->request->post('newsletter-html');
                $newsletter->subject = $app->request->post('newsletter-subject');
                $newsletter->sender = $user->email;

                // Fire off to just the current user 
                $email = (object)array(
                    'email' => $user->email,
                    'uuid' => 'TEST-EMAIL-FAKE-UUID' // Fake UUID for unsubscribe links
                );

                $newsletter->send($app->view, array($email), $app->log);

                $app->log->info('Newsletter test sent', array(
                    'user' => $user->email,
                    'ip' => $app->request->getIp(),
                    'headers' => $app->request->headers()
                ));

                $app->response->setStatus(201);
            } 
            catch (\Exception $e) {

                $app->log->error('Failed to send test newsletter', array(
                    'user' => $user->email,
                    'ip' => $app->request->getIp(),
                    'headers' => $app->request->headers(),
                    'exception' => (string)$e
                ));

                $app->contentType('application/json');
                $app->halt(400, json_encode((object)array(
                    'error' => $e->getMessage()
                )));
            }

        });

        /**
         * AJAX POST: Send current newsletter draft out to all subscribers
         */
        $app->post('/send', function () use ($app) {
            $user = $app->view->getData('user');

            try {
                // Prepare listserv
                $listserv = new Listserv($this->db);

                // Prepare newsletter
                $newsletter = new OSTEM\Newsletter($app->db);
                $newsletter->loadDraft();
                $newsletter->message = $app->request->post('newsletter-html');
                $newsletter->subject = $app->request->post('newsletter-subject');
                $newsletter->sender = $user->email;

                // Fire off to the listserv
                $newsletter->sendToListserv($app->view, $listserv, $app->log);

                $app->log->info('Newsletter sent', array(
                    'user' => $user->email,
                    'ip' => $app->request->getIp(),
                    'headers' => $app->request->headers()
                ));

                $app->response->setStatus(201);
            } 
            catch (\Exception $e) {

                $app->log->error('Failed to send newsletter', array(
                    'user' => $user->email,
                    'ip' => $app->request->getIp(),
                    'headers' => $app->request->headers(),
                    'exception' => (string)$e
                ));

                $app->contentType('application/json');
                $app->halt(400, json_encode((object)array(
                    'error' => $e->getMessage()
                )));
            }
        });
    });
});
