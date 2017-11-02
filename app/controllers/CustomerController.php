<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

/*
All customer CRUD operations 
*/


class CustomerController extends Controller {
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
    create new customer 
    paramters:
    workMobile,nationalIdNumber,fullName,location
    */

    public function create() {//($workMobile,$nationalIdNumber,$fullName,$location,
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $token = $json->token;
        $location = $json->location;
        $workMobile = $json->workMobile;
        $fullName = $json->fullName;
        $nationalIdNumber = $json->nationalIdNumber;
        $serialNumber = $json->serialNumber;
        $productID = $json->productID ? $json->productID : NULL;
        $salePartner = $json->salePartner;
        $userID = $json->userID;

       // $res->dataError("USer data ".json_encode($json));

        if (!$token || !$workMobile || !$fullName || !$serialNumber || !$salePartner || !$userID) {
            return $res->dataError("Missing data ");
        }

        try {

            $workMobile = $res->formatMobileNumber($workMobile);

            $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
                        'bind' => array("w_mobile" => $workMobile)));
             $contactsID = $contact->contactsID;

            if (!$contact) {

                $contact = new Contacts();
                $contact->workMobile = $workMobile;
                $contact->fullName = $fullName;
                $contact->location = $location;
                $contact->nationalIdNumber = $nationalIdNumber;

                $contact->createdAt = date("Y-m-d H:i:s");

                if ($contact->save() === false) {
                    $errors = array();
                    $messages = $contact->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback("Contacts create error" . json_encode($errors));
                    return $res->dataError('Contacts create error', $message);
                }

                $res->sendMessage($workMobile, "Dear " . $fullName . ", welcome to Envirofit. For any questions or comments call 0800722700 ");
                $contactsID = $contact->contactsID;

            }

            $customer = $this->createCustomer($userID, $contactsID,$dbTransaction);

            if (!$customer) {
                $dbTransaction->rollback("Customer create error");
                return $res->dataError('sale create error', 'Nothing');
            }

                $customerID = $customer->customerID;
                
           
                $item = Item::findFirst(array("serialNumber=:serialNumber: ",
                        'bind' => array("serialNumber" => $serialNumber)));

                if (!$item) {
                    $item = new Item();
                    $item->productID = $productID;
                    $item->serialNumber = $serialNumber;
                    $item->status = 2;
                    $item->createdAt = date("Y-m-d H:i:s");

                    if ($item->save() === false) {
                        $errors = array();
                        $messages = $item->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                        $dbTransaction->rollback("Partner item create error" . json_encode($errors));
                        return $res->dataError('sale create error', $message);
                    }
                }

                
                $partnerSaleItem = PartnerSaleItem::findFirst(array("itemID=:itemID: ",
                            'bind' => array("itemID" => $item->itemID)));

                if ($partnerSaleItem) {
                    return $res->success("Sale already exists ", $item);
                } 

                    $partnerSaleItem = new PartnerSaleItem();
                    $partnerSaleItem->customerID = $customerID;
                    $partnerSaleItem->contactsID = $customer->contactsID;
                    $partnerSaleItem->salesPartner = $salePartner;
                    $partnerSaleItem->productID = $productID;
                    $partnerSaleItem->createdAt = date("Y-m-d H:i:s");

                      if($item){
                        $partnerSaleItem->itemID = $item->itemID;
                        $partnerSaleItem->serialNumber = $serialNumber;
                      }
                      else{
                        $partnerSaleItem->itemID = 0;
                         $partnerSaleItem->serialNumber = "n/a";
                      }
                    

                    if ($partnerSaleItem->save() === false) {
                        $errors = array();
                        $messages = $partnerSaleItem->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                        $dbTransaction->rollback("Partner create error" . json_encode($errors));
                       
                    }
            
            $dbTransaction->commit();

            return $res->success("Success ", $item);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Contacts create error '.$message, $message);
        }
    }



     /*
    update new customer 
    paramters:
    token, userID, fullName, workMobile,workEmail, nationalIdNumber,location,
    customerID (required)
    */
    public function update() { //token, userID, fullName, workMobile,workEmail, nationalIdNumber,location,customerID
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();

        $token = $json->token;
        //$contactsID = $json->contactsID;
        $customerID = $json->customerID;
        $prospectsID = $json->prospectsID;
        $userID = $json->userID;
        $fullName = $json->fullName;
        $workMobile = $json->workMobile;
        $workEmail = $json->workEmail;
        $nationalIdNumber = $json->nationalIdNumber;
        $location = $json->location;
        $sourceID = $json->sourceID;
        $otherSource = $json->otherSource;
        
//        $logger->log("Update Request Data: " . json_encode($json));

        if (!$token || !$userID || (!$customerID && !$prospectsID)) {
            return $res->dataError("missing data ", []);
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("data compromised");
        }

        $customer = NULL;
        $contact = NULL;

        if ($customerID) {
            $customer = Customer::findFirst(array("customerID=:id: ",
                        'bind' => array("id" => $customerID)));

            if (!$customer) {
                return $res->dataError("customer doesn't exist");
            }

            $customer->updatedBy = $userID;

            $contact = Contacts::findFirst(array("contactsID=:id: ",
                        'bind' => array("id" => $customer->contactsID)));
            if (!$contact) {
                return $res->dataError("contact doesn't exist");
            }

            if ($fullName) {
                $contact->fullName = $fullName;
            }

            if ($nationalIdNumber) {
                $contact->nationalIdNumber = $nationalIdNumber;
            }

            if ($workMobile) {
                $workMobile1 = $res->formatMobileNumber($workMobile);
                $contact->workMobile = $workMobile1;
            }

            if ($workEmail) {
                $contact->workEmail = $workEmail;
            }

            if ($location) {
                $contact->location = $location;
            }
        }

        if ($prospectsID) {
            $customer = Prospects::findFirst(array("prospectsID=:id: ",
                        'bind' => array("id" => $prospectsID)));

            if (!$customer) {
                return $res->dataError("prospect doesn't exist");
            }

            $customer->updatedBy = $userID;

            if ($sourceID) {
                $customer->sourceID = $sourceID;
            }

            if ($otherSource) {
                $customer->otherSource = $otherSource;
            }

            $contact = Contacts::findFirst(array("contactsID=:id: ",
                        'bind' => array("id" => $customer->contactsID)));
            if (!$contact) {
                return $res->dataError("contact doesn't exist");
            }

            if ($fullName) {
                $contact->fullName = $fullName;
            }

            if ($nationalIdNumber) {
                $contact->nationalIdNumber = $nationalIdNumber;
            }

            if ($workMobile) {
                $workMobile1 = $res->formatMobileNumber($workMobile);
                $contact->workMobile = $workMobile1;
            }

            if ($workEmail) {
                $contact->workEmail = $workEmail;
            }

            if ($location) {
                $contact->location = $location;
            }
        }


        if ($contact->save() === false) {
            $errors = array();
            $messages = $contact->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('contact edit failed', $errors);
        }

        if ($customer->save() === false) {
            $errors = array();
            $messages = $contact->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('customer/prospect edit failed', $errors);
        }


        return $res->success('contact edited successfully', $customer);
    }
  
  /*
    remove a customer 
    parameters:
    customerID,token,
    userID
  */
    public function delete() {//customerID,prospectsID,token,userID
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();

        $customerID = $json->customerID;
        $prospectsID = $json->prospectsID;
        $userID = $json->userID;
        $token = $json->token;

        if (!$token || !$userID || (!$customerID && !$prospectsID)) {
            return $res->dataError("fields missing");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        if ($customerID) {
            $customer = Customer::findFirst(array("customerID=:id: ",
                        'bind' => array("id" => $customerID)));

            if (!$customer) {
                return $res->dataError('customer does not exist', []);
            }

            $customer->status = 0;
            $customer->updatedBy = $userID;
        }

        if ($prospectsID) {
            $customer = Prospects::findFirst(array("prospectsID=:id: ",
                        'bind' => array("id" => $prospectsID)));

            if (!$customer) {
                return $res->dataError('prospect does not exist', []);
            }

            $customer->status = 0;
            $customer->updatedBy = $userID;
        }

        if ($customer->save() === false) {
            $errors = array();
            $messages = $customer->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('customer/prospect delete failed', $errors);
        }

        return $res->success("customer/prospect successfully deleted ", $customer);
    }

    public function createCustomer($userID, $contactsID, $dbTransaction, $locationID = 0) {


        $customer = Customer::findFirst(array("contactsID=:id: ",
                    'bind' => array("id" => $contactsID)));
        $res = new SystemResponses();

        $res->dataError("select user $userID contact $contactsID");
        if ($customer) {
            return $customer;
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
                $dbTransaction->rollback("Partner sale transaction create error",$errors);
                return NULL;
            }
             $res->dataError("select user $userID contact $contactsID json ".json_encode($customer));
            return $customer;
        }
    }

    /*
    retrieve all customers owned/created by a given user
    parameters:
    customerID (optional),userID
    */
    public function getAll() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token') ? $request->getQuery('token') : '';
        $customerID = $request->getQuery('customerID') ? $request->getQuery('customerID') : '';
        $userID = $request->getQuery('userID') ? $request->getQuery('userID') : '';
        $filter = $request->getQuery('filter') ? $request->getQuery('filter') : '';

        if (!$token) {
            return $res->dataError("Missing data ", []);
        }

        $customerQuery = "SELECT cu.customerID, cu.userID, cu.contactsID, c.workMobile, c.workEmail, "
                . "c.nationalIdNumber, c.fullName, c.location, cu.createdAt "
                . "FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID ";

        $whereArray = [
            'cu.customerID' => $customerID,
            'cu.userID' => $userID,
            'filter' => $filter
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {
            if ($key == 'filter') {
                $searchColumns = ['c.workMobile', 'c.nationalIdNumber', 'c.fullName', 'c.location'];

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
            } else {
                if ($key == 'status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                } else if ($key == 'date') {
                    if ($value[0] && $value[1]) {
                        $valueString = " DATE(cu.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    }
                } else {
                    $valueString = $value ? "" . $key . "=" . $value . " AND" : "";
                }
                $whereQuery .= $valueString;
            }
        }

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND");
        }

        $whereQuery = $whereQuery ? "WHERE $whereQuery " : "WHERE cu.customerID IS NULL ";

        $customerQuery = $customerQuery . $whereQuery;

        $customers = $this->rawSelect($customerQuery);

        return $res->success("customers", $customers);
    }
  

    /*
    retrieve  customers to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */
    public function getTableCustomers() { //sort, order, page, limit,filter
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';
        $isExport = $request->getQuery('isExport') ? $request->getQuery('isExport') : '';


        $countQuery = "SELECT count(c.customerID) as totalCustomers ";

        $baseQuery = " FROM customer  c join contacts co on c.contactsID=co.contactsID LEFT JOIN contact_credit_score cs on c.contactsID=cs.contactsID ";

        $selectQuery = "SELECT c.customerID,co.contactsID, co.fullName,co.nationalIdNumber,co.workMobile,co.workEmail,co.location, c.createdAt,cs.score as crbCheckStatus ";


        $whereArray = [
            'filter' => $filter,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['co.fullName', 'co.nationalIdNumber', 'co.workMobile', 'co.location'];

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
                    $valueString = " DATE(c.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
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

        $whereQuery = $whereQuery ? "WHERE c.status=1 AND $whereQuery AND datediff(now(),c.createdAt)>30" : " WHERE c.status=1 AND datediff(now(),c.createdAt)>30 ";

        $countQuery = $countQuery . $baseQuery . $whereQuery;
        $selectQuery = $selectQuery . $baseQuery . $whereQuery;
        $esxportQuery = $selectQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);

        $selectQuery .= $queryBuilder;
       // return $res->success("customers", $selectQuery);

        $count = $this->rawSelect($countQuery);

        $customers = $this->rawSelect($selectQuery);
        if($isExport){
            $exportCustomers = $this->rawSelect($esxportQuery);
            $data["totalCustomers"] = $count[0]['totalCustomers'];
            $data["customers"] = $customers;
            $data["exportCustomers"] = $exportCustomers;
        }
        else{
            $data["totalCustomers"] = $count[0]['totalCustomers'];
            $data["customers"] = $customers;
            $data["exportCustomers"] = 'no data';
        }
        

        return $res->success("customers", $data);
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

//move from customers to prospects
    public function falseCustomers(){
         $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();

        $selectQuery = "SELECT * from customer c JOIN sales s on c.`contactsID` = s.contactsID WHERE s.status = 0 AND paid<=0";
        $customers = $this->rawSelect($selectQuery);
        foreach ($customers as $customer) {
            $contactsID = $customer['contactsID'];
            $customerID = $customer['customerID'];
            $contactQuery = "SELECT * from customer c JOIN sales s on c.`contactsID` = s.contactsID WHERE s.status>0 AND paid>0 AND c.contactsID=$contactsID";
            $paidCustomer = $this->rawSelect($contactQuery); 
            
            $prospect = Prospects::findFirst("contactsID=$contactsID");

            if(!$paidCustomer && !$prospect){
                $customer_o=Customer::findFirst("contactsID=".$contactsID);
                $customer_o =  Customer::findFirst("customerID=$customerID");
                $prospect = new Prospects();
                $prospect->status =1;
                $prospect->userID = $customer_o->userID;
                $prospect->contactsID=$contactsID;
                $prospect->sourceID =0;
                $prospect->otherSource = 0;
                $prospect->createdAt = date("Y-m-d H:i:s");
                $prospect->save();
                $customer_o->status=-2;
                $customer_o->save();
            }

        }
        return $res->success("Success ",true);
    }


   public function getGasCustomers(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();

        $token = $json->token;
        $workMobile = $json->mobile;

        if(!$token || !$workMobile){
            return $res->dataError("Missing data");
        }

        $data = array();
        $serials = array();
        $paygo = 0;

        $items = $this->rawSelect("SELECT c.workMobile,c.fullName,c.nationalIdNumber,c.contactsID,i.serialNumber,i.itemID FROM contacts c join user_items ui on c.contactsID=ui.contactsID join item i on ui.itemID=i.itemID WHERE c.workMobile=$workMobile");
        foreach ($items as $item) {
            //get sale item 
            $saleItem = $this->rawSelect("SELECT (s.amount-s.paid) as pending FROM sales_item si join sales s on si.saleID=s.salesID join payment_plan pp on s.paymentPlanID=pp.paymentPlanID where pp.salesTypeID=2 AND s.status>0 AND si.itemID=".$item['itemID']);

            if($saleItem ){
                //$data['paygo'] = $saleItem[0]['pending'];
                $paygo = $paygo+ $saleItem[0]['pending'];
            }
            //$data['']
            array_push( $serials, $item['serialNumber']);
        }

        $data['paygo'] = $paygo;
        $data['customer_name'] = $items[0]['fullName'];
        $data['is_number'] = $items[0]['nationalIdNumber'];
        $data['serials'] = $serials;

        return $res->success("User data",$data);

   }

}
