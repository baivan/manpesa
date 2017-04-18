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
$app=new Micro($di);

$user_route = new MicroCollection();
$user_route ->setPrefix('/user/');
$user_route ->setHandler(new UsersController());
$user_route ->post('login','login'); //{username,password,token}
$user_route ->post('update','update'); //userID,workMobile,homeMobile,homeEmail,workEmail,passportNumber,nationalIdNumber,fullName,locationID,roleID,token
$user_route ->post('create','create');//workMobile,homeMobile,homeEmail,workEmail,passportNumber,nationalIdNumber,fullName,locationID,roleID,token,location
$user_route ->post('resetpassword','resetPassword');//
$user_route ->post('all/{page}/{max}','getTableUsers');
$user_route ->get('all/{page}/{max}','getTableUsers');
$user_route ->post('delete','removeUser'); //{token,userId}

$item_route = new MicroCollection();
$item_route ->setPrefix('/item/');
$item_route ->setHandler(new ItemsController());
$item_route ->post('create','create');//{productID,serialNumber,token,status}
$item_route ->post('update','update');//{productID,serialNumber,token,itemID,status}
$item_route ->post('all','getAllItems');
$item_route ->get('all','getAllItems');
$item_route ->post('assign','assignItem');//{itemID,userID,token}

$prospect_route = new MicroCollection();
$prospect_route ->setPrefix('/prospect/');
$prospect_route ->setHandler(new ProspectsController());
$prospect_route ->post('create','create');//{userID,workMobile,nationalIdNumber,fullName,location,token}
//$prospect_route ->post('update','update');
$prospect_route ->post('all','getAll');
$prospect_route ->get('all','getAll');

$sale_route = new MicroCollection();
$sale_route ->setPrefix('/sale/');
$sale_route ->setHandler(new SalesController());
$sale_route ->post('create','create');//{paymentPlanID,amount,userID,workMobile,nationalIdNumber,fullName,location,token}
//$sale_route ->post('update')
$sale_route ->post('all','getSales'); 
$sale_route ->get('all','getSales');

$category_route = new MicroCollection();
$category_route ->setPrefix('/category/');
$category_route ->setHandler(new CategoryController());
$category_route ->post('create','create');
$category_route ->post('update','update');
$category_route ->post('all','getAll');
$category_route ->get('all','getAll');

$product_route = new MicroCollection();
$product_route ->setPrefix('/product/');
$product_route ->setHandler(new ProductsController());
$product_route ->post('create','create');
$product_route ->post('update','update');
$product_route ->post('all','getAll');
$product_route ->get('all','getAll');

$sale_type_route = new MicroCollection();
$sale_type_route ->setPrefix('/saleType/');
$sale_type_route ->setHandler(new SalesTypeController());
$sale_type_route ->post('create','create');////{salesTypeName,salesTypeDeposit}
$sale_type_route ->post('update','update');//{salesTypeName,salesTypeDeposit,salesTypeID}
$sale_type_route ->post('all','getAll');
$sale_type_route ->get('all','getAll');

$frequency_route = new MicroCollection();
$frequency_route ->setPrefix('/frequency/');
$frequency_route ->setHandler(new FrequencyController());
$frequency_route ->post('create','create');//{numberOfDays,frequencyName,token,frequencyID}
$frequency_route ->post('update','update');//{numberOfDays,frequencyName,token,frequencyID}
$frequency_route ->post('all','getAll');
$frequency_route ->get('all','getAll');

$product_sale_type_price_route = new MicroCollection();
$product_sale_type_price_route ->setPrefix('/price/');
$product_sale_type_price_route ->setHandler(new ProductSaleTypePriceController());
$product_sale_type_price_route ->post('create','create');//{productID,salesTypeID,categoryID,price}
$product_sale_type_price_route ->post('update','update');//{productID,salesTypeID,categoryID,price,productSaleTypePriceID}
$product_sale_type_price_route ->post('all','getAll');
$product_sale_type_price_route ->get('all','getAll');




$app->mount($user_route);
$app->mount($item_route);
$app->mount($prospect_route);
$app->mount($sale_route);
$app->mount($category_route);
$app->mount($product_route);
$app->mount($sale_type_route);
$app->mount($frequency_route);
$app->mount($product_sale_type_price_route);

try {
    // Handle the request
    $response =  $app->handle();


} catch (\Exception $e) {
    // $logg_file = $config->logPath->location;

    $logger = new FileAdapter($config->logPath->location.'apicalls_logs.log');
     $logger->log(date("Y-m-d H:i:s").' '.$e->getMessage());
      $res = new SystemResponses(); 
      $request = new Request();
      $res->composePushLog("Error",$e->getMessage(),"Client ip ".$request->getClientAddress());
    echo "(ง'̀-'́)ง I wanna go home! Get me out of here please!! ༼ つ ಥ_ಥ ༽つ Am lost this page is not available ";
}








