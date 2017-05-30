<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class FrequencyController extends Controller
{
	protected $dailyAmount = 35;

    protected function rawSelect($statement){ 
	      $connection = $this->di->getShared("db"); 
	      $success = $connection->query($statement);
	      $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
	      $success = $success->fetchAll($success); 
	      return $success;
	   }
	
	public function create(){//{numberOfDays,frequencyName,token}
		  $jwtManager = new JwtManager();
		  $request = new Request();
		  $res = new SystemResponses();
		  $json = $request->getJsonRawBody();
		  $token = $json->token;
		  $numberOfDays = $json->numberOfDays;
		  $frequencyName = $json->frequencyName;

		  if(!$token || !$numberOfDays || !$frequencyName){
				return $res->dataError("Missing data ");
			}

		    $tokenData = $jwtManager->verifyToken($token,'openRequest');

	       if(!$tokenData){
	         return $res->dataError("Data compromised");
	       }

	       $frequency = Frequency::findFirst(array("frequencyName=:name: AND numberOfDays=:number:",
				    					'bind'=>array("name"=>$frequencyName,"number"=>$numberOfDays)));

	       if($frequency){
	       	return $res->dataError("frequency with similar name exists");
	       }

	        $frequency = new Frequency();
	       $frequency->numberOfDays = $numberOfDays;
	       $frequency->frequencyName = $frequencyName;
	       $frequency->createdAt = date("Y-m-d H:i:s");

	        if($frequency->save()===false){
		            $errors = array();
		                    $messages = $frequency->getMessages();
		                    foreach ($messages as $message) 
		                       {
		                         $e["message"] = $message->getMessage();
		                         $e["field"] = $message->getField();
		                          $errors[] = $e;
		                        }
		                  return $res->dataError('Frequency create failed',$errors);
		          }


		   return $res->success('Frequency created',$frequency);

	}
	
	public function update(){//{numberOfDays,frequencyName,token,frequencyID}
		  $jwtManager = new JwtManager();
		  $request = new Request();
		  $res = new SystemResponses();
		  $json = $request->getJsonRawBody();
		  $token = $json->token;
		  $numberOfDays = $json->numberOfDays;
		  $frequencyName = $json->frequencyName;
		  $frequencyID = $json->frequencyID;
		  $status = $json->status;

		  if(!$token || !$frequencyID){
				return $res->dataError("Missing data ");
			}

		    $tokenData = $jwtManager->verifyToken($token,'openRequest');

	       if(!$tokenData){
	         return $res->dataError("Data compromised");
	       }

	       $frequency = Frequency::findFirst(array("frequencyID=:id:",
				    					'bind'=>array("id"=>$frequencyID)));

	       if(!$frequency){
	       	return $res->dataError("frequency with similar name exists");
	       }

	       //$frequency = new Frequency();
	       if($numberOfDays){
	       	 $frequency->numberOfDays = $numberOfDays;
	       }

	       if($frequencyName){
	       	  $frequency->frequencyName = $frequencyName;
	       }
	       if($status){
	       	  $frequency->status = $status;
	       }

	        if($frequency->save()===false){
		            $errors = array();
		                    $messages = $frequency->getMessages();
		                    foreach ($messages as $message) 
		                       {
		                         $e["message"] = $message->getMessage();
		                         $e["field"] = $message->getField();
		                          $errors[] = $e;
		                        }
		                  return $res->dataError('Frequency update failed',$errors);
		          }


		   return $res->success('Frequency updated',$frequency);
	}
	
	public function getAll(){
		       $jwtManager = new JwtManager();
		    	$request = new Request();
		    	$res = new SystemResponses();
		    	$token = $request->getQuery('token');
		        $frequencyID = $request->getQuery('frequencyID');

		        $frequencyQuery = "SELECT * FROM frequency WHERE status=1";

		        if(!$token  ){
				    	return $res->dataError("Missing data ");
				 }

			    $tokenData = $jwtManager->verifyToken($token,'openRequest');
		       if(!$tokenData){
		         return $res->dataError("Data compromised");
		       }

		       if($frequencyID){
		       	  $frequencyQuery = "SELECT * FROM frequency WHERE status=1 and frequencyID=$frequencyID ";
		       }

		       $frequencies = $this->rawSelect($frequencyQuery);

		       $data = array();
		       $data['frequencies'] = $frequencies;
		       $data['dailyAmount'] = $dailyAmount;


		       //return $res->getSalesSuccess($frequencies);
		       return $res->success("Prices ", $data);
	}
	
	 public function getTableFrequency(){ //sort, order, page, limit,filter
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

	        $countQuery = "SELECT count(frequencyID) as totalFrequency from frequency";

	        $selectQuery = "SELECT * FROM `frequency` f ";

	      

	        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

	        if($queryBuilder){
	        	$selectQuery=$selectQuery." ".$queryBuilder;
	        }
	        //return $res->success($selectQuery);

	        $count = $this->rawSelect($countQuery);

			$salesTypes= $this->rawSelect($selectQuery);
	//users["totalUsers"] = $count[0]['totalUsers'];
			$data["totalFrequency"] = $count[0]['totalFrequency'];
			$data["salesTypes"] = $salesTypes;

			return $res->success("frequencies",$data);


	}

	public function tableQueryBuilder($sort="",$order="",$page=0,$limit=10,$filter=""){
		$query = "";
		if(!$page || $page <= 0){
			$page=1;
		}

		$ofset = ($page-1)*$limit;
		if($sort  && $order  && $filter ){
			$query = " WHERE f.numberOfDays  REGEXP '$filter' OR f.frequencyName  REGEXP '$filter'  ORDER by f.$sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter && $limit > 0 ){
			$query = " ORDER by f.$sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter && !$limit ){
			$query = " ORDER by f.$sort $order  LIMIT $ofset,10";
		}
		else if(!$sort && !$order && $limit>0){
			$query = " LIMIT $ofset,$limit";
		}
		else if(!$sort && !$order && $filter && !$limit){
			$query = " WHERE f.numberOfDays  REGEXP '$filter' OR f.frequencyName  REGEXP '$filter'  LIMIT $ofset,10";
		}

		else if(!$sort && !$order && $filter && $limit){
			$query = " WHERE f.numberOfDays  REGEXP '$filter' OR f.frequencyName  REGEXP '$filter'  LIMIT $ofset,$limit";
		}

		return $query;

	}


}

