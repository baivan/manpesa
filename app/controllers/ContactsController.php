<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

/*
All contacts CRUD operations 
*/

class ContactsController extends Controller {
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
     search for a specific contact based on contactname or mobile number or location
     parameters: 
     filter (search criteria),
     userID (user requesting this service),
     token (authentication token)
    */
    
    public function searchContacts() {
        $jwtManager = new JwtManager(); 
        $request = new Request(); 
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $filter = $request->getQuery('filter');

        if (!$token) {
            return $res->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("search Data compromised");
        }

        $searchQuery = "SELECT c.contactsID,c.workMobile,c.fullName,c.passportNumber,c.nationalIdNumber,c.location,p.prospectsID,cu.customerID,u.userID FROM contacts c "
                . "LEFT JOIN prospects p ON c.contactsID=p.contactsID LEFT JOIN customer cu ON c.contactsID=cu.contactsID "
                . "LEFT JOIN users u ON c.contactsID=u.userID WHERE c.workMobile REGEXP '$filter' OR c.fullName REGEXP '$filter' ";

        if ($filter) {
            $contacts = $this->rawSelect($searchQuery);
        } else {
            $contacts = [];
        }

        return $res->success("contacts ", $contacts);
    }

    

   /*
    retrieve product contacts to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */

    public function getTableContacts() { //sort, order, page, limit,filter
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');

        $countQuery = "SELECT count(co.contactsID) as totalCustomerProspects from contacts co LEFT JOIN customer cu on co.contactsID=cu.contactsID LEFT JOIN prospects p on co.contactsID = p.contactsID";


        $selectQuery = "SELECT co.contactsID,co.homeMobile,co.nationalIdNumber,co.passportNumber,co.workEmail,co.fullName,co.location,cu.customerID,cu.userID as customerAgentID,p.prospectsID,p.userID as prospectAgentID from contacts co LEFT JOIN customer cu on co.contactsID=cu.contactsID LEFT JOIN prospects p on co.contactsID = p.contactsID";

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit, $filter);

        if ($queryBuilder) {
            $selectQuery = $selectQuery . " " . $queryBuilder;
        }
        //  return $res->success($selectQuery);

        $count = $this->rawSelect($countQuery);

        $contactsCustomers = $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
        $data["totalCustomerProspects"] = $count[0]['totalCustomerProspects'];
        $data["contactsCustomers"] = $contactsCustomers;

        return $res->success("CustomerProspects ", $data);
    }

    public function tableQueryBuilder($sort = "", $order = "", $page = 0, $limit = 10, $filter = "") {
        $query = "";

        if (!$page || $page <= 0) {
            $page = 1;
        }

        $ofset = ($page - 1) * $limit;
        if ($sort && $order && $filter) {
            $query = " WHERE co.nationalIdNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' ORDER by $sort $order LIMIT $ofset,$limit";
        } else if ($sort && $order && !$filter && $limit > 0) {
            $query = " ORDER by $sort $order LIMIT $ofset,$limit";
        } else if ($sort && $order && !$filter && !$limit) {
            $query = " ORDER by $sort $order  LIMIT $ofset,10";
        } else if (!$sort && !$order && $limit > 0) {
            $query = " LIMIT $ofset,$limit";
        } else if (!$sort && !$order && $filter && !$limit) {
            $query = " WHERE co.nationalIdNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' LIMIT $ofset,10";
        } else if (!$sort && !$order && $filter && $limit) {
            $query = " WHERE co.nationalIdNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' LIMIT $ofset,10";
        }

        return $query;
    }

    /*
    create new contact 
    paramters:
    workMobile,nationalIdNumber,fullName,location
    */

    public function createContact() {//($workMobile,$nationalIdNumber,$fullName,$location,
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

        if (!$token || !$workMobile || !$fullName) {
            return $res->dataError("Missing data ");
        }

        $workMobile = $res->formatMobileNumber($workMobile);
        if ($homeMobile) {
            $homeMobile = $res->formatMobileNumber($homeMobile);
        }

        $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
                    'bind' => array("w_mobile" => $workMobile)));

        if ($contact) {
            $res->sendMessage($workMobile, "Dear " . $fullName . ", thankyou for your support, we value you. For any questions or comments call 0800722700 ");
            return $res->success("Success ", $contact);
        } else {
            try {
                $contact = new Contacts();
                // $contact->workEmail = $workEmail;
                // $contact->homeEmail=$homeEmail;
                $contact->workMobile = $workMobile;
                //  $contact->homeMobile=$homeMobile;
                $contact->fullName = $fullName;
                $contact->location = $location;
                $contact->nationalIdNumber = $nationalIdNumber;
                //$contact->passportNumber=$passportNumber;
                // $contact->locationID=$locationID;

                $contact->createdAt = date("Y-m-d H:i:s");

                if ($contact->save() === false) {
                    $errors = array();
                    $messages = $contact->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback("Contacts create" . json_encode($errors));
                }
                $dbTransaction->commit();
                $res->sendMessage($workMobile, "Dear " . $fullName . ", welcome to Envirofit. For any questions or comments call 0800722700 ");

                return $res->success("Success ", $contact);
            } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
                $message = $e->getMessage();
                return $res->dataError('Contacts create', $message);
            }
        }
    }

    /*
     clean contacts data from the old crm to make it meaningful
    */

    public function reconcile() {
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);
        $res = new SystemResponses();

        $limit = 50;
        $batchSize = 1;

        try {

            $contactsRequest = $res->rawSelect("select COUNT(contactsID) AS contactsCount from contacts where workMobile !=0");
            $contactsCount = $contactsRequest[0]['contactsCount'];
            $logger->log("Contacts Count: " . $contactsCount);

            if ($contactsCount <= $limit) {
                $batchSize = 1;
            } else {
                $batchSize = (int) ($contactsCount / $limit) + 1;
            }

            for ($count = 0; $count < $batchSize; $count++) {
                $page = $count + 1;
                $offset = (int) ($page - 1) * $limit;
                $contacts = $this->rawSelect("SELECT contactsID,workMobile FROM contacts WHERE workMobile !=0 LIMIT $offset,$limit");
                $toDelete = [];

                //$logger->log("Batch NO: " . $page);

                foreach ($contacts as $contact) {
                    //$logger->log("Customer Transaction: " . json_encode($transaction));
                    $workMobile = $contact['workMobile'];

                    $duplicates = Contacts::find(array("workMobile=:id: ",
                                'bind' => array("id" => $workMobile)));
                    $first = $duplicates[0];

                    foreach ($duplicates as $duplicate) {

                        $contactsID = $duplicate->contactsID;

                        if ($contactsID != $first->contactsID) {
                            //Get Customers
                            $customers = Customer::find(array("contactsID=:id: ",
                                        'bind' => array("id" => $contactsID)));

                            foreach ($customers as $customer) {
                                $customer->contactsID = $first->contactsID;
                                if ($customer->save() == FALSE) {
                                    $errors = array();
                                    $messages = $customer->getMessages();
                                    foreach ($messages as $message) {
                                        $e["message"] = $message->getMessage();
                                        $e["field"] = $message->getField();
                                        $errors[] = $e;
                                    }
                                    $logger->log("Could NOT save Customer contactsID: " . json_encode($errors));
                                }
                            }

                            //Get Prospects
                            $prospects = Prospects::find(array("contactsID=:id: ",
                                        'bind' => array("id" => $contactsID)));

                            foreach ($prospects as $prospect) {
                                $prospect->contactsID = $first->contactsID;
                                if ($prospect->save() == FALSE) {
                                    $errors = array();
                                    $messages = $prospect->getMessages();
                                    foreach ($messages as $message) {
                                        $e["message"] = $message->getMessage();
                                        $e["field"] = $message->getField();
                                        $errors[] = $e;
                                    }
                                    $logger->log("Could NOT save Prospect contactsID: " . json_encode($errors));
                                }
                            }

                            //Get Users
                            $users = Users::find(array("contactID=:id: ",
                                        'bind' => array("id" => $contactsID)));

                            foreach ($users as $user) {
                                $user->contactID = $first->contactsID;
                                if ($user->save() == FALSE) {
                                    $errors = array();
                                    $messages = $user->getMessages();
                                    foreach ($messages as $message) {
                                        $e["message"] = $message->getMessage();
                                        $e["field"] = $message->getField();
                                        $errors[] = $e;
                                    }
                                    $logger->log("Could NOT save User contactsID: " . json_encode($errors));
                                }
                            }

                            //Get Sales
                            $sales = Sales::find(array("contactsID=:id: ",
                                        'bind' => array("id" => $contactsID)));

                            foreach ($sales as $sale) {
                                $sale->contactsID = $first->contactsID;
                                if ($sale->save() == FALSE) {
                                    $errors = array();
                                    $messages = $sale->getMessages();
                                    foreach ($messages as $message) {
                                        $e["message"] = $message->getMessage();
                                        $e["field"] = $message->getField();
                                        $errors[] = $e;
                                    }
                                    $logger->log("Could NOT save Sale contactsID: " . json_encode($errors));
                                }
                            }


                            //Get transactions
                            $transactions = CustomerTransaction::find(array("contactsID=:id: ",
                                        'bind' => array("id" => $contactsID)));

                            foreach ($transactions as $transaction) {
                                $transaction->contactsID = $first->contactsID;
                                if ($transaction->save() == FALSE) {
                                    $errors = array();
                                    $messages = $transaction->getMessages();
                                    foreach ($messages as $message) {
                                        $e["message"] = $message->getMessage();
                                        $e["field"] = $message->getField();
                                        $errors[] = $e;
                                    }
                                    $logger->log("Could NOT save transaction contactsID: " . json_encode($errors));
                                }
                            }

                            $toDelete[] = $contactsID;
                        }
                    }

                    $logger->log("MAIN CONTACT: " . json_encode($first));
                }

                foreach ($toDelete as $itemID) {
                    $item = Contacts::findFirst(array("contactsID=:id: ",
                                'bind' => array("id" => $itemID)));

                    if ($item) {
                        if ($item->delete() == FALSE) {
                            $errors = array();
                            $messages = $item->getMessages();
                            foreach ($messages as $message) {
                                $e["message"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                $errors[] = $e;
                            }
                            $logger->log("Could NOT DELETE DUPLICATE CONTACT: " . json_encode($errors));
                        } else {
                            $logger->log("DUPLICATE CONTACT DELETED: " . json_encode($item));
                        }
                    }
                }
            }
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('transaction update error', $message);
        }
    }

    public function contactSales(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $contactsID = $request->getQuery('contactsID');

        if (!$token && !$contactsID) {
            return $res->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("search Data compromised");
        }
        $getContactsSalesQuery = "SELECT *  FROM sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID join sales_type st on pp.salesTypeID=st.salesTypeID WHERE s.status >= 0 AND s.status < 2 AND contactsID=$contactsID ";
        $totalDepositQuery = "SELECT SUM(replace(t.depositAmount,',','')) as totalAmount FROM customer_transaction ct join transaction t ON ct.transactionID=t.transactionID  where ct.contactsID=$contactsID";
        $contactSales = $this->rawSelect($getContactsSalesQuery);
        $totalDeposit = $this->rawSelect($totalDepositQuery);

        $totalDeposit = $totalDeposit[0]["totalAmount"];
        $tobePaid = $totalDeposit;
        $totalSalesAmount = 0;

        $data = array();
        $productIDs = array();

        
        foreach ($contactSales as $sale) {
                $amount = $sale['amount'];
                $status = $sale['status'];
                $salesID = $sale['salesID'];
                $productID = $sale['productID'];
                $totalSalesAmount = $totalSalesAmount + $amount;
                $tobePaid = $tobePaid - $amount;
                array_push($productIDs, $productID);

            }


    if($tobePaid < 0 && isset($productIDs)){
        //cannot sell to this client, ask them to pay 
        //return $res->success("Success ", $tobePaid);
        $data['canSell'] = false;
        $data['pending'] = $tobePaid;
        $data['products'] = $productIDs;
        $data['sales']=$contactSales;
    }

    else if($tobePaid < 0 && !isset($productIDs) ){
        //can sell to this client
        //return $res->success("Success ", $tobePaid);
        $data['canSell'] = true;
        $data['pending'] = $tobePaid;
        $data['products'] = $productIDs;
        $data['sales']=$contactSales;

    }
    else if($tobePaid == 0 ){
        //can sell to this client
        //return $res->success("Success ", $tobePaid);
        $data['canSell'] = true;
        $data['pending'] = $tobePaid;
        $data['products'] = $productIDs;
        $data['sales']=$contactSales;

    }
    else if($tobePaid > 0 ){
        //can sell to this client
        //return $res->success("Success ", $tobePaid);
        $data['canSell'] = true;
        $data['pending'] = $tobePaid;
        $data['products'] = $productIDs;
        $data['sales']=$contactSales;
    }
    return $res->success("Success ", $data);


    }

}
