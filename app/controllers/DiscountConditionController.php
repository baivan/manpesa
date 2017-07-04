<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;


class DiscountConditionController extends Controller
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


    public function create() //{conditionName,conditionDescription,token}
    {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $token = $json->token;
        $conditionName = $json->conditionName;
        $conditionDescription = $json->conditionDescription;

        if(!$token || !$conditionName ||!$conditionDescription){
        	return $res->dataError("Missing data "); 
        }

        $discountCondition = DiscountCondition::findFirst(array("conditionName=:name: ",
                        'bind' => array("name" => $conditionName)));
        if($discountCondition){
        	return $res->dataError("Condition with same name exists");
        }

        $discountCondition = new DiscountCondition();
        $discountCondition->conditionName = $conditionName;
        $discountCondition->conditionDescription = $conditionDescription;
        $discountCondition->createdAt = date("Y-m-d H:i:s");

        try{

		       if ($discountCondition->save() === false) {
                    $errors = array();
                    $messages = $discountCondition->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback("DiscountCondition create" . json_encode($errors));
                }
                $dbTransaction->commit();
                return $res->success("Success ", $discountCondition);
            } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
                $message = $e->getMessage();
                return $res->dataError('DiscountCondition create', $message);
            }
      
    }

     public function edit() //{discountConditionID,conditionName,conditionDescription,token}
    {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $token = $json->token;
        $conditionName = $json->conditionName;
        $conditionDescription = $json->conditionDescription;
        $discountConditionID=$json->discountConditionID;

        if(!$token || !$discountConditionID ){
        	return $res->dataError("Missing data "); 
        }

        $discountCondition = DiscountCondition::findFirst(array("discountConditionID=:id: ",
                        'bind' => array("id" => $discountConditionID)));
        if(!$discountCondition){
        	return $res->dataError("Condition not found");
        }

        if($conditionName){
        	$discountCondition->conditionName = $conditionName;
        }
        if($conditionDescription){
        	$discountCondition->conditionDescription = $conditionDescription;
        }

        try{

		       if ($discountCondition->save() === false) {
                    $errors = array();
                    $messages = $discountCondition->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback("DiscountCondition edit" . json_encode($errors));
                }
                $dbTransaction->commit();
                return $res->success("Success ", $discountCondition);
            } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
                $message = $e->getMessage();
                return $res->dataError('DiscountCondition edit', $message);
            }
      
    }

   public function getAll(){
    	$jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        if(!$token){
        	return $res->dataError("Missing data "); 
        }
   	  $selectQuery = "SELECT discountConditionID,conditionDescription,conditionName FROM discount_condition ";
   	  $discountConditions = $this->rawSelect($selectQuery);
   	  return $res->success("Conditions ",$discountConditions);

   }



}

