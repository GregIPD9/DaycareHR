<?php
//// C653TcWF7MGFnuCm

session_cache_limiter(false);
session_start();

require_once 'vendor/autoload.php';

//DB::$host = '127.0.0.1';
DB::$user = 'daycare';
DB::$password = 'C653TcWF7MGFnuCm'; 
DB::$dbName = 'daycare';
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
if (!isset($_SESSION['daycareuser'])){
    $_SESSION['daycareuser'] = array();
}

$twig = $app->view()->getEnvironment();
$twig->addGlobal('daycareuser', $_SESSION['daycareuser']);

// STATE 1: First show
$app->get('/register', function() use ($app) {
    $app->render('register.html.twig');
});

// Receiving a submission
$app->post('/register', function() use ($app) {
    $name = $app->request()->post('name');
    $email = $app->request()->post('email');
    $pass1 = $app->request()->post('pass1');
    $pass2 = $app->request()->post('pass2');
    $position = $app->request()->post('position');
    $valueList = array('email' => $email);
    $errorList = array();
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
        array_push($errorList, "Email is invalid");
    } else {$user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($user) {array_push($errorList, "Email already in use");}}
    if ($pass1 != $pass2) {
        array_push($errorList, "Passwors do not match");
    } else {
        if (strlen($pass1) < 6) {array_push($errorList, "Password too short, must be 6 characters or longer");} 
        if (preg_match('/[A-Z]/', $pass1) != 1 || preg_match('/[a-z]/', $pass1) != 1 || preg_match('/[0-9]/', $pass1) != 1) {
            array_push($errorList, "Password must contain at least one lowercase, " . "one uppercase letter, and a digit");}}
    if ($errorList) {$app->render('register.html.twig', array('errorList' => $errorList, 'v' => $valueList));
    } else { DB::insert('users', array('name' => $name, 'email' => $email, 'password' => $pass1, 'position' => $position));    
    $app->render('register_success.html.twig');}});

// AJAX: Is user with this email already registered?
$app->get('/ajax/emailused/:email', function($email) {
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    echo json_encode($user != null);    
});


$app->get('/login', function() use ($app) {
    $app->render('login.html.twig');
});

$app->get('/logout', function() use ($app) {
    $app->render('logout.html.twig');
});

$app->post('/login', function() use ($app) {
   // $name = $app->request()->post('name');
    $email = $app->request()->post('email');
    $pass = $app->request()->post('pass');
  //  $position = $app->request()->post('position');
    // verification    
    $error = false;
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    if (!$user) {
        $error = true;
    } else {
        if ($user['password'] != $pass) {
            $error = true;
        }
    }
    if ($error) {
        $app->render('login.html.twig', array("error" => true));
    } else {
        unset($user['password']);
        $_SESSION['daycareuser'] = $user;
        $app->render('login_success.html.twig');
    }
});

//
$app->run();
