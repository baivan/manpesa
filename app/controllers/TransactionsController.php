<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

/*
All Transactions CRUD operations 
*/

class TransactionsController extends Controller {



    private $salePaid = 1; //salepaid status

    //sales types
    private $installment = "installment"; 
    private $cash = "cash";
    private $paygo = "Pay As you Go";

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
    create new transaction usually called by southwell payment gateway
    paramters:
    mobile,account,referenceNumber,amount,fullName,token
    */

    public function create() { //{mobile,account,referenceNumber,amount,fullName,token}
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
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
                $res->dataError('sale create failed', $errors);
                $dbTransaction->rollback('transaction create failed' . json_encode($errors));
                return $res->success("Payment received", TRUE);
            }

            //Determine customer

            $transactionID = $transaction->transactionID;

            $customerTransaction = new CustomerTransaction();
            $contact = NULL;
            $contactsID = NULL;

            $contactMapping = $this->rawSelect("SELECT contactsID FROM contacts "
                    . "WHERE homeMobile='$accounNumber' || homeMobile='$mobile' || "
                    . "workMobile='$accounNumber' || workMobile='$mobile' || passportNumber='$accounNumber' || "
                    . "passportNumber='$mobile' || nationalIdNumber='$accounNumber' || "
                    . "nationalIdNumber='$mobile' || fullName='$accounNumber' || "
                    . "fullName='$mobile'");

            if ($contactMapping) {
                $contact = $contactMapping;
                $contactsID = $contactMapping[0]['contactsID'];
                $customerTransaction->transactionID = $transactionID;
                $customerTransaction->contactsID = $contactsID;
                $customerTransaction->createdAt = date("Y-m-d H:i:s");
            }

            if ($contact) {
                $res->sendMessage($mobile, "Dear " . $fullName . ", your payment of KES " . $depositAmount . " has been received");

                $customer = Customer::findFirst(array("contactsID=:id: ",
                            'bind' => array("id" => $contactsID)));
                if ($customer) {
                    $customerTransaction->customerID = $customer->customerID;
                }

                $prospect = Prospects::findFirst(array("contactsID=:id: ",
                            'bind' => array("id" => $contactsID)));
                if ($prospect) {
                    $customerTransaction->prospectsID = $prospect->prospectsID;
                }

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

                //Find incomplete sales
                $depositAmount = floatval(str_replace(',', '', $depositAmount));

                $incompleteSales = Sales::find(array("contactsID=:id: AND status=:status: AND amount>:amount: ",
                            'bind' => array("id" => $contactsID, "status" => 0, "amount" => 0)));
                foreach ($incompleteSales as $incompleteSale) {
                    $amount = floatval($incompleteSale->amount);
                    $paid = floatval($incompleteSale->paid);
                    $unpaid = $amount - $paid;

                    if ($depositAmount >= $unpaid) {
                        $pay = $paid + $unpaid;
                        $depositAmount = $depositAmount - $unpaid;
                        $incompleteSale->paid = $pay;
                        $incompleteSale->status = 2;
                    } else {
                        $pay = $paid + $depositAmount;
                        $incompleteSale->paid = $pay;
                        $incompleteSale->status = 1;
                        $depositAmount = 0;
                    }

                    if ($incompleteSale->save() === false) {
                        $errors = array();
                        $messages = $incompleteSale->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                        $logger->log("Error while saving sale data: " . json_encode($errors));

                        $dbTransaction->rollback('customer transaction create failed' . json_encode($errors));
                        $res->dataError('customer transaction create failed', $messages);
                        return $res->success("Payment received", TRUE);
                    }

                    $logger->log("Amount Remaining: " . json_encode($depositAmount));

                    if ($depositAmount == 0) {
                        break;
                    }
                }
            } else {
                $unknown = new TransactionUnknown();
                $unknown->transactionID = $transactionID;
                $unknown->createdAt = date("Y-m-d H:i:s");

                $res->sendMessage($mobile, "Dear " . $fullName . ", your payment of KES " . $depositAmount . " has been received");

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

            return $res->success("payment received ", true);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Transaction create error', $message);
        }
    }

    /*
    cron job reconcile a transaction with sales and contacts
    */

    public function reconcile() {
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);
        $res = new SystemResponses();

        $limit = 500;
        $batchSize = 1;

        try {



//            $transactionsRequest = $res->rawSelect("SELECT COUNT(customerTransactionID) AS transactionCount FROM customer_transaction ");
//            $transactionCount = $transactionsRequest[0]['transactionCount'];
//            $logger->log("Transactions Count: " . json_encode($transactionCount));

            $salesRequest = $res->rawSelect("SELECT COUNT(salesID) AS salesCount FROM sales ");
            $salesCount = $salesRequest[0]['salesCount'];

            if ($salesCount <= $limit) {
                $batchSize = 1;
            } else {
                $batchSize = (int) ($salesCount / $limit) + 1;
            }

            for ($count = 0; $count < $batchSize; $count++) {
                $page = $count + 1;
                $offset = (int) ($page - 1) * $limit;
                $sales = Sales::find([
                            "limit" => $limit,
                            "offset" => $offset
                ]);

                $logger->log("Batch NO: " . $page);

                foreach ($sales as $sale) {
                    //$logger->log("Customer Transaction: " . json_encode($transaction));
                    $contactsID = $sale->contactsID;

                    //Find all sales with the contactsID

                    $contactSales = Sales::find(array("contactsID=:id: AND amount>:amount:",
                                'bind' => array("id" => $contactsID, "amount" => 0)));

                    //Find all transactions of the contactsID and sum them

                    $contactTransactions = $res->rawSelect("SELECT t.depositAmount FROM customer_transaction ct "
                            . "INNER JOIN transaction t ON ct.transactionID=t.transactionID WHERE ct.contactsID=$contactsID");

                    $contactTotalAmount = 0;

                    foreach ($contactTransactions as $contactTransaction) {
                        $amount = $contactTransaction['depositAmount'];
                        $depositAmount = floatval(str_replace(',', '', $amount));
                        $contactTotalAmount += $depositAmount;
                    }


                    foreach ($contactSales as $sale) {

                        $amount = floatval($sale->amount);
                        $paid = floatval($sale->paid);
                        $unpaid = $amount - $paid;

                        if ($contactTotalAmount > $paid) {
                            $contactTotalAmount = $contactTotalAmount - $paid;

                            if ($contactTotalAmount >= $unpaid) {
                                $pay = $paid + $unpaid;
                                $contactTotalAmount = $contactTotalAmount - $unpaid;
                                $sale->paid = $pay;
                                $sale->status = 2;
                            } else {
                                $pay = $paid + $contactTotalAmount;
                                $sale->paid = $pay;
                                $sale->status = 1;
                                $contactTotalAmount = 0;
                            }
                        } else {
                            $contactTotalAmount = 0;
                        }

                        if ($sale->save() === false) {
                            $errors = array();
                            $messages = $sale->getMessages();
                            foreach ($messages as $message) {
                                $e["message"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                $errors[] = $e;
                            }
                            $logger->log("Error while saving sale data: " . json_encode($errors));

                            //$dbTransaction->rollback('customer transaction create failed' . json_encode($errors));
                            $res->dataError('customer transaction create failed', $messages);
                            //return $res->success("Payment received", TRUE);
                        }

                        $logger->log("Customer Sale: " . json_encode($sale));

                        if ($contactTotalAmount == 0) {
                            break;
                        }
                    }
                }
            }
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('sale payments update error', $message);
        }
    }

/*
reconcile old transactions to match with customer
parameters (all required):
contactsID,
transactionID,
userID,
token
*/

    public function reconcilePayment() { //contactsID, transactionID, userID,token
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $transactionID = isset($json->transactionID) ? $json->transactionID : NULL;
        $contactsID = isset($json->contactsID) ? $json->contactsID : NULL;
        $userID = isset($json->userID) ? $json->userID : NULL;
        $token = isset($json->token) ? $json->token : NULL;

        if (!$token || !$transactionID || !$contactsID || !$userID) {
            return $res->dataError("data missing ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        try {

            $payment = CustomerTransaction::findFirst(array("transactionID=:id: AND contactsID=:contactsID: ",
                        'bind' => array("id" => $transactionID, "contactsID" => $contactsID)));

            if ($payment) {
                $logger->log("Unknown payment exists: " . json_encode($payment));
                $unknownPayment = TransactionUnknown::findFirst(array("transactionID=:id: ",
                            'bind' => array("id" => $transactionID)));

                if ($unknownPayment) {
                    $unknownPayment->status = 1;

                    if ($unknownPayment->save() === false) {
                        $errors = array();
                        $messages = $unknownPayment->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                        $dbTransaction->rollback('failed to reconcile payment' . json_encode($errors));
                        return $res->dataError('failed to reconcile payment', $messages);
                    }
                }
                $dbTransaction->commit();
                return $res->success("payment successfully reconciled", $payment);
            }

            $customerTransaction = new CustomerTransaction();
            $customerTransaction->transactionID = $transactionID;
            $customerTransaction->contactsID = $contactsID;
            $customerTransaction->status = 1;
            $customerTransaction->createdAt = date("Y-m-d H:i:s");

            $customer = Customer::findFirst(array("contactsID=:id: ",
                        'bind' => array("id" => $contactsID)));
            if ($customer) {
                $customerTransaction->customerID = $customer->customerID;
            }

            $prospect = Prospects::findFirst(array("contactsID=:id: ",
                        'bind' => array("id" => $contactsID)));
            if ($prospect) {
                $customerTransaction->prospectsID = $prospect->prospectsID;
            }

            if ($customerTransaction->save() === false) {
                $errors = array();
                $messages = $customerTransaction->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $dbTransaction->rollback('customer transaction create failed' . json_encode($errors));
                return $res->dataError('failed to reconcile payment', $messages);
            }

            $transaction = Transaction::findFirst(array("transactionID=:id: ",
                        'bind' => array("id" => $transactionID)));

            if ($transaction) {
                $depositAmount = $transaction->depositAmount;
                //Find incomplete sales
                $depositAmount = floatval(str_replace(',', '', $depositAmount));

                $incompleteSales = Sales::find(array("contactsID=:id: AND status=:status: AND amount>:amount: ",
                            'bind' => array("id" => $contactsID, "status" => 0, "amount" => 0)));
                foreach ($incompleteSales as $incompleteSale) {
                    $amount = floatval($incompleteSale->amount);
                    $paid = floatval($incompleteSale->paid);
                    $unpaid = $amount - $paid;

                    if ($depositAmount >= $unpaid) {
                        $pay = $paid + $unpaid;
                        $depositAmount = $depositAmount - $unpaid;
                        $incompleteSale->paid = $pay;
                        $incompleteSale->status = 1;
                    } else {
                        $pay = $paid + $depositAmount;
                        $incompleteSale->paid = $pay;
                        $incompleteSale->status = 1;
                        $depositAmount = 0;
                    }

                    if ($incompleteSale->save() === false) {
                        $errors = array();
                        $messages = $incompleteSale->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                        $logger->log("Error while saving sale data: " . json_encode($errors));

                        $dbTransaction->rollback('customer transaction create failed' . json_encode($errors));
                        $res->dataError('customer transaction create failed', $messages);
                        return $res->success("Payment received", TRUE);
                    }

                    $logger->log("Amount Remaining: " . json_encode($depositAmount));

                    if ($depositAmount == 0) {
                        break;
                    }
                }
            }

            $unknownPayment = TransactionUnknown::findFirst(array("transactionID=:id: ",
                        'bind' => array("id" => $transactionID)));

            if ($unknownPayment) {
                $unknownPayment->status = 1;

                if ($unknownPayment->save() === false) {
                    $errors = array();
                    $messages = $unknown->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback('failed to reconcile payment' . json_encode($errors));
                    return $res->dataError('failed to reconcile payment', $messages);
                }
            }

            $dbTransaction->commit();

            $logger->log("payment successfully reconciled: " . json_encode($customerTransaction));

            return $res->success("payment successfully reconciled ", true);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('failed to reconcile payment', $message);
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



        /* $getAmountQuery = "SELECT SUM(replace(t.depositAmount,',','')) as amount, s.amount as saleAmount, st.salesTypeDeposit,st.salesTypeName,si.saleItemID,i.serialNumber,i.status as itemStatus FROM transaction t JOIN contacts c on t.salesID=c.workMobile or t.salesID=c.nationalIdNumber JOIN customer cu on c.contactsID=cu.contactsID JOIN sales s on cu.customerID=s.customerID JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID left join sales_item si on t.salesID=si.saleID LEFT JOIN item i on si.itemID=i.itemID where s.salesID=$salesID and c.workMobile <>0"; */
        $getAmountQuery = " SELECT s.paid as amount,s.amount as saleAmount,st.salesTypeDeposit,st.salesTypeName,si.saleItemID,i.serialNumber,i.status as itemStatus from sales s JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID left join sales_item si on s.salesID=si.saleID LEFT JOIN item i on si.itemID=i.itemID where s.salesID=$salesID";

        $transaction = $this->rawSelect($getAmountQuery);

        $dataToReturn = array();

        if (strcasecmp($transaction[0]['salesTypeName'], $this->cash) == 0 || strcasecmp($transaction[0]['salesTypeName'], $this->installment) == 0) {
            $calculateAmount = $transaction[0]['amount'];

            if ($transaction[0]['amount'] >= $transaction[0]['saleAmount']) {
                $dataToReturn['amount'] = (empty($transaction[0]['amount'])) ? NULL : $transaction[0]['amount'];
            } else {
                $dataToReturn['amount'] = (empty($transaction[0]['amount'])) ? NULL : $transaction[0]['amount'] - $transaction[0]['saleAmount'];
            }

            $dataToReturn['paid'] = (empty($transaction[0]['amount'])) ? NULL : $transaction[0]['amount'];
            $dataToReturn['saleAmount'] = (empty($transaction[0]['saleAmount'])) ? NULL : $transaction[0]['saleAmount']; //$transaction[0]['saleAmount'];
            $dataToReturn['salesTypeDeposit'] = (empty($transaction[0]['salesTypeDeposit'])) ? NULL : $transaction[0]['salesTypeDeposit']; //$transaction[0]['saleAmount'];
            $dataToReturn['serialNumber'] = (empty($transaction[0]['serialNumber'])) ? NULL : $transaction[0]['serialNumber']; //$transaction[0]['serialNumber'];
            $dataToReturn['status'] = (empty($transaction[0]['status'])) ? NULL : $transaction[0]['status']; //$transaction[0]['status'];
            $dataToReturn['salesTypeName'] = (empty($transaction[0]['salesTypeName'])) ? NULL : $transaction[0]['salesTypeName']; //$transaction[0]['salesTypeName'];
            $dataToReturn['saleItemID'] = (empty($transaction[0]['saleItemID'])) ? NULL : $transaction[0]['saleItemID']; //$transaction[0]['saleItemID'];

            return $res->success("Sale paid", $dataToReturn);
        } else {
            return $res->success("Sale paid", $transaction[0]);
        }
    }

/*
check if sale is paid. Called internally within the backend 
params:
salesID (required)
*/
    public function checkSalePaid($salesID) {
        /* $transactionQuery = "SELECT SUM(replace(t.depositAmount,',','')) as amount, s.amount as saleAmount, st.salesTypeDeposit,st.salesTypeName,si.saleItemID,i.serialNumber,i.status as itemStatus from transaction t join contacts c on t.salesID=c.workMobile or t.salesID=c.nationalIdNumber join customer cu on c.contactsID=cu.contactsID join sales s on cu.customerID=s.customerID JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID join sales_type st on pp.salesTypeID=st.salesTypeID left join sales_item si on t.salesID=si.saleID left join item i on si.itemID=i.itemID where s.salesID=$salesID and c.workMobile <>0";
         */
        $transactionQuery = " SELECT s.paid as amount,s.amount as saleAmount,st.salesTypeDeposit,st.salesTypeName,si.saleItemID,i.serialNumber,i.status as itemStatus from sales s JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID left join sales_item si on s.salesID=si.saleID LEFT JOIN item i on si.itemID=i.itemID where s.salesID=$salesID";

        $transaction = $this->rawSelect($transactionQuery);

        $amountpaid = $transaction[0]["amount"];
        $amountToCompare = 0;

        if (strcasecmp($transaction[0]['salesTypeName'], $this->cash) == 0 || strcasecmp($transaction[0]['salesTypeName'], $this->installment) == 0) {
            $amountToCompare = $transaction[0]["saleAmount"];
        } elseif (strcasecmp($transaction[0]['salesTypeName'], $this->paygo) == 0) {
            $amountToCompare = $transaction[0]["salesTypeDeposit"];
        }
        if ($amountpaid >= $amountToCompare && $amountpaid > 0) {
            return true;
        } else {
            return false;
        }
    }
 /*
    retrieve  transactions to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */
    public function getTableTransactions() { //sort, order, page, limit,filter
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $salesID = $request->getQuery('salesID');
        $contactsID = $request->getQuery('contactsID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';

        $selectQuery = "SELECT ct.customerTransactionID AS transactionID, ct.contactsID, ct.customerID, ct.prospectsID, "
                . "t.nationalID,t.fullName AS depositorName,t.referenceNumber, "
                . "t.mobile, t.depositAmount, c.fullName, t.salesID AS accountNumber, t.createdAt ";

        $countQuery = "SELECT count(DISTINCT ct.customerTransactionID) as totalTransaction ";

        $baseQuery = "FROM customer_transaction ct INNER JOIN transaction t "
                . "ON ct.transactionID=t.transactionID INNER JOIN contacts c "
                . "ON ct.contactsID=c.contactsID ";


        $whereArray = [
            'filter' => $filter,
            'ct.salesID' => $salesID,
            'ct.contactsID' => $contactsID,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['t.fullName', 't.salesID', 't.mobile', 'c.fullName', 't.referenceNumber', 'c.workMobile', 'c.nationalIdNumber', 't.nationalID'];

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


        $count = $this->rawSelect($countQuery);
        $items = $this->rawSelect($selectQuery);

        $data["totalTransaction"] = $count[0]['totalTransaction'];
        $data["transactions"] = $items;
        return $res->success("Transactions get successfully ", $data);
    }

     /*
    retrieve  unknown/ unmatched transactions to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */

    public function getTableUnknownPayments() { //sort, order, page, limit,filter
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);

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

        $selectQuery = "SELECT tu.unknownTransactionID, tu.transactionID, t.referenceNumber, "
                . "t.nationalID, t.fullName AS depositorName, t.mobile, t.depositAmount, t.salesID AS accountNumber, t.createdAt ";

        $countQuery = "SELECT count(DISTINCT tu.unknownTransactionID) as totalTransaction ";

        $baseQuery = "FROM transaction_unknown tu INNER JOIN transaction t ON tu.transactionID=t.transactionID  ";


        $whereArray = [
            'filter' => $filter,
            'tu.status' => 404,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['t.fullName', 't.mobile', 't.referenceNumber', 't.nationalID'];

                $valueString = "";
                foreach ($searchColumns as $searchColumn) {
                    $valueString .= $value ? "" . $searchColumn . " REGEXP '" . $value . "' ||" : "";
                }
                $valueString = chop($valueString, " ||");
                if ($valueString) {
                    $valueString = "(" . $valueString;
                    $valueString .= ") AND ";
                }
                $whereQuery .= $valueString;
            } else if ($key == 'tu.status' && $value == 404) {
                $valueString = "" . $key . "=0" . " AND ";
                $whereQuery .= $valueString;
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(t.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
                }
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND " : "";
                $whereQuery .= $valueString;
            }
        }

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND ");
        }

        $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";

        $countQuery = $countQuery . $baseQuery . $whereQuery;
        $selectQuery = $selectQuery . $baseQuery . $whereQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

        $logger->log("TableUnknown Query: " . $selectQuery);
        //  return $res->success($countQuery);
        $count = $this->rawSelect($countQuery);
        $items = $this->rawSelect($selectQuery);

        $data["totalTransaction"] = $count[0]['totalTransaction'];
        $data["transactions"] = $items;
        return $res->success("Unknown payments get successfully ", $data);
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

    
}
