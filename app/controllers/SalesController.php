<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

class SalesController extends Controller {

    public function indexAction() {
        
    }

    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    protected function isDateBetweenDates($date, $startDate, $endDate) {
        $date = new DateTime($date);
        $startDate = new DateTime($startDate);
        $endDate = new DateTime($endDate);
        return $date > $startDate && $date < $endDate;
    }

    public function create() { //{contactsID,amount,userID,salesTypeID,frequencyID,productID}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $paymentPlanDeposit = isset($json->paymentPlanDeposit) ? $json->paymentPlanDeposit : 0;
        $salesTypeID = isset($json->salesTypeID) ? $json->salesTypeID : 0;
        $frequencyID = isset($json->frequencyID) ? $json->frequencyID : 0;
        $contactsID = isset($json->contactsID) ? $json->contactsID : NULL;
        $userID = isset($json->userID) ? $json->userID : NULL;
        $amount = isset($json->amount) ? $json->amount : 0;
        $productID = isset($json->productID) ? $json->productID : NULL;

        $location = isset($json->location) ? $json->location : NULL;
        $workMobile = isset($json->workMobile) ? $json->workMobile : NULL;
        $fullName = isset($json->fullName) ? $json->fullName : NULL;
        $nationalIdNumber = isset($json->nationalIdNumber) ? $json->nationalIdNumber : NULL;

        $token = $json->token;



        if (!$token) {
            return $res->dataError("Token missing " . json_encode($json), []);
        }
        if (!$salesTypeID) {
            return $res->dataError("salesTypeID missing ", []);
        }
        if (!$userID) {
            return $res->dataError("userID missing ", []);
        }
        if (!$amount) {
            return $res->dataError("amount missing ", []);
        }
        if (!$frequencyID) {
//return $res->dataError("frequencyID missing ");
            $frequencyID = 0;
        }
        if (!$productID) {
            return $res->dataError("product missing ", []);
        }



        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }


        try {

            if (!$contactsID) {
                if ($workMobile && $fullName && $location && $nationalIdNumber) { //createContact 
                    $contactsID = $this->createContact($workMobile, $nationalIdNumber, $fullName, $location, $dbTransaction);
                }
            }

            $paymentPlanID = $this->createPaymentPlan($paymentPlanDeposit, $salesTypeID, $frequencyID, $dbTransaction);
            $customerID = $this->createCustomer($userID, $contactsID, $dbTransaction);

            $prospectsID = NULL;
            $prospect = Prospects::findFirst(array("contactsID=:id: ",
                        'bind' => array("id" => $contactsID)));
            if ($prospect) {
                $prospectsID = $prospect->prospectsID;
            }

            $sale = new Sales();
            $sale->status = 0;
            $sale->paymentPlanID = $paymentPlanID;
            $sale->userID = $userID;
            $sale->customerID = $customerID;
            $sale->prospectsID = $prospectsID;
            $sale->contactsID = $contactsID;
            $sale->amount = $amount;
            $sale->productID = $productID;
            $sale->createdAt = date("Y-m-d H:i:s");

            if ($sale->save() === false) {
                $errors = array();
                $messages = $sale->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
//return $res->dataError('sale create failed',$errors);
                $dbTransaction->rollback('sale create failed' . json_encode($errors));
            }

            /* send message to customer */
            $customer = $this->getCustomerDetails($customerID);
            $MSISDN = $customer["workMobile"];
            $name = $customer["fullName"];

            $res->sendMessage($MSISDN, "Dear " . $name . ", your sale has been placed successfully, please pay Ksh. " . $paymentPlanDeposit);
            $dbTransaction->commit();

            return $res->success("Sale saved successfully, await payment ", $sale);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('sale create error', $message);
        }
    }

    public function createPaymentPlan($paymentPlanDeposit, $salesTypeID, $frequencyID, $dbTransaction, $repaymentPeriodID = 0) {
        $res = new SystemResponses();
        $paymentPlan = PaymentPlan::findFirst(array("salesTypeID=:s_id: AND frequencyID=:f_id: AND paymentPlanDeposit=:pp_deposit:",
                    'bind' => array("s_id" => $salesTypeID, "f_id" => $frequencyID, 'pp_deposit' => $paymentPlanDeposit)));
        $saleType = SalesType::findFirst(array("salesTypeID=:id: ",
                    'bind' => array("id" => $salesTypeID)));

        if ($paymentPlan) {
            return $paymentPlan->paymentPlanID;
        } else {
            $paymentPlan = new PaymentPlan();
            $paymentPlan->paymentPlanDeposit = $paymentPlanDeposit;
            $paymentPlan->salesTypeID = $salesTypeID;
            $paymentPlan->frequencyID = $frequencyID;
            $paymentPlan->repaymentPeriodID = $repaymentPeriodID;
            $paymentPlan->createdAt = date("Y-m-d H:i:s");

            if ($paymentPlan->save() === false) {
                $errors = array();
                $messages = $paymentPlan->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }

                return 0;
// $res->dataError('paymentPlan create failed',$errors);
//$dbTransaction->rollback('paymentPlan create failed' . json_encode($errors));
//return 0;
            }
            return $paymentPlan->paymentPlanID;
        }
    }

    public function createCustomer($userID, $contactsID, $dbTransaction, $locationID = 0) {
        $res = new SystemResponses();
        $customer = Customer::findFirst(array("contactsID=:id: ",
                    'bind' => array("id" => $contactsID)));

//$res->dataError("select user $userID contact $contactsID");
        if ($customer) {
            return $customer->customerID;
        } else {
            $customer = new Customer();
            $customer->locationID = $locationID;
            $customer->userID = $userID;
            $customer->contactsID = $contactsID;
            $customer->createdAt = date("Y-m-d H:i:s");
            if ($customer->save() === false) {
                $errors = array();
                $messages = $customer->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
//  $res->dataError('customer create failed',$errors);
                $dbTransaction->rollback('customer create failed' . json_encode($errors));
//return 0;
            }

            return $customer->customerID;
        }
    }

    public function createContact($workMobile, $nationalIdNumber, $fullName, $location, $dbTransaction, $homeMobile = null, $homeEmail = null, $workEmail = null, $passportNumber = 0, $locationID = 0) {
        $res = new SystemResponses();
        $workMobile = $res->formatMobileNumber($workMobile);
        if ($homeMobile) {
            $homeMobile = $res->formatMobileNumber($homeMobile);
        }

        $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
                    'bind' => array("w_mobile" => $workMobile)));
        if ($contact) {
            return $contact->contactsID;
        } else {


            $contact = new Contacts();
            $contact->workEmail = $workEmail;
            $contact->homeEmail = $homeEmail;
            $contact->workMobile = $workMobile;
            $contact->homeMobile = $homeMobile;
            $contact->fullName = $fullName;
            $contact->location = $location;
            $contact->nationalIdNumber = $nationalIdNumber;
            $contact->passportNumber = $passportNumber;
            $contact->locationID = $locationID;
            $contact->createdAt = date("Y-m-d H:i:s");

            if ($contact->save() === false) {
                $errors = array();
                $messages = $contact->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
// $res->dataError('contact create failed',$errors);
                $dbTransaction->rollback('paymentPlan create failed' . json_encode($errors));
// return 0;
            }

            $res->sendMessage($workMobile, "Dear " . $fullName . ", welcome to Envirofit. For any questions or comments call 0800722700 ");
            return $contact->contactsID;
        }
    }

    public function mapItemToSale($saleID, $itemID) {
        $res = new SystemResponses();
        $saleItem = SalesItem::findFirst(array("itemID=:i_id: ",
                    'bind' => array("i_id" => $itemID)));

        if ($saleItem) {
            $res->dataError("Item already sold $itemID");
            return 0;
        } else {
            $saleItem = new SalesItem();
            $saleItem->itemID = $itemID;
            $saleItem->saleID = $saleID;
            $saleItem->status = 0;
            $saleItem->createdAt = date("Y-m-d H:i:s");
            if ($saleItem->save() === false) {
                $errors = array();
                $messages = $saleItem->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $res->dataError('saleItem create failed ' . $saleID, $errors);
                return 0;
            }
            return $saleItem->saleItemID;
        }
    }

    public function updateItemToSold($itemID) {
        $res = new SystemResponses();
        $item = Item::findFirst(array("itemID=:id: ",
                    'bind' => array("id" => $itemID)));
        if ($item) {
            $item->status = 2;
            $item->save();
            return true;
        } else {
            return false;
        }
    }

    public function createSale() {//{salesTypeID,frequencyID,itemID,prospectID,nationalIdNumber,fullName,location,workMobile,userID,paymentPlanDeposit,customerID}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
//$items = $json->items;

        $salesTypeID = $json->salesTypeID;
        $frequencyID = $json->frequencyID;
        $itemID = $json->itemID;
        $userID = $json->userID;
        $prospectID = $json->prospectID;
        $customerID = $json->customerID;
        $token = $json->token;
        $location = $json->location;
        $workMobile = $json->workMobile;
        $fullName = $json->fullName;
        $nationalIdNumber = $json->nationalIdNumber;
        $paymentPlanDeposit = $json->paymentPlanDeposit;
        $amount = $json->amount;

        $contactsID;
        $paymentPlanID;

        if (!$token) {//|| !$salesTypeID || !$userID || !$amount || !$frequencyID){
            return $res->dataError("Token missing ");
        }
        if (!$salesTypeID) {
            return $res->dataError("salesTypeID missing ");
        }
        if (!$userID) {
            return $res->dataError("userID missing ");
        }
        if (!$amount) {
            return $res->dataError("amount missing ");
        }
        if (!$frequencyID) {
//return $res->dataError("frequencyID missing ");
            $frequencyID = 0;
        }


        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }


        if ($prospectID || $prospectID > 0) {
            $prospect = Prospects::findFirst(array("prospectsID=:id: ",
                        'bind' => array("id" => $prospectID)));
            if ($prospect) {
                $contactsID = $prospect->contactsID;
            } else {
                return $res->dataError("Prospect not found");
            }
        } elseif ($customerID || $customerID > 0) { //added create sale of an existing customer
            $customer = Customer::findFirst(array("customerID=:id: ",
                        'bind' => array("id" => $customerID)));
        } elseif ($workMobile && $nationalIdNumber && $fullName && $location) {
            $workMobile = $res->formatMobileNumber($workMobile);
            $contactsID = $this->createContact($workMobile, $nationalIdNumber, $fullName, $location);

            if (!$contactsID || $contactsID <= 0) {
                return $res->dataError("Contacts create error");
            }
        } else {
            return $res->dataError("Prospect not found");
        }

//then we create customer if customerId not provided
        if (!$customerID || $customerID <= 0) {
            $customerID = $this->createCustomer($userID, $contactsID);
        }


        if (!$customerID || $customerID <= 0) {
            return $res->dataError("Customer not found");
        }

//after creating customer and contacts above we create payment plan
        $paymentPlanID = $this->createPaymentPlan($paymentPlanDeposit, $salesTypeID, $frequencyID);

        if (!$paymentPlanID || $paymentPlanID <= 0) {
            return $res->dataError("Payment Plan not found");
        }


//now we can create a sale
        $sale = new Sales();
        $sale->status = 0;
        $sale->paymentPlanID = $paymentPlanID;
        $sale->userID = $userID;
        $sale->customerID = $customerID;
        $sale->amount = $amount;
        $sale->createdAt = date("Y-m-d H:i:s");

        if ($sale->save() === false) {
            $errors = array();
            $messages = $sale->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('sale create failed', $errors);
        }

        /* send message to customer */
        $MSISDN = $this->getCustomerMobileNumber($customerID);
        $res->sendMessage($workMobile, "Your sale has been placed successfully");

        $saleStatus = $this->mapItemToSale($sale->saleID, $itemID);

//now we map this sale to item mapItemToSale($saleID,$itemID)
        if (!$saleStatus || $saleStatus <= 0) {
            return $res->dataError("Item not mapped to sale, please contact system admin $itemID");
        }
//set item as sold
        if (!$this->updateItemToSold($itemID)) {
            return $res->dataError('Item not marked as sold, please contact system admin', $sale);
        }

        return $res->success("Sale successfully done ", $sale);
    }

    public function delete() {//salesID
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();

        $salesID = $json->salesID;
        $token = $json->token;

        if (!$token || !$salesID) {//|| !$salesTypeID || !$userID || !$amount || !$frequencyID){
            return $res->dataError("fields missing");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $sale = Sales::findFirst(array("salesID=:id: ",
                    'bind' => array("id" => $salesID)));
        $sale->status = -2;

        if ($sale->save() === false) {
            $errors = array();
            $messages = $sale->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('sale delete failed', $errors);
        }

        return $res->success("sale successfully deleted ", $sale);
    }

    public function getCustomerDetails($customerID) {
        $customerquery = "SELECT cs.workMobile ,cs.fullName from contacts cs left join customer co on cs.contactsID=co.contactsID WHERE co.customerID=$customerID";

        $customer = $this->rawSelect($customerquery);
        return $customer[0];
    }

    public function createCustomerSale() {//{paymentPlanID,amount,userID,workMobile,nationalIdNumber,fullName,location,token,items[]}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();

        $paymentPlanID = $json->paymentPlanID;
        $userID = $json->userID;
        $status = $json->status;
        $amount = $json->amount;
        $items = $json->items;
        $token = $json->token;
        $location = $json->location;
        $workMobile = $json->workMobile;
        $fullName = $json->fullName;
        $nationalIdNumber = $json->nationalIdNumber;
        $customerID = 0;

        if (!$token || !$paymentPlanID || !$userID || !$amount) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }


        if (!$status) {
            $status = 0;
        }

        $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
                    'bind' => array("w_mobile" => $workMobile)));
        if ($contact) {
            $customer = Customer::findFirst(array("contactsID=:id: ",
                        'bind' => array("id" => $contact->contactsID)));
            if ($customer) {
                $res->dataError("Customer exists");
                $customerID = $customer->customerID;
            }
        } else {
            $contact = new Contacts();
            $contact->workEmail = "null";
            $contact->workMobile = $workMobile;
            $contact->fullName = $fullName;
            $contact->location = $location;
            $contact->createdAt = date("Y-m-d H:i:s");
            if ($nationalIdNumber) {
                $contact->nationalIdNumber = $nationalIdNumber;
            } else {
                $contact->nationalIdNumber = "null";
            }

            if ($contact->save() === false) {
                $errors = array();
                $messages = $contact->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $res->dataError('contact create failed', $errors);
            }

            $customer = new Customer();
            $customer->status = 0;
            $customer->locationID = 0;
            $customer->userID = $userID;
            $customer->contactsID = $contact->contactsID;
            $customer->createdAt = date("Y-m-d H:i:s");
            if ($customer->save() === false) {
                $errors = array();
                $messages = $customer->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                return $res->dataError('customer create failed', $errors);
            }
            $customerID = $customer->customerID;
        }

//  create sale
        $sale = new Sales();
        $sale->status = 0;
        $sale->paymentPlanID = $paymentPlanID;
        $sale->userID = $userID;
        $sale->customerID = $customerID;
        $sale->amount = $amount;
        $sale->createdAt = date("Y-m-d H:i:s");

        if ($sale->save() === false) {
            $errors = array();
            $messages = $sale->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            $res->dataError('sale create failed', $errors);
        }



//mapp items to this sale
        foreach ($items as $itemID) {
            $saleItem = SalesItem::findFirst("itemID=$itemID");
            if ($saleItem) {
                $res->dataError("Item already sold");
            } else {
                $saleItem = new SalesItem();
                $saleItem->itemID = $itemID;
                $saleItem->saleID = $sale->salesID;
                $saleItem->status = 0;
                $saleItem->createdAt = date("Y-m-d H:i:s");
                if ($saleItem->save() === false) {
                    $errors = array();
                    $messages = $saleItem->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    return $res->dataError('saleItem create failed', $errors);
                }
            }
        }


        return $res->success("Sale created successfully ", $sale);
    }

    public function getSales() {//{userID,customerID,token}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $customerID = $request->getQuery('customerID');
        $userID = $request->getQuery('userID');

        $saleQuery = " SELECT s.salesID,si.itemID,co.workMobile,co.workEmail,co.passportNumber,co.nationalIdNumber,co.fullName,s.createdAt,co.location,c.customerID,s.paymentPlanID,s.amount,st.salesTypeName,i.serialNumber,p.productID,p.productName, ca.categoryName FROM sales s JOIN customer c on s.customerID=c.customerID LEFT JOIN contacts co on c.contactsID=co.contactsID LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID  LEFT JOIN sales_item si ON s.salesID=si.saleID LEFT JOIN item i on si.itemID=i.itemID LEFT JOIN product p ON s.productID=p.productID LEFT JOIN category ca on p.categoryID=ca.categoryID ";


        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }


        if ($customerID > 0 && $userID > 0) {
            $saleQuery = $saleQuery . " WHERE s.userID=$userID AND s.customerID=$customerID";
        } elseif ($customerID > 0 && $userID <= 0) {
            $saleQuery = $saleQuery . " WHERE s.customerID=$customerID";
        } elseif ($userID > 0 && $customerID <= 0) {
            $saleQuery = $saleQuery . " WHERE s.userID=$userID";
        }
        /* else{
          $saleQuery ="SELECT s.salesID,si.itemID,co.workMobile,co.workEmail,co.passportNumber,co.nationalIdNumber,co.fullName,s.createdAt,co.location,c.customerID,s.paymentPlanID,s.amount,st.salesTypeName,i.serialNumber,p.productName, ca.categoryName FROM sales s JOIN sales_item si ON s.salesID=si.saleID LEFT JOIN customer c on s.customerID=c.customerID LEFT JOIN contacts co on c.contactsID=co.contactsID LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID LEFT JOIN item i on si.itemID=i.itemID LEFT JOIN product p on i.productID=p.productID LEFT JOIN category ca on p.categoryID=ca.categoryID ";
          } */

        $sales = $this->rawSelect($saleQuery);


        return $res->getSalesSuccess($sales);
    }

    public function getTableSales() { //sort, order, page, limit,filter,userID
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $userID = $request->getQuery('userID');
        $sort = $request->getQuery('sort') ? $request->getQuery('sort') : 'salesID';
        $order = $request->getQuery('order') ? $request->getQuery('order') : 'ASC';
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $status = $request->getQuery('status');
        $salesID = $request->getQuery('salesID');
        $contactsID = $request->getQuery('contactsID');
        $startDate = $request->getQuery('start');
        $endDate = $request->getQuery('end');

        $countQuery = "SELECT count(DISTINCT s.salesID) as totalSales ";
//        $defaultQuery = "from sales s LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID LEFT JOIN frequency f on pp.frequencyID=f.frequencyID LEFT JOIN product_sale_type_price psp on s.productID=psp.productID left JOIN product p on s.productID=p.productID  LEFT JOIN users u on s.userID=u.userID LEFT join contacts c on u.contactID=c.contactsID left JOIN customer cu on s.customerID=cu.customerID left JOIN contacts c1 on cu.contactsID=c1.contactsID WHERE s.status > 0; ";
//        $selectQuery = "SELECT s.salesID, s.paymentPlanID, pp.paymentPlanDeposit, "
//                . "pp.salesTypeID, st.salesTypeName, psp.price,pp.frequencyID,"
//                . "f.numberOfDays, f.frequencyName, s.userID,c.fullName AS agentName,"
//                . "c.workMobile AS agentNumber,s.customerID, c1.fullName AS customerName, "
//                . "c1.workMobile AS customerNumber, c1.nationalIdNumber, s.productID, p.productName, s.createdAt ";

        $selectQuery = "SELECT s.salesID, s.paymentPlanID,pp.paymentPlanDeposit AS planDepositAmount,"
                . "pp.salesTypeID, st.salesTypeName,pp.frequencyID,f.numberOfDays, "
                . "f.frequencyName,s.customerID, s.contactsID, s.prospectsID,c.fullName AS customerName, "
                . "c.workMobile AS customerMobile, c.nationalIdNumber, s.productID, "
                . "p.productName, s.userID,c1.fullName AS agentName, c1.workMobile AS agentMobile, s.amount, s.paid, s.status, s.createdAt ";

        /*  $defaultQuery = "FROM sales s LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID "
          . "LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID LEFT JOIN frequency f "
          . "ON pp.frequencyID=f.frequencyID LEFT JOIN customer cu on s.customerID=cu.customerID "
          . "LEFT JOIN contacts c on cu.contactsID=c.contactsID LEFT JOIN product p "
          . "ON s.productID=p.productID LEFT JOIN users u ON s.userID=u.userID "
          . "LEFT JOIN contacts c1 ON u.contactID=c1.contactsID  ";
         */
        $defaultQuery = "FROM sales s LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID "
                . "LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID LEFT JOIN frequency f "
                . "ON pp.frequencyID=f.frequencyID "
                . "LEFT JOIN contacts c on s.contactsID=c.contactsID LEFT JOIN product p "
                . "ON s.productID=p.productID LEFT JOIN users u ON s.userID=u.userID "
                . "LEFT JOIN contacts c1 ON u.contactID=c1.contactsID  ";

        $whereArray = [
            's.status' => $status,
            'filter' => $filter,
            's.salesID' => $salesID,
            's.contactsID' => $contactsID,
            's.userID' => $userID,
            'date' => [$startDate, $endDate]
        ];

//        $logger->log("Sales Request Data: " . json_encode($whereArray));

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['st.salesTypeName', 'f.frequencyName', 'c.fullName', 'c.workMobile', 'c1.fullName', 'c1.workMobile', 'p.productName'];

                $valueString = "";
                foreach ($searchColumns as $searchColumn) {
                    $valueString .= $value ? "" . $searchColumn . " REGEXP '" . $value . "' ||" : "";
                }
                $valueString = chop($valueString, " ||");
                if ($valueString) {
                    $valueString = "(" . $valueString;
                    $valueString .= ") AND";
                }
                $whereQuery .= $valueString;
            } else if ($key == 'unsorted') {
                $valueString = $value ? "s.status=0 AND" : "";
                $whereQuery .= $valueString;
            } else if ($key == 's.status' && $value == 404) {
                $valueString = $value ? "" . $key . ">0" . " AND " : "";
                $whereQuery .= $valueString;
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(s.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
                }
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND" : "";
                $whereQuery .= $valueString;
            }
        }

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND");
        }

//$whereQuery = $whereQuery ? "AND $whereQuery " : "";
        $whereQuery = $whereQuery ? " WHERE (s.status=1 || s.status=2) AND $whereQuery " : " WHERE (s.status=1 || s.status=2) ";

        $countQuery = $countQuery . $defaultQuery . $whereQuery;
        $selectQuery = $selectQuery . $defaultQuery . $whereQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

        $logger->log("Sales Request Query: " . $selectQuery);
//$return 
//return $res->success("Sales ", $selectQuery);

        $count = $this->rawSelect($countQuery);
        $sales = $this->rawSelect($selectQuery);


        $displaySales = array();

        foreach ($sales as $sale) {
            $items = $this->getSaleItems($sale['salesID']);
//$transactions = $this->getSalesTransactions($sale['nationalIdNumber'], $sale['customerNumber']);
            $sale['items'] = $items;
//$sale['transactions'] = $transactions; //return $res->success("salesID",$items);
            array_push($displaySales, $sale);
        }
//
        $data["totalSales"] = $count[0]['totalSales'];
        $data["sales"] = $displaySales;


        return $res->success("Sales ", $data);
    }

    public function getTablePendingSales() { //sort, order, page, limit,filter,userID
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);
        $request = new Request();
        $res = new SystemResponses();
        $userID = $request->getQuery('userID');
        $sort = $request->getQuery('sort') ? $request->getQuery('sort') : 'salesID';
        $order = $request->getQuery('order') ? $request->getQuery('order') : 'ASC';
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $status = $request->getQuery('status');
        $salesID = $request->getQuery('salesID');
        $contactsID = $request->getQuery('contactsID');
        $startDate = $request->getQuery('start');
        $endDate = $request->getQuery('end');

        $countQuery = "SELECT count(DISTINCT s.salesID) as totalSales ";

        $selectQuery = "SELECT s.salesID, s.paymentPlanID,pp.paymentPlanDeposit AS planDepositAmount,"
                . "pp.salesTypeID, st.salesTypeName,pp.frequencyID,f.numberOfDays, "
                . "f.frequencyName,s.customerID, s.contactsID, s.prospectsID,c.fullName AS customerName, "
                . "c.workMobile AS customerMobile, c.nationalIdNumber, s.productID, "
                . "p.productName, s.userID,c1.fullName AS agentName, c1.workMobile AS agentMobile, s.amount, s.paid, s.status, s.createdAt ";

        $defaultQuery = "FROM sales s LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID "
                . "LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID LEFT JOIN frequency f "
                . "ON pp.frequencyID=f.frequencyID "
                . "LEFT JOIN contacts c on s.contactsID=c.contactsID LEFT JOIN product p "
                . "ON s.productID=p.productID LEFT JOIN users u ON s.userID=u.userID "
                . "LEFT JOIN contacts c1 ON u.contactID=c1.contactsID  ";

        $whereArray = [
            's.status' => $status,
            'filter' => $filter,
            's.salesID' => $salesID,
            's.contactsID' => $contactsID,
            's.userID' => $userID,
            'date' => [$startDate, $endDate]
        ];

//        $logger->log("Sales Request Data: " . json_encode($whereArray));

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['st.salesTypeName', 'f.frequencyName', 'c.fullName', 'c.workMobile', 'c1.fullName', 'c1.workMobile', 'p.productName'];

                $valueString = "";
                foreach ($searchColumns as $searchColumn) {
                    $valueString .= $value ? "" . $searchColumn . " REGEXP '" . $value . "' ||" : "";
                }
                $valueString = chop($valueString, " ||");
                if ($valueString) {
                    $valueString = "(" . $valueString;
                    $valueString .= ") AND";
                }
                $whereQuery .= $valueString;
            } else if ($key == 'unsorted') {
                $valueString = $value ? "s.status=0 AND" : "";
                $whereQuery .= $valueString;
            } else if ($key == 's.status' && $value == 404) {
                $valueString = $value ? "" . $key . ">0" . " AND " : "";
                $whereQuery .= $valueString;
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(s.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
                }
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND" : "";
                $whereQuery .= $valueString;
            }
        }

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND");
        }

//$whereQuery = $whereQuery ? "AND $whereQuery " : "";
        $whereQuery = $whereQuery ? " WHERE s.status=0 AND $whereQuery " : " WHERE s.status=0 ";

        $countQuery = $countQuery . $defaultQuery . $whereQuery;
        $selectQuery = $selectQuery . $defaultQuery . $whereQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

        $logger->log("Pending Sales Request Query: " . $selectQuery);

        $count = $this->rawSelect($countQuery);
        $sales = $this->rawSelect($selectQuery);


        $displaySales = array();

        foreach ($sales as $sale) {
            $items = $this->getSaleItems($sale['salesID']);
            $sale['items'] = $items;
            array_push($displaySales, $sale);
        }

        $data["totalSales"] = $count[0]['totalSales'];
        $data["sales"] = $displaySales;


        return $res->success("pending sales ", $data);
    }

    public function getTablePartnerSales() { //sort, order, page, limit,filter,userID
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);
        $request = new Request();
        $res = new SystemResponses();
        $userID = $request->getQuery('userID') ? $request->getQuery('userID') : NULL;
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $contactsID = $request->getQuery('contactsID') ? $request->getQuery('contactsID') : 0;
        $startDate = $request->getQuery('start');
        $endDate = $request->getQuery('end');

        $countQuery = "SELECT count(psi.partnerSaleItemID) as totalSales ";

        $defaultQuery = "FROM partner_sale_item psi LEFT JOIN product p on psi.productID=p.productID "
                . "LEFT JOIN contacts c on psi.contactsID=c.contactsID ";

        $selectQuery = "SELECT psi.partnerSaleItemID,psi.serialNumber,psi.productID,"
                . "p.productName,psi.salesPartner,psi.customerID,psi.contactsID,"
                . "c.workMobile,c.nationalIdNumber,c.fullName,c.location,psi.createdAt ";

//        $defaultQuery = "FROM partner_sale_item psi INNER JOIN item i on psi.itemID=i.itemID "
//                . "LEFT JOIN product p ON i.productID=p.productID INNER JOIN customer cust "
//                . "ON psi.customerID=cust.customerID INNER JOIN contacts c ON cust.contactsID=c.contactsID ";
//
//        $selectQuery = "SELECT psi.partnerSaleItemID, psi.itemID,i.serialNumber, "
//                . "i.productID, p.productName, i.status, psi.customerID, c.workMobile, "
//                . "c.nationalIdNumber, c.fullName, c.location, psi.salesPartner AS partnerName,psi.createdAt ";

        $whereArray = [
            'filter' => $filter,
            'psi.contactsID' => $contactsID,
            'date' => [$startDate, $endDate]
        ];

        $logger->log("Partner Sales Request Data: " . json_encode($whereArray));

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['psi.serialNumber', 'psi.salesPartner', 'c.fullName', 'c.workMobile', 'p.productName'];

                $valueString = "";
                foreach ($searchColumns as $searchColumn) {
                    $valueString .= $value ? "" . $searchColumn . " REGEXP '" . $value . "' ||" : "";
                }
                $valueString = chop($valueString, " ||");
                if ($valueString) {
                    $valueString = "(" . $valueString;
                    $valueString .= ") AND";
                }
                $whereQuery .= $valueString;
            } else if ($key == 't.status' && $value == 404) {
                $valueString = "" . $key . "=0" . " AND ";
                $whereQuery .= $valueString;
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(psi.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
                }
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND" : "";
                $whereQuery .= $valueString;
            }
        }

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND");
        }

        $whereQuery = $whereQuery ? "AND $whereQuery " : "";

        $countQuery = $countQuery . $defaultQuery . $whereQuery;
        $selectQuery = $selectQuery . $defaultQuery . $whereQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

        $logger->log("Sales Request Query: " . $selectQuery);

        $count = $this->rawSelect($countQuery);
        $sales = $this->rawSelect($selectQuery);

        $data["totalSales"] = $count[0]['totalSales'];
        $data["sales"] = $sales;


        return $res->success("Sales ", $data);
    }

    public function tableQueryBuilder($sort = "", $order = "", $page = 0, $limit = 10) {

        $sortClause = "ORDER BY $sort $order";

        if (!$page || $page <= 0) {
            $page = 1;
        }
        if (!$limit) {
            $limit = 10;
        }

        $ofset = (int) ($page - 1) * $limit;
        $limitQuery = "LIMIT $ofset, $limit";

        return "$sortClause $limitQuery";
    }

    public function dashBoardSummary() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $date = $request->getQuery('date');
        $token = $request->getQuery('token');

        if (!$token) {
            return $res->dataError("Token missing " . json_encode($json));
        }
        if (!$date) {
            $date = date("Y-m-d");
        }

        $totalSalesQuery = "SELECT SUM(replace(t.depositAmount,',','')) as totalSales FROM transaction t ";
        $todaysSalesQuery = "SELECT SUM(replace(t.depositAmount,',','')) as todaysSales FROM transaction t where date(t.createdAt)=CURDATE()";

        $totalSaleType = "SELECT st.salesTypeID,st.salesTypeName,SUM(replace(t.depositAmount,',','')) as totalAmount from sales_type st join payment_plan pp on st.salesTypeID=pp.salesTypeID join sales s on pp.paymentPlanID=s.paymentPlanID join transaction t on s.salesID=t.salesID group by st.salesTypeID";
        $todaysSaleType = "SELECT st.salesTypeID,st.salesTypeName,SUM(replace(t.depositAmount,',','')) as totalAmount from sales_type st join payment_plan pp on st.salesTypeID=pp.salesTypeID join sales s on pp.paymentPlanID=s.paymentPlanID join transaction t on s.salesID=t.salesID  where date(t.createdAt)=CURDATE() group by st.salesTypeID ";

        $totalProductSales = "SELECT p.productID,p.productName,count(s.productID) as numberOfProducts,SUM(replace(t.depositAmount,',','')) as totalAmount,c.categoryID,c.categoryName FROM product p join sales s on p.productID=s.productID join transaction t on s.salesID=t.salesID join category c on p.categoryID=c.categoryID group by p.productID ";
        $todaysProductSales = "SELECT p.productID,p.productName,count(s.productID) as numberOfProducts,SUM(replace(t.depositAmount,',','')) as totalAmount,c.categoryID,c.categoryName FROM product p join sales s on p.productID=s.productID join transaction t on s.salesID=t.salesID join category c on p.categoryID=c.categoryID where date(t.createdAt)=CURDATE() group by p.productID ";

        $ticketsQuery = "SELECT * from ticket ";

        $totalSales = $this->rawSelect($totalSalesQuery);
        $todaysSales = $this->rawSelect($todaysSalesQuery);
        $totalSaleType = $this->rawSelect($totalSaleType);
        $todaysSaleType = $this->rawSelect($todaysSaleType);
        $totalProductSales = $this->rawSelect($totalProductSales);
        $todaysProductSales = $this->rawSelect($todaysProductSales);
        $tickets = $this->rawSelect($ticketsQuery);

        $summaryData = array();
        $summaryData['totalSales'] = $totalSales[0]['totalSales'];
        $summaryData['todaysSales'] = $todaysSales[0]['todaysSales'];
        $summaryData['totalSaleType'] = $totalSaleType;
        $summaryData['todaysSaleType'] = $todaysSaleType;
        $summaryData['totalProductSales'] = $totalProductSales;
        $summaryData['todaysProductSales'] = $todaysProductSales;
        $summaryData['tickets'] = $tickets;

        return $res->success("Summary data ", $summaryData);
    }

    public function getCRMSaleItems() {//{salesID,token}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $salesID = $request->getQuery('salesID');

        /* $selectQuery = "select i.serialNumber, p.productName, c.categoryName "
          . "from sales_item si left join item i on si.itemID=i.itemID left join product p on i.productID=p.productID "
          . "left join category c on p.categoryID=c.categoryID";
         */
        $selectQuery = "SELECT SUM(replace(t.depositAmount,',','')) as amount, s.amount as saleAmount, st.salesTypeDeposit,st.salesTypeName,si.saleItemID,i.serialNumber,i.status as itemStatus,s.productID,p.productName FROM transaction t JOIN contacts c on t.salesID=c.workMobile or t.salesID=c.nationalIdNumber JOIN customer cu on c.contactsID=cu.contactsID JOIN sales s on cu.customerID=s.customerID JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID left join sales_item si on s.salesID=si.saleID LEFT JOIN item i on si.itemID=i.itemID left join product p  on s.productID=p.productID ";


        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }


        if ($salesID) {
            $selectQuery = $selectQuery . " WHERE si.saleID = $salesID";
        }

        $saleItems = $this->rawSelect($selectQuery);


        return $res->success("saleItems", $saleItems);
    }

    public function getSaleItems($salesID) {
        $selectQuery = "select i.serialNumber, p.productName, c.categoryName from sales_item si join item i on si.itemID=i.itemID join product p on i.productID=p.productID join category c on p.categoryID=c.categoryID where saleID = $salesID";
        $items = $this->rawSelect($selectQuery);
        return $items;
    }

    public function getSalesTransactions($nationalIdNumber, $w_mobile) {
        $selectQuery = "select * from transaction t where t.salesID>0 and (t.salesID='$nationalIdNumber' or t.salesID='$w_mobile') ";
        $transactions = $this->rawSelect($selectQuery);
        return $transactions;
    }

    public function saleSummary() {
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
//        $transactionManager = new TransactionManager();
//        $dbTransaction = $transactionManager->get();
        $token = $request->getQuery('token');

        if (!$token) {
            return $res->dataError("Token missing " . json_encode($json));
        }

        $totalSales = $this->rawSelect("SELECT COUNT(salesID) AS totalSales FROM sales");
        $salesWithoutPaymentPlan = $this->rawSelect("SELECT COUNT(salesID) AS withoutPayment FROM sales WHERE paymentPlanID=0");
        $cashSales = $this->rawSelect("SELECT COUNT(s.salesID) AS cashTotal FROM sales s 
            INNER JOIN payment_plan pp ON s.paymentPlanID=pp.paymentPlanID 
            INNER JOIN sales_type st ON pp.salesTypeID=st.salesTypeID LEFT JOIN frequency f 
            ON pp.frequencyID=f.frequencyID WHERE pp.salesTypeID=1");

        $paygoSales = $this->rawSelect("SELECT COUNT(s.salesID) AS paygoTotal FROM sales s 
            INNER JOIN payment_plan pp ON s.paymentPlanID=pp.paymentPlanID 
            INNER JOIN sales_type st ON pp.salesTypeID=st.salesTypeID LEFT JOIN frequency f 
            ON pp.frequencyID=f.frequencyID WHERE pp.salesTypeID=2");

        $installmentSales = $this->rawSelect("SELECT COUNT(s.salesID) AS installmentTotal FROM sales s 
            INNER JOIN payment_plan pp ON s.paymentPlanID=pp.paymentPlanID 
            INNER JOIN sales_type st ON pp.salesTypeID=st.salesTypeID LEFT JOIN frequency f 
            ON pp.frequencyID=f.frequencyID WHERE pp.salesTypeID=3");

        $closedSales = $this->rawSelect("SELECT COUNT(s.salesID) AS closed FROM sales s INNER JOIN payment_plan pp ON s.paymentPlanID=pp.paymentPlanID "
                . "INNER JOIN sales_type st ON pp.salesTypeID=st.salesTypeID LEFT JOIN frequency f ON pp.frequencyID=f.frequencyID WHERE s.status>=1");

        $delinquencyTiers = $this->rawSelect("SELECT tierName, tierCount FROM delinquency_tier ORDER BY tierName ASC");

        $salesData = array();
        $salesData['totalSales'] = $totalSales[0]['totalSales'];
        $salesData['withoutPayment'] = $salesWithoutPaymentPlan[0]['withoutPayment'];
        $salesData['cash'] = $cashSales[0]['cashTotal'];
        $salesData['paygo'] = $paygoSales[0]['paygoTotal'];
        $salesData['installment'] = $installmentSales[0]['installmentTotal'];
        $salesData['closed'] = $closedSales[0]['closed'];
        $salesData['delinquency'] = $delinquencyTiers;

        return $res->success("sale stats", $salesData);
    }

    public function monitorSales() {
        $logPathLocation = $this->config->logPath->location . 'apicalls_log.log';
        $logger = new FileAdapter($logPathLocation);

        $request = new Request();
        $res = new SystemResponses();
        $callbackLimit = 50;
        $callbackBatchSize = 1;

        $limit = 5;
        $batchSize = 1;

        $activeCallbacks = $res->rawSelect("SELECT log.callTypeID, ct.callTypeName,"
                . "log.contactsID, c.fullName, log.recipient, log.comment, "
                . "log.userID FROM call_log log INNER JOIN call_type ct "
                . "ON log.callTypeID=ct.callTypeID LEFT JOIN contacts c on log.contactsID=c.contactsID "
                . "WHERE date(callback)=curdate()");

        /*  $activeCallbacks = $res->rawSelect("SELECT log.callTypeID, ct.callTypeName,"
          . "log.contactsID, c.fullName, log.recipient, log.comment, "
          . "log.userID FROM call_log log INNER JOIN call_type ct "
          . "ON log.callTypeID=ct.callTypeID LEFT JOIN contacts c on log.contactsID=c.contactsID "
          . "WHERE date(callback)<=curdate() AND date(callback)>'00-00-00'"); */

        foreach ($activeCallbacks as $activeCallback) {

            $ticket = new Ticket();
            $ticket->ticketTitle = "Callback on " . $activeCallback['callTypeName'];
            $ticket->ticketDescription = $activeCallback['comment'] ? $activeCallback['comment'] : 'A contact callback ticket';
            $ticket->contactsID = $activeCallback['contactsID'];
            $ticket->otherOwner = $activeCallback['recipient'];
            $ticket->assigneeID = $activeCallback['userID'];
            $ticket->ticketCategoryID = 2;
            $ticket->otherCategory = NULL;
            $ticket->priorityID = 2;
            $ticket->userID = NULL;
            $ticket->status = 0;
            $ticket->createdAt = date("Y-m-d H:i:s");

            if ($ticket->save() === false) {
                $errors = array();
                $messages = $ticket->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $logger->log("Ticket save failed for callback" . json_encode($activeCallback));
//return $res->dataError('sale create failed',$errors);
//$dbTransaction->rollback('ticket create failed' . json_encode($errors));
            }

            $ticketID = $ticket->ticketID;
            $ticketTitle = $activeCallback['callTypeName'];

            $ticketData = $res->rawSelect("SELECT t.ticketTitle, t.ticketDescription, "
                    . "t.userID,c.fullName AS triggerName,t.contactsID, c1.fullName AS owner, "
                    . "t.otherOwner,t.assigneeID, c2.fullName AS assigneeName, c2.workMobile, c2.workEmail, t.ticketCategoryID,"
                    . "cat.ticketCategoryName, t.otherCategory, t.priorityID,p.priorityName FROM ticket t "
                    . "LEFT JOIN users u ON t.userID=u.userID LEFT JOIN contacts c ON u.contactID=c.contactsID "
                    . "LEFT JOIN contacts c1 ON t.contactsID=c1.contactsID LEFT JOIN users u1 "
                    . "ON t.assigneeID=u1.userID LEFT JOIN contacts c2 ON u1.contactID=c2.contactsID "
                    . "LEFT JOIN ticket_category cat ON t.ticketCategoryID=cat.ticketCategoryID "
                    . "INNER JOIN priority p ON t.priorityID=p.priorityID WHERE t.ticketID=$ticketID");

            $ticketData[0]['ticketCategoryName'] = $ticketData[0]['ticketCategoryName'] ? $ticketData[0]['ticketCategoryName'] : $ticketData[0]['otherCategory'];

            $assigneeName = $ticketData[0]['assigneeName'];
            $triggerName = $ticketData[0]['triggerName'];

            $assigneeMessage = "Dear $assigneeName, the ticket named $ticketTitle "
                    . "has been assigned to you. Please ensure its resolve.";
            $res->sendMessage($ticketData[0]['workMobile'], $assigneeMessage);
            $res->sendEmail($ticketData[0]);

            $logger->log("Ticket for callback successfully created" . json_encode($ticket));
        }

        /*
          $openSales = Sales::find([
          "status = :status:",
          "bind" => [
          "status" => 0
          ]
          ]);

          $openSaleCount = count($openSales);

          if ($openSaleCount <= $limit) {
          $batchSize = 1;
          } else {
          $batchSize = (int) ($openSaleCount / $limit) + 1;
          }

          $tiers = DelinquencyTier::find();
          foreach ($tiers as $tier) {
          $tier->tierCount = 0;
          $tier->save();
          }

          for ($count = 0; $count < $batchSize; $count++) {
          $page = $count + 1;

          $offset = (int) ($page - 1) * $limit;

          $sales = $res->rawSelect("SELECT s.salesID,s.paymentPlanID,pp.paymentPlanDeposit,
          pp.salesTypeID,st.salesTypeName,pp.frequencyID,f.frequencyName,f.numberOfDays,s.amount, s.createdAt
          FROM sales s INNER JOIN payment_plan pp ON s.paymentPlanID=pp.paymentPlanID
          INNER JOIN sales_type st ON pp.salesTypeID=st.salesTypeID LEFT JOIN frequency f
          ON pp.frequencyID=f.frequencyID WHERE s.status<=0 LIMIT $offset,$limit");

          foreach ($sales as $sale) {
          $elapse = date_diff(new DateTime(), new DateTime($sale['createdAt']), TRUE);
          $numDays = (int) $elapse->days;
          $customerTier = 0;

          if ($numDays == 3) {
          //Welcome Call Ticket and update tier count
          $tier = DelinquencyTier::findFirst(array("tierName=:tierName: ",
          'bind' => array("tierName" => 0)));
          if ($tier) {
          $tier->tierCount = $tier->tierCount + 1;
          $tier->save();
          }
          } else if ($numDays > 3 && $numDays <= 5) {
          $customerTier = 1;
          $tier = DelinquencyTier::findFirst(array("tierName=:tierName: ",
          'bind' => array("tierName" => 1)));
          if ($tier) {
          $tier->tierCount = $tier->tierCount + 1;
          $tier->save();
          }
          //UPDATE delinquency_tier SET tierCount=tierCount+2 WHERE tierName=6
          //First Delinquent Tier: generate ticket and update tierCount
          } else if ($numDays > 5 && $numDays <= 10) {
          $customerTier = 2;
          $tier = DelinquencyTier::findFirst(array("tierName=:tierName: ",
          'bind' => array("tierName" => 2)));
          if ($tier) {
          $tier->tierCount = $tier->tierCount + 1;
          $tier->save();
          }
          //Second Delinquent Tier: generate ticket and update tierCount
          } else if ($numDays > 10 && $numDays <= 20) {
          $customerTier = 3;
          $tier = DelinquencyTier::findFirst(array("tierName=:tierName: ",
          'bind' => array("tierName" => 3)));
          if ($tier) {
          $tier->tierCount = $tier->tierCount + 1;
          $tier->save();
          }
          //Third Delinquent Tier: generate ticket and update tierCount
          } else if ($numDays > 20 && $numDays <= 40) {
          $customerTier = 4;
          $tier = DelinquencyTier::findFirst(array("tierName=:tierName: ",
          'bind' => array("tierName" => 4)));
          if ($tier) {
          $tier->tierCount = $tier->tierCount + 1;
          $tier->save();
          }
          //Fourth Delinquent Tier: generate ticket and update tierCount
          } else if ($numDays > 40 && $numDays <= 45) {
          $customerTier = 5;
          $tier = DelinquencyTier::findFirst(array("tierName=:tierName: ",
          'bind' => array("tierName" => 5)));
          if ($tier) {
          $tier->tierCount = $tier->tierCount + 1;
          $tier->save();
          }
          //Fifth Delinquent Tier: generate ticket and update tierCount
          } else if ($numDays > 45 && $numDays <= 89) {
          $customerTier = 6;
          $tier = DelinquencyTier::findFirst(array("tierName=:tierName: ",
          'bind' => array("tierName" => 6)));
          if ($tier) {
          $tier->tierCount = $tier->tierCount + 1;
          $tier->save();
          }
          //Sixth Delinquent Tier: generate ticket and update tierCount
          } else if ($numDays == 90) {
          $customerTier = 7;
          //Default Customer
          }

          $logger->log("Days Since Commencement " . $numDays . " Customer Tier: " . $customerTier);
          }
          }

          return $res->success("response", $batchSize); */
    }

    public function updateOldSales() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        /* $query = "select s.salesID,s.customerID,c.homeMobile,c.nationalIdNumber,c.fullName,t.transactionID,t.fullName,t.salesID from sales s  JOIN contacts c on s.customerID=c.contactsID  JOIN transaction t on t.salesID=c.nationalIdNumber or t.salesID=c.homeMobile  where s.createdAt='0000-00-00 00:00:00' and s.customerID > 0 and t.salesID > 0 group by s.salesID;" */
        try {

            /* $salesQuery = " select * from sales where createdAt<>'0000-00-00 00:00:00' ";

              $sales = $this->rawSelect($salesQuery);
              foreach ($sales as $sale) {
              $customerID = $sale["customerID"];
              $saleID = $sale["salesID"];
              $customerQuery = "select * from customer where customerID=$customerID";

              $customers = $this->rawSelect($customerQuery);


              foreach ($customers as $customer) {
              $contactsID = $customer['contactsID'];

              $sale_object = Sales::findFirst(array("salesID=:id: ",
              'bind' => array("id" => $saleID)));

              $sale_object->contactsID=$contactsID;


              if ($sale_object->save() === false) {
              $errors = array();
              $messages = $sale_object->getMessages();
              foreach ($messages as $message) {
              $e["message"] = $message->getMessage();
              $e["field"] = $message->getField();
              $errors[] = $e;
              }
              $dbTransaction->rollback("sale create failed " . json_encode($errors));
              }

              }

              } */

            $salesQuery = "select * from sales ";
            $sales = $this->rawSelect($salesQuery);
            foreach ($sales as $sale) {
                $saleID = $sale["salesID"];

                $contactsID = $sale["contactsID"];
                $saleID = $sale["salesID"];
                $contactsQuery = "select * from contacts where contactsID=$contactsID";
                $contacts = $this->rawSelect($contactsQuery);
                foreach ($contacts as $contact) {
                    $workMobile = $contact["workMobile"];
                    $idNumber = $contact["nationalIdNumber"];
                    $transactionQuery = "select replace(depositAmount,',','') as depositAmount from transaction where salesID='$workMobile' OR salesID='$idNumber' ";
                    $transactions = $this->rawSelect($transactionQuery);
                    $paidAmount = 0;

                    foreach ($transactions as $transaction) {
                        $amount = $transaction['depositAmount'];
                        $paidAmount = $paidAmount + $amount;
//  return $res->success("sale updated ".$amount." ".$paidAmount, $workMobile);
                    }


                    $sale_object = Sales::findFirst(array("salesID=:id: ",
                                'bind' => array("id" => $saleID)));

                    if ($sale_object && $paidAmount > 0) {
                        $sale_object->status = 1;

                        if ($sale_object->save() === false) {
                            $errors = array();
                            $messages = $sale_object->getMessages();
                            foreach ($messages as $message) {
                                $e["message"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                $errors[] = $e;
                            }
                            $dbTransaction->rollback("sale create failed " . json_encode($errors));
                        }
                    }


// if ($paidAmount > 0) {
//     $sale_object->status = 1;
//     // return $res->success("sale updated ".$paidAmount, $sale_object);
// } 
// else{
//     $sale_object->status = 0;
// }

                    /*  $productIDQuery ="select i.productID from sales_item si join item i on si.itemID=i.itemID where si.saleID=$saleID";
                      $productIDs = $this->rawSelect($productIDQuery);
                      foreach ($productIDs as $id) {
                      $productID = $id['productID'];

                      $sale_object = Sales::findFirst(array("salesID=:id: ",
                      'bind' => array("id" => $saleID)));
                      if($sale_object && $productID > 0){
                      $sale_object->productID = $productID;



                      if ($sale_object->save() === false) {
                      $errors = array();
                      $messages = $sale_object->getMessages();
                      foreach ($messages as $message) {
                      $e["message"] = $message->getMessage();
                      $e["field"] = $message->getField();
                      $errors[] = $e;
                      }
                      $dbTransaction->rollback("sale create failed " . json_encode($errors));
                      }

                      } */
                }
            }


// return $res->success("sale updated ", $sale_object);

            $dbTransaction->commit();
            return $res->success("sale updated ", $sales);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('sale update error', $message);
        }
    }

    public function reconcilePartnerSales() {
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);

        $partnerSales = PartnerSaleItem::find();

        foreach ($partnerSales as $sale) {

            $serialNumber = $sale->serialNumber;
            $productID = $sale->productID;
            $status = $sale->status;

            $item = Item::findFirst(array("serialNumber=:serialNumber: ",
                        'bind' => array("serialNumber" => $serialNumber)));

            if ($item) {
                $sale->itemID = $item->itemID;
            } else {
                $item = new Item();
                $item->serialNumber = $serialNumber;
                $item->status = $status;
                $item->productID = $productID;
                $item->createdAt = date('Y-m-d H:i:s');

                if ($item->save() === false) {
                    $sale->itemID = NULL;
                } else {
                    $sale->itemID = $item->itemID;
                }
            }

            if ($sale->save() === false) {
                $logger->log("Product item for sale could NOT be saved: " . json_encode($sale));
            } else {
                $logger->log("Product item for sale saved: " . json_encode($sale));
            }
        }
    }

    public function reconcileSales() { //salesID,contactsID,productID,serialNumber,salesTypeID,frequencyID,status,userID,token,agentID
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();

        $salesID = isset($json->salesID) ? $json->salesID : NULL;
        $contactsID = isset($json->contactsID) ? $json->contactsID : NULL;
        $agentID = isset($json->agentID) ? $json->agentID : NULL;
        $productID = isset($json->productID) ? $json->productID : NULL;
        $serialNumber = isset($json->serialNumber) ? $json->serialNumber : NULL;
        $userID = isset($json->userID) ? $json->userID : NULL;
        $token = isset($json->token) ? $json->token : NULL;
        $dateCreated = isset($json->dateCreated) ? $json->dateCreated : NULL;
        $amount = isset($json->amount) ? $json->amount : NULL;
// $depositAmount = isset($json->depositAmount) ? $json->depositAmount : NULL;
        $salesTypeID = isset($json->salesTypeID) ? $json->salesTypeID : NULL;
        $status = isset($json->status) ? $json->status : 0;

        $paymentPlanID = 0;

        $logger->log("Reconcilliation Request Data: " . json_encode($json));

        if (!$token || !$salesID || !$userID) {
            return $res->dataError("missing data", []);
        }

        if (!$status) {
            if (!$dateCreated || !$amount || !$salesTypeID) {
                return $res->dataError("missing data", []);
            }

            $janReview = $this->isDateBetweenDates($dateCreated, "2017-01-01 00:00:00", "2017-02-28 23:59:59");
            $marchReview = $this->isDateBetweenDates($dateCreated, "2017-03-01 00:00:00", "2017-05-09 23:59:59");

            switch ($review) {
                case $marchReview:
                    switch ($salesTypeID) {
                        case 1:
                            $paymentPlanID = 14;
                            break;
                        case 2:
                            $paymentPlanID = 16;
                            break;
                        case 3:
                            $paymentPlanID = 15;
                            break;

                        default:
                            break;
                    }
                    break;
                case $janReview:
                    switch ($salesTypeID) {
                        case 1:
                            $paymentPlanID = 14;
                            break;
                        case 2:
                            $paymentPlanID = 13;
                            break;
                        case 3:
                            $paymentPlanID = 15;
                            break;

                        default:
                            break;
                    }
                    break;

                default:

                    break;
            }
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised", $token);
        }

        $sale = Sales::findFirst(array("salesID=:id: ",
                    'bind' => array("id" => $salesID)));

//        $logger->log("Sale Data: " . json_encode($sale));

        if (!$sale) {
            return $res->dataError("sale does not exist", $salesID);
        }



//Incase serial number is provided
        if ($serialNumber && $productID) {

            $item = Item::findFirst(array("serialNumber=:serialNumber: ",
                        'bind' => array("serialNumber" => $serialNumber)));

            if (!$item) {
                return $res->dataError("serial number does not exist.please contact logistics", $serialNumber);
            }

            $item->productID = $productID;
            $item->status = 2;

            if ($item->save() === false) {
                $errors = array();
                $messages = $sale->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                return $res->dataError('sale reconcilliation failed', $errors);
            }

            $saleItem = SalesItem::findFirst(array("itemID=:id: ",
                        'bind' => array("id" => $item->itemID)));

            if (!$saleItem) {
                $saleItem = new SalesItem();
                $saleItem->itemID = $item->itemID;
                $saleItem->saleID = $salesID;
                $saleItem->status = 2;
                $saleItem->createdAt = date('Y-m-d H:i:s');
            } else {
                $saleItem->saleID = $salesID;
                $saleItem->status = 2;
            }

            if ($saleItem->save() === false) {
                $errors = array();
                $messages = $sale->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                return $res->dataError('sale reconcilliation failed', $errors);
            }
        }

        if ($contactsID) {
            $sale->contactsID = $contactsID;
        }

        if ($productID) {
            $sale->productID = $productID;
        }
        if ($agentID) {
            $sale->userID = $agentID;
        }
        if (!$status && $paymentPlanID > 0) {
            $sale->amount = $amount;
            $sale->createdAt = $dateCreated;
            $sale->paymentPlanID = $paymentPlanID;
        }


        $sale->updatedBy = $userID;

        if ($sale->save() === false) {
            $errors = array();
            $messages = $sale->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('sale reconcilliation failed', $errors);
        }

        return $res->success("sale successfully reconcilled ", $sale);
    }

    public function updatePartnerSale() {//partnerSaleItemID, contactsID,token
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();

        $partnerSaleItemID = $json->partnerSaleItemID;
        $contactsID = $json->contactsID;
        $token = $json->token;

        if (!$token || !$partnerSaleItemID || !$contactsID) {
            return $res->dataError("data missing ", []);
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised", []);
        }

        $partnerSale = PartnerSaleItem::findFirst(array("partnerSaleItemID=:id: ",
                    'bind' => array("id" => $partnerSaleItemID)));

        if (!$partnerSale) {
            return $res->dataError("partner sale does not exist", []);
        }

        $partnerSale->contactsID = $contactsID;

        if ($partnerSale->save() === false) {
            $errors = array();
            $messages = $partnerSale->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('partner sale update failed', $errors);
        }

        return $res->success("partner sale successfully updated ", $partnerSale);
    }

    public function crmCreateSale() { //{amount,createdAt,paymentPlanDeposit,paid,workMobile,fullName,nationalIdNumber,location,productID,salesTypeID,userID,contactsID}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $paymentPlanDeposit = isset($json->paymentPlanDeposit) ? $json->paymentPlanDeposit : 0;
        $amount = isset($json->amount) ? $json->amount : 0;
        $salesTypeID = isset($json->salesTypeID) ? $json->salesTypeID : 0;
        $contactsID = isset($json->contactsID) ? $json->contactsID : NULL;
        $userID = isset($json->userID) ? $json->userID : NULL;
        $createdAt = isset($json->createdAt) ? $json->createdAt : NULL;
        $productID = isset($json->productID) ? $json->productID : NULL;

        $location = isset($json->location) ? $json->location : NULL;
        $workMobile = isset($json->workMobile) ? $json->workMobile : NULL;
        $fullName = isset($json->fullName) ? $json->fullName : NULL;
        $nationalIdNumber = isset($json->nationalIdNumber) ? $json->nationalIdNumber : NULL;
        $serialNumber = isset($json->serialNumber) ? $json->serialNumber : NULL;

        $token = $json->token;
        $frequencyID = $json->frequencyID;



        if (!$token || !$paymentPlanDeposit || !$amount || !$salesTypeID || !$userID || !$createdAt || !$productID) {
            return $res->dataError("fields data missing ", []);
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        if (!$frequencyID) {
            $frequencyID = 0;
        }
        try {

            if (!$contactsID) {
                if ($workMobile && $fullName && $location && $nationalIdNumber) { //createContact 
                    $contactsID = $this->createContact($workMobile, $nationalIdNumber, $fullName, $location, $dbTransaction);
                }
            }
            $paymentPlanID = $this->createPaymentPlan($paymentPlanDeposit, $salesTypeID, $frequencyID, $dbTransaction);
            $customerID = $this->createCustomer($userID, $contactsID, $dbTransaction);

            $sale = new Sales();
            $sale->status = 0;
            $sale->paymentPlanID = $paymentPlanID;
            $sale->userID = $userID;
            $sale->customerID = $customerID;
            $sale->prospectsID = NULL;
            $sale->contactsID = $contactsID;
            $sale->amount = $amount;
            $sale->productID = $productID;
            $sale->createdAt = $createdAt;
            $sale->paid = 0;

            if ($sale->save() === false) {
                $errors = array();
                $messages = $sale->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
//return $res->dataError('sale create failed',$errors);
                $dbTransaction->rollback('sale create failed' . json_encode($errors));
            }

            $dbTransaction->commit();

            if ($serialNumber) {
                $this->setCrmSaleItem($serialNumber, $sale->salesID);
            }

            $this->checkCustomerTransaction($contactsID);

            return $res->success("Sale saved successfully, await payment ", $sale);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('sale create error', $message);
        }
    }

    private function checkCustomerTransaction($contactsID) {
        /* $customerDepositAmountQuery = "SELECT SUM(replace(t.depositAmount,',','')) as totalDeposit FROM customer_transaction ct JOIN transaction t ON ct.transactionID=t.transactionID WHERE ct.contactsID=$contactsID";
         */
        $res = new SystemResponses();
        $customerDepositAmountQuery = "SELECT SUM(replace(t.depositAmount,',','')) as totalDeposit FROM contacts c JOIN transaction t ON c.workMobile=t.mobile OR c.nationalIdNumber=t.salesID WHERE c.contactsID=$contactsID";

        $customerDepositAmount = $this->rawSelect($customerDepositAmountQuery);

        $totalDeposit = $customerDepositAmount[0]['totalDeposit'];


        if (!$totalDeposit || $totalDeposit <= 0) {
            return false;
        }
        $customerSales = Sales::find(array("contactsID=:id: ",
                    'bind' => array("id" => $contactsID)));

        foreach ($customerSales as $sale) {
            if ($sale->paid == $totalDeposit) {
                return false;
            } elseif ($sale->paid == $sale->amount) {
                $totalDeposit = $totalDeposit - $sale->paid;
            } elseif ($sale->paid < $sale->amount) {
                $saleBalance = $sale->amount - $sale->paid;
                if ($saleBalance < $totalDeposit) {
                    $totalDeposit = $totalDeposit - $saleBalance;
                    $sale->paid = $sale->paid + $totalDeposit;
                    $sale->status = 2;
                } elseif ($saleBalance == $totalDeposit) {
// $amountToUpdate = $saleBalance - $depositAmount;
                    $sale->paid = $sale->paid + $totalDeposit;
                    $sale->status = 2;
                } elseif ($saleBalance > $totalDeposit) {
                    $sale->paid = $sale->paid + $totalDeposit;
                }

                if ($sale->save() === false) {
                    $errors = array();
                    $messages = $sale->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                        $res->dataError('Sale update failed', $errors);
                    }
                }
                return true;
            }
        }
    }

    private function setCrmSaleItem($serialNumber, $saleID) {
        $res = new SystemResponses();

        $itemQuery = "SELECT itemID FROM item WHERE serialNumber = '" . $serialNumber . "'";
        $item = $this->rawSelect($itemQuery);
        if (!$item) {
            $res->dataError('crm create sale item failed, no such item exists', $errors);
            return false;
        }


        $itemID = $item[0]['itemID'];

        $saleItem = SalesItem::findFirst(array("saleID=:s_id: OR itemID=:i_id: ",
                    'bind' => array("s_id" => $saleID, "i_id" => $itemID)));

        if ($saleItem) {
            $res->dataError('crm create sale item failed item or sale already mapped', false);
            return false;
        }
        $saleItem = new SalesItem();
        $saleItem->itemID = $itemID;
        $saleItem->saleID = $saleID;
        $saleItem->status = 0;
        $saleItem->createdAt = date("Y-m-d H:i:s");
        if ($saleItem->save() === false) {
            $errors = array();
            $messages = $saleItem->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
                $res->dataError('crm sale item mapping failed', $errors);
            }
        }
        $res->success('crm sale item mapping success', true);

        return true;
    }

    public function matchContacts() {
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);
        $res = new SystemResponses();

        $limit = 500;
        $batchSize = 1;

        try {

            $salesCountRequest = $res->rawSelect("SELECT COUNT(salesID) AS salesCount FROM sales WHERE customerID=0 AND prospectsID IS NULL AND status=0 ");
            $salesCount = $salesCountRequest[0]['salesCount'];
//            $logger->log("Transactions Count: " . json_encode($transactionCount));

            if ($salesCount <= $limit) {
                $batchSize = 1;
            } else {
                $batchSize = (int) ($salesCount / $limit) + 1;
            }

            for ($count = 0; $count < $batchSize; $count++) {
                $page = $count + 1;
                $offset = (int) ($page - 1) * $limit;
                $sales = Sales::find([
                            "customerID=:id: AND status=:status: AND prospectsID IS NULL",
                            "bind" => array(
                                "id" => 0,
                                "status" => 0
                            ),
                            "limit" => $limit,
                            "offset" => $offset
                ]);
                //$sales = $res->rawSelect("SELECT salesID, contactsID FROM sales WHERE customerID=0 AND prospectsID IS NULL AND status=0 LIMIT $offset, $limit");

                $logger->log("Batch NO: " . $page);

                foreach ($sales as $sale) {
                    //$logger->log("Customer Transaction: " . json_encode($transaction));
                    $contactsID = $sale->contactsID;
                    $salesID = $sale->salesID;

                    $customer = Customer::findFirst(array("contactsID=:id: ",
                                'bind' => array("id" => $contactsID)));

                    if (!$customer) {
                        $customer = new Customer();
                        $customer->contactsID = $contactsID;
                        $customer->locationID = 0;
                        $customer->userID = 54;
                        $customer->createdAt = date("Y-m-d H:i:s");
                        $customer->status = 1;

                        if ($customer->save() === false) {
                            $errors = array();
                            $messages = $sale->getMessages();
                            foreach ($messages as $message) {
                                $e["message"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                $errors[] = $e;
                            }

                            $logger->log("Failed to save NEW customer: " . json_encode($errors));
                        }
                    }

                    if ($customer) {
                        $customerID = $customer->customerID;
                        $sale->customerID = $customerID;
                        if ($sale->save() === false) {
                            $errors = array();
                            $messages = $sale->getMessages();
                            foreach ($messages as $message) {
                                $e["message"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                $errors[] = $e;
                            }
//return $res->dataError('sale create failed',$errors);
                            return $res->dataError('sale customer failed to match', $errors);
                        }
                        $logger->log("Sale customerID updated: " . json_encode($sale));
                    }
                }
            }
            //return $res->success('contacts successfully matched', count($sales));
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('transaction update error', $message);
        }
    }

}
