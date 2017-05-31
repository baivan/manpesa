<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

class ProspectsController extends Controller {

    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    public function createProspect() {//
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();


        $userID = $json->userID;
        $contactsID = $json->contactsID;
        $token = $json->token;

        if (!$token || !$contactsID || !$userID) {
            return dataError("Fields Missing");
        }
        try {
            $prospect = new Prospects();
            $prospect->status = 0;
            $prospect->userID = $userID;
            $prospect->contactsID = $contactsID;
            $prospect->createdAt = date("Y-m-d H:i:s");
            if ($prospect->save() === false) {
                $errors = array();
                $messages = $prospect->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $dbTransaction->rollback("Prospect create" . json_encode($errors));
            }

            $dbTransaction->commit();
            return $res->success("Prospect created successfully ", $prospect);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Contacts create', $message);
        }
    }

    public function createContactProspect() {//{userID,workMobile,nationalIdNumber,fullName,location,token}
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();

        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $userID = $json->userID;
        $workMobile = $json->workMobile;
        $nationalIdNumber = $json->nationalIdNumber;
        $fullName = $json->fullName;
        $location = $json->location;
        $sourceID = $json->sourceID ? (int) $json->sourceID : NULL;
        $otherSource = $json->otherSource ? $json->otherSource : NULL;
        $token = $json->token;

        if (!$token || !$workMobile || !$fullName) {
            return $res->dataError("Missing data ");
        }

        $workMobile = $res->formatMobileNumber($workMobile);
//        if ($homeMobile) {
//            $homeMobile = $res->formatMobileNumber($homeMobile);
//        }

        $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
                    'bind' => array("w_mobile" => $workMobile)));
        if ($contact) {

            $prospect = Prospects::findFirst(array("contactsID=:id: ",
                        'bind' => array("id" => $contact->contactsID)));
            if ($prospect) {
                return $res->success("Prospect exists ", false);
            }
            return $res->success("Similar mobile number exists", false);
        } else {
            try {

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
                    $dbTransaction->rollback('contact create failed', json_encode($errors));
                }

                $prospect = new Prospects();
                $prospect->status = 0;
                $prospect->userID = $userID;
                $prospect->contactsID = $contact->contactsID;
                $prospect->sourceID = $sourceID;
                $prospect->otherSource = $otherSource;
                $prospect->createdAt = date("Y-m-d H:i:s");
                if ($prospect->save() === false) {
                    $errors = array();
                    $messages = $prospect->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback('prospect create failed', json_encode($errors));
                }

                $res->sendMessage($workMobile, "Dear " . $fullName . ", welcome to Envirofit. For any questions or comments call 0800722700 ");

                $dbTransaction->commit();

                return $res->success("Prospect created successfully ", $prospect);
            } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
                $message = $e->getMessage();
                return $res->dataError('Contacts create', $message);
            }
        }
    }

    public function getAll() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $prospectID = $request->getQuery('prospectID');
        $userID = $request->getQuery('userID');

        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $prospectQuery = "SELECT p.prospectsID, p.status, p.contactsID,c.workMobile, "
                . "c.fullName,c.nationalIdNumber, c.workEmail,c.location, p.sourceID, ps.sourceName,"
                . "p.otherSource FROM prospects p INNER JOIN contacts c ON p.contactsID=c.contactsID "
                . "LEFT JOIN prospect_source ps ON p.sourceID=ps.sourceID ";

//        $prospectQuery = "SELECT * FROM prospects p JOIN contacts c on p.contactsID=c.contactsID ";

        if ($userID && !$prospectID) {
            $prospectQuery = $prospectQuery . " WHERE p.userID=$userID";
        } elseif (!$userID && $prospectID) {
            $prospectQuery .= " WHERE prospectsID=$prospectID";
        } elseif ($userID && $prospectID) {
            $prospectQuery = $prospectQuery . " WHERE p.userID=$userID AND p.prospectsID=$prospectID";
        }


        $prospects = $this->rawSelect($prospectQuery);

        if ($userID && !$prospectID) {
            return $res->getSalesSuccess($prospects);
        } else {
            return $res->success("prospects ", $prospects);
        }
    }

    public function getTableProspects() { //sort, order, page, limit,filter
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

        $countQuery = "SELECT count(prospectsID) as totalProspects ";

        $baseQuery = " FROM prospects  p join contacts co on p.contactsID=co.contactsID LEFT JOIN prospect_source ps "
                . "ON p.sourceID=ps.sourceID ";

        $selectQuery = "SELECT p.prospectsID, p.contactsID, co.fullName,co.nationalIdNumber,co.workMobile,co.workEmail,co.location, p.sourceID, "
                . "ps.sourceName, p.otherSource, p.createdAt  ";

        $whereArray = [
            'p.status' => 1,
            'filter' => $filter,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['co.fullName', 'co.nationalIdNumber', 'co.workMobile', 'co.location'];

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
                    $valueString = " DATE(p.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
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
        
//        $logger->log("Prospects Table Query Data: " . json_encode($selectQuery));

        $count = $this->rawSelect($countQuery);

        $prospects = $this->rawSelect($selectQuery);
        $data["totalProspects"] = $count[0]['totalProspects'];
        $data["prospects"] = $prospects;

        return $res->success("Prospects ", $data);
    }

    public function getSources() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');

        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $prospectSourceQuery = "SELECT sourceID, sourceName FROM prospect_source ";

        $prospectSources = $this->rawSelect($prospectSourceQuery);

        return $res->getSalesSuccess($prospectSources);
//        return $res->success("prospectSources ", $prospectSources);
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
