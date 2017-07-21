<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

/*
All outbox sms CRUD operations 
*/


class OutboxController extends Controller {

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
    create new sms outbox 
    paramters:
    message,contactsID,userID,status
    */

    public function create() { //{message,contactsID,userID,status}
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $token = $json->token;
        $message = $json->message;
        $status = $json->status;
        $contactsID = $json->contactsID;
        $recipient = $json->recipient;
        $userID = $json->userID;


        if (!$token || !$message || !$userID || (!$contactsID && !$recipient)) {
            return $res->dataError("Fields missing ");
        }


        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        try {
            $outbox = new Outbox();
            $outbox->message = $message;
            $outbox->userID = $userID;
            $outbox->status = $status;
            $outbox->contactsID = $contactsID;
            $outbox->recipient = $recipient;
            $outbox->createdAt = date("Y-m-d H:i:s");

            if ($outbox->save() === false) {
                $errors = array();
                $messages = $outbox->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                //return $res->dataError('sale create failed',$errors);
                $dbTransaction->rollback('outbox create failed' . json_encode($errors));
            }


            $outboxID = $outbox->outboxID;
            
            $logger->log("I have reached here: "+$outboxID);

            $outboxRecipient = $this->rawSelect("SELECT c.workMobile, o.recipient FROM outbox o LEFT JOIN contacts c "
                    . "ON o.contactsID=c.contactsID WHERE outboxID=$outboxID");

            if ($outboxRecipient) {
                $recipientObj = $outboxRecipient[0]['recipient'];
                $workMobile = $outboxRecipient[0]['workMobile'];

                if ($workMobile) {
                    $res->sendMessage($workMobile, $message);
                } else {
                    $res->sendMessage($recipientObj, $message);
                }
            }
            $dbTransaction->commit();

            return $res->success("outbox successfully created ", $outbox);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('outbox create error', $message);
        }
    }
 /*
    retrieve  outbox sms to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */
    public function getTableOutbox() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $sort = $request->getQuery('sort') ? $request->getQuery('sort') : '';
        $order = $request->getQuery('order') ? $request->getQuery('order') : '';
        $page = $request->getQuery('page') ? $request->getQuery('page') : '';
        $limit = $request->getQuery('limit') ? $request->getQuery('limit') : '';
        $filter = $request->getQuery('filter') ? $request->getQuery('filter') : '';
        $contactsID = $request->getQuery('contactsID') ? $request->getQuery('contactsID') : '';
        $customerID = $request->getQuery('customerID') ? $request->getQuery('customerID') : '';
        $userID = $request->getQuery('userID') ? $request->getQuery('userID') : '';
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';
        $isExport = $request->getQuery('isExport') ? $request->getQuery('isExport') : '';

       

        if ($customerID) {
            $customer = Customer::findFirst(array("customerID=:customerID:",
                        'bind' => array("customerID" => $customerID)));
            if ($customer) {
                $contactsID = $customer->contactsID;
            }
        }


        $countQuery = "SELECT count(outboxID) as totalOutBox ";

        $selectQuery = "SELECT o.outboxID, o.contactsID, c.fullName AS contactName, c.workMobile, "
                . "o.recipient, o.status, o.userID, c1.fullName, o.message, o.createdAt ";

        $baseQuery = "FROM outbox o LEFT JOIN contacts c ON o.contactsID=c.contactsID "
                . "LEFT JOIN users u ON o.userID=u.userID LEFT JOIN contacts c1 ON u.contactID=c1.contactsID ";

        $whereArray = [
            'o.contactsID' => $contactsID,
            'filter' => $filter,
            'o.userID' => $userID,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['o.message', 'c.fullName', 'c.workMobile', 'o.contact'];

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
            } else if ($key == 'status' && $value == 404) {
                $valueString = "" . $key . "=0" . " AND ";
                $whereQuery .= $valueString;
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(o.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
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

       

        if($isExport){
            $exportMessages  = $this->rawSelect($exportQuery);
            $count = $this->rawSelect($countQuery);
             $messages = $this->rawSelect($selectQuery);
             $data["totalOutBox"] = $count[0]['totalOutBox'];
             $data["Messages"] = $messages;
            $data["exportMessage"] =  $exportMessages;//$exportMessage;
        }
        else{
             $count = $this->rawSelect($countQuery);
             $messages = $this->rawSelect($selectQuery);
             $data["totalOutBox"] = $count[0]['totalOutBox'];
             $data["Messages"] = $messages;
             $data["exportMessage"] = "no data ".$isExport;
        }

        return $res->success("Messages ", $data);
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

}
