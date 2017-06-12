<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

/*
All TicketCategory CRUD operations 
*/

class TicketCategoryController extends Controller
{
 /*
    Raw query select function to work in any version of phalcon
    */
   protected function rawSelect($statement)
		       { 
		          $connection = $this->di->getShared("db"); 
		          $success = $connection->query($statement);
		          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
		          $success = $success->fetchAll($success); 
		          return $success;
		       }

  /*
    create new TicketCategory  
    paramters:
    ticketCategoryName,ticketCategoryDescription,token
    */

   public function create(){ //{token,ticketCategoryName,ticketCategoryDescription}
	    $jwtManager = new JwtManager();
	   $request = new Request();
	  $res = new SystemResponses();
	  $json = $request->getJsonRawBody();
	  $transactionManager = new TransactionManager(); 
       $dbTransaction = $transactionManager->get();

       $token = $json->token;
       $ticketCategoryName = $json->ticketCategoryName;
       $ticketCategoryDescription = $json->ticketCategoryDescription;

        $tokenData = $jwtManager->verifyToken($token,'openRequest');

        if(!$token || !$ticketCategoryName || !$ticketCategoryDescription){
	    	return $res->dataError("Fields missing ");
	    }


	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

       try {
       	  $ticketCategory = new TicketCategory();
       	  $ticketCategory->ticketCategoryName = $ticketCategoryName;
       	  $ticketCategory->ticketCategoryDescription =$ticketCategoryDescription;
       	  $ticketCategory->createdAt = date("Y-m-d H:i:s");

       	  if($ticketCategory->save()===false){
	            $errors = array();
	                    $messages = $ticketCategory->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	               //return $res->dataError('sale create failed',$errors);
	                $dbTransaction->rollback('Ticket Category create failed' . json_encode($errors));  
	          }
	        $dbTransaction->commit();

	     return $res->success("Ticket Category successfully created ",$sale);
       	
       }  catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
	       $message = $e->getMessage(); 
	       return $res->dataError('Ticket category create error', $message); 
       }
  }

/*
   retrive all TicketCategory
   parameters;
   token
   ticketCategoryID (optional)

*/

   public function getAll(){
     	    
     	    $jwtManager = new JwtManager();
	    	$request = new Request();
	    	$res = new SystemResponses();
	    	$token = $request->getQuery('token');
	        $ticketCategoryID = $request->getQuery('ticketCategoryID');
	        $selectQuery = "SELECT * FROM ticket_category ";

	          if(!$token){
		        	return $res->dataError("Missing data ");
		        }

		        $tokenData = $jwtManager->verifyToken($token,'openRequest');
		       if(!$tokenData){
		         return $res->dataError("Data compromised");
		       }
		       if($ticketCategoryID){
		       	  $selectQuery=$selectQuery." WHERE ticketCategoryID=$ticketCategoryID";
		       }

		        $categories = $this->rawSelect($selectQuery);

		       return $res->success("Ticket  ",$categories);
		     
     }
  	

}

