<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

/*
Records calls made by customer care 
*/ 

class CallController extends Controller {


    /*
    raw query select function to work in any version of phalcon
    */
    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    /*
    Create a call
    parameters: token,status,callTypeID,contactsID,recipient
    userID,comment,callback,
    previousTool (what the customer was using previously),
    promoterID,
    customerComment,productExperience,
    recommendation,referralScheme,agentBehaviour,deliveryRating,deliveryReason,
    overalExperience
    */

    public function create() { 
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $token = isset($json->token) ? $json->token : '';

        //Mandatory
        $status = $json->status ? $json->status : 0;
        $callTypeID = isset($json->callTypeID) ? $json->callTypeID : '';
        $contactsID = isset($json->contactsID) ? $json->contactsID : NULL;
        $recipient = isset($json->recipient) ? $json->recipient : NULL;
        $userID = isset($json->userID) ? $json->userID : NULL;
        $comment = isset($json->comment) ? $json->comment : NULL;
        $callback = !empty(trim($json->callback)) ? $json->callback : NULL;

        $agentBehaviourComment = !empty(trim($json->agentBehaviourComment)) ? $json->agentBehaviourComment : NULL;
        $overalExperienceComment = !empty(trim($json->overalExperienceComment)) ? $json->overalExperienceComment : NULL;
        $recomendationComment = !empty(trim($json->recomendationComment)) ? $json->recomendationComment : NULL;

        $previousTool = $json->previousTool ? $json->previousTool : NULL;
        $promoterID = $json->promoterID ? $json->promoterID : NULL;
        $customerComment = $json->customerComment ? $json->customerComment : NULL;

        $productExperience = $json->productExperience ? $json->productExperience : NULL;
        $recommendation = $json->recommendation ? (int) $json->recommendation : NULL;
        $referralScheme = $json->referralScheme ? (int) $json->referralScheme : 0;
        $agentBehaviour = $json->agentBehaviour ? (int) $json->agentBehaviour : NULL;
        $deliveryRating = $json->deliveryRating ? (int) $json->deliveryRating : NULL;
        $deliveryReason = $json->deliveryReason ? $json->deliveryReason : NULL;
        $overalExperience = $json->overalExperience ? (int) $json->overalExperience : NULL;


        if (!$token || !$callTypeID || !$userID || (!$contactsID && !$recipient)) {
            return $res->dataError("Fields missing ", []);
        }


        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        try {

//            $logger->log("Request Data: " . json_encode($json));
            $call = new Call();
            $call->callTypeID = $callTypeID;
            $call->contactsID = $contactsID;
            $call->recipient = $recipient;
            $call->callback = $callback;
            $call->status = $status;
            $call->userID = $userID;
            $call->comment = $comment;
            $call->createdAt = date("Y-m-d H:i:s");

            if ($contactsID) {
                $promoterScore = PromoterScore::findFirst(array("contactsID=:contactsID:",
                            'bind' => array("contactsID" => $contactsID)));
                if ($promoterScore) {
                    if (!$promoterScore->promoterID) {
                        if ($promoterID) {
                            $promoterScore->previousTool = $previousTool;
                            $promoterScore->promoterID = $promoterID;
                            $promoterScore->comment = $customerComment;
                        }
                    }
                    if (!$promoterScore->saleAgentBehavior) {
                        if ($agentBehaviour) {
                            $promoterScore->saleAgentBehavior = $agentBehaviour;
                            $promoterScore->deliveryExperience = $deliveryRating;
                            $promoterScore->comment = $deliveryReason;
                            $promoterScore->overallExperience = $overalExperience;
                            $promoterScore->productExperience = $productExperience;
                            $promoterScore->recommendation = $recommendation;
                            $promoterScore->referralScheme = $referralScheme;
                            $promoterScore->agentBehaviourComment = $agentBehaviourComment;
                            $promoterScore->overalExperienceComment = $overalExperienceComment;
                            $promoterScore->recomendationComment = $recomendationComment;
                            
                        }
                    }
                } else {
                    $promoterScore = new PromoterScore(); 
                    if ($promoterID) {
                        $promoterScore->previousTool = $previousTool;
                        $promoterScore->promoterID = $promoterID;
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
                    $promoterScore->agentBehaviourComment = $agentBehaviourComment;
                    $promoterScore->overalExperienceComment = $overalExperienceComment;
                    $promoterScore->recomendationComment = $recomendationComment;

                    $promoterScore->userID = $userID;
                    $promoterScore->contactsID = $contactsID;
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
                        $messages = $call->getMessages();
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
            } else {
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
            }

            return $res->success("call successfully created ", $call);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('call log error', $message);
        }
    }

    /**
    *retrives all the calls made 
    parameters: 
    sort (tabled field to be used in order criteria)
    order (either asc or desc)
    page (current table page)
    limit (maximum fields to be drawn)
    filter (search key word)
    */

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
        $status = $request->getQuery('status');
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';
        $isExport = $request->getQuery('isExport') ? $request->getQuery('isExport') : false;

        if ($customerID) {
            $customer = Customer::findFirst(array("customerID=:customerID:",
                        'bind' => array("customerID" => $customerID)));
            if ($customer) {
                $contactsID = $customer->contactsID;
            }
        }


        $countQuery = "SELECT count(callLogID) as totalCalls ";

        $selectQuery = "SELECT log.callLogID,log.callTypeID, log.contactsID, "
                . "c.fullName, c.workMobile, c.workEmail, ct.callTypeName, "
                . "log.recipient, log.callback, log.comment, log.status, log.userID, "
                . "c1.fullName AS callerName, log.createdAt ";

        $baseQuery = "FROM call_log log INNER JOIN call_type ct ON log.callTypeID=ct.callTypeID "
                . "LEFT JOIN contacts c ON log.contactsID=c.contactsID LEFT JOIN users u "
                . "ON log.userID=u.userID LEFT JOIN contacts c1 ON u.contactID=c1.contactsID ";

        $whereArray = [
            'filter' => $filter,
            'log.userID' => $userID,
            'log.contactsID' => $contactsID,
            'log.status' => $status,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['ct.callTypeName', 'c.workMobile', 'c.fullName',
                    'c.workEmail', 'log.recipient', 'log.comment'];

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
            } else if ($key == 'log.status' && $value == 404) {
                $valueString = "" . $key . "=0" . " AND ";
                $whereQuery .= $valueString;
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(log.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
                }
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND" : "";
                $whereQuery .= $valueString;
            }
        }
        $logger->log("Request Data Item: Key:" . $key . " Value: " . Json_encode($whereQuery));

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND");
        }

        $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";

        $countQuery = $countQuery . $baseQuery . $whereQuery;
        $selectQuery = $selectQuery . $baseQuery . $whereQuery;
        $exportQuery = $selectQuery;
       

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

        $logger->log("Calls Request Query: " . $selectQuery);
        $count = $this->rawSelect($countQuery);
        $messages = $this->rawSelect($selectQuery);

        if($isExport){
             $exportMessages = $this->rawSelect($exportQuery);
             $data["exportCalls"] = $exportMessages;
             $data["totalCalls"] = $count[0]['totalCalls'];
             $data["calls"] = $messages;
           
        }
        else{
             $data["totalCalls"] = $count[0]['totalCalls'];
             $data["calls"] = $messages;
        }
        

        return $res->success("calls", $data);
    }

    /*
     Get call types
     should pass token
    */

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

    /*
    retrieve ways cutstomers can know about envirofit
    should pass tocken
    */

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

    /*
     Record customer's promoter rating
     parameters: 
     token,productExperience,recommendation,referralScheme,
     agentBehaviour,customerID,deliveryRating,deliveryReason,
     overalExperience,userID
    */

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

   /*
     get promoter scores 

   */
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

    /*
    util function to build all get queries based on passed parameters
    */

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
