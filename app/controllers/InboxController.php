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

        try {
            $inbox = new Inbox();
            $inbox->MSISDN = $MSISDN;
            $inbox->message = $message;
            $inbox->receivedAt = $receivedAt;
            $inbox->shortCode = $shortCode;
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

            if (preg_match('/warant/', strtolower($message)) || preg_match('/warrant/', strtolower($message))) {
                $contact = Contacts::findFirst(array("workMobile=:workMobile:",
                            'bind' => array("workMobile" => $MSISDN)));
                $contactsID = NULL;
                $otherOwner = NULL;
                $ticketData = NULL;

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

    public function getTableInbox() { //sort, order, page, limit,filter
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');

        $countQuery = "SELECT count(inboxID) as totalInbox ";

        $selectQuery = "SELECT i.inboxID,i.MSISDN,i.message,i.createdAt,c.fullName,c.contactsID  ";

        $baseQuery = "FROM inbox i LEFT JOIN contacts c on i.MSISDN=c.workMobile  ";

        $condition = "";

        if ($filter) {
            $condition = " WHERE ";
        } elseif (!$filter) {
            $condition = "  ";
        }

        $countQuery = $countQuery . $baseQuery . $condition;
        $selectQuery = $selectQuery . $baseQuery . $condition;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit, $filter);

        if ($queryBuilder) {
            $selectQuery = $selectQuery . " " . $queryBuilder;
        }

        $count = $this->rawSelect($countQuery);

        $messages = $this->rawSelect($selectQuery);
        $data["totalInbox"] = $count[0]['totalInbox'];
        $data["Messages"] = $messages;

        return $res->success("Messages ", $data);
    }

    /*
    util function to build all get queries based on passed parameters
    */

    public function tableQueryBuilder($sort = "", $order = "", $page = 0, $limit = 10, $filter = "") {
        $query = "";

        if (!$page || $page <= 0) {
            $page = 1;
        }
        if (!$limit) {
            $limit = 10;
        }

        $ofset = ($page - 1) * $limit;
        if ($sort && $order && $filter) {
            $query = "  i.MSISDN REGEXP '$filter' OR c.fullName REGEXP '$filter' OR i.message REGEXP '$filter' ORDER by $sort $order LIMIT $ofset,$limit";
        } elseif ($sort && $order && !$filter) {
            $query = " ORDER by $sort $order LIMIT $ofset,$limit";
        } elseif ($sort && $order && !$filter) {
            $query = " ORDER by $sort $order  LIMIT $ofset,$limit";
        } elseif (!$sort && !$order) {
            $query = " LIMIT $ofset,$limit";
        } elseif (!$sort && !$order && $filter) {
            $query = "  i.MSISDN REGEXP '$filter' OR c.fullName REGEXP '$filter' OR i.message REGEXP '$filter' LIMIT $ofset,$limit";
        }

        return $query;
    }

}
