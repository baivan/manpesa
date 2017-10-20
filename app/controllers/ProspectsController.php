<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

/*
All prospects CRUD operations 
*/

class ProspectsController extends Controller {

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
    create new prospect from an existing contact 
    paramters:
    userID,contactsID,token
    */

    public function createProspect() {
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
 /*
    create new prospect passing all details
    paramters:
    userID,workMobile,nationalIdNumber,fullName,location,token
    */
    
    public function createContactProspect() {//{userID,workMobile,nationalIdNumber,fullName,location,token}
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
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


        $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
                    'bind' => array("w_mobile" => $workMobile)));
        if ($contact) {

            $prospect = Prospects::findFirst(array("contactsID=:id: ",
                        'bind' => array("id" => $contact->contactsID)));
            if ($prospect) {
                return $res->success("prospect exists ", false);
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
                $prospect->status = 1;
                $prospect->userID = $userID;
                $prospect->updatedBy = $userID;
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

    /*
    retrieve all prospects owned/created by a given user
    parameters:
    prospectID (optional),userID
    */

    public function getAll() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $prospectID = $request->getQuery('prospectID');
        $userID = $request->getQuery('userID');
        $longitude = $request->getQuery('longitude');
        $latitude = $request->getQuery('latitude');
        $timeToQuery = $request->getQuery('timeToQuery');
        $activityLog= new ActivityLogsController();
        $activityLog->create($userID,"get prospects ",$longitude,$latitude);

       
       

        if (!$token) {
            return $res->dataError("Missing data ");
        }

      /*  $prospectQuery = "SELECT p.prospectsID, p.status, p.contactsID,c.workMobile, "
                . "c.fullName,c.nationalIdNumber, c.workEmail,c.location, p.sourceID, ps.sourceName, p.createdAt, "
                . "p.otherSource FROM prospects p INNER JOIN contacts c ON p.contactsID=c.contactsID "
                . "LEFT JOIN prospect_source ps ON p.sourceID=ps.sourceID ";
                */
        $prospectQuery="SELECT p.prospectsID, p.status, p.contactsID,c.workMobile, "
                    ."c.fullName,c.nationalIdNumber, c.workEmail,c.location, p.sourceID, ps.sourceName, "
                    ."p.createdAt,p.otherSource,s.salesID,pr.productID,s.status as saleStatus,s.amount, "
                    ."s.paid FROM prospects p INNER JOIN contacts c ON p.contactsID=c.contactsID LEFT JOIN "
                    ."prospect_source ps ON p.sourceID=ps.sourceID LEFT JOIN sales s on c.contactsID=s.contactsID "
                    ."and s.status > 0 LEFT JOIN product pr on s.productID=pr.productID ";


        if ($userID && !$prospectID) {
            $prospectQuery = $prospectQuery . " WHERE p.userID=$userID";
        } elseif (!$userID && $prospectID) {
            $prospectQuery .= " WHERE prospectsID=$prospectID";
        } elseif ($userID && $prospectID) {
            $prospectQuery = $prospectQuery . " WHERE p.userID=$userID AND p.prospectsID=$prospectID";
        }

        if($timeToQuery>7){
            $prospectQuery=$prospectQuery.' AND month(date(c.createdAt))=month(CURRENT_DATE()) ';
        }
        else if($timeToQuery==7){
             $prospectQuery=$prospectQuery.' AND CURRENT_DATE()-date(c.createdAt) <=7 ';
        }
        else if($timeToQuery ==1){
             $prospectQuery=$prospectQuery.' AND date(c.createdAt) = CURRENT_DATE() ';
        }
     $res->success("prospect query $prospectQuery");


        $prospects = $this->rawSelect($prospectQuery);

        if ($userID && !$prospectID) {
            return $res->getSalesSuccess($prospects);
        } else {
            return $res->success("prospects ", $prospects);
        }
    }

     /*
    retrieve  prospects to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */

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
        $isExport = $request->getQuery('isExport') ? $request->getQuery('isExport') : '';
        $userID = $request->getQuery('userID') ? $request->getQuery('userID') : '';


        $countQuery = "SELECT count(prospectsID) as totalProspects ";

        $baseQuery = " FROM prospects  p join contacts co on p.contactsID=co.contactsID LEFT JOIN prospect_source ps "
                . "ON p.sourceID=ps.sourceID ";

        $selectQuery = "SELECT p.prospectsID, p.contactsID, co.fullName,co.nationalIdNumber,co.workMobile,co.workEmail,co.location, p.sourceID, "." ps.sourceName, p.otherSource, p.createdAt ";

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


        $whereQuery = $whereQuery ? " WHERE datediff(now(),co.createdAt)>30 or p.userID=$userID AND $whereQuery   " : " WHERE datediff(now(),co.createdAt)>30 "; 

        $countQuery = $countQuery . $baseQuery . $whereQuery;
        $selectQuery = $selectQuery . $baseQuery . $whereQuery;
        $exportQuery = $selectQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);

        $selectQuery .= $queryBuilder;
        

        $count = $this->rawSelect($countQuery);

        $prospects = $this->rawSelect($selectQuery);
        if($isExport){
             $exportProspects = $this->rawSelect($exportQuery);
            $data["totalProspects"] = $count[0]['totalProspects'];
            $data["prospects"] = $prospects;
            $data['exportProspects'] = $exportProspects;

        }
        else{
            $data["totalProspects"] = $count[0]['totalProspects'];
            $data["prospects"] = $prospects;
            $data['exportProspects'] = 'no data';
        }
       
 
        return $res->success("Prospects ", $data);
    }

   /*
   get all  prospect sources
   */
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

    public function removeProspectsWhoAreCustomers(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();

        $selectQuery = "SELECT * FROM prospects p join contacts c on p.contactsID=c.contactsID join sales s on c.contactsID=s.contactsID WHERE s.paid>0 and s.status>0 ";

        $allProspects = $this->rawSelect($selectQuery);
       // return $res->success("Done ",$allProspects);

        foreach ($allProspects as $prospect) {
            $prospectsID = $prospect['prospectsID'];

            $contactsID = $prospect['contactsID'];
            if($contactsID && $prospectsID){
                $prospect_o = Prospects::findFirst("prospectsID = $prospectsID");
                $customer = Customer::findFirst("contactsID = $contactsID");

                if($customer && $prospect_o){
                    $prospect_o->status = 5;
                    $prospect_o->save();
                    $res->success("Customer exists ",$prospect);
                }
                elseif (!$customer && $prospect_o ) {
                    $customer = new Customer();
                    $customer->userID = $prospect_o->userID;
                    $customer->locationID=0;
                    $customer->contactsID = $prospect_o->contactsID;
                    $customer->status = 0;
                    $customer->updatedBy =0;
                    $customer->createdAt = date("Y-m-d H:i:s");
                    $customer->save();
                    $prospect_o->status = 5;
                    $prospect_o->save();
                    $res->success("Customer create ".json_encode($customer),$prospect);
                }

            }
            else{
                $res->success(" $contactsID Done ".$prospectsID);
            }
        }

        return $res->success("Done ",$allProspects);

    }

}
