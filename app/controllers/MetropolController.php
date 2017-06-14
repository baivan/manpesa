<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

/*
All Metropol  operations 
*/


class MetropolController extends Controller
{
    
    private $publicKey="uhFjiyoxRfwbJthxuSqzznhjrugUye";
	private $privateKey="rsaxnhnEfttbYejqxhTbpwjMxArndsdsxwqmsxbdraivxrzephhbirjutfwc";
	private $metropolRoot = "https://api.metropol.co.ke:22225/v2_1";

	private $consumerRateRType = 3;
	private $identityScrubNumber = 6;

	/*
	credit rate api
	can be used for new or existing consumers
	params:
	contactsID
	mobile,nationalIdNumber,location,fullName,

	*/
	public function creditRate(){
        $request = new Request();
        $json = $request->getJsonRawBody();
        $res = new SystemResponses();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();


        $location = $json->location ;
        $workMobile = $json->workMobile ;
        $fullName = $json->fullName ;
        $nationalIdNumber = $json->nationalIdNumber;
        $contactsID = $json->contactsID;
        $userID = $json->userID ;

        $token = $json->token;

        $score = 0;

        if (!$token) {
            return $res->dataError("Token missing " . json_encode($json), []);
        }

        if(!$contactsID && (!$nationalIdNumber || !$location || !$workMobile || !$fullName)){
        	return $res->dataError("Customer missing " . json_encode($json), []);
        }


        try {

            if (!$contactsID) { 
            	
                if ($workMobile && $fullName && $location && $nationalIdNumber) { 
                	$sales = new SalesController();
                    $contactsID = $sales->createContact($workMobile, $nationalIdNumber, $fullName, $location, $dbTransaction);
                }
            }
            else{
            	$contact = Contacts::findFirst(array("contactsID = :id:",
            		'bind'=>array("id"=>$contactsID)));
            	$nationalIdNumber = $contact->nationalIdNumber;
            }

            $contactCreditScore = ContactCreditScore::findFirst(array("contactsID=:c_id: AND date(createdAt) > :condition:",
                    'bind' => array("c_id" => $contactsID,"condition"=>" curdate() - interval 1 year")));  //select only customer scores picked less than a yea ago 

            if(!$contactCreditScore)  {
            	 $response = $this->consumerRate($nationalIdNumber);

	            if($response){
	            	$score = $response['credit_score'];
	            	$contactCreditScore = new ContactCreditScore();
	            	$contactCreditScore ->contactsID = $contactsID;
	            	$contactCreditScore ->userID = $userID;
	            	$contactCreditScore ->score = isset($score) ? $score :0;
	            	$contactCreditScore ->createdAt = date("Y-m-d H:i:s");

	            	if ($contactCreditScore->save() === false) {
		                $errors = array();
		                $messages = $contactCreditScore->getMessages();
		                foreach ($messages as $message) {
		                    $e["message"] = $message->getMessage();
		                    $e["field"] = $message->getField();
		                    $errors[] = $e;
		                }
		                $dbTransaction->rollback('Metropol check failed' . json_encode($errors));
		            }

	            }

            }
            else{
            	$score = $contactCreditScore->score;
            } 

            $userWorth = array();

            if($score <= 400  && $score > 0){
            	$userWorth["award"] = false;
            	$userWorth["rated"] = true;
            }
            elseif ($score==0) {
            	$userWorth["award"] = true;
            	$userWorth["rated"] = false;
            }
            elseif ($score > 400) {
            	$userWorth["award"] = true;
            	$userWorth["rated"] = true;
            }

            $dbTransaction->commit();

 			return $res->success("Customer rated ", $userWorth);  

        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Metropol check failed', $message);
        }


	}
   /*
	customer identity api
	can be used for new or existing consumers
	params:
	contactsID
	mobile,nationalIdNumber,location,fullName,

	*/
   public function identityVerification(){
   	    $request = new Request();
        $json = $request->getJsonRawBody();
        $res = new SystemResponses();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();


        $location = isset($json->location) ? $json->location : NULL;
        $workMobile = isset($json->workMobile) ? $json->workMobile : NULL;
        $fullName = isset($json->fullName) ? $json->fullName : NULL;
        $nationalIdNumber = isset($json->nationalIdNumber) ? $json->nationalIdNumber : NULL;
        $contactsID = isset($json->contactsID) ? $json->contactsID : NULL;
        $userID = isset($json->userID) ? $json->userID : NULL;

        $token = $json->token;

        if (!$token) {
            return $res->dataError("Token missing " . json_encode($json), []);
        }

        if(!$contactsID && (!$nationalIdNumber || !$location || !$workMobile || !$fullName)){
        	return $res->dataError("Customer missing " . json_encode($json), []);
        }

        try {

            if (!$contactsID) { 
            	
                if ($workMobile && $fullName && $location && $nationalIdNumber) { 
                	$sales = new SalesController();
                    $contactsID = $sales->createContact($workMobile, $nationalIdNumber, $fullName, $location, $dbTransaction);
                }
            }
            

            $contact = Contacts::findFirst(array("contactsID = :id:",
            		      'bind'=>array("id"=>$contactsID)));
            if(!$contact){
            	return $res->dataError("Customer missing " . json_encode($json), []);
            }


           if($contact->idVerified == 0){
           		 $response = $this->identityScrub($nationalIdNumber);

           		 if($response){
           		 	$contact->fullName = $response['fullName'];
           		 	$contact->dateOfBeing =$response['dateOfBeing']; 
           		 	$contact->gender = $response['gender'];
           		 	$contact->employment = $response['employment'];
           		 	$contact->physicalAddress =$response['physicalAddress']; 
           		 	$contact->postalAddress = $response['postalAddress'];
           		 	$contact->metropolCutomerPhone =$response['phone']; 
           		 	$contact->idVerified = 1;

           		 	if ($contact->save() === false) {
		                $errors = array();
		                $messages = $contact->getMessages();
		                foreach ($messages as $message) {
		                    $e["message"] = $message->getMessage();
		                    $e["field"] = $message->getField();
		                    $errors[] = $e;
		                }
		                $dbTransaction->rollback(' Metropol check failed ' . json_encode($errors));
		            }

           		 }
           		 else{
           		 	return $res->success("Contact not verified ", false); 
           		 }

            }

            $dbTransaction->commit();

            return $res->success("Contact successfuly verified ", true); 

        }
        catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Metropol check failed', $message);
        }

   }

	
	/*
	get customer's credit score 
	Params:
	identityNumber (required customer nationalID or passport number)
	identityType (for nationalID not required but for passport send 002)
	*/
	public function consumerRate($identityNumber,$identityType="001"){
		    $res = new SystemResponses();
		    $metropolReportType = MetropolReportType::findFirst(array("reportType=:r_type: ",
                    'bind' => array("r_type" => $this->consumerRateRType)));
			$response = $this->sendMetropolRequest($identityNumber,$identityType,$metropolReportType->reportType,
			    	       $metropolReportType->endpoint);
			   
			if($response == false){
			    	return false;
			 }
				
           	 return array("identityNumber"=>$identityNumber,
           	  				"credit_score"=>$response->credit_score);

	}


	/*
	  get customer's identity
	*/
	public function identityScrub($identityNumber,$identityType="001"){
			    $res = new SystemResponses();
			    $metropolReportType = MetropolReportType::findFirst(array("reportType=:r_type: ",
	                    'bind' => array("r_type" => $this->identityScrubNumber)));

			    $response = $this->sendMetropolRequest($identityNumber,$identityType,$metropolReportType->reportType,
			    	       $metropolReportType->endpoint);
			   
			    if($response == false){
			    	return false;
			    }

	           
	           	return array("identity_number"=>$response->identity_number,
	           	  				"dateOfBeing"=>$response->date_of_being[0],
	           	  				"gender"=>$response->gender[0],
	           	  				"employment"=>$response->employment[0],
	           	  				"fullName"=>$response->names[0],
	           	  				"email"=>$response->email[0],
	           	  				"phone"=>$response->phone[0],
	           	  				"physicalAddress"=>$response->physical_address[0],
	           	  				"postal_address"=>$response->postal_address[0]);

		}
	/*
	sends set request to the metropol server
	*/
	private function sendMetropolRequest($identityNumber,$identityType,$report_type,$endpoint){
		$res = new SystemResponses();
		$date = gmdate('YmdHis');
		
		
			$jsonPayload='{"report_type":'.$report_type.',"identity_number":"'.$identityNumber.'","identity_type":"'.$identityType.'"}';

	   		$apiHash= hash("sha256",$this->privateKey.$jsonPayload.$this->publicKey.$date);

	   		$url = $this->metropolRoot.$endpoint;

	   		 $headers = array(
               "Content-Type:application/json",
               "x-metropol-rest-api-hash: ".$apiHash,
    		   "x-metropol-rest-api-key: ".$this->publicKey,
   			   "x-metropol-rest-api-timestamp: ".$date
	        );

	        $ch = curl_init();
	        curl_setopt($ch, CURLOPT_URL, $url);
	        curl_setopt($ch, CURLOPT_POST, 1);
	        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
	       
	        
	        $response = curl_exec($ch);
            $err = curl_error($ch);

            curl_close($ch);
            if($response){
            	 $res->metropolResponseLogs(date('Y-m-d H:i:s')." ".$metropolReportType->reportName,$response);
            }

            if($err){
            	 $res->metropolResponseErrorLogs(date('Y-m-d H:i:s')." ".$metropolReportType->reportName,$response);
            }

           $response = json_decode($response);
           if($response->has_error == true){
           	   return false;
           }
           elseif ($response->has_error == false) {
             return $response;
           }
	}

}

