<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;


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
			                  return $res->dataError('role create failed',$errors);
			          }

			      return $res->success("role saved successfully",$role);

     }
     public function update(){//{roleID,roleName,roleDescription}
     		   $jwtManager = new JwtManager();
		    	$request = new Request();
		    	$res = new SystemResponses();
		    	$json = $request->getJsonRawBody();
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

		        if($role->save()===false){
			            $errors = array();
			                    $messages = $role->getMessages();
			                    foreach ($messages as $message) 
			                       {
			                         $e["message"] = $message->getMessage();
			                         $e["field"] = $message->getField();
			                          $errors[] = $e;
			                        }
			                  return $res->dataError('role update failed',$errors);
			          }

			      return $res->success("role updated successfully",$role);

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

		       return $res->getSalesSuccess($roles);

		      
     }
     

}

