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

    public function create() { //{contactsID,amount,userID,salesTypeID,frequencyID,productID}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $paymentPlanDeposit = $json->paymentPlanDeposit;
        $salesTypeID = $json->salesTypeID;
        $frequencyID = $json->frequencyID;
        $contactsID = $json->contactsID;
        $userID = $json->userID;
        $amount = $json->amount;
        $productID = $json->productID;

        $location = $json->location;
        $workMobile = $json->workMobile;
        $fullName = $json->fullName;
        $nationalIdNumber = $json->nationalIdNumber;

        $token = $json->token;



        if (!$token) {
            return $res->dataError("Token missing er" . json_encode($json));
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
        if (!$productID) {
            return $res->dataError("product missing ");
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

            $sale = new Sales();
            $sale->status = 0;
            $sale->paymentPlanID = $paymentPlanID;
            $sale->userID = $userID;
            $sale->customerID = $customerID;
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
        $paymentPlan = PaymentPlan::findFirst(array("salesTypeID=:s_id: AND frequencyID=:f_id: ",
                    'bind' => array("s_id" => $salesTypeID, "f_id" => $frequencyID)));

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
                // $res->dataError('paymentPlan create failed',$errors);
                $dbTransaction->rollback('paymentPlan create failed' . json_encode($errors));
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
//        $logPathLocation = $this->config->logPath->location . 'error.log';
//        $logger = new FileAdapter($logPathLocation);
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $userID = $request->getQuery('userID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $customerID = $request->getQuery('customerID');
        $startDate = $request->getQuery('start');
        $endDate = $request->getQuery('end');

        $countQuery = "SELECT count(s.salesID) as totalSales ";

//        $defaultQuery = " FROM sales s join customer c on s.customerID=c.customerID JOIN contacts co on c.contactsID=co.contactsID JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID JOIN users u on s.userID=u.userID where s.status=1 ";
//        $selectQuery = "SELECT s.salesID,s.userID as agentID,u.agentNumber,co.workMobile,co.workEmail,co.passportNumber,co.nationalIdNumber,co.fullName,s.createdAt,co.location,c.customerID,s.paymentPlanID,s.amount,pp.paymentPlanDeposit ";
        $defaultQuery = "FROM sales s INNER JOIN payment_plan pp ON s.paymentPlanID=pp.paymentPlanID "
                . "INNER JOIN sales_type st ON pp.salesTypeID=st.salesTypeID INNER JOIN frequency f "
                . "ON pp.frequencyID=f.frequencyID INNER JOIN users u ON s.userID=u.userID "
                . "INNER JOIN contacts c ON u.contactID=c.contactsID INNER JOIN customer cust "
                . "ON s.customerID=cust.customerID INNER JOIN contacts c1 ON cust.contactsID=c1.contactsID "
                . "INNER JOIN product p ON s.productID=p.productID INNER JOIN product_sale_type_price AS psp "
                . "ON (pp.salesTypeID=psp.salesTypeID AND s.productID=psp.productID) WHERE s.status=1 ";

        $selectQuery = "SELECT s.salesID, s.paymentPlanID, pp.paymentPlanDeposit, "
                . "pp.salesTypeID, st.salesTypeName, psp.price,pp.frequencyID,"
                . "f.numberOfDays, f.frequencyName, s.userID,c.fullName AS agentName,"
                . "c.workMobile AS agentNumber,s.customerID, c1.fullName AS customerName, "
                . "c1.workMobile AS customerNumber, c1.nationalIdNumber, s.productID, p.productName, s.createdAt ";
//        $condition = " AND ";

        $whereArray = [
            'filter' => $filter,
            's.customerID' => $customerID,
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
            } else if ($key == 't.status' && $value == 404) {
                $valueString = "" . $key . "=0" . " AND ";
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

        $whereQuery = $whereQuery ? "AND $whereQuery " : "";

//        if ($userID && $filter && $customerID) {
//            $condition = "  AND s.userID=$userID AND s.customerID=$customerID AND ";
//        } elseif ($userID && $filter && !$customerID) {
//            $condition = "  AND s.userID=$userID AND ";
//        } elseif ($userID && !$filter && $customerID) {
//            $condition = " AND s.userID=$userID AND s.customerID=$customerID ";
//        } elseif ($userID && !$filter && !$customerID) {
//            $condition = " AND s.userID=$userID ";
//        } elseif (!$userID && !$filter && $customerID) {
//
//            $condition = " AND s.customerID=$customerID ";
//        } elseif (!$userID && $filter && !$customerID) {
//
//            $condition = " AND ";
//        } elseif (!$userID && !$filter && !$customerID) {
//            $condition = "  ";
//        }

        $countQuery = $countQuery . $defaultQuery . $whereQuery;
        $selectQuery = $selectQuery . $defaultQuery . $whereQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

//        $logger->log("Sales Request Query: " . $selectQuery);
//        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit, $filter);
//        if ($queryBuilder) {
//            $selectQuery = $selectQuery . $defaultQuery . $condition . " " . $queryBuilder;
//            //$countQuery = $countQuery.$defaultQuery.$condition." ".$queryBuilder;
//            if ($filter) {
//                $countQuery = $countQuery . $defaultQuery . $condition . " " . $queryBuilder;
//            } else {
//                $countQuery = $countQuery . $defaultQuery . $condition;
//            }
//        } else {
//            $selectQuery = $selectQuery . $defaultQuery . $condition;
//            $countQuery = $countQuery . $defaultQuery . $condition;
//        }
        //return $res->success($countQuery ."    ".$selectQuery);

        $count = $this->rawSelect($countQuery);
        $sales = $this->rawSelect($selectQuery);


        $displaySales = array();
        foreach ($sales as $sale) {
            $items = $this->getSaleItems($sale['salesID']);
            $transactions = $this->getSalesTransactions($sale['salesID']);
            $sale['items'] = $items;
            $sale['transactions'] = $transactions; //return $res->success("salesID",$items);
            array_push($displaySales, $sale);
        }

        $data["totalSales"] = $count[0]['totalSales'];
        $data["sales"] = $displaySales;


        return $res->success("Sales ", $data);
    }

    public function getTablePartnerSales() { //sort, order, page, limit,filter,userID
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $userID = $request->getQuery('userID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $customerID = $request->getQuery('customerID');
        $startDate = $request->getQuery('start');
        $endDate = $request->getQuery('end');

        $countQuery = "SELECT count(psi.partnerSaleItemID) as totalSales ";
        $defaultQuery = "FROM partner_sale_item psi LEFT JOIN product p ON psi.productID=p.productID "
                . "INNER JOIN customer cust ON psi.customerID=cust.customerID INNER JOIN contacts c "
                . "ON cust.contactsID=c.contactsID ";

        $selectQuery = "SELECT psi.partnerSaleItemID, psi.serialNumber, psi.productID, "
                . "p.productName, cust.customerID, c.fullName,c.workMobile AS customerNumber, "
                . "c.nationalIdNumber,psi.salesPartner AS partnerName, psi.status,psi.createdAt ";

        $whereArray = [
            'filter' => $filter,
            'psi.customerID' => $customerID,
            'date' => [$startDate, $endDate]
        ];

        $logger->log("Sales Request Data: " . json_encode($whereArray));

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

//        if ($sort && $order && $filter) {
//            $query = "  co.fullName REGEXP '$filter' OR t.ticketTitle REGEXP '$filter' OR tc.ticketCategoryName REGEXP '$filter' ORDER by $sort $order LIMIT $ofset,$limit";
//        } elseif ($sort && $order && !$filter) {
//            $query = " ORDER by $sort $order LIMIT $ofset,$limit";
//        } elseif ($sort && $order && !$filter) {
//            $query = " ORDER by $sort $order  LIMIT $ofset,$limit";
//        } elseif (!$sort && !$order) {
//            $query = " LIMIT $ofset,$limit";
//        } elseif (!$sort && !$order && $filter) {
//            $query = "  co.fullName REGEXP '$filter' OR t.ticketTitle REGEXP '$filter' OR tc.ticketCategoryName REGEXP '$filter' LIMIT $ofset,$limit";
//        }

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

        $totalSalesQuery = "SELECT sum(t.depositAmount) as totalSales FROM transaction t ";
        $todaysSalesQuery = "SELECT sum(t.depositAmount) as todaysSale FROM transaction t where date(t.createdAt)='$date'";

        $totalSaleType = "SELECT st.salesTypeID,st.salesTypeName,sum(t.depositAmount) as totalAmount from sales_type st join payment_plan pp on st.salesTypeID=pp.salesTypeID join sales s on pp.paymentPlanID=s.paymentPlanID join transaction t on s.salesID=t.salesID group by st.salesTypeID";
        $todaysSaleType = "SELECT st.salesTypeID,st.salesTypeName,sum(t.depositAmount) as totalAmount from sales_type st join payment_plan pp on st.salesTypeID=pp.salesTypeID join sales s on pp.paymentPlanID=s.paymentPlanID join transaction t on s.salesID=t.salesID  where date(t.createdAt)='$date' group by st.salesTypeID ";

        $totalProductSales = "SELECT p.productID,p.productName,count(s.productID) as numberOfProducts,sum(t.depositAmount) as totalAmount,c.categoryID,c.categoryName FROM product p join sales s on p.productID=s.productID join transaction t on s.salesID=t.salesID join category c on p.categoryID=c.categoryID group by p.productID ";
        $todaysProductSales = "SELECT p.productID,p.productName,count(s.productID) as numberOfProducts,sum(t.depositAmount) as totalAmount,c.categoryID,c.categoryName FROM product p join sales s on p.productID=s.productID join transaction t on s.salesID=t.salesID join category c on p.categoryID=c.categoryID where date(t.createdAt)='$date' group by p.productID ";

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
        $summaryData['todaysSales'] = $todaysSale[0]['todaysSales'];
        $summaryData['totalSaleType'] = $totalSaleType;
        $summaryData['todaysSaleType'] = $todaysSaleType;
        $summaryData['totalProductSales'] = $totalProductSales;
        $summaryData['todaysProductSales'] = $todaysProductSales;
        $summaryData['tickets'] = $tickets;

        return $res->success("Summary data ", $summaryData);
    }

    public function getSaleItems($salesID) {
        $selectQuery = "select i.serialNumber, p.productName, c.categoryName from sales_item si join item i on si.itemID=i.itemID join product p on i.productID=p.productID join category c on p.categoryID=c.categoryID where saleID = $salesID";
        $items = $this->rawSelect($selectQuery);
        return $items;
    }

    public function getSalesTransactions($salesID) {
        $selectQuery = "select * from transaction t where salesID=$salesID";
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
                . "INNER JOIN sales_type st ON pp.salesTypeID=st.salesTypeID LEFT JOIN frequency f ON pp.frequencyID=f.frequencyID WHERE s.status=1");

        $salesData = array();
        $salesData['totalSales'] = $totalSales[0]['totalSales'];
        $salesData['withoutPayment'] = $salesWithoutPaymentPlan[0]['withoutPayment'];
        $salesData['cash'] = $cashSales[0]['cashTotal'];
        $salesData['paygo'] = $paygoSales[0]['paygoTotal'];
        $salesData['installment'] = $installmentSales[0]['installmentTotal'];
        $salesData['closed'] = $closedSales[0]['closed'];

        return $res->success("sale stats", $salesData);
    }


    public function updateOldSales(){
             $jwtManager = new JwtManager();
            $request = new Request();
            $res = new SystemResponses();
            $json = $request->getJsonRawBody();
            $transactionManager = new TransactionManager();
            $dbTransaction = $transactionManager->get();
            /*$query = "select s.salesID,s.customerID,c.homeMobile,c.nationalIdNumber,c.fullName,t.transactionID,t.fullName,t.salesID from sales s  JOIN contacts c on s.customerID=c.contactsID  JOIN transaction t on t.salesID=c.nationalIdNumber or t.salesID=c.homeMobile  where s.createdAt='0000-00-00 00:00:00' and s.customerID > 0 and t.salesID > 0 group by s.salesID;"*/
            try {
                 $salesQuery = "select * from sales where s.createdAt='0000-00-00 00:00:00' and s.customerID > 0 ";
                 $sales = $this->rawSelect($salesQuery);


            foreach ($sales as $sale) {
                $contactsID= $sale["customerID"];
                $saleID = $sale["salesID"];
                $contactsQuery = "select * from contacts where contactsID=$contactsID";
                $contacts = $this->rawSelect($contactsQuery);
                foreach ($contacts as $contact) {
                    $workMobile = $contact["workMobile"];
                    $idNumber = $contact["nationalIdNumber"];
                    $transactionQuery = "select * from transaction where salesID=$workMobile OR salesID=$idNumber";
                    $transactions = $this->rawSelect($contactsQuery);
                    $paidAmount = 0;
                    foreach ($transactions as $transaction) {
                        $paidAmount = $paidAmount+$transaction['depositAmount'];
                    }
                    $sale = Sales::findFirst(array("salesID=:id: ",
                            'bind' => array("id" => $saleID)));

                    if($paidAmount > 2000){
                        
                        $sale->status = 1;
                    }
                        
                    else{
                        $sale->status = 3;

                    }

                    if ($sale->save() === false) {
                            $errors = array();
                            $messages = $sale->getMessages();
                            foreach ($messages as $message) {
                                $e["message"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                $errors[] = $e;
                            }
                        }
                    
                      $dbTransaction->rollback('sale create failed' . json_encode($errors));
                 }

                }
            }
          return $res->success("sale updated ", $sales);
            
            
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('sale update error', $message);
        }

       
    }

}
