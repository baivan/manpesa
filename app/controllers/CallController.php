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
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $token = isset($json->token) ? $json->token : '';

        $promoterID = $json->promoterID ? $json->promoterID : '';
        $previousTool = $json->previousTool ? $json->previousTool : NULL;
        $callback = $json->callback ? $json->callback : NULL;
        $customerComment = $json->customerComment ? $json->customerComment : '';

        $productExperience = $json->productExperience ? $json->productExperience : '';
        $recommendation = $json->recommendation ? (int) $json->recommendation : '';
        $referralScheme = $json->referralScheme ? (int) $json->referralScheme : 0;
        $agentBehaviour = $json->agentBehaviour ? (int) $json->agentBehaviour : '';
        $deliveryRating = $json->deliveryRating ? (int) $json->deliveryRating : '';
        $deliveryReason = $json->deliveryReason ? $json->deliveryReason : '';
        $overalExperience = $json->overalExperience ? (int) $json->overalExperience : '';

        $customerReached = $json->customerReached ? $json->customerReached : 0;
        $callTypeID = $json->callTypeID ? $json->callTypeID : '';
        $customerID = $json->customerID ? $json->customerID : '';
        $ticketID = $json->ticketID ? $json->ticketID : NULL;
        $userID = $json->userID ? $json->userID : '';
        $userComment = $json->userComment ? $json->userComment : '';


        if (!$token || !$callTypeID || !$userID || !$customerID) {
            return $res->dataError("Fields missing ", []);
        }


        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        try {

            $logger->log("Request Data: " . json_encode($json));
            $call = new Call();
            $call->callTypeID = $callTypeID;
            $call->ticketID = $ticketID;
            $call->status = $customerReached;
            $call->callback = $callback;
            $call->comment = $userComment;
            $call->customerID = $customerID;
            $call->userID = $userID;
            $call->createdAt = date("Y-m-d H:i:s");

            $promoterScore = PromoterScore::findFirst(array("customerID=:customerID:",
                        'bind' => array("customerID" => $customerID)));
            if ($promoterScore) {
                $logger->log("Promoter score exists: " . json_encode($promoterScore));

//                $scorePromoter = PromoterScore::findFirst(array("customerID=:customerID: AND promoter=:promoterID:",
//                            'bind' => array("customerID" => $customerID, "promoterID" => $promoterID)));
//
//                $scoreAgent = PromoterScore::findFirst(array("customerID=:customerID: AND saleAgentBehavior=:saleAgentBehavior:",
//                            'bind' => array("customerID" => $customerID, "saleAgentBehavior" => $agentBehaviour)));

                if (!$promoterScore->promoter) {
                    $logger->log("Promoter score promoter field does not have data: " . json_encode($promoterScore->promoter));
                    if ($promoterID) {
                        $promoterScore->previousTool = $previousTool;
                        $promoterScore->promoter = $promoterID;
                        $promoterScore->comment = $customerComment;
                    }
                }

                if (!$promoterScore->saleAgentBehavior) {
                    $logger->log("Rating data not exists: " . json_encode($promoterScore->saleAgentBehavior));
                    if ($agentBehaviour) {
                        $logger->log("Rating data to be used: ");
                        $promoterScore->saleAgentBehavior = $agentBehaviour;
                        $promoterScore->deliveryExperience = $deliveryRating;
                        $promoterScore->comment = $deliveryReason;
                        $promoterScore->overallExperience = $overalExperience;
                        $promoterScore->productExperience = $productExperience;
                        $promoterScore->recommendation = $recommendation;
                        $promoterScore->referralScheme = $referralScheme;
                    }
                }
            } else {
                $logger->log("Promoter score does not exist: " . json_encode($promoterScore));
                $promoterScore = new PromoterScore();

                if ($promoterID) {
                    $promoterScore->previousTool = $previousTool;
                    $promoterScore->promoter = $promoterID;
                    $promoterScore->comment = $customerComment;
                }

                if ($agentBehaviour) {
                    $promoterScore->deliveryExperience = $deliveryRating;
                    $promoterScore->comment = $deliveryReason;
                    $promoterScore->overallExperience = $overalExperience;
                    $promoterScore->productExperience = $productExperience;
                    $promoterScore->recommendation = $recommendation;
                    $promoterScore->referralScheme = $referralScheme;
                }

                $promoterScore->userID = $userID;
                $promoterScore->customerID = $customerID;
                $promoterScore->createdAt = date("Y-m-d H:i:s");
            }

            if ($call->save() === false) {
                $errors = array();
                $messages = $call->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                return $res->dataError('call log failed', $errors);
            } else {
                if ($promoterScore->save() === false) {
                    $errors = array();
                    $messages = $promoterScore->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    return $res->dataError('call log failed', $errors);
                } else {
                    return $res->success("call successfully created ", $call);
                }
            }

            //return $res->success("call successfully created ", $call);
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

        $selectQuery = "SELECT log.callLogID,log.callTypeID,callType.callTypeName,"
                . "log.customerID,c.workMobile,c.fullName, "
                . "log.callback,log.comment,log.userID,c1.fullName AS user, log.status,log.createdAt,log.updatedAt ";

        $baseQuery = "FROM call_log log INNER JOIN call_type callType ON log.callTypeID=callType.callTypeID "
                . "INNER JOIN customer cust ON log.customerID=cust.customerID "
                . "INNER JOIN contacts c ON cust.contactsID=c.contactsID "
                . "INNER JOIN users u ON log.userID=u.userID "
                . "INNER JOIN contacts c1 ON u.contactID=c1.contactsID ";

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

    public function createScores() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $token = isset($json->token) ? $json->token : '';
        $productExperience = $json->productExperience ? $json->productExperience : '';
        $recommendation = $json->recommendation ? (int) $json->recommendation : '';
        $referralScheme = $json->referralScheme ? (int) $json->referralScheme : 0;
        $agentBehaviour = $json->agentBehaviour ? (int) $json->agentBehaviour : '';
        $customerID = $json->customerID ? $json->customerID : '';
        $deliveryRating = $json->deliveryRating ? (int) $json->deliveryRating : '';
        $deliveryReason = $json->deliveryReason ? $json->deliveryReason : '';
        $overalExperience = $json->overalExperience ? (int) $json->overalExperience : '';
        $userID = $json->userID ? $json->userID : '';


        if (!$token || !$productExperience || !$userID || !$recommendation || !$customerID ||
                !$agentBehaviour || !$deliveryRating || !$deliveryReason || !$overalExperience) {
            return $res->dataError("Fields missing ", []);
        }


        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        try {
            $score = new PromoterScore();
            $score->scoreCategoryID = 1;
            $score->scoreResponse = $agentBehaviour;
            $score->extra = NULL;
            $score->userID = $userID;
            $score->customerID = $customerID;
            $score->createdAt = date("Y-m-d H:i:s");


            $score1 = new PromoterScore();
            $score1->scoreCategoryID = 2;
            $score1->scoreResponse = $deliveryRating;
            $score1->extra = $deliveryReason;
            $score1->userID = $userID;
            $score1->customerID = $customerID;
            $score1->createdAt = date("Y-m-d H:i:s");

            $score2 = new PromoterScore();
            $score2->scoreCategoryID = 3;
            $score2->scoreResponse = $referralScheme;
            $score2->extra = NULL;
            $score2->userID = $userID;
            $score2->customerID = $customerID;
            $score2->createdAt = date("Y-m-d H:i:s");

            $score3 = new PromoterScore();
            $score3->scoreCategoryID = 4;
            $score3->scoreResponse = $recommendation;
            $score3->extra = NULL;
            $score3->userID = $userID;
            $score3->customerID = $customerID;
            $score3->createdAt = date("Y-m-d H:i:s");

            $score4 = new PromoterScore();
            $score4->scoreCategoryID = 5;
            $score4->scoreResponse = $overalExperience;
            $score4->extra = NULL;
            $score4->userID = $userID;
            $score4->customerID = $customerID;
            $score4->createdAt = date("Y-m-d H:i:s");

            $score5 = new PromoterScore();
            $score5->scoreCategoryID = 6;
            $score5->scoreResponse = NULL;
            $score5->extra = $productExperience;
            $score5->userID = $userID;
            $score5->customerID = $customerID;
            $score5->createdAt = date("Y-m-d H:i:s");

            if ($score->save() === false) {
                $errors = array();
                $messages = $score->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                return $res->dataError('call log failed', $errors);
            }

            if ($score1->save() === false) {
                $errors = array();
                $messages = $score1->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                return $res->dataError('call log failed', $errors);
            }

            if ($score2->save() === false) {
                $errors = array();
                $messages = $score2->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                return $res->dataError('call log failed', $errors);
            }

            if ($score3->save() === false) {
                $errors = array();
                $messages = $score3->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                return $res->dataError('call log failed', $errors);
            }

            if ($score4->save() === false) {
                $errors = array();
                $messages = $score4->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                return $res->dataError('call log failed', $errors);
            }

            if ($score5->save() === false) {
                $errors = array();
                $messages = $score5->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                return $res->dataError('call log failed', $errors);
            }

            return $res->success("call successfully created ", $score);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('call log error', $message);
        }
    }

    public function promoterScores() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $customerID = $request->getQuery('customerID') ? $request->getQuery('customerID') : '';

        $promoterScores = [];

        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        if ($customerID) {
            $promoterScoreQuery = "SELECT ps.saleAgentBehavior, ps.deliveryExperience, "
                    . "ps.referralScheme, ps.recommendation, ps.overallExperience, ps.productExperience, "
                    . "p.promoterName, ps.previousTool, ps.comment FROM promoter_score ps LEFT JOIN promoter p "
                    . "ON ps.promoter=p.promoterID "
                    . "WHERE ps.customerID=$customerID";
            $promoterScores = $this->rawSelect($promoterScoreQuery);
        } else {
            $promoterScores = [];
        }

        return $res->getSalesSuccess($promoterScores);
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
