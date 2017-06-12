<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT; 
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;


/*
All SmsTemplates  CRUD operations 
*/

class SmsTemplatesController extends Controller
{ 
     
	protected $customerSaleCreate = "customerSaleCreate";
	protected $customerInitialPayment = "customerInitialPayment";
	protected $agentOnCustomerPayment = "agentOnCustomerPayment";
	protected $shortcodeAutoResponse ="shortcodeAutoResponse";
	protected $customerSubsequentInstallment = "customerSubsequentInstallment";
	protected $customerLastInstallment = "customerLastInstallment";
	protected $customerPaymentReminder = "customerPaymentReminder";
	protected $delinquentCustomer = "delinquentCustomer";
	protected $defaultedCustomer = "defaultedCustomer";
	protected $warrantyActivation = "warrantyActivation";
	protected $airtimeFollowup = "airtimeFollowup";

    protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }
    private function smsTempate($templateType){
     	$smsTemplate = SmsTemplates::findFirst(array("templateType=:templateType:",
	    					'bind'=>array("templateType"=>$customerSaleCreate)));
     	return $smsTemplate->temptate;
     }

     private function composeSMS($template,$templateData,$userData){
     	return str_replace($templateData, $userData, $template);
     }

     private function logMessage($recipient,$message,$contactsID=0,$userID=0){
     		$outbox = new Outbox();
     		$outbox->message = $message;
     		$outbox->recipient = $recipient;
     		if($contactsID>0){
     	       $outbox->contactsID = $contactsID;
     		}

     		if($userID>0){
     			$outbox->userID = $userID;
     		}
     		if($outbox->save()===false){
	            $errors = array();
	                    $messages = $outbox->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                $res->dataError('Error saving sms',$errors);
	          }
     }
     

     public function customerSaleCreateSMS($msisdn,$name,$product,$account,$amount,$contactsID=0,$userID=0){
     	    $res = new SystemResponses();
     		$template = $this->smsTempate($this->customerSaleCreate);
     		$userData = [$name,$product,$account,$amount];
     		$templateData = ["[name]","[product]","[account]","[amount]"];
     		$smsToSend = $this->composeSMS($template,$templateData,$userData);

     		$this->logMessage($msisdn,$smsToSend,$contactsID,$userID);
     		$res->sendMessage($msisdn,$smsToSend);

     }

     public function customerInitialPaymentSMS($msisdn,$amount,$account,$contactsID=0,$userID=0){
     	   $res = new SystemResponses();
     		$template = $this->smsTempate($this->customerInitialPayment);
     		$userData = [$amount,$account];
     		$templateData = ["[amount]","[account]"];
     		$smsToSend = $this->composeSMS($template,$templateData,$userData);
     		$this->logMessage($msisdn,$smsToSend,$contactsID,$userID);
     		$res->sendMessage($msisdn,$smsToSend);
     }

     public function agentOnCustomerPaymentSMS($msisdn,$amount,$date,$contactsID=0,$userID=0){
	     	$res = new SystemResponses();
	     	$template = $this->smsTempate($this->agentOnCustomerPayment);
	     	$userData = [$amount,$date];
	     	$templateData = ["[amount]","[date]"];
	     	$smsToSend = $this->composeSMS($template,$templateData,$userData);
     		$this->logMessage($msisdn,$smsToSend,$contactsID,$userID);
     		$res->sendMessage($msisdn,$smsToSend);

     }

     public function customerSubsequentInstallmentSMS($msisdn,$name,$amount,$balance,$contactsID=0,$userID=0){
     		$res = new SystemResponses();
     		$template = $this->smsTempate($this->customerSubsequentInstallment);
     		$userData = [$name,$amount,$balance];
     		$templateData = ["[name]","[amount]","[balance]"];
     		$smsToSend = $this->composeSMS($template,$templateData,$userData);
     		$this->logMessage($msisdn,$smsToSend,$contactsID,$userID);
     		$res->sendMessage($msisdn,$smsToSend);
     }

     public function customerLastInstallmentSMS($msisdn,$name,$amount,$account,$serial,$contactsID=0,$userID=0){
     	    $res = new SystemResponses();
     		$template = $this->smsTempate($this->customerLastInstallment);
     		$userData = [$name,$amount,$account,$serial];
     		$templateData = ["[name]","[amount]","[account]","[serial]"];
     		$smsToSend = $this->composeSMS($template,$templateData,$userData);
     		$this->logMessage($msisdn,$smsToSend,$contactsID,$userID);
     		$res->sendMessage($msisdn,$smsToSend);

     }

     public function customerPaymentReminderSMS($msisdn,$name,$product,$date,$contactsID=0,$userID=0){
     	    $res = new SystemResponses();
     		$template = $this->smsTempate($this->customerPaymentReminder);
     		$userData = [$name,$product,$date];
     		$templateData = ["[name]","[product]","[date]"];
     		$smsToSend = $this->composeSMS($template,$templateData,$userData);
     		$this->logMessage($msisdn,$smsToSend,$contactsID,$userID);
     		$res->sendMessage($msisdn,$smsToSend);

     }

     public function delinquentCustomerSMS($msisdn,$name,$account,$amount,$pending,$contactsID=0,$userID=0){
     	   $res = new SystemResponses();
     	   $template = $this->smsTempate($this->delinquentCustomer);
     	   $userData = [$name,$account,$amount,$pending];
     	   $templateData = ["[name]","[account]","[amount]","[pending]"];
     	   $smsToSend = $this->composeSMS($template,$templateData,$userData);
     	    $this->logMessage($msisdn,$smsToSend,$contactsID,$userID);
     		$res->sendMessage($msisdn,$smsToSend);
     }

     public function defaultedCustomerSMS($msisdn,$name,$account,$balance,$contactsID=0,$userID=0){
	     	  $res = new SystemResponses();
	     	  $template = $this->smsTempate($this->defaultedCustomer);
	     	  $userData = [$name,$account,$amount,$pending];
	     	  $templateData = ["[name]","[account]","[amount]","[pending]"];
	     	  $smsToSend = $this->composeSMS($template,$templateData,$userData);
	     	  $this->logMessage($msisdn,$smsToSend,$contactsID,$userID);
	     	  $res->sendMessage($msisdn,$smsToSend);

     }

     public function warrantyActivationSMS($msisdn,$name,$contactsID=0,$userID=0){
     		  $res = new SystemResponses();
	     	  $template = $this->smsTempate($this->warrantyActivation);
	     	  $userData = [$name];
     	      $templateData = ["[name]"];
     	      $smsToSend = $this->composeSMS($template,$templateData,$userData);
	     	  $this->logMessage($msisdn,$smsToSend,$contactsID,$userID);
	     	  $res->sendMessage($msisdn,$smsToSend);
     }


    

}

