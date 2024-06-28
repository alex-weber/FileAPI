<?php
//load settings
require_once 'settings.php';
//load the app
require_once 'app.php';
use \App\app;
$app = new app();

try {
    $app->upload();
} catch (Exception $e) {
    error_log($e->getMessage(), 3);
}







