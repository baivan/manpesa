<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class TransactionsController extends Controller {

    private $salePaid = 1;

    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    public function create() { //{mobile,account,referenceNumber,amount,fullName,token}
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

            $saleQuery ="SELECT s.salesID FROM transaction t JOIN contacts c on t.salesID=c.nationalIdNumber or t.salesID=c.workMobile JOIN customer cu on c.contactsID=cu.contactsID JOIN sales s on cu.customerID=s.customerID where c.nationalIdNumber='%$salesID%' or c.workMobile='%$salesID%'";
            if(!$sale){
                $mappedSale = $this->rawSelect($saleQuery);

               $salesID=$mappedSale[0]['salesID'];
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
        

        $getAmountQuery="SELECT SUM(replace(t.depositAmount,',','')) as amount, s.amount as saleAmount, st.salesTypeDeposit,si.saleItemID,i.serialNumber,i.status as itemStatus from transaction t join contacts c on t.salesID=c.workMobile or t.salesID=c.nationalIdNumber join customer cu on c.contactsID=cu.contactsID join sales s on cu.customerID=s.customerID JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID join sales_type st on pp.salesTypeID=st.salesTypeID left join sales_item si on t.salesID=si.saleID left join item i on si.itemID=i.itemID where s.salesID=$salesID ";

        $transaction = $this->rawSelect($getAmountQuery);

        if($transaction[0]['amount'] <= 0){
            $getAmountQuery = "SELECT SUM(t.depositAmount) amount, s.amount as saleAmount, st.salesTypeDeposit,si.saleItemID,i.serialNumber,i.status as itemStatus FROM transaction t join sales s on t.salesID=s.salesID  JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID join sales_type st on pp.salesTypeID=st.salesTypeID left join sales_item si on t.salesID=si.saleID left join item i on si.itemID=i.itemID WHERE t.salesID=$salesID ";
        }

        $transaction = $this->rawSelect($getAmountQuery);
        

        return $res->success("Sale paid", $transaction[0]);
    }

    public function checkSalePaid($salesID) {
        $transactionQuery = "SELECT SUM(t.depositAmount) amount, s.amount as saleAmount, st.salesTypeDeposit FROM transaction t join sales s on t.salesID=s.salesID  JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID join sales_type st on pp.salesTypeID=st.salesTypeID WHERE t.salesID=$salesID ";

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

        $baseQuery = "FROM transaction t LEFT JOIN contacts co ON t.salesID=co.workMobile OR t.salesID=co.nationalIdNumber LEFT JOIN customer cu ON co.contactsID=cu.contactsID LEFT JOIN sales s ON cu.customerID=s.customerID LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID LEFT JOIN sales_type st on st.salesTypeID=pp.salesTypeID  ";

        $whereArray = [
            'filter' => $filter,
            's.salesID' => $salesID,
            'cu.customerID' => $customerID,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['t.depositorName', 't.mobile', 'co.fullName', 't.referenceNumber', 'st.salesTypeName'];

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
