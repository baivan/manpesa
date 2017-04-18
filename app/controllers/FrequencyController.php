<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;

class FrequencyController extends Controller
{

    protected function rawSelect($statement){ 
	      $connection = $this->di->getShared("db"); 
	      $success = $connection->query($statement);
	      $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
	      $success = $success->fetchAll($success); 
	      return $success;
	   }
	
	public function create(){//{numberOfDays,frequencyName,token}
		  $jwtManager = new JwtManager();
		  $request = new Request();
		  $res = new SystemResponses();
		  $json = $request->getJsonRawBody();
		  $token = $json->token;
		  $numberOfDays = $json->numberOfDays;
		  $frequencyName = $json->frequencyName;

		  if(!$token || !$numberOfDays || !$frequencyName){
				return $res->dataError("Missing data ");
			}

		    $tokenData = $jwtManager->verifyToken($token,'openRequest');

	       if(!$tokenData){
	         return $res->dataError("Data compromised");
	       }

	       $frequency = Frequency::findFirst(array("frequencyName=:name: AND numberOfDays=:number:",
				    					'bind'=>array("name"=>$frequencyName,"number"=>$numberOfDays)));

	       if($frequency){
	       	return $res->dataError("frequency with similar name exists");
	       }

	        $frequency = new Frequency();
	       $frequency->numberOfDays = $numberOfDays;
	       $frequency->frequencyName = $frequencyName;
	       $frequency->createdAt = date("Y-m-d H:i:s");

	        if($frequency->save()===false){
		            $errors = array();
		                    $messages = $frequency->getMessages();
		                    foreach ($messages as $message) 
		                       {
		                         $e["message"] = $message->getMessage();
		                         $e["field"] = $message->getField();
		                          $errors[] = $e;
		                        }
		                  return $res->dataError('Frequency create failed',$errors);
		          }


		   return $res->success('Frequency created',$frequency);

	}
	
	public function update(){//{numberOfDays,frequencyName,token,frequencyID}
		  $jwtManager = new JwtManager();
		  $request = new Request();
		  $res = new SystemResponses();
		  $json = $request->getJsonRawBody();
		  $token = $json->token;
		  $numberOfDays = $json->numberOfDays;
		  $frequencyName = $json->frequencyName;
		  $frequencyID = $json->frequencyID;

		  if(!$token || !$frequencyID){
				return $res->dataError("Missing data ");
			}

		    $tokenData = $jwtManager->verifyToken($token,'openRequest');

	       if(!$tokenData){
	         return $res->dataError("Data compromised");
	       }

	       $frequency = Frequency::findFirst(array("frequencyID=:id:",
				    					'bind'=>array("id"=>$frequencyID)));

	       if(!$frequency){
	       	return $res->dataError("frequency with similar name exists");
	       }

	       //$frequency = new Frequency();
	       if($numberOfDays){
	       	 $frequency->numberOfDays = $numberOfDays;
	       }

	       if($frequencyName){
	       	  $frequency->frequencyName = $frequencyName;
	       }

	        if($frequency->save()===false){
		            $errors = array();
		                    $messages = $frequency->getMessages();
		                    foreach ($messages as $message) 
		                       {
		                         $e["message"] = $message->getMessage();
		                         $e["field"] = $message->getField();
		                          $errors[] = $e;
		                        }
		                  return $res->dataError('Frequency update failed',$errors);
		          }


		   return $res->success('Frequency updated',$frequency);
	}
	
	public function getAll(){
		       $jwtManager = new JwtManager();
		    	$request = new Request();
		    	$res = new SystemResponses();
		    	$token = $request->getQuery('token');
		        $frequencyID = $request->getQuery('frequencyID');

		        $frequencyQuery = "SELECT * FROM frequency ";

		        if(!$token  ){
				    	return $res->dataError("Missing data ");
				 }

			    $tokenData = $jwtManager->verifyToken($token,'openRequest');
		       if(!$tokenData){
		         return $res->dataError("Data compromised");
		       }

		       if($frequencyID){
		       	  $frequencyQuery = "SELECT * FROM frequency WHERE frequencyID=$frequencyID";
		       }

		       $frequencies = $this->rawSelect($frequencyQuery);

		       return $res->getSalesSuccess($frequencies);
	}
	
	//public function delete(){}


}

