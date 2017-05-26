<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class InboxController extends Controller {

    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    public function create() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        //$json = $request->getJsonRawBody();
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
                //return $res->dataError('sale create failed',$errors);
                $dbTransaction->rollback('inbox create failed' . json_encode($errors));
            }

            //Generate warranty ticket

            if (preg_match('/warant/', strtolower($message)) || preg_match('/warrant/', strtolower($message))) {
                $contact = Contacts::findFirst(array("workMobile=:workMobile:",
                            'bind' => array("workMobile" => $MSISDN)));
                $contactsID = NULL;
                $otherOwner = NULL;

                if ($contact) {
                    $contactsID = $contact->contactsID;
                } else {
                    $otherOwner = $MSISDN;
                }

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
                    //$dbTransaction->rollback('ticket create failed' . json_encode($errors));
                }
            }

            $dbTransaction->commit();

            return $res->success("Inbox successfully created ", []);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Inbox create error', $message);
        }
    }

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
//return $res->success($selectQuery);

        $count = $this->rawSelect($countQuery);

        $messages = $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
        $data["totalInbox"] = $count[0]['totalInbox'];
        $data["Messages"] = $messages;

        return $res->success("Messages ", $data);
    }

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
