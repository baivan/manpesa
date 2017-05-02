<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class PriorityController extends Controller
{

    protected function rawSelect($statement)
		       { 
		          $connection = $this->di->getShared("db"); 
		          $success = $connection->query($statement);
		          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
		          $success = $success->fetchAll($success); 
		          return $success;
		       }

    public function create(){ //{priorityName,priorityDescription}
	   $jwtManager = new JwtManager();
	   $request = new Request();
	   $res = new SystemResponses();
	   $json = $request->getJsonRawBody();
	   $transactionManager = new TransactionManager(); 
       $dbTransaction = $transactionManager->get();

       $token = $json->token;
       $priorityName = $json->priorityName;
       $priorityDescription = $json->priorityDescription;

        $tokenData = $jwtManager->verifyToken($token,'openRequest');

        if(!$token || !$priorityName || !$priorityDescription){
	    	return $res->dataError("Token missing epriorityDescriptionr".json_encode($json));
	    }


	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

       try {
       	  $priority = new Priority();
       	  $priority->priorityName = $priorityName;
       	  $priority->priorityDescription =$priorityDescription;
       	  $priority->createdAt = date("Y-m-d H:i:s");

       	  if($priority->save()===false){
	            $errors = array();
	                    $messages = $priority->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	               //return $res->dataError('sale create failed',$errors);
	                $dbTransaction->rollback('Priority create failed' . json_encode($errors));  
	          }
	        $dbTransaction->commit();

	     return $res->success("Priority successfully created ",$sale);
       	
       }  catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
	       $message = $e->getMessage(); 
	       return $res->dataError('Priority create error', $message); 
       }
  }

   public function getAll(){
     	    
     	    $jwtManager = new JwtManager();
	    	$request = new Request();
	    	$res = new SystemResponses();
	    	$token = $request->getQuery('token');
	        $priorityID = $request->getQuery('priorityID');
	        $selectQuery = "SELECT * FROM priority ";

	          if(!$token){
		        	return $res->dataError("Missing data ");
		        }

		        $tokenData = $jwtManager->verifyToken($token,'openRequest');
		       if(!$tokenData){
		         return $res->dataError("Data compromised");
		       }
		       if($priorityID){
		       	  $selectQuery=$selectQuery." WHERE priorityID=$priorityID";
		       }

		        $categories = $this->rawSelect($selectQuery);

		       return $res->success("Priorities ",$categories);
		     
     }

}

