<?php
//// C653TcWF7MGFnuCm

session_cache_limiter(false);
session_start();

require_once 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// create a log channel
$log = new Logger('main');
$log->pushHandler(new StreamHandler('logs/everything.log', Logger::DEBUG));
$log->pushHandler(new StreamHandler('logs/errors.log', Logger::ERROR));

//DB::$host = '127.0.0.1';
DB::$user = 'daycare';
DB::$password = 'C653TcWF7MGFnuCm'; 
DB::$dbName = 'daycare';
DB::$port = 3333;
DB::$encoding = 'utf8';

DB::$error_handler = 'sql_error_handler';
DB::$nonsql_error_handler = 'nonsql_error_handler';

function nonsql_error_handler($params) {
    global $app, $log;
    $log->error("Database error: " . $params['error']);
    http_response_code(500);
    $app->render('error_internal.html.twig');
    die;
}

function sql_error_handler($params) {
    global $app, $log;
    $log->error("SQL error: " . $params['error']);
    $log->error(" in query: " . $params['query']);
    http_response_code(500);
    $app->render('error_internal.html.twig');
    die; // don't want to keep going if a query broke
}

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
        array_push($errorList, "Passwords do not match");
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

//LOGOUT
$app->get('/logout', function() use ($app) {
    unset($_SESSION['daycareuser']);
    $app->render("logout.html.twig");
});

$app->post('/login', function() use ($app) {
    $email = $app->request()->post('email');
    $pass = $app->request()->post('pass');
    $error = false;
    $daycareuser = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    if (!$daycareuser) {
        $error = true;
    } else {
        if ($daycareuser['password'] != $pass) {
            $error = true;
        }
    }
    if ($error) {
        $app->render('login.html.twig', array("error" => true));
    } else {
        unset($daycareuser['password']);
        $_SESSION['daycareuser'] = $daycareuser;
        $app->render('login_success.html.twig', 
                array('daycareuser' => $_SESSION['daycareuser']));
    }
});

//Director view
$app->get('/director', function() use ($app) {
    $app->render("director.html.twig");
});

//Educator view
$app->get('/educator', function() use ($app) {
    $app->render("educator.html.twig");
});

// List of educators
$app->get('/listofeducators', function() use ($app) {
  if (!$_SESSION['daycareuser']) {
   $app->render('login.html.twig');
   return;
  }
    $educators = DB::query("SELECT id,name,phone,email,groupName,startDate,"
            . "yearlySalary,previousVacation,nextVacation FROM educators");
    $app->render('listofeducators.html.twig', ['educators' => $educators]);
});

$app->get('/viewphoto/:id', function($id) use ($app) {
 if (!$_SESSION['daycareuser']) {
       $app->render('forbidden.html.twig');
       return;
   }
    $educators = DB::queryFirstRow("SELECT photo, photomimetype FROM educators WHERE id=%i", $id);   
        $app->response->headers->set('Content-Type', $educators['photomimetype']);
        echo $educators['photo'];   
});

// List of kids
$app->get('/listofkids', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
       $app->render('login.html.twig');
        return;
    }
    $kids = DB::query("SELECT id,kidName,age,groupName,motherName,motherPhone,address,allergies,notes"
            ." FROM kids");
    $app->render('listofkids.html.twig', ['kids' => $kids]);
});

// List of kids
$app->get('/editchild/edit/listofkids', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
       $app->render('login.html.twig');
        return;
    }
    $kids = DB::query("SELECT id,kidName,age,groupName,motherName,motherPhone,address,allergies,notes"
            ." FROM kids");
    $app->render('listofkids.html.twig', ['kids' => $kids]);
});

// List of kids by group
$app->get('/listofkids/:groupName', function($groupName) use ($app) {
    if (!$_SESSION['daycareuser']) {
       $app->render('login.html.twig');
        return;
    }
    $kids = DB::query("SELECT * FROM kids WHERE groupName=%s", $groupName);
    $app->render('listofkids.html.twig', ['kids' => $kids]);
});

$app->get('/viewphotokids/:id', function($id) use ($app) {
   if (!$_SESSION['daycareuser']) {
        $app->render('forbidden.html.twig');
       return;
    }
    $kids = DB::queryFirstRow("SELECT photo, photomimetype FROM kids WHERE id=%i", $id); 
        $app->response->headers->set('Content-Type', $kids['photomimetype']);
        echo $kids['photo'];
});

// Add an Educator
$app->get('/addeducator', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $app->render('addeducator.html.twig');
});

$app->post('/addeducator', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    // extract variables
    $name = $app->request()->post('name');
    $email = $app->request()->post('email');
    $phone = $app->request()->post('phone');
    $group = $app->request()->post('groupName');
    $startdate = $app->request()->post('startDate');
    $previousVacation = $app->request()->post('previousVacation');
    $nextVacation = $app->request()->post('nextVacation');
    $yearlySalary = $app->request()->post('yearlySalary');
    $photo = isset($_FILES['photo']) ? $_FILES['photo'] : array();
   
    $valueList = array('name' => $name, 'email' => $email, 'phone' => $phone, 'groupName' => $group, 
        'startDate' => $startdate, 'previousVacation' => $previousVacation, 'nextVacation' => $nextVacation,
        'yearlySalary' => $yearlySalary );
    // verify inputs,
    $errorList = array();
    if (strlen($name) < 2 || strlen($name) > 100) {
        array_push($errorList, "Name must be between 2 and 100 characters");
    }
    if (empty($startdate)) {
        array_push($errorList, "You must select a valid due date");
    }
    if (empty($previousVacation)) {
        array_push($errorList, "You must select a valid due date");
    }
    if (empty($nextVacation)) {
        array_push($errorList, "You must select a valid due date");
    }
    if ($photo) {
        $imageInfo = getimagesize($photo["tmp_name"]);
        if (!$imageInfo) {
            array_push($errorList, "File does not look like an valid image");
        } else {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            if ($width > 300 || $height > 300) {
                array_push($errorList, "Image must at most 300 by 300 pixels");
            }
        }
    }
    // receive data and insert
    if (!$errorList) {
        $imageBinaryData = file_get_contents($photo['tmp_name']);
     //   $ownerId = $_SESSION['daycareuser']['id'];
        $mimeType = mime_content_type($photo['tmp_name']);
        DB::insert('educators', array(
  
            'name' => $name,
            'email' => $email, 
            'phone' => $phone,
            'groupName' => $group, 
            'startDate' => $startdate, 
            'previousVacation' => $previousVacation, 
            'nextVacation' => $nextVacation,
            'yearlySalary' => $yearlySalary,
            'photo' => $imageBinaryData,
            'photomimetype' => $mimeType
        ));
        $app->render('addeducator_success.html.twig');
    } else {
        // TODO: keep values entered on failed submission
        $app->render('addeducator.html.twig', array(
            'v' => $valueList
        ));
    }
});

// Add another Child
$app->get('/addchild', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $app->render('addchild.html.twig');
});

$app->post('/addchild', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    // extract variables
    $kidName = $app->request()->post('kidName');
    $age = $app->request()->post('age');
    $groupName = $app->request()->post('groupName');
    $motherName = $app->request()->post('motherName');
    $motherPhone = $app->request()->post('motherPhone');
    $fatherName = $app->request()->post('fatherName');
    $fatherPhone = $app->request()->post('fatherPhone');
    $address = $app->request()->post('address');
    $allergies = $app->request()->post('allergies');
    $notes = $app->request()->post('notes');
    $photo = isset($_FILES['photo']) ? $_FILES['photo'] : array();
   
    $valueList = array('kidName' => $kidName, 'age' => $age, 'groupName' => $groupName, 'motherName' => $motherName,
        'motherPhone' => $motherPhone, 'fatherName' => $fatherName,
        'fatherPhone' => $fatherPhone, 'address' => $address, 'allergies' => $allergies, 'notes' => $notes);
    // verify inputs,
    $errorList = array();
    if (strlen($kidName) < 2 || strlen($kidName) > 100) {
        array_push($errorList, "Name must be between 2 and 100 characters");
    }
    if (strlen($motherName) < 2 || strlen($motherName) > 100) {
        array_push($errorList, "Name must be between 2 and 100 characters");
    }
    if (strlen($fatherName) < 2 || strlen($fatherName) > 100) {
        array_push($errorList, "Name must be between 2 and 100 characters");
    }
    if ($photo) {
        $imageInfo = getimagesize($photo["tmp_name"]);
        if (!$imageInfo) {
            array_push($errorList, "File does not look like an valid image");
        } else {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            if ($width > 300 || $height > 300) {
                array_push($errorList, "Image must at most 300 by 300 pixels");
            }
        }
    }
    // receive data and insert
    if (!$errorList) {
        $imageBinaryData = file_get_contents($photo['tmp_name']);
        $mimeType = mime_content_type($photo['tmp_name']);
        DB::insert('kids', array(
            'kidName' => $kidName,
            'age' => $age, 
            'groupName' => $groupName, 
            'motherName' => $motherName,
            'motherPhone' => $motherPhone, 
            'fatherName' => $fatherName,
            'fatherPhone' => $fatherPhone, 
            'address' => $address, 
            'allergies' => $allergies, 
            'notes' => $notes,
            'photo' => $imageBinaryData,
            'photomimetype' => $mimeType
        ));
        $app->render('addchild_success.html.twig');
    } else {
        $app->render('addchild.html.twig', array(
            'v' => $valueList
        ));
    }
});
// EDIT CHILD - Andrei's try    ε(๏̯͡๏)з
$app->get('/editchild/:op(/:id)', function($op, $id = 0) use ($app) {
    if (!$_SESSION['daycareuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
   if ($op == 'edit') {
        $child = DB::queryFirstRow("SELECT * FROM kids WHERE id=%i", $id);
        if (!$child) {
            echo 'Child not found, you may ADD a new child here:';
             $app->render('addchild.html.twig');
            return;
        }
        $app->render("editchild.html.twig", array(
            'v' => $child, 'operation' => 'Update'
        ));
    }
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));


$app->post('/editchild/:op(/:id)', function($op, $id = 0) use ($app) {
    if (!$_SESSION['daycareuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    // extract variables
    $kidName = $app->request()->post('kidName');
    $age = $app->request()->post('age');
    $groupName = $app->request()->post('groupName');
    $motherName = $app->request()->post('motherName');
    $motherPhone = $app->request()->post('motherPhone');
    $fatherName = $app->request()->post('fatherName');
    $fatherPhone = $app->request()->post('fatherPhone');
    $address = $app->request()->post('address');
    $allergies = $app->request()->post('allergies');
    $notes = $app->request()->post('notes');
    $photo = $_FILES['photo'];
   
    $valueList = array('kidName' => $kidName, 'age' => $age, 'groupName' => $groupName, 'motherName' => $motherName,
        'motherPhone' => $motherPhone, 'fatherName' => $fatherName,
        'fatherPhone' => $fatherPhone, 'address' => $address, 'allergies' => $allergies, 'notes' => $notes);
    // verify inputs,
    $errorList = array();
    if (strlen($kidName) < 2 || strlen($kidName) > 100) {
        array_push($errorList, "Name must be between 2 and 100 characters");
    }
    if (strlen($motherName) < 2 || strlen($motherName) > 100) {
        array_push($errorList, "Name must be between 2 and 100 characters");
    }
    if (strlen($fatherName) < 2 || strlen($fatherName) > 100) {
        array_push($errorList, "Name must be between 2 and 100 characters");
    }
    if ($photo) {
        $imageInfo = getimagesize($photo["tmp_name"]);
        if (!$imageInfo) {
            array_push($errorList, "File does not look like an valid image");
        } else {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            if ($width > 300 || $height > 300) {
                array_push($errorList, "Image must at most 300 by 300 pixels");
            }
        }
    }
    // receive data and insert
    if (!$errorList) {
      // $imageBinaryData= DB::queryFirstField(
      //                  'SELECT photo FROM kids WHERE id=%i', $id);
      //$mimeType = DB::queryFirstField(
      //                   'SELECT photomimetype FROM kids WHERE id=%i', $id);
        $data = array(
            'kidName' => $kidName,
            'age' => $age, 
            'groupName' => $groupName, 
            'motherName' => $motherName,
            'motherPhone' => $motherPhone, 
            'fatherName' => $fatherName,
            'fatherPhone' => $fatherPhone, 
            'address' => $address, 
            'allergies' => $allergies, 
            'notes' => $notes,    
          //  'photo' => $imageBinaryData,
          //  'photomimetype' => $mimeType);
        
           );
        DB::update('kids', $data, "id=%i", $id);
   // } else { 
    } if ($photo['error'] == 0) {
      // if ($photo['error'] == 0) {
                $imageBinaryData = file_get_contents($photo['tmp_name']);
                $mimeType = mime_content_type($photo['tmp_name']);
    $data = array(
            'kidName' => $kidName,
            'age' => $age, 
            'groupName' => $groupName, 
            'motherName' => $motherName,
            'motherPhone' => $motherPhone, 
            'fatherName' => $fatherName,
            'fatherPhone' => $fatherPhone, 
            'address' => $address, 
            'allergies' => $allergies, 
            'notes' => $notes,    
            'photo' => $imageBinaryData,
            'photomimetype' => $mimeType);
       // }
        DB::update('kids', $data, "id=%i", $id);
        } 

      //  else {
      //       $app->render('addchild.html.twig');
      //  }
        $app->render('editchild_success.html.twig');
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));
//
//
//
// EDIT EDUCATORS 
$app->get('/editeducator/:op(/:id)', function($op, $id = 0) use ($app) {
    if (!$_SESSION['daycareuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
   if ($op == 'edit') {
        $educator = DB::queryFirstRow("SELECT * FROM educators WHERE id=%i", $id);
        if (!$educator) {
            echo 'Educator not found, you may ADD a new educator here:';
             $app->render('addeducator.html.twig');
            return;
        }
        $app->render("editeducator.html.twig", array(
            'v' => $educator, 'operation' => 'Update'
        ));
    }
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));


$app->post('/editeducator/:op(/:id)', function($op, $id = 0) use ($app) {
    if (!$_SESSION['daycareuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    // extract variables
    $name = $app->request()->post('name');
    $email = $app->request()->post('email');
    $phone = $app->request()->post('phone');
    $group = $app->request()->post('groupName');
    $startdate = $app->request()->post('startDate');
    $previousVacation = $app->request()->post('previousVacation');
    $nextVacation = $app->request()->post('nextVacation');
    $yearlySalary = $app->request()->post('yearlySalary');
    $photo = $_FILES['photo'];
   
    $valueList = array('name' => $name, 'email' => $email, 'phone' => $phone, 'groupName' => $group, 
        'startDate' => $startdate, 'previousVacation' => $previousVacation, 'nextVacation' => $nextVacation,
        'yearlySalary' => $yearlySalary );
    // verify inputs,
     $errorList = array();
     if (strlen($name) < 2 || strlen($name) > 100) {
        array_push($errorList, "Name must be between 2 and 100 characters");
    }
    if (empty($startdate)) {
        array_push($errorList, "You must select a valid due date");
    }
    if (empty($previousVacation)) {
        array_push($errorList, "You must select a valid due date");
    }
    if (empty($nextVacation)) {
        array_push($errorList, "You must select a valid due date");
    }
    if ($photo['error'] != 0) {
        array_push($errorList, "Image is required to create a product");
    } else {
   
        $imageInfo = getimagesize($photo["tmp_name"]);
        if (!$imageInfo) {
            array_push($errorList, "File does not look like an valid image");
        } else {
            // FIXME: opened a security hole here! .. must be forbidden
            if (strstr($photo["name"], "..")) {
                array_push($errorList, "File name invalid");
            }
            // FIXME: only allow select extensions .jpg .gif .png, never .php
            $ext = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png'))) {
                array_push($errorList, "File name invalid");
            }
        }
    } 
    // receive data and insert
    if (!$errorList) {
      // $imageBinaryData= DB::queryFirstField(
      //                  'SELECT photo FROM educators WHERE id=%i', $id);
      //$mimeType = DB::queryFirstField(
      //                   'SELECT photomimetype FROM educators WHERE id=%i', $id);
        $data = array(
            'name' => $name,
            'email' => $email, 
            'phone' => $phone,
            'groupName' => $group, 
            'startDate' => $startdate, 
            'previousVacation' => $previousVacation, 
            'nextVacation' => $nextVacation,
            'yearlySalary' => $yearlySalary,
          //  'photo' => $imageBinaryData,
          //  'photomimetype' => $mimeType);
        
           );
        DB::update('educators', $data, "id=%i", $id);
   // } else { 
    } if ($photo['error'] == 0) {
      // if ($photo['error'] == 0) {
                $imageBinaryData = file_get_contents($photo['tmp_name']);
                $mimeType = mime_content_type($photo['tmp_name']);
    $data = array(
            'name' => $name,
            'email' => $email, 
            'phone' => $phone,
            'groupName' => $group, 
            'startDate' => $startdate, 
            'previousVacation' => $previousVacation, 
            'nextVacation' => $nextVacation,
            'yearlySalary' => $yearlySalary,
            'photo' => $imageBinaryData,
            'photomimetype' => $mimeType);
       // }
        DB::update('educators', $data, "id=%i", $id);
        } 

      //  else {
      //       $app->render('addeducator.html.twig');
      //  }
        $app->render('editeducator_success.html.twig');
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));
//
//
//
//DELETE CHILD worked and tested
$app->get('/deletechild/delete/:id', function($id) use ($app) {
    $child = DB::queryFirstRow("SELECT * FROM kids WHERE id=%i", $id);
    $app->render('delete_child.html.twig', array(
        'v' => $child
    ));
});

$app->post('/deletechild/delete/:id', function($id) use ($app) {
    DB::delete('kids', 'id=%i', $id);
    $app->render('delete_child_success.html.twig');
});

//DELETE EDUCATOR
$app->get('/deleteeducator/delete/:id', function($id) use ($app) {
    $educator = DB::queryFirstRow("SELECT * FROM educators WHERE id=%i", $id);
    $app->render('delete_educator.html.twig', array(
        'v' => $educator
    ));
});

$app->post('/deleteeducator/delete/:id', function($id) use ($app) {
    DB::delete('educators', 'id=%i', $id);
    $app->render('delete_educator_success.html.twig');
});

// List of comments for kids
$app->get('/listofkidcomments', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
       $app->render('login.html.twig');
        return;
    }
    $kidcomments = DB::query("SELECT kidName,date,comment,commentedBy"
            ." FROM kidcomments");
    $app->render('listofkidcomments.html.twig', ['kidcomments' => $kidcomments]);
});

// List of comments for groups
$app->get('/listofgroupcomments', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
       $app->render('login.html.twig');
        return;
    }
    $groupcomments = DB::query("SELECT groupName,date,comment,commentedBy"
            ." FROM groupcomments");
    $app->render('listofgroupcomments.html.twig', ['groupcomments' => $groupcomments]);
});

//add comment for kids
$app->get('/childcomment', function() use ($app) {
    $app->render('commentforchild.html.twig');
});
// Receiving a submission
$app->post('/childcomment', function() use ($app) {
    $kidName = $app->request()->post('kidName');
    $date = $app->request()->post('date');
    $comment = $app->request()->post('comment');
    $commentedBy = $app->request()->post('commentedBy');
    $valueList = array('kidName' => $kidName, 'date' => $date, 'comment' => $comment, 'commentedBy' => $commentedBy );
   // verify inputs,
    $errorList = array();
    if (strlen($kidName) < 2 || strlen($kidName) > 100) {
        array_push($errorList, "Name must be between 2 and 100 characters");
    }
    if (empty($date)) {
        array_push($errorList, "You must select a valid due date");
    }
    if (strlen($comment) < 2 || strlen($comment) > 2000) {
        array_push($errorList, "Comment must be between 2 and 2000 characters");
    }
     if (!$errorList) {
    // receive data and insert
        DB::insert('kidcomments', array(
            'kidName' => $kidName,
            'date' => $date, 
            'comment' => $comment, 
            'commentedBy' => $commentedBy
        ));
        $app->render('addcomment_success.html.twig');
    } else {
        // TODO: keep values entered on failed submission
        $app->render('commentforchild.html.twig', array(
            'v' => $valueList
         ));
    }
});

// add comments for group
$app->get('/groupcomment', function() use ($app) {
    $app->render('commentforgroup.html.twig');
});

// Receiving a submission
$app->post('/groupcomment', function() use ($app) {
    $groupName = $app->request()->post('groupName');
    $date = $app->request()->post('date');
    $comment = $app->request()->post('comment');
    $commentedBy = $app->request()->post('commentedBy');
    $valueList = array('groupName' => $groupName, 'date' => $date, 'comment' => $comment, 'commentedBy' => $commentedBy );
    $errorList = array();
   // verify inputs,
    if (strlen($groupName) < 2 || strlen($groupName) > 100) {
        array_push($errorList, "Name must be between 2 and 100 characters");
    }
    if (empty($date)) {
        array_push($errorList, "You must select a valid due date");
    }
    if (strlen($comment) < 2 || strlen($comment) > 2000) {
        array_push($errorList, "Comment must be between 2 and 2000 characters");
    }
     if (!$errorList) {
    // receive data and insert
        DB::insert('groupcomments', array(
            'groupName' => $groupName,
            'date' => $date, 
            'comment' => $comment, 
            'commentedBy' => $commentedBy
        ));
        $app->render('addcomment_success.html.twig');
    } else {
        // TODO: keep values entered on failed submission
        $app->render('commentforgroup.html.twig', array(
            'v' => $valueList
          ));
    }
});
//
// PASSWOR RESET

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

$app->map('/passreset', function () use ($app, $log) {
    // Alternative to cron-scheduled cleanup
    if (rand(1,1000) == 111) {
        // TODO: do the cleanup 1 in 1000 accessed to /passreset URL
    }
    if ($app->request()->isGet()) {
        $app->render('passreset.html.twig');
    } else {
        $email = $app->request()->post('email');
        $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($user) {
            $app->render('passreset_success.html.twig');
            $secretToken = generateRandomString(50);
            // VERSION 1: delete and insert
            /*
              DB::delete('passresets', 'userID=%d', $user['ID']);
              DB::insert('passresets', array(
              'userID' => $user['ID'],
              'secretToken' => $secretToken,
              'expiryDateTime' => date("Y-m-d H:i:s", strtotime("+5 hours"))
              )); */
            // VERSION 2: insert-update TODO
            DB::insertUpdate('passresets', array(
                'userID' => $user['id'],
                'secretToken' => $secretToken,
                'expiryDateTime' => date("Y-m-d H:i:s", strtotime("+5 minutes"))
            ));
            // email user
            $url = 'http://' . $_SERVER['SERVER_NAME'] . '/passreset/' . $secretToken;
            $html = $app->view()->render('email_passreset.html.twig', array(
                'name' => $user['name'],
                'url' => $url
            ));
            $headers = "MIME-Version: 1.0\r\n";
            $headers.= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers.= "From: Noreply <noreply@ipd8.info>\r\n";
            $headers.= "To: " . htmlentities($user['name']) . " <" . $email . ">\r\n";

            mail($email, "Password reset from Daycare", $html, $headers);
            $log->info("Password reset for $email email sent");
        } else {
            $app->render('passreset.html.twig', array('error' => TRUE));
        }
    }
})->via('GET', 'POST');

$app->map('/passreset/:secretToken', function($secretToken) use ($app) {
    $row = DB::queryFirstRow("SELECT * FROM passresets WHERE secretToken=%s", $secretToken);
    if (!$row) {
        $app->render('passreset_notfound_expired.html.twig');
        return;
    }
    if (strtotime($row['expiryDateTime']) < time()) {
        $app->render('passreset_notfound_expired.html.twig');
        return;
    }
    //
    if ($app->request()->isGet()) {
        $app->render('passreset_form.html.twig');
    } else {
        $pass1 = $app->request()->post('pass1');
        $pass2 = $app->request()->post('pass2');
        // TODO: verify password quality and that pass1 matches pass2
        $errorList = array();
        $msg = verifyPassword($pass1);
        if ($msg !== TRUE) {
            array_push($errorList, $msg);
        } else if ($pass1 != $pass2) {
            array_push($errorList, "Passwords don't match");
        }
        //
        if ($errorList) {
            $app->render('passreset_form.html.twig', array(
                'errorList' => $errorList
            ));
        } else {
            // success - reset the password
            DB::update('users', array(
                'password' => password_hash($pass1, CRYPT_BLOWFISH)
                    ), "id=%d", $row['userID']);
            DB::delete('passresets','secretToken=%s', $secretToken);
            $app->render('passreset_form_success.html.twig');
            $log->info("Password reset completed for " . $row['email'] . " uid=". $row['userID']);
        }
    }
})->via('GET', 'POST');


$app->get('/scheduled/daily', function() use ($app, $log) {
    DB::$error_handler = FALSE;
    DB::$throw_exception_on_error = TRUE;
    $log->debug("Daily scheduler run started");
    // 1. clean up old password reset requests
    try {
        DB::delete('passresets', "expiryDateTime < NOW()");    
        $log->debug("Password resets clean up, removed " . DB::affectedRows());
    } catch (MeekroDBException $e) {
        sql_error_handler(array(
                    'error' => $e->getMessage(),
                    'query' => $e->getQuery()
                ));
    }
    $log->debug("Daily scheduler run completed");
    echo "Completed";
});

$app->get('/emailexists/:email', function($email) use ($app, $log) {
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    if ($user) {
        echo "Email already registered";
    }
});

// DO NOT DELETE NEXT LINE!!!
$app->run();
