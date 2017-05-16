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

//LOGOUT
$app->get('/logout', function() use ($app) {
    unset($_SESSION['daycareuser']);
    $app->render("logout.html.twig");
});


// List of educators

$app->get('/listofeducators', function() use ($app) {
  //  if (!$_SESSION['daycareuser']) {
   //     $app->render('login.html.twig');
   //     return;
  //  }
   // $educatorId = $_SESSION['daycareuser']['id'];
    $educators = DB::query("SELECT educatorId,name,phone,email,groupName,startDate,"
            . "yearlySalary,previousVacation,nextVacation FROM educator");
    //print_r($todoList);
    $app->render('listofeducators.html.twig', ['educators' => $educators]);
});

$app->get('/viewphoto/:educatorId', function($educatorId) use ($app) {
 //   if (!$_SESSION['daycareuser']) {
  //      $app->render('forbidden.html.twig');
  //      return;
 //   }
   // $userId = $_SESSION['daycareuser']['id'];
    $educators = DB::queryFirstRow("SELECT photo, photomimetype FROM educator WHERE educatorId=%i", $educatorId);
           /* . " WHERE educatorId=%i" , $userId */
   // if (!$educators) {
     //   $app->response()->status(404);
     //   echo "404 - not found";
   // } else {    
        $app->response->headers->set('Content-Type', $educators['photomimetype']);
        echo $educators['photo'];
 //   }
    
});

// List of kids

$app->get('/listofkids', function() use ($app) {
  //  if (!$_SESSION['daycareuser']) {
   //     $app->render('login.html.twig');
   //     return;
  //  }
   // $educatorId = $_SESSION['daycareuser']['id'];
    $kids = DB::query("SELECT kidId,kidName,age,groupName,motherName,motherPhone,address,allergies,notes"
            ." FROM kids");
    $app->render('listofkids.html.twig', ['kids' => $kids]);
});

$app->get('/viewphotokids/:kidId', function($kidId) use ($app) {
 //   if (!$_SESSION['daycareuser']) {
  //      $app->render('forbidden.html.twig');
  //      return;
 //   }
   // $userId = $_SESSION['daycareuser']['id'];
    $kids = DB::queryFirstRow("SELECT photo, photomimetype FROM kids WHERE kidId=%i", $kidId);
           /* . " WHERE educatorId=%i" , $userId */
   // if (!$educators) {
     //   $app->response()->status(404);
     //   echo "404 - not found";
   // } else {    
        $app->response->headers->set('Content-Type', $kids['photomimetype']);
        echo $kids['photo'];
 //   }
    
});


// Edit 

$app->get('/edit/:educatorId', function() use ($app) {
 
    $educators = DB::queryFirstRow("SELECT * FROM educator WHERE id=%s", $educatorId);
    if ($educators != null) {
        $app->render('edit.html.twig', array(
            "educatorId" => $educators['educatorId'],
            "name" => $educators['name'],
            "phone" => $educators['phone'],
            "photo" => $educators['photo']
        ));
    } else {
        echo "No educators with this id. ";
    }   
});


$app->post('/edit/:educatorId', function($educatorId) use ($app) {
    $educatorId = $app->request()->post('educatorId');
    $name = $app->request()->post('name');
    $phone = $app->request()->post('phone');
    $photo = $app->request()->post('photo');
    $errorList = array();
    $valueList = array('educatorId' => $educatorId);

    $educators = DB::queryFirstRow("SELECT * FROM educator WHERE educatorId=%s", $educatorId);

    if (strlen($name) < 2 || strlen($name) > 100) {
        array_push($errorList, "name too short or too long");
    }

    if ($errorList) {
        $app->render('edit.html.twig', array(
        ));
    } else {
        DB::update('todos', array(
            "name" => $name,
            "phone" => $phone,
            "photo" => $photo
                ), "educatorId=%s", $educatorId);

        $app->render('edit_success.html.twig', array(
            "educatorId" => $educatorId,
             "name" => $name,
            "phone" => $phone,
            "photo" => $photo
        ));
    }
});

//Delete

$app->get('/delete/:educatorId', function($educatorId) use ($app) {

    $educatorId = $_SESSION['daycareuser']['educatorId'];
    $educators = DB::queryFirstRow("select * FROM educator WHERE educatorId=%s", $educatorId);
    //echo json_encode($user, JSON_PRETTY_PRINT);
    if ($educators != null) {
        $app->render('delete.html.twig', array(
            "educatorId" => $educators['educatorId'],
            "name" => $educators['name'],
            "phone" => $educators['phone'],
            "photo" => $educators['photo']
        ));
    } else {
        echo "Not educators with this id. ";
    }
});

$app->post('/delete/:educatorId', function($educatorId) use ($app) {

    $deleteResult = DB::delete('educator', "educatorId=%s", $educatorId);
    if (!$deleteResult) {
        
    } else {

        $app->render('delete_success.html.twig');
    }
}
);
//
$app->run();
