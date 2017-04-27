<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

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
    public function create(){ //{contactsID,amount,userID,salesTypeID,frequencyID}
    	$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$transactionManager = new TransactionManager(); 
        $dbTransaction = $transactionManager->get();

    	$paymentPlanDeposit = $json->paymentPlanDeposit;
    	$salesTypeID = $json->salesTypeID;
    	$frequencyID = $json->frequencyID;
    	$contactsID = $json->contactsID;
    	$userID = $json->userID;
    	$amount = $json->amount;
    	$token = $json->token;

    	if(!$token ){
	    	return $res->dataError("Token missing er".json_encode($json));
	    }
	    if(!$salesTypeID ){
	    	return $res->dataError("salesTypeID missing ");
	    }
	     if(!$userID ){
	    	return $res->dataError("userID missing ");
	    }
	     if(!$amount ){
	    	return $res->dataError("amount missing ");
	    }
	     if(!$frequencyID ){
	    	//return $res->dataError("frequencyID missing ");
	    	$frequencyID=0;
	    }
	    if(!$contactsID){
	    	return $res->dataError("Customer missing ");
	    }


	    $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }


	      try {
	      	$paymentPlanID = $this->createPaymentPlan($paymentPlanDeposit,$salesTypeID,$frequencyID,$dbTransaction);
	      	$customerID = $this->createCustomer($userID,$contactsID,$dbTransaction);

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
	               //return $res->dataError('sale create failed',$errors);
	                $dbTransaction->rollback('sale create failed' . json_encode($errors));  
	          }

	          /*send message to customer*/
	          $customer = $this->getCustomerDetails($customerID);
	          $MSISDN = $customer["workMobile"];
	          $name = $customer["fullName"];

	         $res->sendMessage($MSISDN,"Dear ".$name.", your sale has been placed successfully, please pay Ksh. ".$paymentPlanDeposit);
              $dbTransaction->commit();

	      	  return $res->success("Sale successfully done ",$sale);
	      } 
	       catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
	       $message = $e->getMessage(); 
	       return $res->dataError('sale create error', $message); 
       }

    }

    public function createPaymentPlan($paymentPlanDeposit,$salesTypeID,$frequencyID,$dbTransaction,$repaymentPeriodID=0){
    	$res = new SystemResponses();
    	$paymentPlan = PaymentPlan::findFirst(array("salesTypeID=:s_id: AND frequencyID=:f_id: ",
	    					'bind'=>array("s_id"=>$salesTypeID,"f_id"=>$frequencyID)));

    	if($paymentPlan){
    		return $paymentPlan->paymentPlanID;
    	}
    	else{
    		$paymentPlan = new PaymentPlan();
    		$paymentPlan->paymentPlanDeposit = $paymentPlanDeposit;
    		$paymentPlan->salesTypeID = $salesTypeID;
    		$paymentPlan->frequencyID = $frequencyID;
    		$paymentPlan->repaymentPeriodID = $repaymentPeriodID;
    		$paymentPlan->createdAt = date("Y-m-d H:i:s");
    		
    		if($paymentPlan->save()===false){
	            $errors = array();
	                    $messages = $paymentPlan->getMessages();
	                    foreach ($messages as $message) 
	                       {
	                         $e["message"] = $message->getMessage();
	                         $e["field"] = $message->getField();
	                          $errors[] = $e;
	                        }
	                 // $res->dataError('paymentPlan create failed',$errors);
	                 $dbTransaction->rollback('paymentPlan create failed' . json_encode($errors)); 
	                  //return 0;
	          }
	          return $paymentPlan->paymentPlanID;
    	}
    }

    public function createCustomer($userID,$contactsID,$dbTransaction,$locationID=0){
    	    $res = new SystemResponses();
       		$customer =  Customer::findFirst(array("contactsID=:id: ",
	    					'bind'=>array("id"=>$contactsID)));

       		//$res->dataError("select user $userID contact $contactsID");
       		if($customer){
       			return $customer->customerID;
       		}
       		else{
	       			$customer = new Customer();
			        $customer->locationID=$locationID;
			        $customer->userID = $userID;
			        $customer->contactsID = $contactsID;
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
			             //  $res->dataError('customer create failed',$errors);
			             $dbTransaction->rollback('customer create failed' . json_encode($errors)); 
			                //return 0;
			          }

			      return $customer->customerID;
	       	}
    }

    public function createContact($workMobile,$nationalIdNumber,$fullName,$location,$homeMobile=null,
    						$homeEmail=null,$workEmail=null,$passportNumber=0,$locationID=0){
    	$res = new SystemResponses();
    	 $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
	    					'bind'=>array("w_mobile"=>$workMobile)));
    	 if($contact){
    	 	return $contact->contactsID;
    	 }
    	 else{
    	 	$contact = new Contacts();
	    	$contact->workEmail = $workEmail;
	    	$contact->homeEmail=$homeEmail;
	    	$contact->workMobile = $workMobile;
	    	$contact->homeMobile=$homeMobile;
	    	$contact->fullName = $fullName;
	    	$contact->location = $location;
	    	$contact->nationalIdNumber = $nationalIdNumber;
	    	$contact->passportNumber=$passportNumber;
	    	$contact->locationID=$locationID;
	    	$contact->createdAt = date("Y-m-d H:i:s");

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
	                 return 0;
	          }

	         $res->sendMessage($workMobile,"Dear ".$fullName.", welcome to Envirofit. For any questions or comments call 0800722700 ");
	         return $contact->contactsID;
    	 }


    }

    public function mapItemToSale($saleID,$itemID){
    	        $res = new SystemResponses();
    	        $saleItem =  SalesItem::findFirst(array("itemID=:i_id: ",
	    					'bind'=>array("i_id"=>$itemID)));
	          	
	          	if($saleItem){
	          		$res->dataError("Item already sold $itemID");
	          		return 0;
	          	}
	          	else{
		          	$saleItem = new SalesItem();
		          	$saleItem->itemID = $itemID;
		          	$saleItem->saleID = $saleID;
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
		                  $res->dataError('saleItem create failed '.$saleID,$errors);
		                  return 0;
			          }
			       return $saleItem->saleItemID;
	          }
    }

    public function updateItemToSold($itemID){
    	$res = new SystemResponses();
    	    $item = Item::findFirst(array("itemID=:id: ",
	    					'bind'=>array("id"=>$itemID)));
    	    if($item){
    	    	$item->status = 2;
    	    	$item->save();
    	    	return true;
    	    }
    	    else{
    	    	return false;
    	    }

    }

    public function createSale(){//{salesTypeID,frequencyID,itemID,prospectID,nationalIdNumber,fullName,location,workMobile,userID,paymentPlanDeposit,customerID}
    	$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	//$items = $json->items;

    	$salesTypeID = $json->salesTypeID;
    	$frequencyID = $json->frequencyID;
    	$itemID = $json->itemID;
    	$userID = $json->userID;
    	$prospectID = $json->prospectID;
    	$customerID = $json->customerID;
    	$token =$json->token;
    	$location = $json->location;
    	$workMobile = $json->workMobile;
    	$fullName = $json->fullName;
    	$nationalIdNumber = $json->nationalIdNumber;
    	$paymentPlanDeposit = $json->paymentPlanDeposit;
    	$amount = $json->amount;

    	$contactsID;
    	$paymentPlanID;

    	if(!$token ){//|| !$salesTypeID || !$userID || !$amount || !$frequencyID){
	    	return $res->dataError("Token missing ");
	    }
	    if(!$salesTypeID ){
	    	return $res->dataError("salesTypeID missing ");
	    }
	     if(!$userID ){
	    	return $res->dataError("userID missing ");
	    }
	     if(!$amount ){
	    	return $res->dataError("amount missing ");
	    }
	     if(!$frequencyID ){
	    	//return $res->dataError("frequencyID missing ");
	    	$frequencyID=0;
	    }


	    $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

			
			if($prospectID || $prospectID > 0){ 
				$prospect = Prospects::findFirst(array("prospectsID=:id: ",
	    					'bind'=>array("id"=>$prospectID)));
				if($prospect){
					$contactsID = $prospect->contactsID;
				}
				else{
					 return $res->dataError("Prospect not found");
				}
			}
			elseif ($customerID || $customerID > 0) { //added create sale of an existing customer
				$customer = Customer::findFirst(array("customerID=:id: ",
	    					'bind'=>array("id"=>$customerID)));
			}
			elseif ($workMobile && $nationalIdNumber && $fullName && $location) {
				$workMobile = $res->formatMobileNumber($workMobile);
				$contactsID = $this->createContact($workMobile,$nationalIdNumber,$fullName,$location);

				if(!$contactsID || $contactsID <=0){
					return $res->dataError("Contacts create error");
				}
			}
			else{
				return $res->dataError("Prospect not found");
			}
 
			//then we create customer if customerId not provided
			if(!$customerID || $customerID <=0){
				$customerID = $this->createCustomer($userID,$contactsID);
			}
			

			if(!$customerID || $customerID <= 0 ){
				return $res->dataError("Customer not found");
			}

			//after creating customer and contacts above we create payment plan
			$paymentPlanID = $this->createPaymentPlan($paymentPlanDeposit,$salesTypeID,$frequencyID);

			if(!$paymentPlanID || $paymentPlanID <= 0 ){
				return $res->dataError("Payment Plan not found");
			}


		//now we can create a sale
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
	               return $res->dataError('sale create failed',$errors);
	          }

	          /*send message to customer*/
	           $MSISDN = $this->getCustomerMobileNumber($customerID);
	          $res->sendMessage($workMobile,"Your sale has been placed successfully");

	          $saleStatus = $this->mapItemToSale($sale->saleID,$itemID);

	          //now we map this sale to item mapItemToSale($saleID,$itemID)
	          if(!$saleStatus || $saleStatus <=0){
	          	return $res->dataError("Item not mapped to sale, please contact system admin $itemID");
	          }
	          //set item as sold
	          if (!$this->updateItemToSold($itemID)) {
	          	return $res->dataError('Item not marked as sold, please contact system admin',$sale);
	          }

	          return $res->success("Sale successfully done ",$sale);
    }

    public function getCustomerDetails($customerID){
    	$customerquery = "SELECT cs.workMobile ,cs.fullName from contacts cs left join customer co on cs.contactsID=co.contactsID WHERE co.customerID=$customerID";

    	$customer = $this->rawSelect($customerquery);
    	return $customer[0];

      }



	public function createCustomerSale(){//{paymentPlanID,amount,userID,workMobile,nationalIdNumber,fullName,location,token,items[]}
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

        $saleQuery ="SELECT s.salesID,si.itemID,co.workMobile,co.workEmail,co.passportNumber,co.nationalIdNumber,co.fullName,s.createdAt,co.location,c.customerID,s.paymentPlanID,s.amount,st.salesTypeName,i.serialNumber,p.productName, ca.categoryName FROM sales s JOIN sales_item si ON s.salesID=si.saleID LEFT JOIN customer c on s.customerID=c.customerID LEFT JOIN contacts co on c.contactsID=co.contactsID LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID LEFT JOIN item i on si.itemID=i.itemID LEFT JOIN product p on i.productID=p.productID LEFT JOIN category ca on p.categoryID=ca.categoryID";

		if(!$token){
		   return $res->dataError("Missing data ");
		}

		$tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }


		if($customerID > 0 && $userID > 0){
			 $saleQuery = $saleQuery." WHERE s.userID=$userID AND s.customerID=$customerID";
		}
		elseif ($customerID > 0 &&  $userID <=0) {
			$saleQuery = $saleQuery." WHERE s.customerID=$customerID";
		}
		elseif ($userID>0 &&  $customerID <=0) {
			 $saleQuery =$saleQuery." WHERE s.userID=$userID";
		}
		/*else{
			 $saleQuery ="SELECT s.salesID,si.itemID,co.workMobile,co.workEmail,co.passportNumber,co.nationalIdNumber,co.fullName,s.createdAt,co.location,c.customerID,s.paymentPlanID,s.amount,st.salesTypeName,i.serialNumber,p.productName, ca.categoryName FROM sales s JOIN sales_item si ON s.salesID=si.saleID LEFT JOIN customer c on s.customerID=c.customerID LEFT JOIN contacts co on c.contactsID=co.contactsID LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID LEFT JOIN item i on si.itemID=i.itemID LEFT JOIN product p on i.productID=p.productID LEFT JOIN category ca on p.categoryID=ca.categoryID ";
		}*/

		$sales = $this->rawSelect($saleQuery);


		return $res->getSalesSuccess($sales);

	}

	public function getTableSales(){ //sort, order, page, limit,filter,userID
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $userID = $request->getQuery('userID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');

        $countQuery = "SELECT count(s.salesID) as totalSales ";

        $defaultQuery = " FROM sales s JOIN sales_item si ON s.salesID=si.saleID LEFT JOIN customer c on s.customerID=c.customerID LEFT JOIN contacts co on c.contactsID=co.contactsID LEFT JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID LEFT JOIN item i on si.itemID=i.itemID LEFT JOIN product p on i.productID=p.productID LEFT JOIN category ca on p.categoryID=ca.categoryID ";

        $selectQuery ="SELECT s.salesID,s.userID as agentID , si.itemID,co.workMobile,co.workEmail,co.passportNumber,co.nationalIdNumber,co.fullName,s.createdAt,co.location,c.customerID,s.paymentPlanID,s.amount,st.salesTypeName,i.serialNumber,p.productName, ca.categoryName ";
          $condition ="";

       if($userID && $filter){
       	    $condition = " WHERE s.userID=$userID AND ";
        	//$countQuery=$countQuery.$defaultQuery.
        	//$selectQuery=$selectQuery.$defaultQuery." WHERE s.userID=$userID  ";
        }
        elseif ($userID && !$filter) {
            $condition = " WHERE s.userID=$userID ";
        }
        elseif (!$userID && $filter) {
            $condition = " WHERE ";
        	
        }
        elseif(!$userID && !$filter){
        	$condition = " ";
        }


        // else {
        // 	$selectQuery=$selectQuery.$defaultQuery." WHERE ";
        // 	$countQuery = $countQuery.$defaultQuery." WHERE ";
        // }

        
		      

        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
        	$selectQuery=$selectQuery.$defaultQuery.$condition." ".$queryBuilder;
        	//$countQuery = $countQuery." ".$queryBuilder;
        	if($filter){
        		$countQuery = $countQuery.$defaultQuery.$condition." ".$queryBuilder;
        	}
        	else{
        		$countQuery=$countQuery.$defaultQuery.$condition;
        	}
        }
        else{
        	$selectQuery=$selectQuery.$defaultQuery.$condition;
        	$countQuery=$countQuery.$defaultQuery.$condition;
        }
        //return $res->success($selectQuery);

        $count = $this->rawSelect($countQuery);

		$sales= $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
		$data["totalSales"] = $count[0]['totalSales'];
		$data["sales"] = $sales;

		return $res->success("Sales ",$data);


	}

	public function tableQueryBuilder($sort="",$order="",$page=1,$limit=10,$filter=""){
		$query = "";

		if(!$page || $page <= 0){
			$page=1;
		}
		if(!$limit ){
			$limit=10;
		}

		$ofset = ($page-1)*$limit;

		if($sort  && $order  && $filter ){
			$query = "  co.fullName REGEXP '$filter' OR ".
					"co.workMobile REGEXP '$filter' OR ".
					" co.nationalIdNumber REGEXP '$filter' OR ".
					"st.salesTypeName REGEXP '$filter' OR ".
					" p.productName REGEXP '$filter' OR ".
					" ca.categoryName REGEXP '$filter' ".
					" ORDER by $sort $order LIMIT $ofset,$limit";
		}
	
		else if($sort  && $order  && !$filter ){
			$query = " ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && !$order  && !$filter ){
			$query = " ORDER by $sort LIMIT $ofset,$limit";
		}

		else if(!$sort && !$order && !$filter){
			$query = " LIMIT $ofset,$limit";
		}

		else if(!$sort && !$order && $filter){
			$query =  " co.fullName REGEXP '$filter' OR ".
					"co.workMobile REGEXP '$filter' OR ".
					" co.nationalIdNumber REGEXP '$filter' OR ".
					"st.salesTypeName REGEXP '$filter' OR ".
					" p.productName REGEXP '$filter' OR ".
					" ca.categoryName REGEXP '$filter' ".
					" LIMIT $ofset,$limit";
		}

		return $query;

	}


}

