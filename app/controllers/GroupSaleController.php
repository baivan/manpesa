<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

class GroupSaleController extends Controller
{

    /*
    Raw query select function to work in any version of phalcon
    */
    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
        
    }

    /*
      checks if date is between two dates
    */
    protected function isDateBetweenDates($date, $startDate, $endDate) {
        $date = new DateTime($date);
        $startDate = new DateTime($startDate);
        $endDate = new DateTime($endDate);
        return $date > $startDate && $date < $endDate;
    }

     public function create() { 
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $groupName = isset($json->groupName) ? $json->groupName : NULL;
        $MSISDN = isset($json->MSISDN)?$json->MSISDN:NULL;
        $token = $json->token;

        if (!$token) {
            return $res->dataError("Token missing ",[]);
        }
        if (!$groupName || !$MSISDN) {
            return $res->dataError("salesTypeID missing ", []);
        }

        try{
	        $lastId = $this->rawSelect("SELECT groupID from group_sale ORDER by groupID desc limit 1");

	        if(!$lastId){
	        	$lastId = 1;
	        }
	        else{
	        	$lastId = $lastId[0]['groupID'];
	        }
	      
	        $groupSale = new GroupSale();
	        $groupSale->createdAt = date("Y-m-d H:i:s");
	        $groupSale->numberOfMembers = 0;
	        $groupSale->status=0;
	        $groupSale->groupToken="group".$lastId;
	        $groupSale->groupName = $groupName;

	         if ($groupSale->save() === false) {
	                $errors = array();
	                $messages = $groupSale->getMessages();
	                foreach ($messages as $message) {
	                    $e["message"] = $message->getMessage();
	                    $e["field"] = $message->getField();
	                    $errors[] = $e;
	                }
	                $dbTransaction->rollback('Group Sale create failed' . json_encode($errors));
	          }

	          $dbTransaction->commit();

              $customerMessage = "Please use ".$groupSale->groupToken." for the group with name ".$groupSale->groupName;
	          $res->sendMessage($MSISDN, $customerMessage);
	          return $res->success("Sale saved successfully, await payment ", $sale);
	      }
	      catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Group Sale create error', $message);
        }

    }

}

