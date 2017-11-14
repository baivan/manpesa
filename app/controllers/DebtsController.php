<?php
use Phalcon\Mvc\Model\Query\Builder as Builder;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Http\Request;


class DebtsController extends  ControllerBase
{

    //create
    public function create(){
    	$jwtManager = new JwtManager();
        $request = new Request();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $userId = isset($json->userId) ? $json->userId : NULL;
        $debtorName = isset($json->debtorName) ? $json->debtorName : NULL;
        $amount = isset($json->amount) ? $json->amount :NULL;
        $dueDate = isset($json->dueDate) ? $json->dueDate : NULL;
        $lendDate = isset($json->lendDate) ? $json->lendDate : NULL;
        $debtTypeId = isset($json->debtTypeId) ? $json->debtTypeId : NULL;
        $settleDate = isset($json->settleDate) ? $json->settleDate :NULL;
        $status = isset($json->status) ? $json->status : NULL;
        $token = isset($json->token) ? $json->token : NULL;

        if (!$token || !$userId || !$debtorName || !$amount || !$dueDate 
        	|| !$lendDate || !$debtTypeId || !$settleDate) {
            return $this->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token);

        $user = Users::findFirst(array("userID=:id: ",
	                        'bind' => array("id" => $userId)));


        if (!$tokenData) {
            return $this->dataError("User Data compromised");
        }
        elseif ($tokenData->userId !=$user->userId ) {
        	return $this->dataError("User Data compromised");
        }

        try{
        	$debt = new Debts();
	        $debt->userId = $userId;
	        $debt->debtorName = $debtorName;
	        $debt->amount = $amount;
	        $debt->dueDate = $dueDate;
	        $debt->lendDate = $lendDate;
	        $debt->debtTypeId = $debtTypeId;
	        $debt->settleDate = $settleDate;
	        $debt->status = $status;
	        $debt->createdAt = date("Y-m-d H:i:s");

	         if ($debt->save() === false) {
		                $errors = array();
		                $messages = $debt->getMessages();
		                foreach ($messages as $message) {
		                    $e["message"] = $message->getMessage();
		                    $e["field"] = $message->getField();
		                    $errors[] = $e;
		                }
		                $dbTransaction->rollback("debt create failed " . json_encode($errors));
		            }

		         $dbTransaction->commit();

		  return $this->success("Debt created successfully ",$debt);

        }catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $this->dataError('debt create error', $message);
        }
        
    }
    //edit 
    public function edit(){
    	$jwtManager = new JwtManager();
        $request = new Request();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $userId = isset($json->userId) ? $json->userId : NULL;
        $debtId = isset($json->debtId) ? $json->debtId : NULL;
        $paid = isset($json->paid) ? $json->paid : NULL;
        $debtorName = isset($json->debtorName) ? $json->debtorName : NULL;
        $amount = isset($json->amount) ? $json->amount :NULL;
        $dueDate = isset($json->dueDate) ? $json->dueDate : NULL;
        $lendDate = isset($json->lendDate) ? $json->lendDate : NULL;
        $debtTypeId = isset($json->debtTypeId) ? $json->debtTypeId : NULL;
        $settleDate = isset($json->settleDate) ? $json->settleDate :NULL;
        $status = isset($json->status) ? $json->status : NULL;
        $token = isset($json->token) ? $json->token : NULL;

        if (!$token || !$userId || !$debtId) {
            return $this->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token);

        $user = Users::findFirst(array("userID=:id: ",
	                        'bind' => array("id" => $userId)));


        if (!$tokenData) {
            return $this->dataError("User Data compromised");
        }
        elseif ($tokenData->userId !=$user->userId ) {
        	return $this->dataError("User Data compromised");
        }

        $debt = Debts::findFirst(array("debtId=:id: and userId=:u_id:",
	                        'bind' => array("id" => $debtId,"u_id"=>$userId)));

        if(!$debt){
        	return $this->notFound("Debt not found");
        }
	 
	  try{
	        if($paid ){
	        	$debt->paid = $paid;
	        }
	        if($amount ){
	        	$debt->amount = $amount;
	        }
	        if($debtorName ){
	        	$debt->debtorName = $debtorName;
	        }
	        if($dueDate ){
	        	$debt->dueDate = $dueDate;
	        }
	        if($lendDate ){
	        	$debt->lendDate = $lendDate;
	        }

	        if($debtTypeId ){
	        	$debt->debtTypeId = $debtTypeId;
	        }
	        if($settleDate ){
	        	$debt->settleDate = $settleDate;
	        }

	        if($status){
	        	$debt->status = $status;
	        }

	         if ($debt->save() === false) {
		                $errors = array();
		                $messages = $debt->getMessages();
		                foreach ($messages as $message) {
		                    $e["message"] = $message->getMessage();
		                    $e["field"] = $message->getField();
		                    $errors[] = $e;
		                }
		                $dbTransaction->rollback("debt create failed " . json_encode($errors));
		            }

		         $dbTransaction->commit();

		   return $this->success("Debt updated successfully ",$debt);

        }catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $this->dataError('debt create error', $message);
        }
        



    }

    //get all

    public function getAll(){
    	$jwtManager = new JwtManager();
        $request = new Request();
        $token = $request->getQuery('token');
        $userId = $request->getQuery('userId');

        if (!$token || !$userId) {
            return $this->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token);

        $user = Users::findFirst(array("userID=:id: ",
	                          'bind' => array("id" => $userId)));

        if (!$tokenData) {
            return $this->dataError("User Data compromised ");
        }
        elseif (!$user) {
           return $this->dataError("User not found ",$tokenData);
        }
        elseif ($tokenData->userId !=$user->userId ) {
        	return $this->dataError("User Data compromised");
        }

        $debts = $this->rawSelect("SELECT * FROM debts WHERE userId=$userId");
        return $this->success("debts ",$debts);

    }

}

