<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class TransactionsController extends Controller
{
	 private $salePaid = 1;

	protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }


	public function create(){ //{mobile,account,referenceNumber,amount,fullName,token}
	    $jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$transactionManager = new TransactionManager(); 
        $dbTransaction = $transactionManager->get();

        $mobile = $json->mobile;
        $referenceNumber = $json->referenceNumber;
        $fullName = $json->fullName;
        $depositAmount = $json->amount;
        $nationalID = $json->account;
        $token = $json->token;

        if(!$token ){
	    	return $res->dataError("Token missing ");
	    }

	    $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	   try {
	   	   $transaction = new Transaction();
	   	   $transaction->mobile=$mobile;
	   	   $transaction->referenceNumber = $referenceNumber;
	   	   $transaction->fullName=$fullName;
	   	   $transaction->depositAmount=$depositAmount;
	   	   $nationalID ->nationalID = $nationalID;
	   	   $transaction->salesID=0;
	   	   $transaction->createdAt = date("Y-m-d H:i:s");

	   	   if($transaction->save()===false){
	            $errors = array();
	                    $messages = $transaction->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	               //return $res->dataError('sale create failed',$errors);
	                $dbTransaction->rollback('transaction create failed' . json_encode($errors));  
	          }
	        $sale = Sales::findFirst(array("salesID=:id: ",
	    					'bind'=>array("id"=>$salesID))); 
	       $sale->status = $this->$salePaid;

	        if($sale->save()===false){
	            $errors = array();
	                    $messages = $sale->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	               //return $res->dataError('sale create failed',$errors);
	                $dbTransaction->rollback('transaction create failed' . json_encode($errors));  
	          }
	          $dbTransaction->commit();

	       //$res->sendMessage($mobile,"Dear ".$fullName.", your payment has been received");

	       $userQuery = "SELECT userID as userId from sales WHERE salesID=$salesID";



	       $userID = $this->rawSelect($userQuery);
	       $pushNotificationData = array();
	       $pushNotificationData['nationalID']=$nationalID;
	       $pushNotificationData['mobile'] = $mobile;
	       $pushNotificationData['amount'] = $amount;
	       $pushNotificationData['saleAmount'] = $sale->amount;
	       $pushNotificationData['fullName']=$fullName;

	       $res->sendPushNotification($pushNotificationData,"New payment","There is a new payment from a sale you made",$userID);

	       
	      return $res->success("Transaction successfully done ",$transaction);

	   }
	    catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
	       $message = $e->getMessage(); 
	       return $res->dataError('sale create error', $message); 
       }

	}

	public function checkPayment(){//{token,salesID}
	   $jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$transactionManager = new TransactionManager(); 
        $dbTransaction = $transactionManager->get();
        $token = $json->token;
      //  $userID = $json->userID;
        $salesID = $json->salesID;

       // $isPaid = $this->checkSalePaid($salesID);
        
        $getAmountQuery = "SELECT SUM(t.depositAmount) amount, s.amount as saleAmount, st.salesTypeDeposit FROM transaction t join sales s on t.salesID=s.salesID  JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID join sales_type st on pp.salesTypeID=st.salesTypeID WHERE t.salesID=$salesID ";
        	$transaction = $this->rawSelect($getAmountQuery);

        	return $res->success("Sale paid",$transaction[0]);  
       
	}

	public function checkSalePaid($salesID){
			$transactionQuery = "SELECT SUM(t.depositAmount) amount, s.amount as saleAmount, st.salesTypeDeposit FROM transaction t join sales s on t.salesID=s.salesID  JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID join sales_type st on pp.salesTypeID=st.salesTypeID WHERE t.salesID=$salesID ";

        $transaction = $this->rawSelect($transactionQuery);
    if($transaction[0]["amount"] >= $transaction[0]["saleAmount"] || $transaction[0]["amount"] >= $transaction[0]["salesTypeDeposit"] )
        {
        	
        	return true;
        }
       else{
       	return false;
       }
	}



	public function getTableTransactions(){ //sort, order, page, limit,filter
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $productID = $request->getQuery('salesID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');

        $selectQuery = "SELECT t.fullName as DepositorName,t.referenceNumber,t.depositAmount,s.salesID,s.paymentPlanID,s.customerID,co.homeMobile,co.fullName as CustomerName,s.amount,st.salesTypeName,st.salesTypeDeposit ";

        $countQuery = "SELECT count(t.transactionID) as totalTransaction ";
        $condition = " ";
        $baseQuery =" FROM transaction t LEFT JOIN sales s on t.salesID=s.salesID LEFT JOIN customer cu ON s.customerID=cu.customerID LEFT JOIN contacts co on cu.contactsID=co.contactsID LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID LEFT JOIN sales_type st on st.salesTypeID=pp.salesTypeID ";

        if($salesID && $filter){
        	$condition = " WHERE s.salesID=$salesID AND ";
        }

        elseif(!$salesID && !$filter){
        	$condition = "  ";
        }
        elseif ($salesID && !$filter) {
        	$condition = " WHERE s.salesID=$salesID ";    
        }

        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
        	$selectQuery=$selectQuery.$baseQuery.$condition." ".$queryBuilder;
        	if($filter){
        		$countQuery = $countQuery.$baseQuery.$condition." ".$queryBuilder;
        	}
        	else{
        		$countQuery = $countQuery.$baseQuery.$condition;
        	}
        }
        else{
        	$selectQuery=$selectQuery.$baseQuery.$condition;
        	$countQuery = $countQuery.$baseQuery.$condition;
        }
 

       // return $res->success($selectQuery);
        $count = $this->rawSelect($countQuery);
		$items= $this->rawSelect($selectQuery);

		 $data["totalTransaction"] = $count[0]['totalTransaction'];
          $data["transactions"] = $items;
		return $res->success("Transactions get successfully ",$data);

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
			$query = "  t.fullName REGEXP '$filter' OR t.referenceNumber REGEXP '$filter' OR co.fullName REGEXP '$filter' OR st.salesTypeName REGEXP '$filter' ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && !$order  && !$filter ){
			$query = " ORDER by $sort LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter ){
			$query = " ORDER by $sort $order  LIMIT $ofset,$limit";
		}
		else if(!$sort && !$order && !$filter){
			$query = " LIMIT $ofset,$limit";
		}

		else if(!$sort && !$order && $filter){
			$query = " t.fullName REGEXP '$filter' OR t.referenceNumber REGEXP '$filter' OR co.fullName REGEXP '$filter' OR st.salesTypeName REGEXP '$filter' LIMIT $ofset,$limit";
		}

		return $query;

	}

	




}

