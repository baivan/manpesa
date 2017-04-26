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

		return $res->success("Prices ",$prices);

	}
	public function getTablePrices(){ //sort, order, page, limit,filter
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $productID = $request->getQuery('productID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');

        $countQuery = "SELECT count(productSaleTypePriceID) as totalPrices ";
        $baseQuery = " FROM product_sale_type_price ps join product p on ps.productID=p.productID LEFT JOIN category c on ps.categoryID=c.categoryID LEFT JOIN sales_type st on ps.salesTypeID=st.salesTypeID ";

        $selectQuery = "SELECT ps.productSaleTypePriceID, c.categoryName,p.productName,st.salesTypeName,st.salesTypeDeposit ,ps.price  ";
        $condition = "";

        //$countQuery = $countQuery.$baseQuery;
        //$selectQuery = $selectQuery.$baseQuery;


        if($productID && $filter){
        	$condition=" WHERE ps.productID=$productID  AND ";
        }
        elseif ($productID && !$filter) {
        	$condition=" WHERE ps.productID=$productID  ";
        }
        elseif (!$productID && !$filter){
        	$condition="  ";
        	
        }

      	

        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
        	$selectQuery=$selectQuery.$baseQuery.$condition." ".$queryBuilder;
        	if($filter){
        		$countQuery = $countQuery.$baseQuery.$condition." ".$queryBuilder;
        	}
        	else{
        		$countQuery = $countQuery.$baseQuery.$condition;
        	}

        }
        else{
        	$selectQuery=$selectQuery.$baseQuery.$condition;
        	$countQuery = $countQuery.$baseQuery.$condition;
        }
        //return $res->success($selectQuery);

        $count = $this->rawSelect($countQuery);

		$prices= $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
		$data["totalPrices"] = $count[0]['totalPrices'];
		$data["prices"] = $prices;

		return $res->getSalesSuccess($data);


	}

	public function tableQueryBuilder($sort="",$order="",$page=0,$limit=10,$filter=""){
		$query = "";
		if(!$page || $page <= 0){
			$page=1;
		}

		if(!$limit){
			$limit=10;
		}

		$ofset = ($page-1)*$limit;
		if($sort  && $order  && $filter ){
			$query = "  c.categoryName REGEXP $filter OR st.salesTypeDeposit REGEXP $filter OR ps.price REGEXP $filter  OR p.productName REGEXP $filter OR st.salesTypeName REGEXP $filter ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter ){
			$query = " ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter ){
			$query = " ORDER by $sort $order  LIMIT $ofset,$limit";
		}
		else if(!$sort && !$order ){
			$query = " LIMIT $ofset,$limit";
		}

		else if(!$sort && !$order && $filter){
			$query = " c.categoryName REGEXP $filter OR st.salesTypeDeposit REGEXP $filter OR ps.price REGEXP $filter  OR p.productName REGEXP $filter OR st.salesTypeName REGEXP $filter LIMIT $ofset,$limit";
		}

		return $query;

	}


}

