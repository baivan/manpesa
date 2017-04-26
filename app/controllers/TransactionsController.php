<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class TransactionsController extends Controller
{

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

        $countQuery = "SELECT count(c.transactionID) as totalTransaction ";
        $baseQuery = " from transaction  c join contacts co on c.contactsID=co.contactsID ";
        $selectQuery = "SELECT c.customerID, co.fullName,co.nationalIdNumber,co.workMobile,co.location, c.createdAt  ";
        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
        	$selectQuery=$selectQuery.$baseQuery." ".$queryBuilder;
        	if($filter){
        		$countQuery = $countQuery.$baseQuery." ".$queryBuilder;
        	}
        	else{
        		$countQuery = $countQuery.$baseQuery;
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


}

