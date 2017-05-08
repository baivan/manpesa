<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

class TicketController extends Controller {

    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    public function create() { //{ticketTitle,ticketDescription,customerID,assigneeID,ticketCategoryID,priorityID,status}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $token = $json->token;
        $ticketTitle = $json->ticketTitle;
        $ticketDescription = $json->ticketDescription;
        $customerID = $json->customerID;
        $assigneeID = $json->assigneeID;
        $ticketCategoryID = $json->ticketCategoryID;
        $priorityID = $json->priorityID;
        $status = $json->status;



        if (!$token || !$ticketTitle || !$ticketDescription || !$customerID || !$assigneeID || !$ticketCategoryID || !$priorityID) {
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
            $ticket->customerID = $customerID;
            $ticket->assigneeID = $assigneeID;
            $ticket->ticketCategoryID = $ticketCategoryID;
            $ticket->priorityID = $priorityID;
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
            $dbTransaction->commit();

            return $res->success("ticket successfully created ", $ticket);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Priority create error', $message);
        }
    }

    public function update() { //ticketUpdate,callback,userID,ticketID,token
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();

        $token = $json->token ? $json->token : '';
        $ticketUpdate = $json->ticketUpdate ? $json->ticketUpdate : '';
        $callback = $json->callback ? $json->callback : '';
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

    public function close() {//productName,productImage,categoryID,productID,token
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

    public function getTableTickets() { //sort, order, page, limit,filter,status
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $roleID = $request->getQuery('roleID');
        $sort = $request->getQuery('sort') ? $request->getQuery('sort') : 'ticketID';
        $order = $request->getQuery('order') ? $request->getQuery('order') : 'ASC';
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $ticketID = $request->getQuery('ticketID');
        $customerID = $request->getQuery('customerID');
        $status = $request->getQuery('status');
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';


        $countQuery = "SELECT count(ticketID) as totalTickets ";

//        $selectQuery = "SELECT t.ticketID,t.ticketTitle,t.status,tc.ticketCategoryID,"
//                . "tc.ticketCategoryName,tc.ticketCategoryDescription,p.priorityID,"
//                . "t.assigneeID,t.customerID,co.fullName,p.priorityName,p.priorityDescription,"
//                . "t.createdAt  ";
//
//        $baseQuery = " FROM ticket t JOIN customer cu on t.customerID=cu.customerID "
//                . "LEFT JOIN ticket_category tc on t.ticketCategoryID=tc.ticketCategoryID "
//                . "LEFT JOIN priority p on t.priorityID=p.priorityID LEFT JOIN contacts co "
//                . "on cu.contactsID=co.contactsID  ";

        $selectQuery = "SELECT t.ticketID, t.ticketTitle, t.ticketDescription, t.ticketCategoryID, "
                . "cat.ticketCategoryName,t.priorityID,pr.priorityName, t.userID, "
                . "c.fullName AS name, t.customerID, c1.fullName AS customerName, c1.workMobile, "
                . "t.assigneeID, c2.fullName AS assigneeName, t.status, t.createdAt, t.updatedAt ";

        $baseQuery = "FROM ticket t LEFT JOIN users u ON t.userID=u.userID LEFT JOIN contacts c "
                . "ON u.contactID=c.contactsID INNER JOIN customer cust ON t.customerID=cust.customerID "
                . "LEFT JOIN contacts c1 ON cust.contactsID=c1.contactsID LEFT JOIN users u1 "
                . "ON t.assigneeID=u1.userID LEFT JOIN contacts c2 ON u1.contactID=c2.contactsID "
                . "INNER JOIN ticket_category cat ON t.ticketCategoryID=cat.ticketCategoryID "
                . "INNER JOIN priority pr ON t.priorityID=pr.priorityID ";

//        $condition = "";
        $whereArray = [
            'status' => $status,
            'filter' => $filter,
            'ticketID' => $ticketID,
            't.customerID' => $customerID,
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
            } else if($key == 'status' && $value == 404) {
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

//        if ($ticketID && !$filter && $customerID) {
//            $condition = " WHERE t.ticketID=$ticketID AND customerID=$customerID ";
//        } elseif ($ticketID && !$filter && !$customerID) {
//            $condition = " WHERE t.ticketID=$ticketID ";
//        } elseif ($ticketID && $filter && $customerID) {
//            $condition = " WHERE t.ticketID=$ticketID AND customerID=$customerID AND ";
//        } elseif ($ticketID && $filter && !$customerID) {
//            $condition = " WHERE t.ticketID=$ticketID AND ";
//        } elseif (!$ticketID && $filter && $customerID) {
//            $condition = " WHERE customerID=$customerID ";
//        } elseif (!$ticketID && $filter && !$customerID) {
//            $condition = " WHERE ";
//        }
//        $countQuery = $countQuery . $baseQuery . $condition;
        $countQuery = $countQuery . $baseQuery . $whereQuery;
//        $selectQuery = $selectQuery . $baseQuery . $condition;
        $selectQuery = $selectQuery . $baseQuery . $whereQuery;

//        $selectQuery .= $sortClause;
        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

        $logger->log("Request Query: " . $selectQuery);

//        if ($queryBuilder) {
//            $selectQuery = $selectQuery . " " . $queryBuilder;
//        }
        //return $res->success($selectQuery);

        $count = $this->rawSelect($countQuery);

        $tickets = $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
        $data["totalTickets"] = $count[0]['totalTickets'];
        $data["tickets"] = $tickets;

        return $res->success("Tickets ", $data);
    }

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

//        if ($sort && $order && $filter) {
//            $query = "  co.fullName REGEXP '$filter' OR t.ticketTitle REGEXP '$filter' OR tc.ticketCategoryName REGEXP '$filter' ORDER by $sort $order LIMIT $ofset,$limit";
//        } elseif ($sort && $order && !$filter) {
//            $query = " ORDER by $sort $order LIMIT $ofset,$limit";
//        } elseif ($sort && $order && !$filter) {
//            $query = " ORDER by $sort $order  LIMIT $ofset,$limit";
//        } elseif (!$sort && !$order) {
//            $query = " LIMIT $ofset,$limit";
//        } elseif (!$sort && !$order && $filter) {
//            $query = "  co.fullName REGEXP '$filter' OR t.ticketTitle REGEXP '$filter' OR tc.ticketCategoryName REGEXP '$filter' LIMIT $ofset,$limit";
//        }

        return "$sortClause $limitQuery";
    }

}
