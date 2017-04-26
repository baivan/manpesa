<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;

class CustomerController extends Controller
{
	protected function rawSelect($statement)
		       { 
		          $connection = $this->di->getShared("db"); 
		          $success = $connection->query($statement);
		          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
		          $success = $success->fetchAll($success); 
		          return $success;
		       }

   public function getTableCustomers(){ //sort, order, page, limit,filter
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');

        $countQuery = "SELECT count(customerID) as totalCustomers ";
        $baseQuery = " from customer  c join contacts co on c.contactsID=co.contactsID ";
        $selectQuery = "SELECT c.customerID, co.fullName,co.nationalIdNumber,co.workMobile,co.location, c.createdAt  ";
        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
        	$selectQuery=$selectQuery.$baseQuery." ".$queryBuilder;
        	if($filter){
        		$countQuery = $countQuery.$baseQuery." ".$queryBuilder;
        	}
        	
        }
        else{
        	$selectQuery=$selectQuery.$baseQuery;
        	$countQuery = $countQuery.$baseQuery;
        }
        //return $res->success($queryBuilder);

        $count = $this->rawSelect($countQuery);

		$customers= $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
		$data["totalCustomers"] = $count[0]['totalCustomers'];
		$data["customers"] = $customers;

		return $res->success("customers",$data);


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
			$query = " WHERE co.fullName REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.nationalIdNumber REGEXP '$filter' ORDER by $sort $order LIMIT $ofset,$limit";
		}
		elseif($sort  && $order  && !$filter  ){
			$query = " ORDER by $sort $order LIMIT $ofset,$limit";
		}
		elseif($sort  && !$order  && !$filter  ){
			$query = " ORDER by $sort  LIMIT $ofset,$limit";
		}
		elseif(!$sort && !$order && !$filter ){
			$query = " LIMIT $ofset,$limit";
		}
		
		elseif(!$sort && !$order && $filter ){
			$query = " WHERE co.fullName REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.nationalIdNumber REGEXP '$filter' LIMIT $ofset,$limit";
		}

		return $query;

	}

	public function getAll(){
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $customerID = $request->getQuery('customerID');
        $userID = $request->getQuery('userID');
 
        if(!$token){
	    	return $res->dataError("Missing data ");
	    }
	    $customerQuery = "SELECT * FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID ";

	    
	    if($userID && !$customerID){
	    	$customerQuery = "SELECT * FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID AND cu.userID=$userID";
	    }
        elseif($customerID && !$userID){
        	$customerQuery = "SELECT * FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID where cu.userID=$userID AND p.customerID=$customerID";
        }
        elseif($userID && $customerID){
        	$customerQuery = "SELECT * FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID AND cu.userID=$userID where p.userID=$userID AND cu.customerID=$customerID";
        }
        else{
        	$customerQuery = "SELECT * FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID ";
        }

        $customers = $this->rawSelect($customerQuery);

        return $res->success("Customers are ",$customers);

	}


}

