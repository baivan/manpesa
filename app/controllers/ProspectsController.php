<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;


class ProspectsController extends Controller
{

    protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }

	public function create(){//{userID,workMobile,nationalIdNumber,fullName,location,token}
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$userID = $json->userID;
    	$workMobile = $json->workMobile;
    	$nationalIdNumber = $json->nationalIdNumber;
    	$fullName = $json->fullName;
    	$location = $json->location;
    	$token = $json->token;

    	if(!$token || !$workMobile || !$fullName){
	    	return $res->dataError("Missing data ");
	    }

	   $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
	    					'bind'=>array("w_mobile"=>$workMobile)));
	   if($contact){
	   	  $prospect = Prospects::findFirst(array("contactsID=:id: ",
	    					'bind'=>array("id"=>$contact->contactsID)));
	   	  if($prospect){
	   	  	return $res->dataError("Prospect exists");
	   	  }
	   	  return $res->dataError("Similar mobile number exists");
	   }
	   else{
	   		$contact = new Contacts();
	    	$contact->workEmail = "null";
	    	$contact->workMobile = $workMobile;
	    	$contact->fullName = $fullName;
	    	$contact->location =$location;
	    	$contact->createdAt = date("Y-m-d H:i:s");
	    	if ($nationalIdNumber) {
	    		$contact->nationalIdNumber = $nationalIdNumber;
	    	}
	    	else{
	    		$contact->nationalIdNumber="null";
	    	}

	    	if($contact->save()===false){
	            $errors = array();
	                    $messages = $contact->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                  return $res->dataError('contact create failed',$errors);
	          }
	        $prospect = new Prospects();
	        $prospect->status= 0 ;
	        $prospect->userID = $userID;
	        $prospect->contactsID = $contact->contactsID;
	        $prospect->createdAt = date("Y-m-d H:i:s");
	        if($prospect->save()===false){
	            $errors = array();
	                    $messages = $prospect->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                  return $res->dataError('prospect create failed',$errors);
	          }

	          return $res->success("Prospect created successfully ",$prospect);

	   }



	}
	
	public function getAll(){
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $prospectID = $request->getQuery('prospectID');
        $userID = $request->getQuery('userID');
 
        if(!$token || !$userID){
	    	return $res->dataError("Missing data ");
	    }

        $prospectQuery = "SELECT * FROM prospects p JOIN contacts c on p.contactsID=c.contactsID AND p.userID=$userID";

        if($prospectID){
        	$prospectQuery = "SELECT * FROM prospects p JOIN contacts c on p.contactsID=c.contactsID where p.userID=$userID AND p.prospectID=$prospectID";
        }

        $prospects = $this->rawSelect($prospectQuery);

        return $res->getSalesSuccess($prospects);




	}
	

}

