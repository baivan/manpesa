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
	protected $closeGroupStatus = 2;
	protected $expireGroupStatus = 3;
	protected $abortedStatus = 5;

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

    /*Create group */

     public function create() { 
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $groupName = isset($json->groupName) ? $json->groupName : NULL;
        $MSISDN = isset($json->MSISDN)?$json->MSISDN:NULL;
        $userID = isset($json->userID)?$json->userID:NULL;
        $token = $json->token;

        if (!$token) {
            return $res->dataError("Token missing ",[]);
        }
        if (!$groupName || !$MSISDN) {
            return $res->dataError("data missing ", []);
        }

        $group = GroupSale::findFirst("(groupName='$groupName' OR groupToken='$groupName')  AND status=0");

        if($group){
            return $res->success("Group exists ",$group);
        }

        try{
	        $lastId = $this->rawSelect("SELECT groupID from group_sale ORDER by groupID desc limit 1");
	        if(!$lastId){
	        	$lastId = 0;
	        }
	        else{
	        	$lastId = $lastId[0]['groupID'];
	        }
	      
	        $groupSale = new GroupSale();
	        $groupSale->createdAt = date("Y-m-d H:i:s");
	        $groupSale->numberOfMembers = 0;
	        $groupSale->status=0;
	        $groupSale->userID = $userID;
	        $groupSale->groupToken="group".($lastId+1);
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
	          return $res->success("Group Sale created ", $groupSale);
	      }
	      catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Group Sale create error', $message);
        }
    }

    public function closeGroup(){
    	$jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $groupName = isset($json->groupName) ? $json->groupName : NULL;
        $groupID = isset($json->groupID) ? $json->groupID : NULL;
        $MSISDN = isset($json->MSISDN)?$json->MSISDN:NULL;
        $token = $json->token;

        if (!$token) {
            return $res->dataError("Token missing ",[]);
        }
        if (!$MSISDN || $groupID) {
            return $res->dataError("Group data missing ", []);
        }

        try{
	         $groupSale = GroupSale::findFirst("groupID=$groupID");
	         if(!$groupSale){
	         	return $res->success("Group not found ");
	         }

	         $groupSale->closedAt = date("Y-m-d H:i:s");
	         $groupSale->status = $this->closeGroupStatus;

	         if ($groupSale->save() === false) {
	                $errors = array();
	                $messages = $groupSale->getMessages();
	                foreach ($messages as $message) {
	                    $e["message"] = $message->getMessage();
	                    $e["field"] = $message->getField();
	                    $errors[] = $e;
	                }
	                $dbTransaction->rollback('Group Sale close failed' . json_encode($errors));
	          }

	           $dbTransaction->commit();

              $customerMessage =$groupSale->groupName." (".$groupSale->groupToken.")"." has been closed successfully";
	          $res->sendMessage($MSISDN, $customerMessage);
	          return $res->success("Sale saved successfully, await payment ", $sale);

        }
        catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Group Sale close error', $message);
        }
    }

    public function abortGroup(){
    	$jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $groupName = isset($json->groupName) ? $json->groupName : NULL;
        $groupID = isset($json->groupID) ? $json->groupID : NULL;
        $MSISDN = isset($json->MSISDN)?$json->MSISDN:NULL;
        $token = $json->token;

        if (!$token) {
            return $res->dataError("Token missing ",[]);
        }
        if (!$MSISDN || $groupID) {
            return $res->dataError("Group data missing ", []);
        }

        try{
	         $groupSale = GroupSale::findFirst("groupID=$groupID");
	         if(!$groupSale){
	         	return $res->success("Group not found ");
	         }

	         $groupSale->abortedAt = date("Y-m-d H:i:s");
	         $groupSale->status = $this->abortedStatus;

	         if ($groupSale->save() === false) {
	                $errors = array();
	                $messages = $groupSale->getMessages();
	                foreach ($messages as $message) {
	                    $e["message"] = $message->getMessage();
	                    $e["field"] = $message->getField();
	                    $errors[] = $e;
	                }
	                $dbTransaction->rollback('Group Sale close failed' . json_encode($errors));
	          }

	           $dbTransaction->commit();

              $customerMessage =$groupSale->groupName." (".$groupSale->groupToken.")"." has been closed successfully";
	          $res->sendMessage($MSISDN, $customerMessage);
	          return $res->success("Sale saved successfully, await payment ", $sale);

        }
        catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Group Sale close error', $message);
        }
    }


    public function expireGroup(){
         $res = new SystemResponses();
        $expiredGroupsQuery= "SELECT * FROM group_sale WHERE status=0 AND TIMESTAMPDIFF(HOUR,createdAt, now()) >= 1";
        $groupSales = $this->rawSelect($expiredGroupsQuery);

         $res->success('groupSales ',$groupSales);
        
        foreach ($groupSales as $groupSale) {
            $groupID = $groupSale['groupID'];
             $groupSale_o = GroupSale::findFirst("groupID=$groupID");
	         if(!$groupSale){
	         	 $res->success("Group not found ");
	         }
	         $groupSale_o->status=$this->expireGroupStatus;
	         $groupSale_o->expiredAt = date("Y-m-d H:i:s");

            if ($groupSale_o->save() === false) {
                $errors = array();
                $messages = $groupSale_o->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                 $res->dataError(' groupSale_o expire failed sale: '.json_encode($groupSale_o), $errors);
            }

            //get and delete all sales associated with this group
            
            $sales =  $this->select("SELECT * FROM sales WHERE groupID=$groupID");
            foreach ($sales as $sale) {
                 $sale_o = Sales::findFirst("salesID=".$sale['salesID']);
                 $sale_o->status=-2;
                 if ($sale_o->save() === false) {
                        $errors = array();
                        $messages = $sale_o->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                         $res->dataError('sale_o expire failed sale: '.json_encode($sale_o), $errors);
                    }
             }

        }

        $res->success('GroupSale_o expired');
    }

    public function getGroup(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $groupName = isset($json->groupName) ? $json->groupName : NULL;
        $groupToken = isset($json->groupToken) ? $json->groupToken : NULL;
        $groupID = isset($json->groupID) ? $json->groupID : NULL;
        $groups;

        $token = $json->token;

        if (!$groupName && !$groupToken && !$groupID) {
          return $res->dataError("Group data missing ", []);
        }
        if($groupID){
            $groups = $this->rawSelect("SELECT * FROM group_sale WHERE groupID=$groupID");

        }
        elseif($groupToken){
             $groups = $this->rawSelect("SELECT * FROM group_sale WHERE groupToken='".$groupToken."'");
        }
        elseif($groupName){
            $groups = $this->rawSelect("SELECT * FROM group_sale WHERE groupName='".$groupName."'");
        
        }
       return $res->success("Groups ",$groups);
    }

}
