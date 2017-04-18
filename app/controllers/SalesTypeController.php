<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT; 

class SalesTypeController extends Controller
{

	protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }

     public function create(){ //{salesTypeName,salesTypeDeposit}
     	$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();

    	$token = $json->token;
    	$salesTypeName = $json->salesTypeName;
    	$salesTypeDeposit = $json->salesTypeDeposit;

    	if(!$token || !$salesTypeName || !$salesTypeDeposit ){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	    $salesType = SalesType::findFirst(array("salesTypeName=:salesTypeName:",
	    					'bind'=>array("salesTypeName"=>$salesTypeName)));
	    if($salesType){
	    	return $res->dataError("Sales type with the same name exists");
	    }

	    $salesType = new SalesType();
	    $salesType->salesTypeName = $salesTypeName;
	    $salesType->salesTypeDeposit = $salesTypeDeposit;

	    if($salesType->save()===false){
	            $errors = array();
	                    $messages = $salesType->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                  return $res->dataError('salesType create failed',$errors);
	          }

	     return $res->success("Sales Type created successfully ",$salesType);

     }
     public function update(){//{salesTypeName,salesTypeDeposit,salesTypeID}

     	$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();

    	$token = $json->token;
    	$salesTypeName = $json->salesTypeName;
    	$salesTypeDeposit = $json->salesTypeDeposit;
    	$salesTypeID = $json->salesTypeID;


    	if(!$token || !$salesTypeID ){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	    $salesType = SalesType::findFirst(array("salesTypeID=:salesTypeID:",
	    					'bind'=>array("salesTypeID"=>$salesTypeID)));
	    if(!$salesType){
	    	return $res->dataError("Sales type does not exist");
	    }

	    if($salesTypeName){
	    	$salesType->salesTypeName = $salesTypeName;
	    }

	    if($salesTypeName){
	    	 $salesType->salesTypeDeposit = $salesTypeDeposit;
	    }
	    
	   

	    if($salesType->save()===false){
	            $errors = array();
	                    $messages = $salesType->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                  return $res->dataError('salesType update failed',$errors);
	          }

	     return $res->success("Sales Type updated successfully ",$salesType);

 	}
    // public function delete(){}
     public function getAll(){
     	$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $salesTypeID = $request->getQuery('salesTypeID');

        $salesTypeQuery = "SELECT * FROM sales_type ";

         if(!$token){
        	return $res->dataError("Token Missing");
        }

        $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

        if($salesTypeID>0){
        	$salesTypeQuery = "SELECT * FROM sales_type WHERE salesTypeID=$salesTypeID";
        }

        $salesType= $this->rawSelect($salesTypeQuery);

		return $res->getSalesSuccess($salesType);
     }
     



}

