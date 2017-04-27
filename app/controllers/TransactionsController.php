<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class TransactionsController extends Controller
{
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
        $salesID = $json->account;
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
	   	   $transaction->salesID=$salesID;
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
	          $dbTransaction->commit();
	       $res->sendMessage($mobile,"Dear ".$fullName.", your payment has been received");

	       $userQuery = "SELECT userID as userId from sales WHERE salesID=$salesID";

	       $sale = Sales::findFirst(array("salesID=:id: ",
	    					'bind'=>array("id"=>$salesID))); 

	       $userID = $this->rawSelect($userQuery);
	       $pushNotificationData = array();
	       $pushNotificationData['salesID']=$salesID;
	       $pushNotificationData['mobile'] = $salesID;
	       $pushNotificationData['amount'] = $amount;
	       $pushNotificationData['saleAmount'] = $sale->amount;

	       $res->sendPushNotification($pushNotificationData,"New payment","There is a new payment from a sale you made",$userID);

	       
	      return $res->success("Transaction successfully done ".json_encode($userID),$transaction);

	   }
	    catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
	       $message = $e->getMessage(); 
	       return $res->dataError('sale create error', $message); 
       }

	}

	




}

