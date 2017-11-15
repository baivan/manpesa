<?php
use Phalcon\Mvc\Model\Query\Builder as Builder;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Http\Request;

class UsersController  extends ControllerBase
{

    //login

    public function login(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $username = isset($json->username) ? $json->username : NULL;
        $password = isset($json->password) ? $json->password :NULL;
        $token = isset($json->token) ? $json->token : NULL;

        if (!$token || !$username) {
            return $res->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token, 'register');

        if (!$tokenData) {
            return $res->dataError("Login Data compromised");
        }

        $user = Users::findFirst(array("mobile=:username: OR email=:username: ",
                        'bind' => array("username" => $username))); 

        if($user){
            if ($this->security->checkHash($password, $user->password)) {
                 $data['userID'] = $user->userID;
                 $data['mobile'] = $user->mobile;
                 $data['name'] = $user->fullName;
                 $data['email'] = $user->email;
                 $data['token'] = $jwtManager->issueToken($user);
                return $res->success("Login successful ", $data);
            }
            else{
                return $res->dataError("Invalid password");
            }

        }
        else {
            return $res->dataError("Login Failed");
        }

        

    }



    //registero

    public function register(){
    	$jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $mobile = isset($json->mobile) ? $json->mobile : NULL;
        $email = isset($json->email) ? $json->email : NULL;
        $regID = isset($json->regID) ? $json->regID : NULL;
        $password = isset($json->password) ? $json->password :NULL;
        $token = isset($json->token) ? $json->token : NULL;
        $fullName = isset($json->fullName) ? $json->fullName : NULL;

        if (!$token || !$mobile || !$email || !$fullName || !$password) {
            return $res->dataError("Missing data here".json_encode($json));
        }
        $tokenData = $jwtManager->verifyToken($token, 'register');

        if (!$tokenData) {
            return $res->dataError("Login Data compromised");
        }

        try {
        	$user = Users::findFirst(array("mobile=:mobile: OR email=:email: ",
                        'bind' => array("mobile" => $mobile,"email"=>$email)));

	        if($user){
	        	return $res->dataError("User with same email or mobile exists");
	        }

	        $user = new Users();
	        $user->mobile = $mobile;
	        $user->email = $email;
	        $user->regID = $regID;
            $user->fullName = $fullName;
	        $user->password = $this->security->hash($password);
	        $user->createdAt = date("Y-m-d H:i:s");

	         if ($user->save() === false) {
	                $errors = array();
	                $messages = $user->getMessages();
	                foreach ($messages as $message) {
	                    $e["message"] = $message->getMessage();
	                    $e["field"] = $message->getField();
	                    $errors[] = $e;
	                }
	                //return $res->dataError('user create failed',$errors);
	                $dbTransaction->rollback("user create failed " . json_encode($errors));
	            }

	         $dbTransaction->commit();

	         $data['userID'] = $user->userID;
	         $data['mobile'] = $user->mobile;
	         $data['name'] = $user->fullName;
	         $data['email'] = $user->email;
	         $data['token'] = $jwtManager->issueToken($user);
 
	         return $res->success("Regestration successful ", $data);
        	
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('user create error', $message);
        }

        
    }

    //reset password
    public function resetPassword(){
    	$jwtManager = new JwtManager();
        $request = new Request();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

     
        $userID = isset($json->userID) ? $json->userID : NULL;
        $regID = isset($json->regID) ? $json->regID : NULL;
        $password = isset($json->password) ? $json->password :NULL;
        $token = isset($json->token) ? $json->token : NULL;
        
         if (!$token || !$mobile || !$email || !$fullName) {
            return $this->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token);

        if (!$tokenData) {
            return $this->dataError("Login Data compromised");
        }
        elseif($tokenData->userId != $userID){
        	return $this->dataError("Login Data compromised");
        }
        try {
        	$user = User::findFirst(array("userID=:id: ",
	                        'bind' => array("id" => $userID)));

	        if(!$user){
	        	return $this->notFound("User not found");
	        }

	        $user->password = $this->security->hash($password);


	         if ($user->save() === false) {
		                $errors = array();
		                $messages = $user->getMessages();
		                foreach ($messages as $message) {
		                    $e["message"] = $message->getMessage();
		                    $e["field"] = $message->getField();
		                    $errors[] = $e;
		                }
		                //return $res->dataError('user create failed',$errors);
		                $dbTransaction->rollback("user reset failed " . json_encode($errors));
		            }

		      $dbTransaction->commit();
		      return $res->success("User password reset successful");

        	
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('user password reset error', $message);
        }

    }

    //update 
    public function update(){
    	$jwtManager = new JwtManager();
        $request = new Request();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

     
        $mobile = isset($json->mobile) ? $json->mobile : NULL;
        $email = isset($json->email) ? $json->email : NULL;
        $regID = isset($json->regID) ? $json->regID : NULL;
        $token = isset($json->token) ? $json->token : NULL;
        $fullName = isset($json->fullName) ? $json->fullName : NULL;
        $userID = isset($json->userID) ? $json->userID : NULL;
        
         if (!$token && !$userID) {
            return $this->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token);

        if (!$tokenData) {
            return $this->dataError("Update Data compromised");
        }
        elseif($tokenData->userId != $userID){
        	return $this->dataError("Update Data compromised");
        }
        try {
        	$user = User::findFirst(array("userID=:id: ",
	                        'bind' => array("id" => $userID)));

	        if(!$user){
	        	return $this->notFound("User not found");
	        }
	        if($mobile){
	        	$user->mobile = $mobile;
	        }
	        if($email){
	        	$user->email = $email;
	        	
	        }
	        if($regID){
	        	$user->regID = $regID;
	        	
	        }
	        if($fullName){
	        	$user->fullName = $fullName;
	        }

	         if ($user->save() === false) {
		                $errors = array();
		                $messages = $user->getMessages();
		                foreach ($messages as $message) {
		                    $e["message"] = $message->getMessage();
		                    $e["field"] = $message->getField();
		                    $errors[] = $e;
		                }
		                //return $res->dataError('user create failed',$errors);
		                $dbTransaction->rollback("user update failed " . json_encode($errors));
		            }

		      $dbTransaction->commit();
		      return $res->success("User updated  successful");

        	
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('user updated  error', $message);
        }

    }

   /* public function generateTocken(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();

        return $this->success("Token ",$jwtManager->issueToken());
    }*/
    


}

