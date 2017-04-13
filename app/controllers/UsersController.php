<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;

class UsersController extends Controller
{ 

	 protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }
	public function create(){ //workMobile,homeMobile,homeEmail,workEmail,passportNumber,nationalIdNumber,fullName,locationID,roleID,token,location
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$workMobile = $json->workMobile;
    	$roleID=$json->roleID;
    	$homeMobile = $json->homeMobile;
    	$homeEmail = $json->homeEmail;
    	$workEmail = $json->workEmail;
    	$passportNumber = $json->passportNumber;
    	$nationalIdNumber = $json->nationalIdNumber;
    	$fullName = $json->fullName;
    	$locationID = $json->locationID;
    	$location = $json->location;
	    $token = $json->token;

	    if(!$token || !$workMobile || !$workEmail || !$fullName ){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("Login Data compromised");
	      }

	    if(!$roleID){
	    	$roleID=0;
	    }
	    if(!$locationID){
	    	$locationID=0;
	    }
	    $workMobile = $res->formatMobileNumber($workMobile);

	    $contact = Contacts::findFirst(array("workMobile=:w_mobile: OR workEmail=:w_email: ",
	    					'bind'=>array("w_mobile"=>$workMobile,"w_email"=>$workEmail)));

	    if($contact){
	    	$user = Users::findFirst(array("contactID=:contactID:",
	    					'bind'=>array("contactID"=>$contact->contactsID)));
	    	if($user){
	    		return $res->success("User exists ",$user);
	    	}
	    }
	    else{
	    	$contact = new Contacts();
	    	$contact->workEmail = $workEmail;
	    	$contact->workMobile = $workMobile;
	    	$contact->fullName = $fullName;
	    	$contact->createdAt = date("Y-m-d H:i:s");
	    	if($passportNumber){
	    		$contact->passportNumber = $passportNumber;
	    	}

	    	if($nationalIdNumber){
	    		$contact->nationalIdNumber = $nationalIdNumber;
	    	}

	    	if($nationalIdNumber){
	    		$contact->nationalIdNumber = $nationalIdNumber;
	    	}
	    	if($locationID){
	    		$contact->locationID = $locationID;
	    	}
	    	if($location){
	    		$contact->location = $location;
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

	          $code = rand(9999,99999);

	          $user = new Users();
	          $user->username = $workMobile;
	          $user->locationID = $locationID;
	          $user->contactID = $contact->contactsID;
	          $user->roleID=$roleID;
	          $user->createdAt = date("Y-m-d H:i:s");
	          $user->password = $this->security->hash($code);


	          if($user->save()===false){
	            $errors = array();
	                    $messages = $user->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                  return $res->dataError('user create failed',$errors);
	          }
	          

	          $message = "Envirofit verification code is \n ".$code;
              $res->sendMessage($workMobile,$message);

              $data = [
                     "username"=>$user->username,
                      "username"=>$user->username,
                       "userID"=>$user->userID];
           
          return $res->success("User created successfully $code",$data);

	    
	    }
	}

	public function update(){
	//userID,workMobile,homeMobile,homeEmail,workEmail,passportNumber,nationalIdNumber,fullName,locationID,roleID,token
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();

    	$workMobile = $json->workMobile;
    	$roleID=$json->roleID;
    	$userID = $json->userID;
    	$homeMobile = $json->homeMobile;
    	$homeEmail = $json->homeEmail;
    	$workEmail = $json->workEmail;
    	$passportNumber = $json->passportNumber;
    	$nationalIdNumber = $json->nationalIdNumber;
    	$fullName = $json->fullName;
    	$locationID = $json->locationID;
    	$location = $json->location;
	    $token = $json->token;
	    $contactsID=0;

	     if(!$token || !$userID){
	    	return $res->dataError("Missing data ");
	    }

	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("Login Data compromised");
	      }

	    $user = Users::findFirst(array("userID=:id:",
	    					'bind'=>array("id"=>$userID)));

	    if(!$user){
	    	return $res->dataError("User not found ");
	    }

	    $contact = Contacts::findFirst(array("contactsID=:id:",
	    					'bind'=>array("id"=>$user->contactID)));
	    if(!$contact){
	    	$contact = new Contacts();
	    	$contact->workEmail = $workEmail;
	    	$contact->workMobile = $workMobile;
	    	$contact->fullName = $fullName;
	    	$contact->createdAt = date("Y-m-d H:i:s");
	    	if($passportNumber){
	    		$contact->passportNumber = $passportNumber;
	    	}

	    	if($nationalIdNumber){
	    		$contact->nationalIdNumber = $nationalIdNumber;
	    	}

	    	if($nationalIdNumber){
	    		$contact->nationalIdNumber = $nationalIdNumber;
	    	}
	    	if($locationID){
	    		$contact->locationID = $locationID;
	    	}
	    	if($location){
	    		$contact->location = $location;
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
	          $contactsID = $contact->contactsID;

	    }
	    else{
	    	if($fullName){
	    		$contact->fullName = $fullName;
	    	}
	    	if($workMobile){
	    		$contact->workMobile = $workMobile;
	    	}
	    	if($workEmail){
	    		$contact->workEmail = $workEmail;
	    	}

	    	if($passportNumber){
	    		$contact->passportNumber = $passportNumber;
	    	}

	    	if($nationalIdNumber){
	    		$contact->nationalIdNumber = $nationalIdNumber;
	    	}

	    	if($passportNumber){
	    		$contact->passportNumber = $passportNumber;
	    	}
	    	if($locationID){
	    		$contact->locationID = $locationID;
	    	}
	    	if($location){
	    		$contact->location = $location;
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
	                  return $res->dataError('contact update failed',$errors);
	          }
	          $contactsID = $contact->contactsID;
	    }

	    	if($locationID){
	    		$user->locationID = $locationID;
	    	}

	    	if($roleID){
	    		$user->roleID = $roleID;
	    	}

	    	if($username){
	    		$user->username = $username;
	    	}

	        $user->contactID = $contactsID;

	          if($user->save()===false){
	            $errors = array();
	                    $messages = $user->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                  return $res->dataError('user update failed',$errors);
	          }

	      return $res->success("User updated successfully",$user);
    
	}
	
	public function login(){//usename, password
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$username = $json->username;
    	$password = $json->password;
	    $token = $json->token;

	     if(!$username || !$password || !$token){
	    		return $res->dataError("Login fields missing ");
	    	}
	      $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("Login Data compromised");
	      }

	       $username = $res->formatMobileNumber($username);
		   $user = Users::findFirst(array("username=:username:",
        					'bind'=>array("username"=>$username)));


		  if($user){
            if ($this->security->checkHash($password, $user->password)) {
                $token = $jwtManager->issueToken($user);
                $roleId = $user->roleID;
                $contactsId = $user->contactID;

                $r_query = "SELECT * FROM role WHERE roleId=$roleId";
              //  $c_query = "SELECT * FROM contacts WHERE contactsID=$contactsId ";

                $_role = $this->rawSelect($r_query);
             //   $contact = $this->rawSelect($c_query);
                $data = array();

                $data = ["token"=>$token,
                          "username"=>$user->username,
                          "role"=>$_role['roleName'],
                         // "roleID"=>$_role['roleID'],
                          "userID"=>$user->userID,
                          "contactID"=>$user->contactID
                          ];
                          
                
                return $res->success("Login successful ",$data);
            }
            return $res->unProcessable("Password missmatch ",$json);
         }
         
        return $res->notFound("User doesn't exist ",$json);

	}
	
	public function resetPassword(){ //{username, token}
    	$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$username = $json->username;
	    $token = $json->token;
	      
	 
	    if(!$username || !$token){
	    		return $res->dataError("reset password fields missing ");
	    	}
	      $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("reset password data compromised");
	      }
		
		$username = $res->formatMobileNumber($username);
		$user = Users::findFirst(array("username=:username:",
        					'bind'=>array("username"=>$username)));


	      if(!$user){
	        return $res->dataError("user not found");
	      }

			//generate code
			$code = rand(9999,99999);
 			$user->password = $this->security->hash($code);

 			 if($user->save()===false){
	            $errors = array();
	                    $messages = $user->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                  return $res->dataError('reset password failed',$errors);
	          }

          $message = "Envirofit verification code\n ".$code;
          $res->sendMessage($user->username,$message);

          $data = [
                     "username"=>$user->username,
                      "username"=>$user->username,
                       "userID"=>$user->userID];
           
        return $res->success("Password reset successfully",$data);

    }

}

