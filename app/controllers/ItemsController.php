<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT; 
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class ItemsController extends Controller
{

    public function indexAction()
    { 

    }

    protected $notAssigned = 0;
    protected $assigned = 1;
    protected $sold = 2;


     protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }

	public function create(){//{productID,serialNumber,token,status}
	    $jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$transactionManager = new TransactionManager(); 
	    $dbTransaction = $transactionManager->get();

    	$token = $json->token;
    	$serialNumber = $json->serialNumber;
    	$productID = $json->productID;
    	$status = $json->status;

    	if(!$token || !$serialNumber || !$productID ){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	      if(!$status){
	      	$status=0;
	      }


	    $item = Item::findFirst(array("serialNumber=:serialNumber:",
	    					'bind'=>array("serialNumber"=>$serialNumber)));

	    if($item){
	    	return $res->dataError("An item with the same serialNumber exists");
	    }

		 try {

			    $item = new Item();
			    $item->serialNumber = $serialNumber;
			    $item->productID = $productID;
			    $item->status=$status;
			    $item->createdAt = date("Y-m-d H:i:s");

			     if($item->save()===false){
			            $errors = array();
			                    $messages = $item->getMessages();
			                    foreach ($messages as $message) 
			                       {
			                         $e["message"] = $message->getMessage();
			                         $e["field"] = $message->getField();
			                          $errors[] = $e;
			                        }
			                 // return $res->dataError('item create failed',$errors);
			               $dbTransaction->rollback("Item create failed " . json_encode($errors));
			          }
			     $dbTransaction->commit();
			     return $res->success("Item created successfully ",$item);
			}
		catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
		   $message = $e->getMessage(); 
		   return $res->dataError('Item create error', $message); 
		}


	} 
	public function update(){//{productID,serialNumber,token,itemID,status}
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$transactionManager = new TransactionManager(); 
	    $dbTransaction = $transactionManager->get();

    	$token = $json->token;
    	$serialNumber = $json->serialNumber;
    	$productID = $json->productID;
    	$itemID = $json->itemID;
    	$status = $json->status;

    	if(!$token || !$itemID){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	     $item = Item::findFirst(array("itemID=:id:",
	    					'bind'=>array("id"=>$itemID))); 

	     if(!$item){
	     	return $res->dataError("Item not found");
	     }

	     if($productID){
	     	$item->productID = $productID;
	     }
	     if($serialNumber){
	     	$item->serialNumber = $serialNumber;
	     }
	     if($status){
	     	$item->status = $status;
	     }
	     try {

		     if($item->save()===false){
		            $errors = array();
		            $messages = $item->getMessages();
		            foreach ($messages as $message) 
		                  {
		                     $e["message"] = $message->getMessage();
		                     $e["field"] = $message->getField();
		                      $errors[] = $e;
		                    }
		              $dbTransaction->rollback("Item update failed " . json_encode($errors));
		          }

		      $dbTransaction->commit();
		      return $res->success("Item updated successfully",$item);
		    }
		catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
		   $message = $e->getMessage(); 
		   return $res->dataError('Item update error', $message); 
		}


	}
	public function getAllItems(){
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $itemID = $request->getQuery('itemID');
        $productID = $request->getQuery('productID');
        $status = $request->getQuery('status');

     //   $itemsQuery = "SELECT i.itemID,i.serialNumber,i.status,i.productID,i.createdAt FROM `user_items` ui JOIN item i on ui.itemID=i.itemID WHERE i.status=0";//ui.userID=2 AND

        if(!$token){
        	return $res->dataError("Token Missing");
        }

        $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	       $itemsQuery ="SELECT * FROM item ";
	       $condition = " ";

	       if($productID && $itemID && $status >= 0 && $status >=0){
	       	  $condition = " WHERE productID=$productID AND itemID=$itemID AND status = $status ";
	       }
	       elseif ($productID && $itemID  && $status < 0) {
	       	    $condition = " WHERE productID=$productID AND itemID=$itemID  ";
	       }
	       elseif($productID && !$itemID && !$status ){
	       	     $condition = " WHERE productID=$productID ";
	       }
	       elseif(!$productID  && $itemID  && !$status){
	       		$condition = " WHERE itemID=$itemID ";
	       }
	       elseif(!$productID  && !$itemID  && $status >=  0){
	       	    $condition = " WHERE status=$status ";
	       }
	       else{
	       	  $condition="";
	       }
	       $itemsQuery = $itemsQuery.$condition;

	     
	     
	    // return $res->success($itemsQuery);


		$items= $this->rawSelect($itemsQuery);
		return $res->success("Items fetch success",$items);

		//return $res->getSalesSuccess($items);
	}
 
	public function getTableItems(){ //sort, order, page, limit,filter
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $productID = $request->getQuery('productID');
        $userID = $request->getQuery('userID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');

        $selectQuery = "SELECT i.itemID,i.serialNumber,i.status,i.productID,i.createdAt,u.userID,co.fullName FROM item i LEFT JOIN user_items ui on i.itemID=ui.itemID LEFT JOIN users u ON ui.userID=u.userID LEFT JOIN contacts co on u.contactID=co.contactsID ";//"SELECT * FROM item i ";
        $countQuery = "SELECT count(i.itemID) as totalItems from item i";
        $condition = " WHERE ";

        if($productID && $filter){
        	//$selectQuery = $selectQuery." WHERE i.productID=$productID ";
        	$condition = " WHERE i.productID=$productID AND ";
        }

        elseif(!$productID && !$filter){
        	$condition = " WHERE ";
        }
        elseif ($productID && !$filter) {
        	$condition = " WHERE i.productID=$productID ";    
        }

        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
        	$selectQuery=$selectQuery.$condition." ".$queryBuilder;
        	if($filter){
        		$countQuery = $countQuery.$condition." ".$queryBuilder;
        	}
        	else{
        		$countQuery = $countQuery.$condition;
        	}
        }
        else{
        	$selectQuery=$selectQuery.$condition;
        	$countQuery = $countQuery.$condition;
        }

      //  return $res->success($selectQuery);
        $count = $this->rawSelect($countQuery);
		$items= $this->rawSelect($selectQuery);

		 $data["totalItems"] = $count[0]['totalItems'];
          $data["items"] = $items;
		return $res->success("Items get successfully ",$data);



	}

	public function tableQueryBuilder($sort="",$order="",$page=0,$limit=10,$filter=""){
		$query = "";

		if(!$page || $page <= 0){
			$page=1;
		}
		if(!$limit){
			$limit =10;
		}

		$ofset = ($page-1)*$limit;
		if($sort  && $order  && $filter ){
			$query = "  i.serialNumber REGEXP $filter ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter ){
			$query = " ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter ){
			$query = " ORDER by $sort $order  LIMIT $ofset,$limit";
		}
		else if(!$sort && !$order ){
			$query = " LIMIT $ofset,$limit";
		}

		else if(!$sort && !$order && $filter){
			$query = " i.serialNumber REGEXP '$filter' LIMIT $ofset,$limit";
		}

		return $query;

	}
	

	public function assignItem(){//{itemID,userID,token}
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$transactionManager = new TransactionManager(); 
	    $dbTransaction = $transactionManager->get();

    	$itemID = $json->itemID;
    	$userID = $json->userID;
    	$token = $json->token;

    	$userItem = new UserItems();

    	if(!$token || !$itemID){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }



	      $userItem = UserItems::findFirst(array("itemID=:itemId: AND userID=:userID:",
	    					'bind'=>array("itemId"=>$itemID,"userID"=>$userID))); 


	      if($userItem){
	      	return $res->dataError("Item already assigned to this user");
	      }

	      $userItem = UserItems::findFirst(array("itemID=:itemId:",
	    					'bind'=>array("itemId"=>$itemID))); 

	      if(!$userItem){ //create new item
	      	$userItem = new UserItems();
	      	$userItem->userID = $userID;
	      	$userItem->itemID = $itemID;
	      	$userItem->createdAt=date("Y-m-d H:i:s");

	      }
	      else{ //update this item
	      	$userItem->userID = $userID;
	      	$userItem->itemID = $itemID;
	      }


	  try{
	      if($userItem->save()===false){
	            $errors = array();
	            $messages = $userItem->getMessages();
	            foreach ($messages as $message) 
	                  {
	                     $e["message"] = $message->getMessage();
	                     $e["field"] = $message->getField();
	                      $errors[] = $e;
	                    }
	               $dbTransaction->rollback("Item update failed " . json_encode($errors));
	          }

	          $item = Item::findFirst(array("itemID=:id:",
	    					'bind'=>array("id"=>$itemID))); 
	          if(!$item){
	          	$dbTransaction->rollback("Item update failed, item not found " . json_encode($errors));
	          }
	          else{
	          	$item->status = $this->assigned;
	          	if($item->save()===false){
		            $errors = array();
		            $messages = $item->getMessages();
		            foreach ($messages as $message) 
		                  {
		                     $e["message"] = $message->getMessage();
		                     $e["field"] = $message->getField();
		                      $errors[] = $e;
		                    }
		               $dbTransaction->rollback("Item update failed " . json_encode($errors));
		          }
	          }
	        $dbTransaction->commit();
	       return $res->success("Items assigned successfully",$userItem);
	       
	   }
	   catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
		   $message = $e->getMessage(); 
		   return $res->dataError('Item create error', $message); 
		}
	
	}


 

}

