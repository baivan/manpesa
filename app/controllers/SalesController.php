<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;

class SalesController extends Controller
{

    public function indexAction()
    {

    }

     protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }

	public function create(){//{paymentPlanID,amount,userID,workMobile,nationalIdNumber,fullName,location,token,items[]}
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();

    	$paymentPlanID = $json->paymentPlanID;
    	$userID = $json->userID;
    	$status = $json->status;
    	$amount = $json->amount;
    	$items = $json->items;
    	$token =$json->token;
    	$location = $json->location;
    	$workMobile = $json->workMobile;
    	$fullName = $json->fullName;
    	$nationalIdNumber = $json->nationalIdNumber;
    	$customerID = 0;

    	if(!$token || !$paymentPlanID || !$userID || !$amount){
	    	return $res->dataError("Missing data ");
	    }

	    $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }


	    if(!$status){
	    	$status=0;
	    }


    	$tokenData = $jwtManager->verifyToken($token,'openRequest');
       if(!$tokenData){
         return $res->dataError("Data compromised");
       }


        $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
	    					'bind'=>array("w_mobile"=>$workMobile)));
	   if($contact){
	   	  $customer = Customer::findFirst(array("contactsID=:id: ",
	    					'bind'=>array("id"=>$contact->contactsID)));
	   	  if($customer){
	   	  	 $res->dataError("Customer exists");
	   	  	 $customerID = $customer->customerID;
	   	  }
	   	  
	   }
	    else{
	   		$contact = new Contacts();
	    	$contact->workEmail = "null";
	    	$contact->workMobile = $workMobile;
	    	$contact->fullName = $fullName;
	    	$contact->location = $location;
	    	$contact->createdAt = date("Y-m-d H:i:s");
	    	if ($nationalIdNumber) {
	    		$contact->nationalIdNumber = $nationalIdNumber;
	    	}
	    	else{
	    		$contact->nationalIdNumber="null";
	    	}

	    	if($contact->save()===false){
	            $errors = array();
	                    $messages = $contact->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                 $res->dataError('contact create failed',$errors);
	          }

	        $customer = new Customer();
	        $customer->status= 0 ;
	        $customer->locationID=0;
	        $customer->userID = $userID;
	        $customer->contactsID = $contact->contactsID;
	        $customer->createdAt = date("Y-m-d H:i:s");
	        if($customer->save()===false){
	            $errors = array();
	                    $messages = $customer->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                return  $res->dataError('customer create failed',$errors);
	          }
	         $customerID = $customer->customerID;

	   }

	    //  create sale
         $sale = new Sales();
         $sale->status=0;
         $sale->paymentPlanID = $paymentPlanID;
         $sale->userID = $userID;
         $sale->customerID = $customerID;
         $sale->amount = $amount;
         $sale->createdAt = date("Y-m-d H:i:s");

         if($sale->save()===false){
            $errors = array();
                    $messages = $sale->getMessages();
                    foreach ($messages as $message) 
                       {
                         $e["message"] = $message->getMessage();
                         $e["field"] = $message->getField();
                          $errors[] = $e;
                        }
                  $res->dataError('sale create failed',$errors);
          }



          //mapp items to this sale
          foreach ($items as $itemID) {
	          	$saleItem =  SalesItem::findFirst("itemID=$itemID");
	          	if($saleItem){
	          		$res->dataError("Item already sold");

	          	}
	          	else{
		          	$saleItem = new SalesItem();
		          	$saleItem->itemID = $itemID;
		          	$saleItem->saleID = $sale->salesID;
		          	$saleItem->status = 0;
		          	$saleItem->createdAt=date("Y-m-d H:i:s");
	          		if($saleItem->save()===false){
			            $errors = array();
		                    $messages = $saleItem->getMessages();
		                    foreach ($messages as $message) 
		                       {
		                         $e["message"] = $message->getMessage();
		                         $e["field"] = $message->getField();
		                          $errors[] = $e;
		                        }
		                 return $res->dataError('saleItem create failed',$errors);
			          }

	          }

          }


        return $res->success("Sale created successfully ",$sale);

	}
	public function getSales(){//{userID,customerID,token}
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $customerID = $request->getQuery('customerID');
        $userID = $request->getQuery('userID');


        $saleQuery = "SELECT * FROM sales s JOIN sales_item si ON s.salesID=si.saleID LEFT JOIN customer c on s.customerID=c.customerID LEFT JOIN contacts co on c.contactsID=co.contactsID WHERE s.userID=$userID";

		if(!$token || !$userID){
		   return $res->dataError("Missing data ");
		}

		$tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }


		if($customerID){
			 $saleQuery = "SELECT * FROM sales s JOIN sales_item si ON s.salesID=si.saleID LEFT JOIN customer c on s.customerID=c.customerID LEFT JOIN contacts co on c.contactsID=co.contactsID WHERE s.userID=$userID AND s.customerID=$customerID";
		}

		$sales = $this->rawSelect($saleQuery);

		return $res->success("User sales are $saleQuery",$sales);




	}

}

