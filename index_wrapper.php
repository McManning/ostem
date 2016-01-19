<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Because whoever runs the webserver is an idiot.
ini_set('session.use_cookies', 1);

include 'index.php';
