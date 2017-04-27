<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;


class UserItemsController extends Controller
{
	protected function rawSelect($statement)
		       { 
		          $connection = $this->di->getShared("db"); 
		          $success = $connection->query($statement);
		          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
		          $success = $success->fetchAll($success); 
		          return $success;
		       }


    public function getTableUserItems(){ //sort, order, page, limit,filter
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



        $basicQuery = "FROM user_items ui join item i on ui.itemID=i.itemID LEFT JOIN users u on ui.userID=u.userID LEFT JOIN contacts co on u.contactID=co.contactsID LEFT JOIN product on i.productID=p.productID";
        $countQuery = "SELECT count(userItemID) as totalUserItems ";
        $selectQuery = "SELECT ui.userItemID, i.itemID,u.userID,i.serialNumber,i.status,co.workMobile,co.fullName, i.createdAt, as dispatchDate, ui.createdAt as assignDate,i.productID,p.productName ";
        $condition=" ";
 
        if($productID && $userID && !$filter){
        	//$countQuery=$countQuery.$basicQuery." WHERE i.productID = $productID AND ui.userID=$userID ";
        	//$selectQuery=$selectQuery.$basicQuery." WHERE i.productID=$productID AND ui.userID=$userID ";
        	$condition = " WHERE i.productID = $productID AND ui.userID=$userID ";

        }
        elseif($productID && $userID && $filter){
        	$condition = " WHERE i.productID = $productID AND ui.userID=$userID AND ";
        }
        elseif ($productID && !$userID && !$filter) {
        	//$countQuery=$countQuery.$basicQuery." WHERE i.productID = $productID  ";
        	//$selectQuery=$selectQuery.$basicQuery." WHERE i.productID=$productID ";
        	$condition = " WHERE i.productID=$productID ";
        }
        elseif ($productID && !$userID && $filter) {
        	//$countQuery=$countQuery.$basicQuery." WHERE i.productID = $productID  ";
        	//$selectQuery=$selectQuery.$basicQuery." WHERE i.productID=$productID ";
        	$condition = " WHERE i.productID=$productID AND ";
        }
        elseif (!$productID && $userID && !$filter) {
        	//$countQuery=$countQuery.$basicQuery." WHERE i.userID = $userID  ";
        	//$selectQuery=$selectQuery.$basicQuery." WHERE i.userID=$userID ";
        	$condition = " WHERE ui.userID=$userID ";
        }
        elseif (!$productID && $userID && $filter) {
        	//$countQuery=$countQuery.$basicQuery." WHERE i.userID = $userID  ";
        	//$selectQuery=$selectQuery.$basicQuery." WHERE i.userID=$userID ";
        	$condition = " WHERE ui.userID=$userID AND ";
        }
        elseif(!$productID && !$userID && $filter){
        	 
        	//$countQuery=$countQuery.$basicQuery." WHERE ";
        	//$selectQuery=$selectQuery.$basicQuery." WHERE  ";
        	$condition = " WHERE ";
        }
        else{
        	$condition = " ";
        }

        $selectQuery=$selectQuery.$basicQuery.$condition;
        $countQuery =$countQuery.$basicQuery.$condition;

        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
        	$selectQuery=$selectQuery." ".$queryBuilder;
        }
     //  return $res->success($selectQuery);

        $count = $this->rawSelect($countQuery);

		$userItems= $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
		$data["totalUserItems"] = $count[0]['totalUserItems'];
		$data["userItems"] = $userItems;
 
		return $res->success("User items ",$data);


	}

	public function tableQueryBuilder($sort="",$order="",$page=0,$limit=10,$filter=""){
		$query = "";

		if(!$page || $page <= 0){
			$page=1;
		}
		if(!$limit){
			$limit=10;
		}

		$ofset = ($page-1)*$limit;
		if($sort  && $order  && $filter ){
			$query = " i.serialNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter  ){
			$query = " ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter  ){
			$query = " ORDER by $sort $order  LIMIT $ofset,10";
		}
		else if(!$sort && !$order && $limit>0){
			$query = " LIMIT $ofset,$limit";
		}
		else if(!$sort && !$order && $filter ){
			$query = " i.serialNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' LIMIT $ofset,$limit";
		}

		else if(!$sort && !$order && $filter && $limit){
			$query = " i.serialNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' LIMIT $ofset,$limit";
		}

		return $query;

	}

}

