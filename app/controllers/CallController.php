<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class OutboxController extends Controller
{

    protected function rawSelect($statement)
           { 
              $connection = $this->di->getShared("db"); 
              $success = $connection->query($statement);
              $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
              $success = $success->fetchAll($success); 
              return $success;
           }

    public function create(){ //{message,contactsID,userID,status}
	   $jwtManager = new JwtManager();
	   $request = new Request();
	   $res = new SystemResponses();
	   $json = $request->getJsonRawBody();
	   $transactionManager = new TransactionManager(); 
       $dbTransaction = $transactionManager->get();

       $token = $json->token;
       $message = $json->message;
       $status = $json->status;
       $contactsID = $json->contactsID;
       $userID = $json->userID;
        

        if(!$token || !$message || !$userID ||!$contactsID ){
	    	return $res->dataError("Fields missing ");
	    }


	    $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

       try {
       	  $outbox = new Outbox();
       	  $outbox->message = $message;
       	  $outbox->userID =$userID;
       	  $outbox->status =$status;
       	  $outbox->contactsID = $contactsID;
       	  $outbox->createdAt = date("Y-m-d H:i:s");

       	  if($outbox->save()===false){
	            $errors = array();
	                    $messages = $outbox->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	               //return $res->dataError('sale create failed',$errors);
	                $dbTransaction->rollback('outbox create failed' . json_encode($errors));  
	          }
	        $dbTransaction->commit();
        $selectQuery = "SELECT workMobile FROM contacts WHERE contactsID=$contactsID";
        $workMobile = $this->rawSelect($selectQuery);
        $res->sendMessage($workMobile[0]['workMobile'],$message);

	     return $res->success("outbox successfully created ",$outbox);
       	
       }  catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
	       $message = $e->getMessage(); 
	       return $res->dataError('outbox create error', $message); 
       }
  }




  public function getTableOutbox(){ //sort, order, page, limit,filter
    $jwtManager = new JwtManager();
      $request = new Request();
      $res = new SystemResponses();
      $token = $request->getQuery('token');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $contactsID = $request->getQuery('contactsID');
        //$userID = $request->getQuery('userID');
        

        $countQuery = "SELECT count(outboxID) as totalOutBox ";

        $selectQuery = "SELECT o.outboxID,o.message,o.status,c.fullName,c.contactsID,c.workMobile,o.createdAt ";

        $baseQuery = " FROM outbox o JOIN contacts c on o.contactsID=c.contactsID ";

        $condition ="";
         
         if($filter && $customerID){
          $condition = " WHERE o.contactsID=$contactsID AND ";
         }
         elseif ($filter && !$customerID) {
            $condition = " WHERE  ";
         }
         elseif(!$filter && !$customerID){
          $condition = "  ";
         }

         $countQuery = $countQuery.$baseQuery.$condition;
         $selectQuery = $selectQuery.$baseQuery.$condition;

        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
          $selectQuery=$selectQuery." ".$queryBuilder;
        }
        //return $res->success($selectQuery);

        $count = $this->rawSelect($countQuery);

    $messages= $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
    $data["totalOutBox"] = $count[0]['totalOutBox'];
    $data["Messages"] = $messages;

    return $res->success("Messages ",$data);


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
      $query = "  c.workMobile REGEXP '$filter' OR c.fullName REGEXP '$filter' OR o.message REGEXP '$filter' ORDER by $sort $order LIMIT $ofset,$limit";
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
      $query = "  c.workMobile REGEXP '$filter' OR c.fullName REGEXP '$filter' OR o.message REGEXP '$filter' LIMIT $ofset,$limit";
    }

    return $query;

  }

}

