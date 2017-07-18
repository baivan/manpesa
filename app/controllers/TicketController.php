<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;


/*
All Ticket CRUD operations 
*/


class TicketController extends Controller {

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
    create Ticket 
    paramters:
    ticketTitle,ticketDescription,contactsID, otherOwner,assigneeID,
        ticketCategoryID,otherTicketCategory,priorityID,status
    */
    public function create() { 
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $token = $json->token;
        $ticketTitle = $json->ticketTitle;
        $ticketDescription = $json->ticketDescription;
        $contactsID = $json->contactsID ? $json->contactsID : NULL;
        $otherOwner = $json->otherOwner ? $json->otherOwner : NULL;
        $assigneeID = $json->assigneeID ? $json->assigneeID : NULL;
        $userID = $json->userID ? $json->userID : NULL;
        $ticketCategoryID = $json->ticketCategoryID ? $json->ticketCategoryID : NULL;
        $otherTicketCategory = $json->otherTicketCategory ? $json->otherTicketCategory : NULL;
        $priorityID = $json->priorityID;
        $status = $json->status;



        if (!$token || !$ticketTitle || !$ticketDescription || !$priorityID) {
            return $res->dataError("Fields missing ");
        }


        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        try {
            $ticket = new Ticket();
            $ticket->ticketTitle = $ticketTitle;
            $ticket->ticketDescription = $ticketDescription;
            $ticket->contactsID = $contactsID;
            $ticket->otherOwner = $otherOwner;
            $ticket->assigneeID = $assigneeID;
            $ticket->ticketCategoryID = $ticketCategoryID;
            $ticket->otherCategory = $otherTicketCategory;
            $ticket->priorityID = $priorityID;
            $ticket->userID = $userID;
            $ticket->status = $status;
            $ticket->createdAt = date("Y-m-d H:i:s");

            if ($ticket->save() === false) {
                $errors = array();
                $messages = $ticket->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                //return $res->dataError('sale create failed',$errors);
                $dbTransaction->rollback('ticket create failed' . json_encode($errors));
            }

            $ticketID = $ticket->ticketID;

            $ticketData = $this->rawSelect("SELECT t.ticketTitle, t.ticketDescription, "
                    . "t.userID,c.fullName AS triggerName,t.contactsID, c1.fullName AS owner, "
                    . "t.otherOwner,t.assigneeID, c2.fullName AS assigneeName, c2.workMobile, c2.workEmail, t.ticketCategoryID,"
                    . "cat.ticketCategoryName, t.otherCategory, t.priorityID,p.priorityName FROM ticket t "
                    . "LEFT JOIN users u ON t.userID=u.userID LEFT JOIN contacts c ON u.contactID=c.contactsID "
                    . "LEFT JOIN contacts c1 ON t.contactsID=c1.contactsID LEFT JOIN users u1 "
                    . "ON t.assigneeID=u1.userID LEFT JOIN contacts c2 ON u1.contactID=c2.contactsID "
                    . "LEFT JOIN ticket_category cat ON t.ticketCategoryID=cat.ticketCategoryID "
                    . "INNER JOIN priority p ON t.priorityID=p.priorityID WHERE t.ticketID=$ticketID");

            $dbTransaction->commit();

//            $logger->log("Ticket Data: " . json_encode($ticketData));
            $ticketData[0]['ticketCategoryName'] = $ticketData[0]['ticketCategoryName'] ? $ticketData[0]['ticketCategoryName'] : $ticketData[0]['otherCategory'];

            $assigneeName = $ticketData[0]['assigneeName'];
            $triggerName = $ticketData[0]['triggerName'];

            $assigneeMessage = "Dear $assigneeName, the ticket named $ticketTitle "
                    . "has been assigned to you. Please ensure its resolve. Contact $triggerName for more info";
            $res->sendMessage($ticketData[0]['workMobile'], $assigneeMessage);
            $res->sendEmail($ticketData[0]);

            return $res->success("ticket successfully created ", $ticketData[0]);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Priority create error', $message);
        }
    }

/*
    update Ticket 
    paramters:
    ticketID (required),
    ticketUpdate,callback,userID,token
    */
    public function update() { //ticketUpdate,callback,userID,ticketID,token
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();

        $token = $json->token ? $json->token : '';
        $ticketUpdate = $json->ticketUpdate ? $json->ticketUpdate : '';
        $callback = !empty(trim($json->callback)) ? $json->callback : NULL;
        $userID = $json->userID ? $json->userID : '';
        $ticketID = $json->ticketID ? $json->ticketID : '';



        if (!$token || !$ticketUpdate || !$userID || !$ticketID) {
            return $res->dataError("Fields missing ");
        }

        $user = Users::findFirst(array("userID=:id: ",
                    'bind' => array("id" => $userID)));
        if (!$user) {
            return $res->dataError("user not found", []);
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        try {
            $ticketUpdates = new TicketUpdates();
            $ticketUpdates->updateMessage = $ticketUpdate;
            $ticketUpdates->ticketID = $ticketID;
            $ticketUpdates->userID = $userID;
            $ticketUpdates->callback = $callback;
            $ticketUpdates->createdAt = date("Y-m-d H:i:s");

            if ($ticketUpdates->save() === false) {
                $errors = array();
                $messages = $ticketUpdates->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
            }

            return $res->success("ticket successfully updated ", $ticketUpdates);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Priority create error', $message);
        }
    }
/*
    assign Ticket 
    paramters:
    ticketID (required),
    userID (required),
    token (required),
    */
 
    public function assign() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $ticketID = isset($json->ticketID) ? $json->ticketID : '';
        $userID = isset($json->userID) ? $json->userID : '';
        $token = isset($json->token) ? $json->token : '';

        if (!$token || !$ticketID || !$userID) {
            return $res->dataError("Missing data", []);
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised", []);
        }

        $ticket = Ticket::findFirst(array("ticketID=:id: ",
                    'bind' => array("id" => $ticketID)));
        if (!$ticket) {
            return $res->dataError("Ticket not found", []);
        }

        $user = Users::findFirst(array("userID=:id: ",
                    'bind' => array("id" => $userID)));
        if (!$user) {
            return $res->dataError("user not found", []);
        }

        if ($ticketID) {
            $ticket->assigneeID = $userID;
        }



        if ($ticket->save() === false) {
            $errors = array();
            $messages = $ticket->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('ticket assignment failed', $errors);
        }

        return $res->success("ticket assigned successfully", $ticket);
    }


    /*
    close Ticket 
    paramters:
    ticketID (required),
    token (required),
    */

    public function close() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $ticketID = isset($json->ticketID) ? $json->ticketID : '';
        $token = isset($json->token) ? $json->token : '';

        if (!$token || !$ticketID) {
            return $res->dataError("Missing data", []);
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised", []);
        }

        $ticket = Ticket::findFirst(array("ticketID=:id: ",
                    'bind' => array("id" => $ticketID)));
        if (!$ticket) {
            return $res->dataError("Ticket not found", []);
        }

        if ($ticketID) {
            $ticket->status = 1;
        }



        if ($ticket->save() === false) {
            $errors = array();
            $messages = $ticket->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('ticket close failed', $errors);
        }

        return $res->success("Ticket closed successfully", $ticket);
    }

  /*
    retrieve  tickets to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */

    public function getTableTickets() { //sort, order, page, limit,filter,status
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $sort = $request->getQuery('sort') ? $request->getQuery('sort') : 'ticketID';
        $order = $request->getQuery('order') ? $request->getQuery('order') : 'ASC';
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $ticketID = $request->getQuery('ticketID');
        $contactsID = $request->getQuery('contactsID');
        $status = $request->getQuery('status');
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';

        $countQuery = "SELECT count(ticketID) as totalTickets ";

        $selectQuery = "SELECT t.ticketID, t.ticketTitle, t.ticketDescription, t.contactsID, c.fullName AS owner,t.otherOwner, "
                . "c.workMobile, t.ticketCategoryID, cat.ticketCategoryName, t.otherCategory,t.priorityID,pr.priorityName, t.userID, "
                . "c1.fullName AS triggerName, t.assigneeID, c2.fullName AS assigneeName, t.status, t.createdAt, t.updatedAt ";

        $baseQuery = "FROM ticket t LEFT JOIN contacts c ON t.contactsID=c.contactsID LEFT JOIN users u "
                . "ON t.userID=u.userID LEFT JOIN contacts c1 ON u.contactID=c1.contactsID LEFT JOIN users u1 "
                . "ON t.assigneeID=u1.userID LEFT JOIN contacts c2 ON u1.contactID=c2.contactsID "
                . "LEFT JOIN ticket_category cat ON t.ticketCategoryID=cat.ticketCategoryID "
                . "INNER JOIN priority pr ON t.priorityID=pr.priorityID ";

        $whereArray = [
            't.status' => $status,
            'filter' => $filter,
            't.ticketID' => $ticketID,
            't.contactsID' => $contactsID,
            'date' => [$startDate, $endDate]
        ];

        $logger->log("Request Data: " . json_encode($whereArray));

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
                $logger->log("Filter Item: Key:" . $key . " Value: " . Json_encode($whereQuery));
            } else if ($key == 't.status' && $value == 404) {
                $valueString = "" . $key . "=0" . " AND ";
                $whereQuery .= $valueString;
                $logger->log("Status Item: Key:" . $key . " Value: " . Json_encode($whereQuery));
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(t.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
                }
                $logger->log("Date Item: Key:" . $key . " Value: " . Json_encode($whereQuery));
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND" : "";
                $whereQuery .= $valueString;
                $logger->log("Rest Item: Key:" . $key . " Value: " . Json_encode($whereQuery));
            }
        }
//        $logger->log("Request Data Item: Key:" . $key . " Value: " . Json_encode($whereQuery));

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND");
        }

        $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";

        $countQuery = $countQuery . $baseQuery . $whereQuery;
        $selectQuery = $selectQuery . $baseQuery . $whereQuery;
        $exportQuery = $selectQuery;

//        $selectQuery .= $sortClause;
        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

        $logger->log("Request Query: " . $selectQuery);

        $count = $this->rawSelect($countQuery);

        $tickets = $this->rawSelect($selectQuery);
        $exportTickets = $this->rawSelect($exportQuery);

        $data["totalTickets"] = $count[0]['totalTickets'];
        $data["tickets"] = $tickets;
        $data['exportTickets'] = $exportTickets;

        return $res->success("Tickets ", $data);
    }

  /*
    retrieve  updated tickets to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */

    public function tableTicketUpdates() { //sort, order, page, limit,ticketID
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $sort = $request->getQuery('sort') ? $request->getQuery('sort') : 'ticketID';
        $order = $request->getQuery('order') ? $request->getQuery('order') : 'ASC';
        $page = $request->getQuery('page') ? $request->getQuery('page') : 1;
        $limit = $request->getQuery('limit') ? $request->getQuery('limit') : 10;
        $ticketID = $request->getQuery('ticketID') ? $request->getQuery('ticketID') : 10;


        $countQuery = "SELECT count(ticketUpdateID) as totalTicketUpdates ";

        $selectQuery = "SELECT ticketUpdateID, updateMessage, ticketID, callback, createdAt ";

        $baseQuery = "FROM ticket_updates ";

        $whereArray = [
            'ticketID' => $ticketID
        ];

        $logger->log("Request Data: " . json_encode($whereArray));

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {
            if ($key == 'filter') {
                $searchColumns = [];

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
            } else {
                if ($key == 'status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                } else if ($key == 'date') {
                    if ($value[0] && $value[1]) {
                        $valueString = " DATE(t.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    }
                } else {
                    $valueString = $value ? "" . $key . "=" . $value . " AND" : "";
                }
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

        $logger->log("Request Query: " . $selectQuery);

        $count = $this->rawSelect($countQuery);
        $ticketUpdates = $this->rawSelect($selectQuery);
        $data["totalTicketUpdates"] = $count[0]['totalTicketUpdates'];
        $data["ticketUpdates"] = $ticketUpdates;

        return $res->success("ticketUpdates ", $data);
    }

     /*
    retrieve all tickets 
    parameters:
    ticketID (optional)
    */

    public function getAll() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token') ? $request->getQuery('token') : '';
        $ticketID = $request->getQuery('ticketID') ? $request->getQuery('ticketID') : '';

        if (!$token) {
            return $res->dataError("Missing data ", []);
        }

        $selectQuery = "SELECT t.ticketID, t.ticketTitle, t.ticketDescription, "
                . "t.userID,c.fullName AS triggerName,t.contactsID, c1.fullName AS owner, "
                . "t.otherOwner,t.assigneeID, c2.fullName AS assigneeName, c2.workMobile, c2.workEmail, t.ticketCategoryID,"
                . "cat.ticketCategoryName, t.otherCategory, t.priorityID,p.priorityName, t.status, t.createdAt, t.updatedAt ";

        $baseQuery = "FROM ticket t "
                . "LEFT JOIN users u ON t.userID=u.userID LEFT JOIN contacts c ON u.contactID=c.contactsID "
                . "LEFT JOIN contacts c1 ON t.contactsID=c1.contactsID LEFT JOIN users u1 "
                . "ON t.assigneeID=u1.userID LEFT JOIN contacts c2 ON u1.contactID=c2.contactsID "
                . "LEFT JOIN ticket_category cat ON t.ticketCategoryID=cat.ticketCategoryID "
                . "INNER JOIN priority p ON t.priorityID=p.priorityID ";

        $whereQuery = $ticketID ? "WHERE ticketID=$ticketID" : "";
        $ticketQuery = "$selectQuery $baseQuery $whereQuery";

        $tickets = $this->rawSelect($ticketQuery);

        return $res->success("ticket retrieved", $tickets);
    }
/*
sends email notification on ticket create 
*/
    public function email() {
//        $jwtManager = new JwtManager();
//        $request = new Request();
        $res = new SystemResponses();
        $res->sendEmail();
//        $json = $request->getJsonRawBody();
//        $ticketID = isset($json->ticketID) ? $json->ticketID : '';
//        $token = isset($json->token) ? $json->token : '';
//        if (!$token || !$ticketID) {
//            return $res->dataError("Missing data", []);
//        }
//
//        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
//        if (!$tokenData) {
//            return $res->dataError("Data compromised", []);
//        }
//
//        $ticket = Ticket::findFirst(array("ticketID=:id: ",
//                    'bind' => array("id" => $ticketID)));
//        if (!$ticket) {
//            return $res->dataError("Ticket not found", []);
//        }
//
//        if ($ticketID) {
//            $ticket->status = 1;
//        }
//
//
//
//        if ($ticket->save() === false) {
//            $errors = array();
//            $messages = $ticket->getMessages();
//            foreach ($messages as $message) {
//                $e["message"] = $message->getMessage();
//                $e["field"] = $message->getField();
//                $errors[] = $e;
//            }
//            return $res->dataError('ticket close failed', $errors);
//        }
//        return $res->success("Ticket closed successfully", $ticket);
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
