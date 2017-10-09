<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

/*
All sms inbox CRUD operations 
*/

class InboxController extends Controller {


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
    create new sms inbox  and generate waranty ticket
    usually called by infobip sms system when users send interact with envorfit's short code
    paramters:
    sender,text,receiver,when
    */
    public function create() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();

        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $MSISDN = $request->getQuery('sender');
        $message = $request->getQuery('text'); 
        $shortCode = $request->getQuery('receiver');
        $receivedAt = $request->getQuery('when');

        $res->success("SELECT contactsID FROM contacts where workMobile=$MSISDN");

        try {
            //get contact if exists
            $contact = $this->rawSelect("SELECT contactsID FROM contacts where workMobile=$MSISDN");

            $inbox = new Inbox();
            $inbox->MSISDN = $MSISDN;
            $inbox->message = $message;
            $inbox->receivedAt = $receivedAt;
            $inbox->shortCode = $shortCode;
            $inbox->contactsID = isset($contact[0]['contactsID']) ? $contact[0]['contactsID'] : 0;
            $inbox->createdAt = date("Y-m-d H:i:s");

            if ($inbox->save() === false) {
                $errors = array();
                $messages = $inbox->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $dbTransaction->rollback('inbox create failed' . json_encode($errors));
            }

            //Generate warranty ticket
                $contactsID = NULL;
                $otherOwner = NULL;
                $ticketData = NULL;

            if (preg_match('/warant/', strtolower($message)) || preg_match('/warrant/', strtolower($message)) || preg_match('/waranty/', strtolower($message)) || preg_match('/warranty/', strtolower($message))  ) { 
                $contact = Contacts::findFirst(array("workMobile=:workMobile:",
                            'bind' => array("workMobile" => $MSISDN)));
               

                if ($contact) {
                    $contactsID = $contact->contactsID;
                    $ticketData = Ticket::findFirst(array("contactsID=:contactsID: AND status=0",
                                'bind' => array("contactsID" => $contactsID)));
                } else {
                    $otherOwner = $MSISDN;
                    $ticketData = Ticket::findFirst(array("otherOwner=:otherOwner: AND status=0",
                                'bind' => array("otherOwner" => $otherOwner)));
                }

                if (!$ticketData) {
                    $ticket = new Ticket();
                    $ticket->ticketTitle = "Warranty Activation";
                    $ticket->ticketDescription = "SMS trigger from customer to activate warranty on a product item";
                    $ticket->contactsID = $contactsID;
                    $ticket->otherOwner = $otherOwner;
                    $ticket->inboxID=$inbox->inboxID;
                    $ticket->assigneeID = NULL;
                    $ticket->ticketCategoryID = 5; // Warranty SMS ticket
                    $ticket->otherCategory = NULL;
                    $ticket->priorityID = 1; //High priority
                    $ticket->userID = NULL;
                    $ticket->status = 0;
                    $ticket->createdAt = date("Y-m-d H:i:s");

                    if ($ticket->save() === false) {
                        $errors = array();
                        $messages = $ticket->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                        $res->dataError('ticket create failed', $errors);
                    }
                }
            }
            else{

                 if ($contact) {
                    $contactsID = $contact->contactsID;
                    $ticketData = Ticket::findFirst(array("contactsID=:contactsID: AND status=0",
                                'bind' => array("contactsID" => $contactsID)));
                } else {
                    $otherOwner = $MSISDN;
                    $ticketData = Ticket::findFirst(array("otherOwner=:otherOwner: AND status=0",
                                'bind' => array("otherOwner" => $otherOwner)));
                }

                if (!$ticketData) {
                    $ticket = new Ticket();
                    $ticket->ticketTitle = "New sms ticket";
                    $ticket->ticketDescription = "SMS trigger from customer ";
                    $ticket->contactsID = $contactsID;
                    $ticket->otherOwner = $MSISDN;
                    $ticket->inboxID=$inbox->inboxID;
                    $ticket->assigneeID = NULL;
                    $ticket->ticketCategoryID = 9; // Inbox SMS ticket
                    $ticket->otherCategory = NULL;
                    $ticket->priorityID = 1; //High priority
                    $ticket->userID = NULL;
                    $ticket->status = 0;
                    $ticket->createdAt = date("Y-m-d H:i:s");

                    if ($ticket->save() === false) {
                        $errors = array();
                        $messages = $ticket->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                        $res->dataError('ticket create failed', $errors);
                    }
                }

            }

            $dbTransaction->commit();

            return $res->success("Inbox successfully created ", []);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Inbox create error', $message);
        }
    }

    /*
    retrieve  inbox sms to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */
    public function getTableInbox() {
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
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';
        $isExport = $request->getQuery('isExport') ? $request->getQuery('isExport') : '';

        $canExport = false;

        if ($customerID) {
            $customer = Customer::findFirst(array("customerID=:customerID:",
                        'bind' => array("customerID" => $customerID)));
            if ($customer) {
                $contactsID = $customer->contactsID;
            }
        }


        $countQuery = "SELECT count(inboxID) as totalInbox ";

        $selectQuery = "SELECT i.inboxID,i.MSISDN,i.message,i.createdAt,c.fullName,c.contactsID,co.customerID,p.prospectsID ";

        $baseQuery = "FROM inbox i LEFT JOIN contacts c on i.contactsID=c.contactsID LEFT JOIN customer co on c.contactsID=co.contactsID LEFT JOIN prospects p on c.contactsID = p.contactsID ";

        $whereArray = [
            'c.contactsID' => $contactsID,
            'filter' => $filter,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['i.MSISDN', 'c.fullName', 'i.message'];

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
                    $valueString = " DATE(i.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
                    $canExport  =true;
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

       /* $count = $this->rawSelect($countQuery);

        $messages = $this->rawSelect($selectQuery);
        
        $data["totalInbox"] = $count[0]['totalInbox'];
        $data["Messages"] = $messages;

        if($canExport ==true){
            $exportMessages  = $this->rawSelect($exportQuery);
            $data["exportMessage"] = $exportMessage;
        }
        */
        $count = $this->rawSelect($countQuery);
        $messages = $this->rawSelect($selectQuery);

        if($isExport){
            $exportMessages  = $this->rawSelect($exportQuery);
             $data["totalInbox"] = $count[0]['totalInbox'];
             $data["Messages"] = $messages;
            $data["exportMessage"] =  $exportMessages;
        }
        else{
             
             $data["totalInbox"] = $count[0]['totalInbox'];
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
