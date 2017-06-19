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
$user_route->post('login', 'login'); //{username,password,token}
$user_route->post('update', 'update'); //userID,workMobile,homeMobile,homeEmail,workEmail,passportNumber,nationalIdNumber,fullName,locationID,roleID,token
$user_route->post('create', 'create'); //workMobile,homeMobile,homeEmail,workEmail,passportNumber,nationalIdNumber,fullName,locationID,roleID,token,location
$user_route->post('resetpassword', 'resetPassword'); //
// $user_route ->post('all/{page}/{max}','getTableUsers');
// $user_route ->get('all/{page}/{max}','getTableUsers');
//$user_route ->post('delete','removeUser'); //{token,userId}
$user_route->post('summary', 'userSummary');
$user_route->get('summary', 'userSummary');
$user_route->get('agent', 'getUsers');
$user_route->post('agent', 'getUsers');
$user_route->get('all', 'getUsers');
$user_route->get('crm/all', 'getTableUsers');
$user_route->post('crm/all', 'getTableUsers');
$user_route->post('update/status', 'changeUserStatus');
//$user_route->get("updateOldUsers","updateOldUsers");

$item_route = new MicroCollection();
$item_route->setPrefix('/item/');
$item_route->setHandler(new ItemsController());
$item_route->post('create', 'create'); //{productID,serialNumber,token,status}
$item_route->post('update', 'update'); //{productID,serialNumber,token,itemID,status}
$item_route->post('all', 'getAllItems');
$item_route->get('all', 'getAllItems');
$item_route->post('assign', 'assignItem'); //{itemID,userID,token}
$item_route->get('crm/all', 'getTableItems');
$item_route->get('crm/warranties', 'getTableSoldItems');
$item_route->post('warranty', 'activateWarranty');
$item_route->post('crm/all', 'getTableItems');
$item_route->post('return', 'returnItem');
$item_route->post('receive', 'receiveItem');
$item_route->post('delete', 'deleteItem');
$item_route->post('issue', 'issueItem'); //{salesID,ItemID,userID,contactsID}

$prospect_route = new MicroCollection();
$prospect_route->setPrefix('/prospect/');
$prospect_route->setHandler(new ProspectsController());
$prospect_route->post('create', 'createContactProspect'); //{userID,workMobile,nationalIdNumber,fullName,location,token}
$prospect_route->post('update', 'update');
$prospect_route->post('contact/add', 'createProspect'); //maps existing contact to prospect
$prospect_route->post('all', 'getAll');
$prospect_route->get('all', 'getAll');
$prospect_route->post('crm/all', 'getTableProspects');
$prospect_route->get('crm/all', 'getTableProspects');
$prospect_route->get('crm/source', 'getSources');


$sale_route = new MicroCollection();
$sale_route->setPrefix('/sale/');
$sale_route->setHandler(new SalesController());
$sale_route->post('create', 'create');
$sale_route->post('crm/create', 'crmCreateSale');
$sale_route->post('all', 'getSales');
$sale_route->get('all', 'getSales');
$sale_route->get('crm/items', 'getCRMSaleItems');
$sale_route->post('crm/all', 'getTableSales');
$sale_route->get('crm/all', 'getTableSales');
$sale_route->get('crm/pending', 'getTablePendingSales');
$sale_route->get('crm/partners', 'getTablePartnerSales');
$sale_route->post('crm/reconcile', 'reconcileSales');
$sale_route->post('summary', 'dashBoardSummary');
$sale_route->get('summary', 'dashBoardSummary');
$sale_route->get('statistic', 'saleSummary');
$sale_route->get('crm/monitor', 'monitorSales');
$sale_route->post('delete', 'delete');
$sale_route->post('crm/updatepartnersale', 'updatePartnerSale');
$sale_route->get('crm/match', 'matchContacts');
//$sale_route->post('updateOldSales', 'updateOldSales');

$category_route = new MicroCollection();
$category_route->setPrefix('/category/');
$category_route->setHandler(new CategoryController());
$category_route->post('create', 'create');
$category_route->post('update', 'update');
$category_route->post('all', 'getAll');
$category_route->get('all', 'getAll');
$category_route->post('crm/all', 'getTableCategory');
$category_route->get('crm/all', 'getTableCategory');


$product_route = new MicroCollection();
$product_route->setPrefix('/product/');
$product_route->setHandler(new ProductsController());
$product_route->post('create', 'create');
$product_route->post('update', 'update');
$product_route->post('all', 'getAll');
$product_route->get('all', 'getAll'); //getTableProducts
$product_route->post('crm/all', 'getTableProducts');
$product_route->get('crm/all', 'getTableProducts');


$sale_type_route = new MicroCollection();
$sale_type_route->setPrefix('/saleType/');
$sale_type_route->setHandler(new SalesTypeController());
$sale_type_route->post('create', 'create'); ////{salesTypeName,salesTypeDeposit}
$sale_type_route->post('update', 'update'); //{salesTypeName,salesTypeDeposit,salesTypeID}
$sale_type_route->post('all', 'getAll');
$sale_type_route->get('all', 'getAll'); //getTableSaleTypes
$sale_type_route->post('crm/all', 'getTableSaleTypes');
$sale_type_route->get('crm/all', 'getTableSaleTypes');

$frequency_route = new MicroCollection();
$frequency_route->setPrefix('/frequency/');
$frequency_route->setHandler(new FrequencyController());
$frequency_route->post('create', 'create'); //{numberOfDays,frequencyName,token,frequencyID}
$frequency_route->post('update', 'update'); //{numberOfDays,frequencyName,token,frequencyID}
$frequency_route->post('all', 'getAll');
$frequency_route->get('all', 'getAll');
$frequency_route->post('crm/all', 'getTableFrequency');
$frequency_route->get('crm/all', 'getCrmFrequency');



$product_sale_type_price_route = new MicroCollection();
$product_sale_type_price_route->setPrefix('/price/');
$product_sale_type_price_route->setHandler(new ProductSaleTypePriceController());
$product_sale_type_price_route->post('create', 'create'); //{productID,salesTypeID,categoryID,price}
$product_sale_type_price_route->post('update', 'update'); //{productID,salesTypeID,categoryID,price,productSaleTypePriceID}
$product_sale_type_price_route->post('all', 'getAll');
$product_sale_type_price_route->get('all', 'getAll');
$product_sale_type_price_route->post('crm/all', 'getTablePrices');
$product_sale_type_price_route->get('crm/all', 'getTablePrices');

$role_route = new MicroCollection();
$role_route->setPrefix('/role/');
$role_route->setHandler(new RoleController());
$role_route->post('create', 'create'); //{roleName,roleDescription}
$role_route->post('update', 'update'); //{roleID,roleName,roleDescription}
$role_route->post('all', 'getAll');
$role_route->get('all', 'getAll');

$customer_route = new MicroCollection();
$customer_route->setPrefix('/customer/');
$customer_route->setHandler(new CustomerController());
$customer_route->post('crm/all', 'getTableCustomers');
$customer_route->get('crm/all', 'getTableCustomers');
$customer_route->get('all', 'getAll');
$customer_route->post('all', 'getAll');
$customer_route->post('partner/create', 'create');
$customer_route->post('update', 'update');
$customer_route->post('delete', 'delete');

$user_item_route = new MicroCollection();
$user_item_route->setPrefix('/userItem/');
$user_item_route->setHandler(new UserItemsController());
$user_item_route->post('crm/all', 'getTableUserItems');
$user_item_route->get('crm/all', 'getTableUserItems');

$contacts_route = new MicroCollection();
$contacts_route->setPrefix('/contact/');
$contacts_route->setHandler(new ContactsController());
$contacts_route->post('search', 'searchContacts');
$contacts_route->get('search', 'searchContacts');
$contacts_route->get('crm/search', 'searchContacts');
$contacts_route->post('create', 'createContact'); //workMobile,nationalIdNumber,fullName,location
$contacts_route->get('reconcile', 'reconcile');

$transaction_route = new MicroCollection();
$transaction_route->setPrefix('/transaction/');
$transaction_route->setHandler(new TransactionsController());
$transaction_route->post('create', 'create'); //workMobile,nationalIdNumber,fullName,location
$transaction_route->post('crm/all', 'getTableTransactions');
$transaction_route->get('crm/all', 'getTableTransactions');
$transaction_route->get('crm/unknown', 'getTableUnknownPayments');
$transaction_route->post('checkpayment', 'checkPayment');
//$transaction_route->post('dummy/create', 'create'); //workMobile,nationalIdNumber,fullName,location
//$transaction_route->get("dummy", 'dummyTransaction');
$transaction_route->post('reconcilepayment', 'reconcilePayment'); //To reconcile unknown payments
//$transaction_route->get('reconcile', 'reconcileTransaction');
$transaction_route->get('link', 'reconcile');

$inbox_route = new MicroCollection();
$inbox_route->setPrefix('/inbox/');
$inbox_route->setHandler(new InboxController());
$inbox_route->post('create', 'create'); //{MSISDN,message,token}
$inbox_route->get('create', 'create');
$inbox_route->post('crm/all', 'getTableInbox');
$inbox_route->get('crm/all', 'getTableInbox');

$outbox_route = new MicroCollection();
$outbox_route->setPrefix('/outbox/');
$outbox_route->setHandler(new OutboxController());
$outbox_route->post('create', 'create'); //{message,contactsID,userID,status,token}
$outbox_route->post('crm/all', 'getTableOutbox');
$outbox_route->get('crm/all', 'getTableOutbox');


$call_route = new MicroCollection();
$call_route->setPrefix('/call/');
$call_route->setHandler(new CallController());
$call_route->post('create', 'create'); //
$call_route->get('crm/all', 'getTableCalls');
$call_route->get('crm/disposition', 'dispositions');
$call_route->get('crm/promoter', 'promoters');
$call_route->get('crm/promoterscore', 'promoterScores');
$call_route->post('score/create', 'createScores');

$ticket_category_route = new MicroCollection();
$ticket_category_route->setPrefix('/ticketcategory/');
$ticket_category_route->setHandler(new TicketCategoryController());
$ticket_category_route->post('create', 'create'); //{token,ticketCategoryName,ticketCategoryDescription}
$ticket_category_route->post('all', 'getAll');
$ticket_category_route->get('all', 'getAll');

$priority_route = new MicroCollection();
$priority_route->setPrefix('/priority/');
$priority_route->setHandler(new PriorityController());
$priority_route->post('create', 'create'); //{priorityName,priorityDescription,token}
$priority_route->post('all', 'getAll');
$priority_route->get('all', 'getAll');

$ticket_route = new MicroCollection();
$ticket_route->setPrefix('/ticket/');
$ticket_route->setHandler(new TicketController());
$ticket_route->post('create', 'create'); //{ticketTitle,ticketDescription,customerID,assigneeID,ticketCategoryID,priorityID,status}
$ticket_route->post('update', 'update');
$ticket_route->post('crm/all', 'getTableTickets');
$ticket_route->get('crm/all', 'getTableTickets');
$ticket_route->get('all', 'getAll');
$ticket_route->post('close', 'close');
$ticket_route->post('assign', 'assign');
$ticket_route->get('crm/updates', 'tableTicketUpdates');
$ticket_route->get('crm/email', 'email');


$metropol_route = new MicroCollection();
$metropol_route->setPrefix('/metropol/');
$metropol_route->setHandler(new MetropolController());
$metropol_route->post('rate', 'creditRate');
$metropol_route->post('identity/verify','identityVerification');

$reconcile_route = new MicroCollection();
$reconcile_route->setPrefix('/reconcile/');
$reconcile_route->setHandler(new ReconcileController());
$reconcile_route->get('redo', 'redoReconciledTransactions');


$app->mount($user_route);
$app->mount($item_route);
$app->mount($prospect_route);
$app->mount($sale_route);
$app->mount($category_route);
$app->mount($product_route);
$app->mount($sale_type_route);
$app->mount($frequency_route);
$app->mount($product_sale_type_price_route);
$app->mount($role_route);
$app->mount($transaction_route);
$app->mount($contacts_route);
$app->mount($customer_route);
$app->mount($user_item_route);
$app->mount($ticket_route);
$app->mount($inbox_route);
$app->mount($outbox_route);
$app->mount($ticket_category_route);
$app->mount($priority_route);
$app->mount($call_route);
$app->mount($metropol_route);
$app->mount($reconcile_route);


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








