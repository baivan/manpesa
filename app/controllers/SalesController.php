<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

/*
All sales CRUD operations 
*/

class SalesController extends Controller {

     /*
    Raw query select function to work in any version of phalcon
    */
    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

     /*
      checks if date is between two dates
    */
    protected function isDateBetweenDates($date, $startDate, $endDate) {
        $date = new DateTime($date);
        $startDate = new DateTime($startDate);
        $endDate = new DateTime($endDate);
        return $date > $startDate && $date < $endDate;
    }
     /*
        create new sale
        accepts contactsID/prospect if contact/prospect already saved
        also accepts customer details and will created the customer before making sale
        Then communicated to customer via sms with payment details
        paramters:
         see create sale parameters inside create function
    */
    public function create() { 
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        //create sale parameters
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
        $quantity =  isset($json->quantity) ? $json->quantity : 1;

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
            $sale->status = $status?$status:0;
            $sale->paymentPlanID = $paymentPlanID;
            $sale->userID = $userID;
            $sale->customerID = $customerID;
            $sale->prospectsID = $prospectsID;
            $sale->contactsID = $contactsID;
            $sale->amount = $amount;
            $sale->productID = $productID;
            $sale->quantity = $quantity;
            $sale->paid = $paid;
            $sale->createdAt = date("Y-m-d H:i:s");

            if ($sale->save() === false) {
                $errors = array();
                $messages = $sale->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $dbTransaction->rollback('sale create failed' . json_encode($errors));
            }


            $customer = $this->getCustomerDetails($contactsID);
            $MSISDN = $customer["workMobile"];
            $name = $customer["fullName"];
            $paid =0;
            $status = 0;
            $customerMessage ;

           $discountAmount = $this->addDiscount($sale->salesID,$salesTypeID,$userID,$amount,$quantity,$productID);

           if($discountAmount > 0 ){
                  $status = $this->getCustomerBalance($contactsID,($amount - $discountAmount),$paymentPlanDeposit,$dbTransaction);
                  $amount = $amount - $discountAmount;

                  if($status == 2){
                     $paid = $amount;
                     $customerMessage="Dear " . $name . ", your sale has been placed successfully! You have been awarded a discount of Ksh. ".$discountAmount.". Your payment of Ksh. " . $amount ." has been deducted. Your sale is paid in full.";
                    }
                 elseif($status > 2){
                    $paid = $status;
                    $status = 1;
                    $customerMessage="Dear " . $name . ", your sale has been placed successfully! You have been awarded a discount of Ksh. ".$discountAmount.". Your payment of Ksh. " . ($status) ." .Has been deducted. Please pay ".($amount-$status)." .To complete this sale.";

                  }
                  else{
                    $paid = 0;
                    $status = 0;
                    $customerMessage="Dear " . $name . ", your sale has been placed successfully! You have been awarded a discount of Ksh. ".$discountAmount.". Please pay Ksh. " . ($paymentPlanDeposit - $discountAmount) ." .To complete this sale.";
                  }
           }
           else{
             $status = $this->getCustomerBalance($contactsID,$amount,$paymentPlanDeposit,$dbTransaction);
             
                 if($status == 2){
                    $paid = $amount;
                    $customerMessage= "Dear " . $name . ", your sale has been placed successfully! Your payment of Ksh. " . ($amount) ." has been deducted. Your sale is paid in full.";
                }
                elseif($status > 2){
                    $paid = $status;
                    $status = 1;
                    $customerMessage="Dear " . $name . ", your sale has been placed successfully! Your payment of Ksh. " . ($status) ." .Has been deducted. Please pay ".($amount-$status)." .To complete this sale.";

                  }
                  else{
                    $paid = 0;
                    $status = 0;
                    $customerMessage= "Dear " . $name . ", your sale has been placed successfully, please pay Ksh. " . $paymentPlanDeposit." .To complete this sale.";

                  }

                
           }

          /*  
            if($status == 2){
                $paid = $amount;
            }
            elseif($status > 2){
                $paid = $status;
                $status = 1;
            }
            elseif ($status < 2) {
                $paid = 0;
            }
            */
            $sale->status = $status;
            $sale->paid = $paid;
            $sale->amount = $amount;
            $sale->updatedAt = date("Y-m-d H:i:s");


           if ($sale->save() === false) {
                $errors = array();
                $messages = $sale->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $dbTransaction->rollback('Sale Discount add failed' . json_encode($errors));
            }
            $dbTransaction->commit();
            
            $res->sendMessage($MSISDN, $customerMessage);

            return $res->success("Sale saved successfully, await payment ", $sale);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('sale create error', $message);
        }
    }

    /*
    creates a sale payment plan based on deposit, sale type and frequency choosen
    */

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
            }
            return $paymentPlan->paymentPlanID;
        }
    }
    /*
    adds customer incase they were not created or prospectID was passed 
    */
    public function createCustomer($userID, $contactsID, $dbTransaction, $locationID = 0) {
        $res = new SystemResponses();
        $customer = Customer::findFirst(array("contactsID=:id: ",
                    'bind' => array("id" => $contactsID)));

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
                $dbTransaction->rollback('customer create failed' . json_encode($errors));
            }

            return $customer->customerID;
        }
    }

     /*
    adds contacts incase no contacat exists 
    also consumed by metropol controller
    */
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
                $dbTransaction->rollback('paymentPlan create failed' . json_encode($errors));

            }

            $res->sendMessage($workMobile, "Dear " . $fullName . ", welcome to Envirofit. For any questions or comments call 0800722700 ");
            return $contact->contactsID;
        }
    }

    /*
     creates an association between an item and a sale
     incase customer has already paid
    */

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

    /*
    updates an item to sold status
    */
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

   /*
   remove a sale
   parameter:
   saleID
   token
   */

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

    /*
    retrive customer details
    parameters: customerID
    */
    public function getCustomerDetails($contactsID) {
        
        $customerquery = "SELECT workMobile ,fullName FROM contacts WHERE contactsID=$contactsID";


        $customer = $this->rawSelect($customerquery);
        
        return $customer[0];
    }
   
   /*
   get sales made by a specific user
   parameters:
   userID (required)
   customerID (optional)
   token

   */

    public function getSales() {//{userID,customerID,token}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $customerID = $request->getQuery('customerID');
        $userID = $request->getQuery('userID');


        $saleQuery = "SELECT s.salesID,s.quantity,si.itemID,co.workMobile,co.workEmail,co.passportNumber,co.nationalIdNumber,co.fullName,s.createdAt,co.location,c.customerID,s.paymentPlanID,s.amount,st.salesTypeName,i.serialNumber,s.productID,p.productName, ca.categoryName FROM sales s JOIN customer c on s.customerID=c.customerID LEFT JOIN contacts co on c.contactsID=co.contactsID LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID  LEFT JOIN sales_item si ON s.salesID=si.saleID LEFT JOIN item i on si.itemID=i.itemID LEFT JOIN product p ON s.productID=p.productID LEFT JOIN category ca on p.categoryID=ca.categoryID ";


        if (!$token || !$userID) {
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
       
        $sales = $this->rawSelect($saleQuery);


        return $res->getSalesSuccess($sales);
    }

    public function getSalesV2() {//{userID,customerID,token}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $customerID = $request->getQuery('customerID');
        $userID = $request->getQuery('userID');


        $saleQuery = "SELECT s.salesID,s.quantity,co.workMobile,co.workEmail,co.passportNumber,co.nationalIdNumber,co.fullName,s.createdAt,co.location,c.customerID,s.paymentPlanID,s.amount,st.salesTypeName,s.productID,p.productName, ca.categoryName FROM sales s JOIN customer c on s.customerID=c.customerID LEFT JOIN contacts co on c.contactsID=co.contactsID LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID LEFT JOIN product p ON s.productID=p.productID LEFT JOIN category ca on p.categoryID=ca.categoryID ";


        if (!$token || !$userID) {
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
       
        $sales = $this->rawSelect($saleQuery);
        $newSales = array();

        foreach ($sales as $sale) {
              $newSale['salesID'] = $sale['salesID'];
              $newSale['quantity'] = $sale['quantity'];
              $newSale['workMobile'] = $sale['workMobile'];
              $newSale['workEmail'] = $sale['workEmail'];
              $newSale['passportNumber'] = $sale['passportNumber'];
              $newSale['nationalIdNumber'] = $sale['nationalIdNumber'];
              $newSale['fullName'] = $sale['fullName'];
              $newSale['createdAt'] = $sale['createdAt'];
              $newSale['location'] = $sale['location'];
              $newSale['customerID'] = $sale['customerID'];
              $newSale['paymentPlanID'] = $sale['paymentPlanID'];
              $newSale['amount'] = $sale['amount'];
              $newSale['salesTypeName'] = $sale['salesTypeName'];
              $newSale['productID'] = $sale['productID'];
              $newSale['productName'] = $sale['productName'];
              $newSale['categoryName'] = $sale['categoryName'];

              $serials="";

              $productIDs = str_replace("]","",str_replace("[", "", $sale['productID']));
              $productIDs = explode(",",$productIDs);
            if(count($productIDs)>=1){
                $itemsQuery = "SELECT i.serialNumber  FROM sales_item it JOIN item i ON it.itemID=i.itemID WHERE saleID=".$sale["salesID"];
                $serialNumbers = $this->rawSelect($itemsQuery);
                foreach ($serialNumbers as $s_number) {
                    if(empty($serials)){
                        $serials = $serials."".$s_number['serialNumber'];
                    }
                    else{
                        $serials=$serials.",".$s_number['serialNumber'];
                    }
                   
                }
            }
            $newSale['serialNumber']=$serials;
            array_push($newSales, $newSale);
        }


        return $res->getSalesSuccess($newSales);
    }
  /*
    retrieve  sales to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */

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
        $whereQuery = $whereQuery ? " WHERE (s.status=1 || s.status=2) AND $whereQuery " : " WHERE (s.status=1 || s.status=2) ";

        $countQuery = $countQuery . $defaultQuery . $whereQuery;
        $selectQuery = $selectQuery . $defaultQuery . $whereQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

        $logger->log("Sales Request Query: " . $selectQuery);

        $count = $this->rawSelect($countQuery);
        $sales = $this->rawSelect($selectQuery);


        $displaySales = array();

        foreach ($sales as $sale) {
            $items = $this->getSaleItems($sale['salesID']);
            $sale['items'] = $items;
            array_push($displaySales, $sale);
        }
//
        $data["totalSales"] = $count[0]['totalSales'];
        $data["sales"] = $displaySales;


        return $res->success("Sales ", $data);
    }

     /*
    retrieve pending and old crm sales to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */

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
 /*
    retrieve partner sales to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */
    public function getTablePartnerSales() { 
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

 /*
    util function to build all get queries based on passed parameters
    */
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

    /*
    get a sale summary representaion on crm dashboard
    */

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

   /*
   retrive items of a given sale
   parameters
   salesID (required)
   */
    public function getSaleItems($salesID) {
        $selectQuery = "select i.serialNumber, p.productName, c.categoryName from sales_item si join item i on si.itemID=i.itemID join product p on i.productID=p.productID join category c on p.categoryID=c.categoryID where saleID = $salesID";
        $items = $this->rawSelect($selectQuery);
        return $items;
    }

    /*
    get all transactions associated by a given sale
    nationalIdNumber (customer national Id number)
    w_mobile (customer work mobile)

    */

    public function getSalesTransactions($nationalIdNumber, $w_mobile) {
        $selectQuery = "select * from transaction t where t.salesID>0 and (t.salesID='$nationalIdNumber' or t.salesID='$w_mobile') ";
        $transactions = $this->rawSelect($selectQuery);
        return $transactions;
    }

    /*
    get sales reports 
    parameters:
    token
    */
   
    public function saleSummary() {
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
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
    
    /*
     monitors sales to trigger welcome call and delinquency tickets
    */
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
    }



    /*
    reconcile all partner sales from the old system
    */
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

/*
 reconcile agent sales made in the old crm
*/
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

/*
function to update partner sales
parameters
partnerSaleItemID,
contactsID
token
*/
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

/*
add sales that were not captured in the old crm
parameters:
amount,createdAt,paymentPlanDeposit,paid,workMobile,fullName,nationalIdNumber,location,productID,salesTypeID,userID,contactsID
*/
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

/*
check if customer has ever transacted and update their sales
Consumed by crmCreateSale
*/
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

    /*
    map item to sale created in crmCreateSale
    constumed by crmCreateSale
    */

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

/*
create new customers for contacts from old system who had made sales
*/
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


    /*
    incase customer had deposited money before sale was made we 
    use this function to map the transaction to this customer

    */

    private function getCustomerBalance($contactsID,$saleAmount,$saleDeposit,$dbTransaction){
                    $res= new SystemResponses();
                   $matchedTransactionsQuery = "SELECT SUM(replace(t.depositAmount,',','')) as totalDeposit from customer_transaction ct JOIN transaction t on ct.transactionID=t.transactionID WHERE ct.contactsID=$contactsID ";
                   
                    $customerSalesQuery = "SELECT * FROM sales WHERE status>=0 and contactsID=$contactsID ORDER BY salesID ASC";


                    $depositAmounts=$this->rawSelect($matchedTransactionsQuery);
                    $sales = $this->rawSelect($customerSalesQuery);
                    $totalDeposit =$depositAmounts[0]['totalDeposit'];

                    foreach ($sales as $sale) {
                        $status="";
                        $status = $sale['status'];
                        $amount = $sale['amount'];
                        $salesID = $sale['salesID'];
                        $paid = $sale['paid'];

                        $balance = $amount - $paid;
                        $excess = $totalDeposit - $balance;

                        $o_sale = Sales::findFirst(array("salesID=:id: ",
                                    'bind' => array("id" => $salesID)));
                        if($totalDeposit > 0){
                            if($status == 2){
                                 if($totalDeposit >= $balance && $paid <=0){
                                    $o_sale->paid = $paid+$balance;
                                    $o_sale->status=2;
                                     $res->dataError("salesID $salesID $totalDeposit >= $balance ", $totalDeposit);
                                  }
                              
                                $totalDeposit = $totalDeposit - $amount;
                                $res->dataError("salesID $salesID status 2 $excess > 0 totalDeposit ", $totalDeposit);
                            }
                           elseif($status == 1 || $status == 0){//&& $paid >0 && $amount != $paid){
                                if($totalDeposit >= $balance && $paid<=0){
                                    $o_sale->paid = $paid+$balance;
                                    $o_sale->status=2;
                                    $totalDeposit = $totalDeposit-$balance;
                                     $res->dataError("salesID $salesID $totalDeposit >= $balance ", $totalDeposit);
                                }
                                elseif($totalDeposit < $balance && $paid<=0){
                                    $o_sale->paid = $paid + $totalDeposit;
                                    $o_sale->status = 1;
                                    $totalDeposit = 0;
                                    $res->dataError("salesID $salesID $totalDeposit < $balance ", $totalDeposit);
                                }
                            }


                            if ($o_sale->save() === false) {
                                    $errors = array();
                                    $messages = $o_sale->getMessages();
                                    foreach ($messages as $message) {
                                        $e["message"] = $message->getMessage();
                                        $e["field"] = $message->getField();
                                        $errors[] = $e;
                                    }
                                 $dbTransaction->rollback('Sale update new sale  failed to match' . json_encode($errors));
                           }
                        }
                        
                  } 

                  if($totalDeposit >=$saleAmount){
                    return 2;
                  }
                  else if($totalDeposit >= $saleDeposit || $totalDeposit > 0 ){
                    return $totalDeposit;
                  }
                  else{
                    return 0;
                  }

    }

    public function addDiscount($salesID,$saleTypeID,$userID,$amount,$quantity,$productIDs){
         $res = new SystemResponses();
          $productIDs = str_replace("]","",str_replace("[", "", $productIDs));
          $productIDs = explode(",",$productIDs);

        
         $saleDiscount = SaleDiscount::findFirst(array("salesID=:id: ",
                            'bind' => array("id" => $salesID)));
         if($saleDiscount){
            $discountAmountOffered = Discount::findFirst(array("discountID=:id: ",
                                'bind' => array("id" => $saleDiscount->discountID)));
            return $discountAmountOffered->discountAmount;
         }

         $discountAmountOffered = 0;
         $totalDiscountToOffer = 0;

         foreach ($productIDs as $productID) {
            $discountsQuery = "SELECT * from discount d join discount_condition dc on d.discountConditionID=dc.discountConditionID JOIN discount_types dt on d.discountTypeID=dt.discountTypeID WHERE d.status=1 AND d.saleTypeID=$saleTypeID AND productID=$productID";
             $discounts = $this->rawSelect($discountsQuery);

             foreach ($discounts as $discount) {
                    $agents = $discount['agents'];
                    $discountID = $discount['discountID'];
                    $startDate = $discount['startDate'];
                    $endDate = $discount['endDate'];
                    $cur_date = date("Y-m-d H:i:s");
                    $discountType = $discount['discountTypeName'];
                    $discountAmount = $discount['discountAmount'];

                    $can_offer_discount=false;



                    if($this->isDateBetweenDates($cur_date, $startDate, $endDate) == false){
                        $o_discount = Discount::findFirst(array("discountID=:id: ",
                                    'bind' => array("id" => $discountID)));
                        $o_discount->status = 0;
                        $o_discount->save();
                       }
                       else{
                            if (strcasecmp($agents, 'all') == 0 ){
                                    if(strcasecmp($discountType, 'amount')){
                                        $can_offer_discount = $this->compareDiscount($discount['discountMargin'],$discount['conditionName'],$amount);
                                    }
                                    elseif(strcasecmp($discountType, 'quantity')){
                                        $can_offer_discount = $this->compareDiscount($discount['discountMargin'],$discount['conditionName'],$quantity);
                                    }
                                }
                            else{
                                $allAgents = explode(",", $agents);
                                 foreach ($allAgents as $agent) {
                                     if(strcasecmp($agents, $userID) == 0){
                                        if(strcasecmp($discountType, 'amount')){
                                            $can_offer_discount = $this->compareDiscount($discount['discountMargin'],$discount['conditionName'],$amount);
                                            }
                                            elseif(strcasecmp($discountType, 'quantity')){
                                             $can_offer_discount = $this->compareDiscount($discount['discountMargin'],$discount['conditionName'],$quantity);
                                            }
                                     }
                                 }
                            }
                       }

                        $saleDiscount = SaleDiscount::findFirst(array("salesID=:id: ",
                                     'bind' => array("id" => $salesID)));


                       if($can_offer_discount && $saleDiscount){
                             $discountAmountOffered = Discount::findFirst(array("discountID=:id: ",
                                        'bind' => array("id" => $saleDiscount->discountID)));
                            //return $discountAmountOffered->discountAmount;
                             $totalDiscountToOffer = $totalDiscountToOffer + $discountAmountOffered->discountAmount;

                       }
                       else{
                            $saleDiscount = new SaleDiscount();
                            $saleDiscount->salesID = $salesID;
                            $saleDiscount->discountID = $discountID;
                            $saleDiscount->status = 0;
                            $saleDiscount->userID = $userID;
                            $saleDiscount->createdAt = date("Y-m-d H:i:s");
                            if ($saleDiscount->save() === false) {
                                    $errors = array();
                                    $messages = $saleDiscount->getMessages();
                                    foreach ($messages as $message) {
                                        $e["message"] = $message->getMessage();
                                        $e["field"] = $message->getField();
                                        $errors[] = $e;
                                    }
                                     //$dbTransaction->rollback('saleDiscount create failed' . json_encode($errors));
                                     $res->dataError('saleDiscount create failed', $errors);
                                     return 0;
                            }
                            $res->success("Sale Discount added ",$saleDiscount);
                            $discountAmountOffered = Discount::findFirst(array("discountID=:id: ",
                                        'bind' => array("id" => $discountID)));
                            $totalDiscountToOffer =$totalDiscountToOffer + $discountAmountOffered->discountAmount;
                        }
                }
         }
         return $totalDiscountToOffer;

    }

    private function compareDiscount($margin, $condition, $operand){
            $eva= "$operand".$condition.$margin;
          if($eva){
              return true;
          }
          else{
              return false;
          }
    }
}
