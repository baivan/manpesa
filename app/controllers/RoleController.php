<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class RoleController extends Controller
{
	protected function rawSelect($statement)
		       { 
		          $connection = $this->di->getShared("db"); 
		          $success = $connection->query($statement);
		          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
		          $success = $success->fetchAll($success); 
		          return $success;
		       }

     public function create(){ //{roleName,roleDescription}
     			$jwtManager = new JwtManager();
		    	$request = new Request();
		    	$res = new SystemResponses();
		    	$json = $request->getJsonRawBody();
		    	$transactionManager = new TransactionManager(); 
	            $dbTransaction = $transactionManager->get();

		    	$roleName = $json->roleName;
		    	$roleDescription = $json->roleDescription;
		    	$token = $json->token;

		    	if(!$token || !$roleName || !$roleDescription){
			    	return $res->dataError("Missing data ");
			    }

			    $tokenData = $jwtManager->verifyToken($token,'openRequest');
		       if(!$tokenData){
		         return $res->dataError("Data compromised");
		       }

		       $role = Role::findFirst(array("roleName=:name: ",
			    					'bind'=>array("name"=>$roleName)));
		       if($role){
		       	return $res->dataError("Role with similar name exists");
		       }

		       try{

			       $role = new Role();
			       $role->roleName = $roleName;
			       $role->roleDescription = $roleDescription;
			       $role->createdAt = date("Y-m-d H:i:s");

			        if($role->save()===false){
				            $errors = array();
				                    $messages = $role->getMessages();
				                    foreach ($messages as $message) 
				                       {
				                         $e["message"] = $message->getMessage();
				                         $e["field"] = $message->getField();
				                          $errors[] = $e;
				                        }
				                //  return $res->dataError('role create failed',$errors);
				                 $dbTransaction->rollback("role create failed " . $errors);
				          }
				       $dbTransaction->commit();

				      return $res->success("role saved successfully",$role);
			  }

	     catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
			   $message = $e->getMessage(); 
			   return $res->dataError('user create error', $message); 
		}

     }
     public function update(){//{roleID,roleName,roleDescription}
     		   $jwtManager = new JwtManager();
		    	$request = new Request();
		    	$res = new SystemResponses();
		    	$json = $request->getJsonRawBody();
		    	$transactionManager = new TransactionManager(); 
	            $dbTransaction = $transactionManager->get();
		    	$roleName = $json->roleName;
		    	$roleID = $json->roleID;
		    	$roleDescription = $json->roleDescription;
		    	$token = $json->token;

		    	if(!$token || !$roleName || !$roleDescription){
			    	return $res->dataError("Missing data ");
			    }

			    $tokenData = $jwtManager->verifyToken($token,'openRequest');
		       if(!$tokenData){
		         return $res->dataError("Data compromised");
		       }

		       $role = Role::findFirst(array("roleID=:id: ",
			    					'bind'=>array("id"=>$roleID)));
		       if(!$role){
		       	return $res->dataError("Role doesnt exist");
		       }

		       if($roleName){
		       	   $role->roleName = $roleName;
		       }
		       if($roleDescription){
		       	   $role->roleDescription = $roleDescription;
		       }

		       try {
			       	if($role->save()===false){
				            $errors = array();
				                    $messages = $role->getMessages();
				                    foreach ($messages as $message) 
				                       {
				                         $e["message"] = $message->getMessage();
				                         $e["field"] = $message->getField();
				                          $errors[] = $e;
				                        }
				                   $dbTransaction->rollback("role update failed " . $errors);
				          }
				       $dbTransaction->commit();
				      return $res->success("role updated successfully",$role);
		       	 
		       }  
		       catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
				   $message = $e->getMessage(); 
				   return $res->dataError('user create error', $message); 
				}

		        

     }
     public function getAll(){
     	    
     	    $jwtManager = new JwtManager();
	    	$request = new Request();
	    	$res = new SystemResponses();
	    	$token = $request->getQuery('token');
	        $roleID = $request->getQuery('roleID');
	        $selectQuery = "SELECT * FROM role ";

	          if(!$token){
		        	return $res->dataError("Missing data ");
		        }

		        $tokenData = $jwtManager->verifyToken($token,'openRequest');
		       if(!$tokenData){
		         return $res->dataError("Data compromised");
		       }
		       if($roleID){
		       	  $selectQuery=$selectQuery." WHERE roleID=$roleID";
		       }

		        $roles = $this->rawSelect($selectQuery);

		       return $res->success("roles",$roles);

		      
     }
     

}

