<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;


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
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');




        $countQuery = "SELECT count(userItemID) as totalUserItems from user_items";

        $selectQuery = "SELECT ui.userItemID, i.itemID,u.userID,i.serialNumber,i.status,co.workMobile,co.fullName, i.createdAt as dispatchDate, ui.createdAt as assignDate from user_items ui join item i on ui.itemID=i.itemID LEFT JOIN users u on ui.userID=u.userID LEFT JOIN contacts co on u.contactID=co.contactsID ";

        if($productID){
        	$countQuery." WHERE i.productID = $productID ";
        	$selectQuery." WHERE i.productID=$productID ";
        }


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

		$ofset = ($page-1)*$limit;
		if($sort  && $order  && $filter ){
			$query = " i.serialNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter && $limit > 0 ){
			$query = " ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter && !$limit ){
			$query = " ORDER by $sort $order  LIMIT $ofset,10";
		}
		else if(!$sort && !$order && $limit>0){
			$query = " LIMIT $ofset,$limit";
		}
		else if(!$sort && !$order && $filter && !$limit){
			$query = " i.serialNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' LIMIT $ofset,10";
		}

		else if(!$sort && !$order && $filter && $limit){
			$query = " i.serialNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' LIMIT $ofset,10";
		}

		return $query;

	}

}

