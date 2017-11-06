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
        $paymentTypeID = 1; //normal payment type


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
                
            }

            //Determine customer

            $transactionID = $transaction->transactionID;
            $contactsID=0;
            $isRefil = false;

            if(strpos($accounNumber, '.') !== false){
                
                
                $messageArray = explode('.', $accounNumber); 
                $prefix = $messageArray[0];
                $account = $messageArray[1];

                 $res->success("$prefix is gas payment $account $accounNumber");

                if(strcasecmp($prefix,"P")==0){ //paygo repayment
                   // $contactsID = $this->processGasPayments($transactionID,$account);
                    $contactsID = $this->mapTransactionContact($account,$mobile);
                    $paymentTypeID = 2;
                }
                else if(strcasecmp($prefix,"R")==0){
                    //this is a refil transaction
                    //get this item and its owner 
                    $customerData = $this->rawSelect("SELECT c.contactsID,c.fullName,c.nationalIdNumber,c.workMobile  FROM user_items ui join contacts c on ui.contactsID=c.contactsID join item i on ui.itemID=i.itemID where i.serialNumber='$account' ");
                    $contactsID = $customerData[0]['contactsID'];
                    $isRefil = true;
                    $paymentTypeID = 3;
                }
                
                 //lpg pyment type
            }else{
                
                 $contactsID = $this->mapTransactionContact($accounNumber,$mobile);
            }

           

            if ($contactsID) {
                 $customerTransaction = new CustomerTransaction();
                 $customerTransaction->transactionID = $transactionID;
                 $customerTransaction->contactsID = $contactsID;
                 $customerTransaction->paymentTypeID = $paymentTypeID;
                 $customerTransaction->createdAt = date("Y-m-d H:i:s");

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
                    $dbTransaction->rollback('Customer transaction create failed' . json_encode($errors));
                    $res->dataError('Customer transaction create failed', $messages);
                   // return $res->success("Payment received", TRUE);
                }

                //Find incomplete sales
                $depositAmount = floatval(str_replace(',', '', $depositAmount));
                if($depositAmount > 0){
                   // if(strtolower(strpos($accounNumber,"group"))===0 ){
                    if(substr($accounNumber, 0, strlen('group')) === 'group' ){
                        $remainingAmount = $this->distributeGroupTransaction($accounNumber,$depositAmount,$dbTransaction);
                    }
                    elseif($isRefil){
                        //send this payment data to lpg system
                        $res->sendPayment($customerData,$account,$depositAmount,$referenceNumber);

                    }
                    else{
                        $remainingAmount = $this->distributePaymentToSale($contactsID,$depositAmount,$dbTransaction);
                    }
                }
                
            } else {
                $unknown = TransactionUnknown::findFirst("transactionID = $transactionID ");
                if(!$unknown){
                    $unknown = new TransactionUnknown();
                    $unknown->transactionID = $transactionID;
                    $unknown->paymentTypeID = $paymentTypeID;
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
                    }

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
    process gas payments 
    */

    private function processGasPayments($accountNumber){


    }

    /*
    map customer to mobile number or id
    */
    private function mapTransactionContact($nationalID,$mobileNumber){
        $res = new SystemResponses();

        $matchBothQuery = "SELECT contactsID FROM contacts WHERE nationalIdNumber='".$nationalID."' and workMobile='".$mobileNumber."'";
        $matchNationalIDQuery = "SELECT contactsID FROM contacts WHERE nationalIdNumber='".$nationalID."' ";
        $matchMobileQuery = "SELECT contactsID FROM contacts WHERE workMobile='".$mobileNumber ."'";
       
        if(substr($nationalID, 0, strlen('group')) === 'group' ){
            $contact = $this->rawSelect($matchMobileQuery);
            return $contact[0]['contactsID'];
        }
        
        $contact = $this->rawSelect($matchBothQuery);
        
        if($contact){
            return $contact[0]['contactsID'];
        }

        $contact = $this->rawSelect($matchNationalIDQuery);
        if($contact){
            return $contact[0]['contactsID'];
        }
        $contact = $this->rawSelect($matchMobileQuery);
        if($contact){
            return $contact[0]['contactsID'];
        }

        return false;

    }

    /*distibute payment to incomplete sales*/
    private function distributePaymentToSale($contactsID,$depositAmount){
        $res = new SystemResponses();
         $allUserSalesQuery="SELECT * FROM sales WHERE contactsID=$contactsID and status>=0 ";

         $userSales = $this->rawSelect($allUserSalesQuery);
         $depositAmount = $depositAmount;

         foreach ($userSales as $sale) {
             $salesID= $sale['salesID'];
             $paidAmount = $sale['paid'];

             $saleAmount = empty($sale['amount']) ? 4950 : $sale['amount'];

             $res->dataError('Update sale paid', $saleAmount);

             $o_sale = Sales::findFirst("salesID = $salesID");
             $o_sale->amount = $saleAmount;

             if($paidAmount>=$saleAmount){
                if($o_sale->status <= 1){
                    $o_sale->status=2;
                }
                
             }
             else if($depositAmount <=0){
                break;
             }
             else{
                 $balance = $saleAmount - $paidAmount;
                 if($balance == $depositAmount){
                    $o_sale->paid = $o_sale->paid + $balance;
                    $o_sale->status =2;
                    $depositAmount = $depositAmount - $balance;
                 }
                 else if($balance > $depositAmount){
                    $o_sale->paid = $o_sale->paid + $depositAmount;
                    $o_sale->status =1;
                    $depositAmount = $depositAmount - $depositAmount;
                 }
                 else if($balance < $depositAmount){
                    $depositAmount = $depositAmount - $balance;
                    $o_sale->paid = $o_sale->paid + $balance;
                    $o_sale->status =2;
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
                 //$dbTransaction->rollback('Failed to reconcile payment' . json_encode($errors));
                 $res->dataError('Update sale paid', $messages);
            }

         }

         return true;
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

            $salesRequest = $this->rawSelect("SELECT COUNT(salesID) AS salesCount FROM sales ");
            $salesCount = $salesRequest[0]['salesCount'];

//            return $res->success('reconcilliation finished', []);

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

                    $contactsID = $sale->contactsID;

                    if ($contactsID) {
                        //Find all sales with the contactsID

                        $contactSales = Sales::find(array("contactsID=:id: AND amount>:amount:",
                                    'bind' => array("id" => $contactsID, "amount" => 0)));


                        //Find all transactions of the contactsID and sum them

                        $contactTransactions = $this->rawSelect("SELECT t.depositAmount FROM customer_transaction ct "
                                . "INNER JOIN transaction t ON ct.transactionID=t.transactionID WHERE ct.contactsID=$contactsID");

                        $logger->log("Sale Contact Transactions:  " . json_encode($contactTransactions));

                        $contactTotalAmount = 0;

                        foreach ($contactTransactions as $contactTransaction) {
                            $amount = $contactTransaction['depositAmount'];
                            $depositAmount = floatval(str_replace(',', '', $amount));
                            $contactTotalAmount += $depositAmount;
                        }

                        $logger->log("Contact Total deposit Amount:  " . json_encode($contactTotalAmount));


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

                            $logger->log("Customer Sale UPDATED: " . json_encode($sale));

                            if ($contactTotalAmount == 0) {
                                break;
                            }
                        }
                    } else {
                        $logger->log("Sale does NOT have contactsID:  " . json_encode($sale));
                    }

                    $logger->log("========================== COMPLETE ========================");
                }
            }
            return $res->success('reconcilliation finished', []);
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

    //check group sale payment
    public function checkGroupPayment(){//{token,groupID}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $token = $json->token;
        $groupID = $json->groupID;

        $group = Group::findFirst(array("groupID=:id: ",
                    'bind' => array("id" => $groupID)));
        //all sales of this group
         $groupSales = $this->rawSelect("SELECT * FROM sales WHERE groupID=$groupID AND status>=0 ");


    }

    public function checkPayment() {//{token,salesID}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $activityLog= new ActivityLogsController();        
        $dbTransaction = $transactionManager->get();
        $token = $json->token;
        $salesID = $json->salesID;
        $groupID = $json->groupID;
        $userID = $json->userID;
        $latitude = $json->latitude;
        $longitude = $json->longitude;
        $activityLog->create($userID,"Check payment ",$longitude,$latitude);

        
        $getAmountQuery = " SELECT s.paid as amount,s.amount as saleAmount,st.salesTypeDeposit,st.salesTypeName,si.saleItemID,i.serialNumber,i.status as itemStatus from sales s JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID left join sales_item si on s.salesID=si.saleID LEFT JOIN item i on si.itemID=i.itemID where s.salesID=$salesID";

        if($groupID > 0){
           $getAmountQuery = "SELECT s.paid as amount,s.amount as saleAmount,st.salesTypeDeposit,st.salesTypeName,si.saleItemID,i.serialNumber,i.status as itemStatus,sg.groupToken,sg.status from sales s JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID JOIN group_sale sg on s.groupID=sg.groupID left join sales_item si on s.salesID=si.saleID LEFT JOIN item i on si.itemID=i.itemID where s.groupID=7 and sg.status=2";
        }

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
        $isExport = $request->getQuery('isExport') ? $request->getQuery('isExport') : '';

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
        $exportQuery = $selectQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;


        $count = $this->rawSelect($countQuery);
        $items = $this->rawSelect($selectQuery);

        if($isExport){
             $exportTransactions = $this->rawSelect($exportQuery);
            $data["totalTransaction"] = $count[0]['totalTransaction'];
            $data["transactions"] = $items;
            $data['exportTransactions'] = $exportTransactions;

        }
        else{
            $data["totalTransaction"] = $count[0]['totalTransaction'];
            $data["transactions"] = $items;
            $data['exportTransactions'] = "no data";
        }
       
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
        $isExport = $request->getQuery('isExport') ? $request->getQuery('isExport') : '';


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
        $exportQuery = $selectQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

        $logger->log("TableUnknown Query: " . $selectQuery);
        //  return $res->success($countQuery);
        $count = $this->rawSelect($countQuery);
        $items = $this->rawSelect($selectQuery);

        if($isExport){
            $exportTransactions = $this->rawSelect($exportQuery);
            $data["totalTransaction"] = $count[0]['totalTransaction'];
            $data["transactions"] = $items;
            $data["exportTransactions"] = $exportTransactions;
        }

        else{

            $data["totalTransaction"] = $count[0]['totalTransaction'];
            $data["transactions"] = $items;
            $data["exportTransactions"] = "no data";
        }
        
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

    /*
      create new dummy transaction usually called by southwell payment gateway
      paramters:
      mobile,account,referenceNumber,amount,fullName,token
     */

    public function createDummy() { //{mobile,account,referenceNumber,amount,fullName,token}
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

      
        $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJsb2NhbGhvc3QiLCJpYXQiOjE0ODM5NTYwNDYsImFwcCI6ImphdmEzNjAiLCJvd25lciI6ImFub255bW91cyIsImFjdGlvbiI6Im9wZW5SZXF1ZXN0In0.eLHZjnFduufVspUz7E2QfTzKFfPqNWYBoENJbmIeZtA";//$request->getQuery('token');
        $mobile = $request->getQuery('mobile');
        $depositAmount = $request->getQuery('amount');
        $accounNumber = $request->getQuery('account');


        $code = rand(9999, 99999);
        $referenceNumber = "TRATEST$code";
        $fullName = "TRATEST PAYE $code";


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
                //return $res->success("Payment received", TRUE);
            }

            //Determine customer

            $transactionID = $transaction->transactionID;

            

            $contactsID = $this->mapTransactionContact($accounNumber,$mobile);
          //  return $res->success("payment received ", $contactsID);
          //  $customerTransaction = new CustomerTransaction();
            if ($contactsID) {
                 $customerTransaction = new CustomerTransaction();
                 $customerTransaction->transactionID = $transactionID;
                 $customerTransaction->contactsID = $contactsID;
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
                    $res->dataError('customer transaction create failed', $messages);
                   // return $res->success("Payment received", TRUE);
                }

                //Find incomplete sales
                $depositAmount = floatval(str_replace(',', '', $depositAmount));
                if($depositAmount > 0){
                    if(substr($accounNumber, 0, strlen('group')) === 'group' ){
                        $remainingAmount = $this->distributeGroupTransaction($accounNumber,$depositAmount,$dbTransaction);
                    }
                    else{
                        $remainingAmount = $this->distributePaymentToSale($contactsID,$depositAmount,$dbTransaction);
                    }
                }
               
            } else {
                $unknown = TransactionUnknown::findFirst("transactionID = $transactionID ");
                if(!$unknown){
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
                    }

                }
            }

            $dbTransaction->commit();

            $latestSale = Sales::findFirst("contactsID = $contactsID AND status > 0 ");
            $users = array();
            $userId['userId'] = $latestSale->userID;
            array_push($users, $userId);

            $customerMessage;
            if($latestSale->amount > $latestSale->paid){
                $customerMessage = "Dear " . $fullName . ", your payment of Ksh " . $depositAmount . " has been received. Balance due Ksh ".($latestSale->amount - $latestSale->paid);
            }
            elseif($latestSale->amount <= $latestSale->paid){
                $customerMessage = "Dear " . $fullName . ", your payment of Ksh " . $depositAmount . " has been received. Your sale has been paid in full.";
            }

            $pushNotificationData = array();
            $pushNotificationData['title'] = "New Payment";
            $pushNotificationData['body'] = $fullName." has made payment of ".$depositAmount;


            $res->sendMessage($mobile, $customerMessage);

            $res->sendPushNotification($pushNotificationData, "New Payment", $fullName." has made payment of ".$depositAmount, $users);

            return $res->success("Payment received ", true);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Transaction create error', $message);
        }
    }

    public function distributeGroupTransaction($groupToken,$depositAmount){
        $res= new SystemResponses();
        $group = GroupSale::findFirst("groupToken='$groupToken'");
        if(!$group){
        $res->success("distributeGroupTransaction group not found groupToken"
                    .$groupToken." depositAmount ".$depositAmount);
            return false;
        }

        $sales = $this->rawSelect("SELECT * FROM sales WHERE status>=0 AND groupID=".$group->groupID);
        $totalAmount = $this->rawSelect("SELECT SUM(amount) as amount FROM sales WHERE status>=0 AND  groupID=".$group->groupID);
        $totalAmount=$totalAmount[0]['amount'];
        $res->success("group totalAmount ".$totalAmount." depositAmount ".$depositAmount);
        foreach ($sales as $sale) {
            $o_sale = Sales::findFirst("salesID=".$sale['salesID']);
            if($depositAmount>=$sale['amount']){
                $o_sale->paid=$o_sale->amount;
                $o_sale->status=2;
                $depositAmount=$depositAmount-$o_sale->amount;
            }
            elseif($depositAmount>0 && $depositAmount<$sale['amount'] ){
                $o_sale->paid=$depositAmount;
                $o_sale->status=1;
            }
            elseif($depositAmount==0){
                break;
            }

            $o_sale->save();
            $res->success("saved group sale amount salesID".$sale['salesID']);
        }

        //update group to have this  show it has been paid
        $group->status=7 ;
        $group->save();
        $res->success("saved group sale $groupToken");


        return true;
    }



    public function mapCustomerTransaction(){
            $jwtManager = new JwtManager();
            $request = new Request();
            $res = new SystemResponses();
            $json = $request->getJsonRawBody();
            $transactionManager = new TransactionManager();
            $dbTransaction = $transactionManager->get();

            $transactions = $this->rawSelect("SELECT * FROM transaction WHERE transactionID not IN (SELECT transactionID FROM customer_transaction) ");
             $res->success("payment received ", $transactions);

            foreach ($transactions as $trans) {
                
              $contactsID = $this->mapTransactionContact($trans['salesID'],$trans['mobile']);

              $res->success("payment received ", $contactsID);
          
              if ($contactsID) {
                   $customerTransaction = new CustomerTransaction();
                   $customerTransaction->transactionID = $trans['transactionID'];
                   $customerTransaction->contactsID = $contactsID;
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
                      $res->dataError('customer transaction create failed', $messages);
                     // return $res->success("Payment received", TRUE);
                  }

                  //Find incomplete sales
                  $depositAmount = floatval(str_replace(',', '', $depositAmount));
                  if($depositAmount > 0){
                      if(substr($accounNumber, 0, strlen('group')) === 'group' ){
                          $remainingAmount = $this->distributeGroupTransaction($accounNumber,$depositAmount,$dbTransaction);
                      }
                      else{
                          $remainingAmount = $this->distributePaymentToSale($contactsID,$depositAmount,$dbTransaction);
                      }
                  }

                  $this->removeFromUknown($trans["transactionID"]);
                 
              } else {
                 $unknown = TransactionUnknown::findFirst("transactionID = ".$trans['transactionID']);
                 if(!$unknown){
                      $unknown = new TransactionUnknown();
                      $unknown->transactionID = $trans['transactionID'];
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
                 }
                  
              }
            }
             $dbTransaction->commit();

            return $res->success("payment received ", $transactions);
    }

    private function removeFromUknown($transactionID){
          if($transactionID){
              $unknown = TransactionUnknown::findFirst("transactionID = ".$transactionID);
              if($unknown){
                $unknown->status=1;
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
              }

          }
          
          return true;
    }



}
