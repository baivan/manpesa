<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class TicketController extends Controller
{

   protected function rawSelect($statement)
           { 
              $connection = $this->di->getShared("db"); 
              $success = $connection->query($statement);
              $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
              $success = $success->fetchAll($success); 
              return $success;
           }

     public function create(){ //{ticketTitle,ticketDescription,customerID,assigneeID,ticketCategoryID,priorityID,status}
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

        

        if(!$token || !$ticketTitle || !$ticketDescription ||!$customerID || !$assigneeID || !$ticketCategoryID || !$priorityID ){
	    	return $res->dataError("Fields missing ");
	    }


	    $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

       try {
       	  $ticket = new Ticket();
       	  $ticket->ticketTitle = $ticketTitle;
       	  $ticket->ticketDescription =$ticketDescription;
       	  $ticket->customerID = $customerID;
       	  $ticket->assigneeID = $assigneeID;
       	  $ticket->ticketCategoryID = $ticketCategoryID;
       	  $ticket->priorityID = $priorityID;
       	  $ticket->status = $status;
       	  $ticket->createdAt = date("Y-m-d H:i:s");

       	  if($ticket->save()===false){
	            $errors = array();
	                    $messages = $ticket->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	               //return $res->dataError('sale create failed',$errors);
	                $dbTransaction->rollback('ticket create failed' . json_encode($errors));  
	          }
	        $dbTransaction->commit();

	     return $res->success("ticket successfully created ",$sale);
       	
       }  catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
	       $message = $e->getMessage(); 
	       return $res->dataError('Priority create error', $message); 
       }
  }

public function getTableTickets(){ //sort, order, page, limit,filter
    $jwtManager = new JwtManager();
      $request = new Request();
      $res = new SystemResponses();
      $token = $request->getQuery('token');
        $roleID = $request->getQuery('roleID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $ticketID = $request->getQuery('ticketID');
        $customerID = $request->getQuery('customerID');
        

        $countQuery = "SELECT count(ticketID) as totalTickets ";

        $selectQuery = "SELECT t.ticketID,t.ticketTitle,tc.ticketCategoryName,tc.ticketCategoryDescription,p.priorityName,p.priorityDescription,t.createdAt  ";

        $baseQuery = " FROM ticket t JOIN customer cu on t.customerID=cu.customerID LEFT JOIN ticket_category tc on t.ticketCategoryID=tc.ticketCategoryID LEFT JOIN priority p on t.priorityID=p.priorityID LEFT JOIN contacts co on cu.contactsID=co.contactsID  ";

        $condition ="";
         
         if($ticketID && !$filter && $customerID){
          $condition = " WHERE t.ticketID=$ticketID AND customerID=$customerID ";
         }
         elseif($ticketID && !$filter && !$customerID){
          $condition = " WHERE t.ticketID=$ticketID ";
         }
         elseif($ticketID && $filter && $customerID){
          $condition = " WHERE t.ticketID=$ticketID AND customerID=$customerID AND ";
         }
         elseif($ticketID && $filter && !$customerID){
          $condition = " WHERE t.ticketID=$ticketID AND ";
         }
         elseif(!$ticketID && $filter && $customerID){
          $condition = " WHERE customerID=$customerID ";
         }
         elseif(!$ticketID && $filter && !$customerID){
          $condition = " WHERE ";
         }

         $countQuery = $countQuery.$baseQuery.$condition;
         $selectQuery = $selectQuery.$baseQuery.$condition;

        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
          $selectQuery=$selectQuery." ".$queryBuilder;
        }
        //return $res->success($selectQuery);

        $count = $this->rawSelect($countQuery);

    $tickets= $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
    $data["totalTickets"] = $count[0]['totalTickets'];
    $data["tickets"] = $tickets;

    return $res->success("Tickets ",$data);


  }

  public function tableQueryBuilder($sort="",$order="",$page=0,$limit=10,$filter=""){
    $query = "";

    if(!$page || $page <= 0){
      $page=1;
    }
    if(!$limit){
      $limit=10;
    }

    $ofset = ($page-1)*$limit;
    if($sort  && $order  && $filter ){
      $query = "  co.fullName REGEXP '$filter' OR t.ticketTitle REGEXP '$filter' OR tc.ticketCategoryName REGEXP '$filter' ORDER by $sort $order LIMIT $ofset,$limit";
    }
    elseif($sort  && $order  && !$filter ){
      $query = " ORDER by $sort $order LIMIT $ofset,$limit";
    }
    elseif($sort  && $order  && !$filter ){
      $query = " ORDER by $sort $order  LIMIT $ofset,$limit";
    }
    elseif(!$sort && !$order ){
      $query = " LIMIT $ofset,$limit";
    }

    elseif(!$sort && !$order && $filter){
      $query = "  co.fullName REGEXP '$filter' OR t.ticketTitle REGEXP '$filter' OR tc.ticketCategoryName REGEXP '$filter' LIMIT $ofset,$limit";
    }

    return $query;

  }

}

