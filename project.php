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
    $educators = DB::query("SELECT educatorId,name,phone,email,groupName,startDate,"
            . "yearlySalary,previousVacation,nextVacation FROM educator");
    $app->render('listofeducators.html.twig', ['educators' => $educators]);
});

$app->get('/viewphoto/:educatorId', function($educatorId) use ($app) {
 if (!$_SESSION['daycareuser']) {
       $app->render('forbidden.html.twig');
       return;
   }
    $educators = DB::queryFirstRow("SELECT photo, photomimetype FROM educator WHERE educatorId=%i", $educatorId);   
        $app->response->headers->set('Content-Type', $educators['photomimetype']);
        echo $educators['photo'];   
});

// List of kids
$app->get('/listofkids', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
       $app->render('login.html.twig');
        return;
    }
    $kids = DB::query("SELECT kidId,kidName,age,groupName,motherName,motherPhone,address,allergies,notes"
            ." FROM kids");
    $app->render('listofkids.html.twig', ['kids' => $kids]);
});

$app->get('/viewphotokids/:kidId', function($kidId) use ($app) {
   if (!$_SESSION['daycareuser']) {
        $app->render('forbidden.html.twig');
       return;
    }
    $kids = DB::queryFirstRow("SELECT photo, photomimetype FROM kids WHERE kidId=%i", $kidId); 
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
        DB::insert('educator', array(
  
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
    $kids = DB::query("SELECT * FROM kids Where id=%i", $id);
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
// EDIT CHILD
$app->get('/editchild', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $app->render('editchild.html.twig');
});

$app->post('/editchild', function() use ($app) {
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
        $app->render('editchild_success.html.twig');
    } else {
        $app->render('editchild.html.twig', array(
            'v' => $valueList
        ));
    }
});

// List of comments for kids
$app->get('/listofkidcomments', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
       $app->render('login.html.twig');
        return;
    }
    $kidscomments = DB::query("SELECT kidName,date,comment,commentedBy"
            ." FROM kidscomment");
    $app->render('listofkidcomments.html.twig', ['kidscomments' => $kidscomments]);
});

// List of comments for groups
$app->get('/listofgroupcomments', function() use ($app) {
    if (!$_SESSION['daycareuser']) {
       $app->render('login.html.twig');
        return;
    }
    $groupscomments = DB::query("SELECT groupName,date,comment,commentedBy"
            ." FROM groupsscomment");
    $app->render('listofgroupcomments.html.twig', ['groupscomments' => $groupscomments]);
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
        DB::insert('kidscomment', array(
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
    $errorList = array();
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
        DB::insert('groupscomment', array(
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
// DO NOT DELETE NEXT LINE!!!
$app->run();
