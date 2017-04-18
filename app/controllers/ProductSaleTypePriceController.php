<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;

class ProductSaleTypePriceController extends Controller
{

    protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }
	public function create(){ //{productID,salesTypeID,categoryID,price}
		 $jwtManager = new JwtManager();
		$request = new Request();
		$res = new SystemResponses();
		$json = $request->getJsonRawBody();
		$token = $json->token;
		$productID = $json->productID;
		$salesTypeID = $json->salesTypeID;
		$categoryID = $json->categoryID;
		$price = $json->price;

		if(!$token || !$salesTypeID || !$productID || !$categoryID || !$price ){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	     $productSaleTypePrice = ProductSaleTypePrice::findFirst(array("salesTypeID=:salesTypeID: AND productID=:productID: AND categoryID=:categoryID: ",
	    					'bind'=>array("salesTypeID"=>$salesTypeID,"productID"=>$productID,"categoryID"=>$categoryID)));

	     if($productSaleTypePrice){
	     	return $res->dataError("same price exists");
	     }

	      $productSaleTypePrice = new ProductSaleTypePrice();
	      $productSaleTypePrice->productID = $productID;
	      $productSaleTypePrice->salesTypeID = $salesTypeID;
	      $productSaleTypePrice->categoryID = $categoryID;
	      $productSaleTypePrice->price = $price;
	      $productSaleTypePrice->createdAt= date("Y-m-d H:i:s");

	      if($productSaleTypePrice->save()===false){
	            $errors = array();
                $messages = $productSaleTypePrice->getMessages();
                foreach ($messages as $message) 
                   {
                     $e["message"] = $message->getMessage();
                     $e["field"] = $message->getField();
                      $errors[] = $e;
                    }
              return $res->dataError('ProductSaleTypePrice create failed',$errors);
	          }

	     return $res->success("ProductSaleTypePrice created successfully ",$productSaleTypePrice);


	}       
	public function update(){//{productID,salesTypeID,categoryID,price,productSaleTypePriceID}
		 $jwtManager = new JwtManager();
		$request = new Request();
		$res = new SystemResponses();
		$json = $request->getJsonRawBody();
		$token = $json->token;
		$productID = $json->productID;
		$salesTypeID = $json->salesTypeID;
		$categoryID = $json->categoryID;
		$price = $json->price;
		$productSaleTypePriceID = $json->productSaleTypePriceID;

		if(!$token || !$productSaleTypePriceID ){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	     $productSaleTypePrice = ProductSaleTypePrice::findFirst(array("productSaleTypePriceID=:id:",
	    					'bind'=>array("id"=>$productSaleTypePriceID)));

	     if(!$productSaleTypePrice){
	     	return $res->dataError("price doesnot exist");
	     }

	      if($productID){
	      	$productSaleTypePrice->productID = $productID;
	      }
	      if($salesTypeID){
	      	$productSaleTypePrice->salesTypeID = $salesTypeID;
	      }
	      if($categoryID){
	      	 $productSaleTypePrice->categoryID = $categoryID;
	      }
	      if($price){
	      	 $productSaleTypePrice->price = $price;
	      }
	      

	      if($productSaleTypePrice->save()===false){
	            $errors = array();
                $messages = $productSaleTypePrice->getMessages();
                foreach ($messages as $message) 
                   {
                     $e["message"] = $message->getMessage();
                     $e["field"] = $message->getField();
                      $errors[] = $e;
                    }
              return $res->dataError('ProductSaleTypePrice update failed',$errors);
	          }

	     return $res->success("ProductSaleTypePrice updated successfully ",$productSaleTypePrice);

	}
	public function getAll(){
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $productSaleTypePriceID = $request->getQuery('productSaleTypePriceID');

        if(!$token ){
	    	return $res->dataError("Missing data ");
	    }
	    
	    $tokenData = $jwtManager->verifyToken($token,'openRequest');

		  if(!$tokenData){
		    return $res->dataError("Data compromised");
		  }

		  $priceQuery = "SELECT * from product_sale_type_price";

		  if($productSaleTypePriceID){
		  	 $priceQuery = "SELECT * FROM product_sale_type_price WHERE productSaleTypePriceID=$productSaleTypePriceID";
		  }

		 $prices= $this->rawSelect($priceQuery);

		return $res->getSalesSuccess($prices);

	}
	//public function (){}


}

