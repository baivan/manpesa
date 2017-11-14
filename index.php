<?php

use Phalcon\Mvc\Micro;
use Phalcon\Loader;
use Phalcon\Di\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql as PdoMysql;
use Phalcon\Http\Response;
use Phalcon\Mvc\Micro\Collection as MicroCollection;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Http\Request;

error_reporting(E_ALL);

define('APP_PATH', realpath(''));

/**
 * Read the configuration
 */
$config = include APP_PATH . "/app/config/config.php";

/**
 * Read auto-loader
 */
include APP_PATH . "/app/config/loader.php";

/**
 * Read services
 */
include APP_PATH . "/app/config/services.php";

/**
 * Read composer libraries
 */
include APP_PATH . "/vendor/autoload.php";


//create and bind the DI to the application 

$app = new Micro($di);

$user_route = new MicroCollection();
$user_route->setPrefix('/user/');
$user_route->setHandler(new UsersController());
$user_route->post('login', 'login'); 
$user_route->post('register', 'register');
$user_route->post('update', 'update');
$user_route->post('reset/password', 'resetPassword');
//$user_route->get('token','generateTocken');



$debt_route = new MicroCollection();
$debt_route->setPrefix('/debts/');
$debt_route->setHandler(new DebtsController());
$debt_route->post('create','create');
$debt_route->post('edit','edit');
$debt_route->get('all','getAll');


$app->mount($user_route);
$app->mount($debt_route);



try {
    // Handle the request
    $response = $app->handle();
} catch (\Exception $e) {
    // $logg_file = $config->logPath->location;

    $logger = new FileAdapter($config->logPath->location . 'apicalls_logs.log');
    $logger->log(date("Y-m-d H:i:s") . ' ' . $e->getMessage());
    $res = new SystemResponses();
    $request = new Request();
    $res->composePushLog("Error", $e->getMessage(), "Client ip " . $request->getClientAddress());
    echo "(ง'̀-'́)ง I wanna go home! Get me out of here please!! ༼ つ ಥ_ಥ ༽つ Am lost this page is not available ";
}

/*

{
    "success": "Login successful ",
    "data": {
        "userId": "1",
        "mobile": "0724040350",
        "name": "James Test",
        "email": "james@test.com",
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJ3d3cuc291dGh3ZWxsLmlvIiwiaWF0IjoxNTEwNTczMzE4LCJuYmYiOjE1MTA1NzMzMjgsIm5hbWUiOm51bGwsInVzZXJJZCI6IjEifQ.cvYEEaAW3e7wMWnIY1wud48GZHWteZwHL6WR6JTWoX4"
    },
    "code": 201
}


*/






