<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

class CallController extends Controller {

    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    public function create() { //{message,contactsID,userID,status}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $token = isset($json->token) ? $json->token : '';
        $customerReached = $json->customerReached ? $json->customerReached : 0;
        $callTypeID = $json->callTypeID ? $json->callTypeID : '';
        $previousTool = $json->previousTool ? $json->previousTool : NULL;
        $promoterID = $json->promoterID ? $json->promoterID : '';
        $customerID = $json->customerID ? $json->customerID : '';
        $callback = $json->callback ? $json->callback : NULL;
        $comment = $json->comment ? $json->comment : '';
        $userID = $json->userID ? $json->userID : '';


        if (!$token || !$callTypeID || !$userID || !$promoterID || !$customerID) {
            return $res->dataError("Fields missing ", []);
        }


        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        try {
            $call = new Call();
            $call->status = $customerReached;
            $call->userID = $userID;
            $call->disposition = $callTypeID;
            $call->callback = $callback;
            $call->previousTool = $previousTool;
            $call->promoterID = $promoterID;
            $call->comment = $comment;
            $call->customerID = $customerID;
            $call->createdAt = date("Y-m-d H:i:s");

            if ($call->save() === false) {
                $errors = array();
                $messages = $call->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                return $res->dataError('call log failed', $errors);
            }

            return $res->success("call successfully created ", $call);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('call log error', $message);
        }
    }

    public function getTableCalls() { //sort, order, page, limit,filter
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token') ? $request->getQuery('token') : '';
        $sort = $request->getQuery('sort') ? $request->getQuery('sort') : '';
        $order = $request->getQuery('order') ? $request->getQuery('order') : '';
        $page = $request->getQuery('page') ? $request->getQuery('page') : '';
        $limit = $request->getQuery('limit') ? $request->getQuery('limit') : '';
        $filter = $request->getQuery('filter') ? $request->getQuery('filter') : '';
        $contactsID = $request->getQuery('contactsID') ? $request->getQuery('contactsID') : '';
        $customerID = $request->getQuery('customerID') ? $request->getQuery('customerID') : '';
        $userID = $request->getQuery('userID') ? $request->getQuery('userID') : '';


        $countQuery = "SELECT count(callLogID) as totalCalls ";

        $selectQuery = "SELECT log.callLogID,log.disposition,callType.callTypeName,"
                . "log.customerID,c.workMobile,c.fullName,log.previousTool,log.promoterID, p.promoterName,"
                . "log.callback,log.comment,log.userID,c1.fullName AS user, log.status,log.createdAt,log.updatedAt ";

        $baseQuery = "FROM call_log log INNER JOIN call_type callType ON log.disposition=callType.callTypeID "
                . "INNER JOIN customer cust ON log.customerID=cust.customerID "
                . "INNER JOIN contacts c ON cust.contactsID=c.contactsID "
                . "INNER JOIN users u ON log.userID=u.userID "
                . "INNER JOIN contacts c1 ON u.contactID=c1.contactsID INNER JOIN promoter p ON log.promoterID=p.promoterID ";

        $whereArray = [
            'filter' => $filter,
            'log.userID' => $userID,
            'log.customerID' => $customerID
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['t.ticketTitle', 'cat.ticketCategoryName', 'c.fullName', 'c1.fullName', 'c2.fullName'];

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
////                $logger->log("Filter Item: Key:" . $key . " Value: " . Json_encode($whereQuery));
            } else if ($key == 'status' && $value == 404) {
                $valueString = "" . $key . "=0" . " AND ";
                $whereQuery .= $valueString;
////                $logger->log("Status Item: Key:" . $key . " Value: " . Json_encode($whereQuery));
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(t.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
                }
////                $logger->log("Date Item: Key:" . $key . " Value: " . Json_encode($whereQuery));
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND" : "";
                $whereQuery .= $valueString;
////                $logger->log("Rest Item: Key:" . $key . " Value: " . Json_encode($whereQuery));
            }
        }
        $logger->log("Request Data Item: Key:" . $key . " Value: " . Json_encode($whereQuery));

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND");
        }

        $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";

//        $condition = "";
//
//        if ($filter && $customerID) {
//            $condition = " WHERE o.contactsID=$contactsID AND ";
//        } elseif ($filter && !$customerID) {
//            $condition = " WHERE  ";
//        } elseif (!$filter && !$customerID) {
//            $condition = "  ";
//        }

        $countQuery = $countQuery . $baseQuery . $whereQuery;
        $selectQuery = $selectQuery . $baseQuery . $whereQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;
        //return $res->success($selectQuery);

        $logger->log("Calls Request Query: " . $selectQuery);

        $count = $this->rawSelect($countQuery);

        $messages = $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
        $data["totalCalls"] = $count[0]['totalCalls'];
        $data["calls"] = $messages;

        return $res->success("calls", $data);
    }

    public function dispositions() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $callTypeID = $request->getQuery('callTypeID');

        $dispositionQuery = "SELECT * FROM call_type ";

        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        if ($callTypeID) {
            $dispositionQuery = "SELECT * FROM call_type WHERE callTypeID=$callTypeID";
        }

        $dispositions = $this->rawSelect($dispositionQuery);

        return $res->getSalesSuccess($dispositions);
    }

    public function promoters() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $promoterID = $request->getQuery('promoterID');

        $promotersQuery = "SELECT * FROM promoter ";

        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        if ($promoterID) {
            $promotersQuery = "SELECT * FROM promoter WHERE promoterID=$promoterID";
        }

        $promoters = $this->rawSelect($promotersQuery);

        return $res->getSalesSuccess($promoters);
    }

    public function tableQueryBuilder($sort = "", $order = "", $page = 0, $limit = 10) {

        $sortClause = $sort ? "ORDER BY $sort $order" : '';

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
