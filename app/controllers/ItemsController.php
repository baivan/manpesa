<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT; 

class ItemsController extends Controller
{

    public function indexAction()
    {

    }

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
	                  return $res->dataError('item create failed',$errors);
	          }

	     return $res->success("Item created successfully ",$item);



	} 
	public function update(){//{productID,serialNumber,token,itemID,status}
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();

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

	     if($item->save()===false){
	            $errors = array();
	            $messages = $item->getMessages();
	            foreach ($messages as $message) 
	                  {
	                     $e["message"] = $message->getMessage();
	                     $e["field"] = $message->getField();
	                      $errors[] = $e;
	                    }
	              return $res->dataError('item edit failed',$errors);
	          }
	      return $res->success("Item updated successfully",$item);


	}
	public function getAllItems(){
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $itemID = $request->getQuery('itemID');
        $userID = $request->getQuery('userID');
        $productID = $request->getQuery('productID');
        $status = $request->getQuery('status');

        $itemsQuery = "SELECT i.itemID,i.serialNumber,i.status,i.productID,i.createdAt FROM `user_items` ui JOIN item i on ui.itemID=i.itemID WHERE i.status=0";//ui.userID=2 AND 

        if(!$token){
        	return $res->dataError("Token Missing");
        }

        $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }



	      if($itemID && $itemID > 0 ){
	      	 $itemsQuery = "SELECT i.itemID,i.serialNumber,i.status,i.productID,i.createdAt FROM `user_items` ui JOIN item i on ui.itemID=i.itemID WHERE i.itemID=$itemID";//ui.userID=2 AND 
	      }

	      elseif($userID && $userID > 0 && $productID && $productID > 0 && $status && $status > 0){
	      	 $itemsQuery = "SELECT  i.itemID,i.serialNumber,i.status,i.productID,i.createdAt FROM item i LEFT JOIN user_items ui on i.itemID =ui.itemID WHERE  i.status=$status and ui.userID=$userID and i.productID=$productID";
	      }

	      elseif($userID && $userID > 0 && $status && $status > 0 && !$productID && $productID <=0){
	      	$itemsQuery = "SELECT  i.itemID,i.serialNumber,i.status,i.productID,i.createdAt FROM item i LEFT JOIN user_items ui on i.itemID =ui.itemID WHERE  i.status=$status and ui.userID=$userID";//ui.userID=2 AND 
	      }

	      elseif($userID && $userID > 0 && $productID && $productID > 0 && !$status && $status <= 0){
	      	 $itemsQuery = "SELECT  i.itemID,i.serialNumber,i.status,i.productID,i.createdAt FROM item i LEFT JOIN user_items ui on i.itemID =ui.itemID WHERE  i.productID=$productID and ui.userID=$userID";//ui.userID=2 AND 
	      }

	      elseif($productID && $productID > 0 && $status && $status > 0 && !$userID && $userID <=0 ){
	      	 $itemsQuery = "SELECT i.itemID,i.serialNumber,i.status,i.productID,i.createdAt FROM item i WHERE i.productID=$productID AND i.status=$status";
	      }

	      elseif(!$userID && $userID <=0 && !$productID && $productID <= 0 && !$status && $status <= 0){
	      	 $itemsQuery = "SELECT i.itemID,i.serialNumber,i.status,i.productID,i.createdAt FROM item i ";
	      }

	      elseif(!$userID && $userID <=0 && $productID && $productID > 0 && !$status && $status <= 0){
	      	 $itemsQuery = "SELECT i.itemID,i.serialNumber,i.status,i.productID,i.createdAt FROM item i WHERE i.productID=$productID";
	      }
	      


		$items= $this->rawSelect($itemsQuery);
		return $res->success($itemsQuery,$items);

		//return $res->getSalesSuccess($items);
	}
 
	public function getTableItems(){ //sort, order, page, limit,filter
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $productID = $request->getQuery('productID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');

        $selectQuery = "SELECT * FROM item i ";

      

       /* if(!$productID || $productID < 0){
        	return $res->dataError("please provide product id");
        }*/

        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
        	$selectQuery=$selectQuery." ".$queryBuilder;
        }

        //return $res->success($selectQuery);

		$items= $this->rawSelect($selectQuery);
		return $res->getSalesSuccess($items);


	}

	public function tableQueryBuilder($sort="",$order="",$page=0,$limit=10,$filter=""){
		$query = "";

		$ofset = ($page-1)*$limit;
		if($sort  && $order  && $filter ){
			$query = " WHERE i.serialNumber REGEXP $filter ORDER by $sort $order LIMIT $ofset,$limit";
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
			$query = " WHERE i.serialNumber REGEXP '$filter' LIMIT $ofset,$limit";
		}

		return $query;

	}
	

	public function assignItem(){//{itemID,userID,token}
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
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

	      if($userItem->save()===false){
	            $errors = array();
	            $messages = $userItem->getMessages();
	            foreach ($messages as $message) 
	                  {
	                     $e["message"] = $message->getMessage();
	                     $e["field"] = $message->getField();
	                      $errors[] = $e;
	                    }
	              return $res->dataError('item assign failed',$errors);
	          }


	       return $res->getSalesSuccess($userItem);
	
	}
 

}

