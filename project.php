<?php
//Nu3CzSu5Hjo4URWE

session_cache_limiter(false);
session_start();

require_once 'vendor/autoload.php';

//DB::$host = '127.0.0.1';
DB::$user = 'slimtodo';
DB::$password = 'Nu3CzSu5Hjo4URWE'; 
DB::$dbName = 'slimtodo';
DB::$port = 3333;
DB::$encoding = 'utf8';

// Slim creation and setup
$app = new \Slim\Slim(array(
    'view' => new \Slim\Views\Twig()
        ));

$view = $app->view();
$view->parserOptions = array(
    'debug' => true,
    'cache' => dirname(__FILE__) . '/cache'
);
$view->setTemplatesDirectory(dirname(__FILE__) . '/templates');

//pass info to all templates (similar globals)
if (!isset($_SESSION['todouser'])){
    $_SESSION['todouser'] = array();
}

$twig = $app->view()->getEnvironment();
$twig->addGlobal('todouser', $_SESSION['todouser']);


//
$app->run();
