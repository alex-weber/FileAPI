<?php
//load settings
require_once '../settings.php';
//load the app
require_once '../src/controller/App.php';

use App\src\controller\App;

$app = new App();

try {
    $app->upload();
} catch (Exception $e) {
    error_log($e->getMessage(), 3);
}







