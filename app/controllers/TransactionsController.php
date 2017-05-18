<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

class TransactionsController extends Controller {

    private $salePaid = 1;

    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    public function createTransaction() { //{mobile,account,referenceNumber,amount,fullName,token}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $mobile = $json->mobile;
        $referenceNumber = $json->referenceNumber;
        $fullName = $json->fullName;
        $depositAmount = $json->amount;
        $salesID = $json->account;
        $token = $json->token;

        if (!$token) {
            return $res->dataError("Token missing ");
        }
        if (!$salesID) {
            return $res->dataError("Account missing ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        try {
            $transaction = new Transaction();
            $transaction->mobile = $mobile;
            $transaction->referenceNumber = $referenceNumber;
            $transaction->fullName = $fullName;
            $transaction->depositAmount = $depositAmount;
            $nationalID->nationalID = 0;
            $transaction->salesID = $salesID;
            $transaction->createdAt = date("Y-m-d H:i:s");

            if ($transaction->save() === false) {
                $errors = array();
                $messages = $transaction->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                //return $res->dataError('sale create failed',$errors);
                $dbTransaction->rollback('transaction create failed' . json_encode($errors));
            }

            $sale = Sales::findFirst(array("salesID=:id: ",
                        'bind' => array("id" => $salesID)));

            $saleQuery = "SELECT s.salesID FROM transaction t JOIN contacts c on t.salesID=c.nationalIdNumber or t.salesID=c.workMobile JOIN customer cu on c.contactsID=cu.contactsID JOIN sales s on cu.customerID=s.customerID where c.nationalIdNumber='%$salesID%' or c.workMobile='%$salesID%'";
            if (!$sale) {
                $mappedSale = $this->rawSelect($saleQuery);

                $salesID = $mappedSale[0]['salesID'];
                $sale = Sales::findFirst(array("salesID=:id: ",
                            'bind' => array("id" => $salesID)));
            }


            if ($sale) {
                $sale->status = $this->salePaid;
                if ($sale->save() === false) {
                    $errors = array();
                    $messages = $sale->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    //return $res->dataError('sale create failed',$errors);
                    $dbTransaction->rollback('transaction create failed' . json_encode($errors));
                }

                $userQuery = "SELECT userID as userId from sales WHERE salesID=$salesID";


                $userID = $this->rawSelect($userQuery);

                $pushNotificationData = array();
                $pushNotificationData['nationalID'] = $nationalID;
                $pushNotificationData['mobile'] = $mobile;
                $pushNotificationData['amount'] = $amount;
                $pushNotificationData['saleAmount'] = $sale->amount;
                $pushNotificationData['fullName'] = $fullName;



                $res->sendPushNotification($pushNotificationData, "New payment", "There is a new payment from a sale you made", $userID);
            }

            $res->sendMessage($mobile, "Dear " . $fullName . ", your payment has been received");
            $dbTransaction->commit();

            return $res->success("Transaction successfully done ", true);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Transaction create error', $message);
        }
    }

    public function create() { //{mobile,account,referenceNumber,amount,fullName,token}
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $mobile = isset($json->mobile) ? $json->mobile : NULL;
        $referenceNumber = isset($json->referenceNumber) ? $json->referenceNumber : NULL;
        $fullName = isset($json->fullName) ? $json->fullName : NULL;
        $depositAmount = isset($json->amount) ? $json->amount : NULL;
        $accounNumber = isset($json->account) ? $json->account : NULL;
        $nationalID = isset($json->nationalID) ? $json->nationalID : NULL;
        $token = isset($json->token) ? $json->token : NULL;

        if (!$token) {
            return $res->dataError("Token missing ");
        }
        if (!$accounNumber) {
            return $res->dataError("Account missing ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        try {
            $transaction = new Transaction();
            $transaction->mobile = $mobile;
            $transaction->referenceNumber = $referenceNumber;
            $transaction->fullName = $fullName;
            $transaction->depositAmount = $depositAmount;
            $transaction->nationalID = $nationalID;
            $transaction->salesID = $accounNumber;
            $transaction->createdAt = date("Y-m-d H:i:s");

            if ($transaction->save() === false) {
                $errors = array();
                $messages = $transaction->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                //return $res->dataError('sale create failed',$errors);
                $dbTransaction->rollback('transaction create failed' . json_encode($errors));
            }

            $transactionID = $transaction->transactionID;
            $customerTransaction = new CustomerTransaction();
            $customer = NULL;
            $customerSale = NULL;

            $customerMapping = Customer::findFirst(array("customerID=:id: ",
                        'bind' => array("id" => $accounNumber)));

            if ($customerMapping) {
                $customer = $customerMapping;
                $salesID = NULL;
                $customerSale = Sales::findFirst(array("customerID=:id: AND status=:status: ",
                            'bind' => array("id" => $customer->customerID, "status" => 0)));
                if ($customerSale) {
                    $salesID = $customerSale->salesID;
                }

                $customerTransaction->transactionID = $transactionID;
                $customerTransaction->contactsID = $customerMapping->contactsID;
                $customerTransaction->customerID = $customerMapping->customerID;
                $customerTransaction->salesID = $salesID;
                $customerTransaction->createdAt = date("Y-m-d H:i:s");
            } else {
                $saleMapping = Sales::findFirst(array("salesID=:id: ",
                            'bind' => array("id" => $accounNumber)));
                if ($saleMapping) {
                    $customer = Customer::findFirst(array("customerID=:id: ",
                                'bind' => array("id" => $saleMapping->customerID)));

                    if ($customer) {
                        $salesID = NULL;
                        $customerSale = Sales::findFirst(array("customerID=:id: AND status=:status: ",
                                    'bind' => array("id" => $customer->customerID, "status" => 0)));
                        if ($customerSale) {
                            $salesID = $customerSale->salesID;
                        }

                        $customerTransaction->transactionID = $transactionID;
                        $customerTransaction->contactsID = $customer->contactsID;
                        $customerTransaction->customerID = $customer->customerID;
                        $customerTransaction->salesID = $salesID;
                        $customerTransaction->createdAt = date("Y-m-d H:i:s");
                    } else {
                        
                    }
                } else {
                    $contactMapping = $this->rawSelect("SELECT contactsID FROM contacts "
                            . "WHERE homeMobile='$accounNumber' || homeMobile='$mobile' || "
                            . "workMobile='$accounNumber' || workMobile='$mobile' || passportNumber='$accounNumber' || "
                            . "passportNumber='$mobile' || nationalIdNumber='$accounNumber' || "
                            . "nationalIdNumber='$mobile' || fullName='$accounNumber' || "
                            . "fullName='$mobile'");
//                    $logger->log("Mapping Response: ".json_encode($contactMapping));

                    if ($contactMapping) {
                        $customer = Customer::findFirst(array("contactsID=:id: ",
                                    'bind' => array("id" => $contactMapping[0]['contactsID'])));
                        if ($customer) {
                            $customerSale = Sales::findFirst(array("customerID=:id: AND status=:status: ",
                                        'bind' => array("id" => $customer->customerID, "status" => 0)));
                            $salesID = NULL;
                            if ($customerSale) {
                                $salesID = $customerSale->salesID;
                            }
                            $customerTransaction->transactionID = $transactionID;
                            $customerTransaction->contactsID = $customer->contactsID;
                            $customerTransaction->customerID = $customer->customerID;
                            $customerTransaction->salesID = $salesID;
                            $customerTransaction->createdAt = date("Y-m-d H:i:s");
                        } else {
                            
                        }
                    } else {
                        
                    }
                }
            }

            if ($customer) {
                if ($customerTransaction->save() === false) {
                    $errors = array();
                    $messages = $customerTransaction->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback('customer transaction create failed' . json_encode($errors));
                    $res->dataError('customer transaction create failed', $messages);
                    return $res->success("Payment received", TRUE);
                }

                $userID = $customerSale->userID;

                $pushNotificationData = array();
                $pushNotificationData['nationalID'] = $nationalID;
                $pushNotificationData['mobile'] = $mobile;
                $pushNotificationData['amount'] = $depositAmount;
                $pushNotificationData['saleAmount'] = $customerSale->amount;
                $pushNotificationData['fullName'] = $fullName;

                $res->sendPushNotification($pushNotificationData, "New payment", "There is a new payment from a sale you made", $userID);

                $res->sendMessage($mobile, "Dear " . $fullName . ", your payment of KES " . $amount . " has been received");
            } else {
                $unknown = new TransactionUnknown();
                $unknown->transactionID = $transactionID;
                $unknown->createdAt = date("Y-m-d H:i:s");

                $res->sendMessage($mobile, "Dear " . $fullName . ", your payment of KES " . $amount . " has been received");

                if ($unknown->save() === false) {
                    $errors = array();
                    $messages = $unknown->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback('customer transaction create failed' . json_encode($errors));
                    $res->dataError('customer transaction create failed', $messages);
                    return $res->success("Payment received", TRUE);
                }
            }

            $dbTransaction->commit();

            return $res->success("Transaction successfully done ", true);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Transaction create error', $message);
        }
    }

    public function reconcile() { //{mobile,account,referenceNumber,amount,fullName,token}
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        try {

            $limit = 50;
            $batchSize = 1;

            $transactionsData = Transaction::find();
            $transactionCount = $transactionsData->count();

            if ($transactionCount <= $limit) {
                $batchSize = 1;
            } else {
                $batchSize = (int) ($transactionCount / $limit) + 1;
            }

            for ($count = 0; $count < $batchSize; $count++) {
                
                $page = $count + 1;
//                $logger->log("Batch Number: " . $page);

                $offset = (int) ($page - 1) * $limit;

                $transactions = $this->rawSelect("SELECT * FROM transaction LIMIT $offset,$limit");

                foreach ($transactions as $transaction) {
                    $accounNumber = $transaction['salesID'];
                    $mobile = $transaction['mobile'];
                    $transactionID = $transaction['transactionID'];

                    $customerTransaction = CustomerTransaction::findFirst(array("transactionID=:id: ",
                                'bind' => array("id" => $transactionID)));

                    if (!$customerTransaction) {

                        $customerTransaction = new CustomerTransaction();
                        $customer = NULL;
                        $customerSale = NULL;

                        $customerMapping = Customer::findFirst(array("customerID=:id: ",
                                    'bind' => array("id" => $accounNumber)));

                        if ($customerMapping) {
//                        $logger->log("Customer Mapping: " . json_encode($customerMapping));

                            $customer = $customerMapping;
                            $salesID = NULL;
                            $customerSale = Sales::findFirst(array("customerID=:id: AND status=:status: ",
                                        'bind' => array("id" => $customer->customerID, "status" => 0)));
                            if ($customerSale) {
                                $salesID = $customerSale->salesID;
                            }

                            $customerTransaction->transactionID = $transactionID;
                            $customerTransaction->contactsID = $customerMapping->contactsID;
                            $customerTransaction->customerID = $customerMapping->customerID;
                            $customerTransaction->salesID = $salesID;
                            $customerTransaction->createdAt = date("Y-m-d H:i:s");
                        } else {
                            $saleMapping = Sales::findFirst(array("salesID=:id: ",
                                        'bind' => array("id" => $accounNumber)));
                            if ($saleMapping) {
//                            $logger->log("Sale Mapping: " . json_encode($saleMapping));
                                $customer = Customer::findFirst(array("customerID=:id: ",
                                            'bind' => array("id" => $saleMapping->customerID)));

                                if ($customer) {
                                    $salesID = NULL;
                                    $customerSale = Sales::findFirst(array("customerID=:id: AND status=:status: ",
                                                'bind' => array("id" => $customer->customerID, "status" => 0)));
                                    if ($customerSale) {
                                        $salesID = $customerSale->salesID;
                                    }

                                    $customerTransaction->transactionID = $transactionID;
                                    $customerTransaction->contactsID = $customer->contactsID;
                                    $customerTransaction->customerID = $customer->customerID;
                                    $customerTransaction->salesID = $salesID;
                                    $customerTransaction->createdAt = date("Y-m-d H:i:s");
                                } else {
                                    
                                }
                            } else {
                                $contactMapping = $this->rawSelect("SELECT contactsID FROM contacts "
                                        . "WHERE homeMobile='$accounNumber' || homeMobile='$mobile' || "
                                        . "workMobile='$accounNumber' || workMobile='$mobile' || passportNumber='$accounNumber' || "
                                        . "passportNumber='$mobile' || nationalIdNumber='$accounNumber' || "
                                        . "nationalIdNumber='$mobile' || fullName='$accounNumber' || "
                                        . "fullName='$mobile'");

                                if ($contactMapping) {
//                                $logger->log("Contact Mapping: " . json_encode($contactMapping));
                                    $customer = Customer::findFirst(array("contactsID=:id: ",
                                                'bind' => array("id" => $contactMapping[0]['contactsID'])));
                                    if ($customer) {
                                        $customerSale = Sales::findFirst(array("customerID=:id: AND status=:status: ",
                                                    'bind' => array("id" => $customer->customerID, "status" => 0)));
                                        $salesID = NULL;
                                        if ($customerSale) {
                                            $salesID = $customerSale->salesID;
                                        }
                                        $customerTransaction->transactionID = $transactionID;
                                        $customerTransaction->contactsID = $customer->contactsID;
                                        $customerTransaction->customerID = $customer->customerID;
                                        $customerTransaction->salesID = $salesID;
                                        $customerTransaction->createdAt = date("Y-m-d H:i:s");
                                    } else {
                                        
                                    }
                                } else {
                                    
                                }
                            }
                        }

                        if ($customer) {
//                            $logger->log("Saving valid transaction: " . json_encode($transaction));

                            if ($customerTransaction->save() === false) {
                                $errors = array();
                                $messages = $customerTransaction->getMessages();
                                foreach ($messages as $message) {
                                    $e["message"] = $message->getMessage();
                                    $e["field"] = $message->getField();
                                    $errors[] = $e;
                                }
                                $dbTransaction->rollback('customer transaction create failed' . json_encode($errors));
                                $res->dataError('customer transaction create failed', $messages);
                            }
                        } else {

                            $unknownPayment = TransactionUnknown::findFirst(array("transactionID=:id: ",
                                        'bind' => array("id" => $transactionID)));

                            if (!$unknownPayment) {
//                                $logger->log("Saving unknown payment: " . json_encode($transaction));

                                $unknown = new TransactionUnknown();
                                $unknown->transactionID = $transactionID;
                                $unknown->createdAt = date("Y-m-d H:i:s");

                                if ($unknown->save() === false) {
                                    $errors = array();
                                    $messages = $unknown->getMessages();
                                    foreach ($messages as $message) {
                                        $e["message"] = $message->getMessage();
                                        $e["field"] = $message->getField();
                                        $errors[] = $e;
                                    }
                                    $dbTransaction->rollback('customer transaction create failed' . json_encode($errors));
                                    $res->dataError('customer transaction create failed', $messages);
                                }
                            } else {
//                                $logger->log("Unknown Payment already exists: " . json_encode($unknownPayment));
                            }
                        }
                    }else{
//                        $logger->log("Valid Transaction already exists: " . json_encode($customerTransaction));
                    }
                }
            }

            $dbTransaction->commit();
            return $res->success("Transaction successfully done ", true);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Transaction create error', $message);
        }
    }

    public function checkPayment() {//{token,salesID}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $token = $json->token;
        //  $userID = $json->userID;
        $salesID = $json->salesID;


        $getAmountQuery = "SELECT SUM(replace(t.depositAmount,',','')) as amount, s.amount as saleAmount, st.salesTypeDeposit,si.saleItemID,i.serialNumber,i.status as itemStatus from transaction t join contacts c on t.salesID=c.workMobile or t.salesID=c.nationalIdNumber join customer cu on c.contactsID=cu.contactsID join sales s on cu.customerID=s.customerID JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID join sales_type st on pp.salesTypeID=st.salesTypeID left join sales_item si on t.salesID=si.saleID left join item i on si.itemID=i.itemID where s.salesID=$salesID ";

        $transaction = $this->rawSelect($getAmountQuery);

        if ($transaction[0]['amount'] <= 0) {
            $getAmountQuery = "SELECT SUM(replace(t.depositAmount,',','')) as amount, s.amount as saleAmount, st.salesTypeDeposit,si.saleItemID,i.serialNumber,i.status as itemStatus FROM transaction t join sales s on t.salesID=s.salesID  JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID join sales_type st on pp.salesTypeID=st.salesTypeID left join sales_item si on t.salesID=si.saleID left join item i on si.itemID=i.itemID WHERE t.salesID=$salesID ";
        }

        $transaction = $this->rawSelect($getAmountQuery);


        return $res->success("Sale paid", $transaction[0]);
    }

    public function checkSalePaid($salesID) {
        $transactionQuery = "SELECT SUM(replace(t.depositAmount,',','')) as amount, s.amount as saleAmount, st.salesTypeDeposit,si.saleItemID,i.serialNumber,i.status as itemStatus from transaction t join contacts c on t.salesID=c.workMobile or t.salesID=c.nationalIdNumber join customer cu on c.contactsID=cu.contactsID join sales s on cu.customerID=s.customerID JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID join sales_type st on pp.salesTypeID=st.salesTypeID left join sales_item si on t.salesID=si.saleID left join item i on si.itemID=i.itemID where s.salesID=$salesID ";

        $transaction = $this->rawSelect($transactionQuery);
        if ($transaction[0]["amount"] >= $transaction[0]["saleAmount"] || $transaction[0]["amount"] >= $transaction[0]["salesTypeDeposit"]) {

            return true;
        } else {
            return false;
        }
    }

    public function getTableTransactions() { //sort, order, page, limit,filter
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $salesID = $request->getQuery('salesID');
        $customerID = $request->getQuery('customerID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';

        $selectQuery = "SELECT t.fullName as depositorName,t.referenceNumber,t.depositAmount, t.mobile, "
                . "s.salesID,s.paymentPlanID,s.customerID,co.fullName as customerName, "
                . "s.amount,st.salesTypeName,st.salesTypeDeposit,t.createdAt ";

        $countQuery = "SELECT count(DISTINCT t.transactionID) as totalTransaction ";

        /* $baseQuery = " FROM transaction t LEFT JOIN sales s on t.salesID=s.salesID LEFT JOIN customer cu ON s.customerID=cu.customerID LEFT JOIN contacts co on cu.contactsID=co.contactsID LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID LEFT JOIN sales_type st on st.salesTypeID=pp.salesTypeID ";
         */

        $baseQuery = "FROM transaction t LEFT JOIN contacts co ON t.salesID=co.workMobile OR t.salesID=co.nationalIdNumber "
                . "LEFT JOIN customer cu ON co.contactsID=cu.contactsID "
                . "LEFT JOIN sales s ON cu.customerID=s.customerID "
                . "LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID "
                . "LEFT JOIN sales_type st on st.salesTypeID=pp.salesTypeID  ";


        $whereArray = [
            'filter' => $filter,
            's.salesID' => $salesID,
            'cu.customerID' => $customerID,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['t.fullName', 't.mobile', 'co.fullName', 't.referenceNumber', 'st.salesTypeName'];

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
                    $valueString = " DATE(t.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
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

        $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";

        $countQuery = $countQuery . $baseQuery . $whereQuery;
        $selectQuery = $selectQuery . $baseQuery . $whereQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;


        //  return $res->success($countQuery);
        $count = $this->rawSelect($countQuery);
        $items = $this->rawSelect($selectQuery);

        $data["totalTransaction"] = $count[0]['totalTransaction'];
        $data["transactions"] = $items;
        return $res->success("Transactions get successfully ", $data);
    }

    public function tableQueryBuilder($sort = "", $order = "", $page = 0, $limit = 10) {

        $sortClause = "group By transactionID ORDER BY $sort $order";

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

      //dummy transactions
      public function dummyTransaction() { //{mobile,account,referenceNumber,amount,fullName,token}
      $jwtManager = new JwtManager();
      $request = new Request();
      $res = new SystemResponses();
      $transactionManager = new TransactionManager();
      $dbTransaction = $transactionManager->get();

      $mobile = $request->getQuery("mobile");
      $referenceNumber = $request->getQuery("referenceNumber");
      $fullName = $request->getQuery("fullName");
      $depositAmount = $request->getQuery("amount");
      $salesID = $request->getQuery("account");
      $token = $request->getQuery("token");

      if (!$token) {
      return $res->dataError("Token missing ");
      }
      if (!$salesID) {
      return $res->dataError("Account missing ");
      }

      $tokenData = $jwtManager->verifyToken($token, 'openRequest');

      if (!$tokenData) {
      return $res->dataError("Data compromised");
      }

      try {
      $transaction = new Transaction();
      $transaction->mobile = $mobile;
      $transaction->referenceNumber = $referenceNumber;
      $transaction->fullName = $fullName;
      $transaction->depositAmount = $depositAmount;
      $nationalID->nationalID = 0;
      $transaction->salesID = $salesID;
      $transaction->createdAt = date("Y-m-d H:i:s");

      if ($transaction->save() === false) {
      $errors = array();
      $messages = $transaction->getMessages();
      foreach ($messages as $message) {
      $e["message"] = $message->getMessage();
      $e["field"] = $message->getField();
      $errors[] = $e;
      }
      //return $res->dataError('sale create failed',$errors);
      $dbTransaction->rollback('transaction create failed' . json_encode($errors));
      }
      $sale = Sales::findFirst(array("salesID=:id: ",
      'bind' => array("id" => $salesID)));



      $sale->status = $this->salePaid;

      if ($sale->save() === false) {
      $errors = array();
      $messages = $sale->getMessages();
      foreach ($messages as $message) {
      $e["message"] = $message->getMessage();
      $e["field"] = $message->getField();
      $errors[] = $e;
      }
      //return $res->dataError('sale create failed',$errors);
      $dbTransaction->rollback('transaction create failed' . json_encode($errors));
      }
      $dbTransaction->commit();



      $userQuery = "SELECT userID as userId from sales WHERE salesID=$salesID";



      $userID = $this->rawSelect($userQuery);
      $pushNotificationData = array();
      $pushNotificationData['nationalID'] = $nationalID;
      $pushNotificationData['mobile'] = $mobile;
      $pushNotificationData['amount'] = $amount;
      $pushNotificationData['saleAmount'] = $sale->amount;
      $pushNotificationData['fullName'] = $fullName;

      $res->sendPushNotification($pushNotificationData, "New payment", "There is a new payment from a sale you made", $userID);
      $res->sendMessage($mobile, "Dear " . $fullName . ", your payment has been received");

      return $res->success("Transaction successfully done ", true);
      } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
      $message = $e->getMessage();
      return $res->dataError('Transaction create error', $message);
      }
      }

     */
}
