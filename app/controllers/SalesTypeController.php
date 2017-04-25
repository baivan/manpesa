<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT; 

class SalesTypeController extends Controller
{

	protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }

     public function create(){ //{salesTypeName,salesTypeDeposit}
     	$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();

    	$token = $json->token;
    	$salesTypeName = $json->salesTypeName;
    	$salesTypeDeposit = $json->salesTypeDeposit;

    	if(!$token || !$salesTypeName || !$salesTypeDeposit ){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	    $salesType = SalesType::findFirst(array("salesTypeName=:salesTypeName:",
	    					'bind'=>array("salesTypeName"=>$salesTypeName)));
	    if($salesType){
	    	return $res->dataError("Sales type with the same name exists");
	    }

	    $salesType = new SalesType();
	    $salesType->salesTypeName = $salesTypeName;
	    $salesType->salesTypeDeposit = $salesTypeDeposit;

	    if($salesType->save()===false){
	            $errors = array();
	                    $messages = $salesType->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                  return $res->dataError('salesType create failed',$errors);
	          }

	     return $res->success("Sales Type created successfully ",$salesType);

     }
     public function update(){//{salesTypeName,salesTypeDeposit,salesTypeID}

     	$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();

    	$token = $json->token;
    	$salesTypeName = $json->salesTypeName;
    	$salesTypeDeposit = $json->salesTypeDeposit;
    	$salesTypeID = $json->salesTypeID;


    	if(!$token || !$salesTypeID ){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	    $salesType = SalesType::findFirst(array("salesTypeID=:salesTypeID:",
	    					'bind'=>array("salesTypeID"=>$salesTypeID)));
	    if(!$salesType){
	    	return $res->dataError("Sales type does not exist");
	    }

	    if($salesTypeName){
	    	$salesType->salesTypeName = $salesTypeName;
	    }

	    if($salesTypeName){
	    	 $salesType->salesTypeDeposit = $salesTypeDeposit;
	    }
	    
	   

	    if($salesType->save()===false){
	            $errors = array();
	                    $messages = $salesType->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                  return $res->dataError('salesType update failed',$errors);
	          }

	     return $res->success("Sales Type updated successfully ",$salesType);

 	}
    // public function delete(){}
     public function getAll(){
     	$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $salesTypeID = $request->getQuery('salesTypeID');

        $salesTypeQuery = "SELECT * FROM sales_type ";

         if(!$token){
        	return $res->dataError("Token Missing");
        }

        $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

        if($salesTypeID>0){
        	$salesTypeQuery = "SELECT * FROM sales_type WHERE salesTypeID=$salesTypeID";
        }

        $salesType= $this->rawSelect($salesTypeQuery);

		return $res->success("Sales types",$salesType);
     }
     

     public function getTableSaleTypes(){ //sort, order, page, limit,filter
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

	        $countQuery = "SELECT count(salesTypeID) as totalSalesTypes from sales_type";

	        $selectQuery = "SELECT * FROM `sales_type` st ";

	      

	        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

	        if($queryBuilder){
	        	$selectQuery=$selectQuery." ".$queryBuilder;
	        }
	        //return $res->success($selectQuery);

	        $count = $this->rawSelect($countQuery);

			$salesTypes= $this->rawSelect($selectQuery);
	//users["totalUsers"] = $count[0]['totalUsers'];
			$data["totalSalesTypes"] = $count[0]['totalSalesTypes'];
			$data["salesTypes"] = $salesTypes;

			return $res->getSalesSuccess($data);


	}

	public function tableQueryBuilder($sort="",$order="",$page=0,$limit=10,$filter=""){
		$query = "";

		if(!$page || $page <= 0){
			$page=1;
		}

		$ofset = ($page-1)*$limit;
		if($sort  && $order  && $filter ){
			$query = " WHERE st.salesTypeName  REGEXP '$filter' OR st.salesTypeDeposit  REGEXP '$filter'  ORDER by st.$sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter && $limit > 0 ){
			$query = " ORDER by st.$sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter && !$limit ){
			$query = " ORDER by st.$sort $order  LIMIT $ofset,10";
		}
		else if(!$sort && !$order && $limit>0){
			$query = " LIMIT $ofset,$limit";
		}
		else if(!$sort && !$order && $filter && !$limit){
			$query = " WHERE st.salesTypeName  REGEXP '$filter' OR st.salesTypeDeposit  REGEXP '$filter'  LIMIT $ofset,10";
		}

		else if(!$sort && !$order && $filter && $limit){
			$query = " WHERE st.salesTypeName  REGEXP '$filter' OR st.salesTypeDeposit  REGEXP '$filter'  LIMIT $ofset,$limit";
		}

		return $query;

	}



}

