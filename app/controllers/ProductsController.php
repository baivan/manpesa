<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;


class ProductsController extends Controller
{

		protected function rawSelect($statement)
		       { 
		          $connection = $this->di->getShared("db"); 
		          $success = $connection->query($statement);
		          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
		          $success = $success->fetchAll($success); 
		          return $success;
		       }

		public function create (){ //{productName,productImage,categoryID,token}
			   $jwtManager = new JwtManager();
		    	$request = new Request();
		    	$res = new SystemResponses();
		    	$json = $request->getJsonRawBody();
		    	$productName = $json->productName;
		    	$productImage = $json->productImage;
		    	$categoryID = $json->categoryID;
		    	$token = $json->token;

		    	if(!$token || !$categoryID || !$productName){
			    	return $res->dataError("Missing data ");
			    }

			    $tokenData = $jwtManager->verifyToken($token,'openRequest');
		       if(!$tokenData){
		         return $res->dataError("Data compromised");
		       }

		       $product = Product::findFirst(array("productName=:name: ",
			    					'bind'=>array("name"=>$productName)));
		       if($product){
		       	return $res->dataError("Product with similar name exists");
		       }

		       $product = new Product();
		       $product->productName = $productName;
		       $product->productImage = $productImage;
		       $product->categoryID= $categoryID;
		       $product->createdAt = date("Y-m-d H:i:s");

		       if($product->save()===false){
			            $errors = array();
			                    $messages = $product->getMessages();
			                    foreach ($messages as $message) 
			                       {
			                         $e["message"] = $message->getMessage();
			                         $e["field"] = $message->getField();
			                          $errors[] = $e;
			                        }
			                  return $res->dataError('product create failed',$errors);
			          }

			      return $res->success("Product saved successfully",$product);
		}
		public function edit(){//productName,productImage,categoryID,productID,token
				$jwtManager = new JwtManager();
		    	$request = new Request();
		    	$res = new SystemResponses();
		    	$json = $request->getJsonRawBody();
		    	$productName = $json->productName;
		    	$productID = $json->productID;
		    	$productImage = $json->productImage;
		    	$categoryID = $json->categoryID;
		    	$token = $json->token;

		    	if(!$token || !$workMobile || !$fullName){
			    	return $res->dataError("Missing data ");
			    }

			    $tokenData = $jwtManager->verifyToken($token,'openRequest');
		       if(!$tokenData){
		         return $res->dataError("Data compromised");
		       }

		       $product = Product::findFirst(array("productID=:id: ",
			    					'bind'=>array("id"=>$productID)));
		       if(!$product){
		       	return $res->dataError("Product not found");
		       }

		       if($productName){
		       	$product->productName = $productName;
		       }
		       if($productImage){
		       	$product->productImage = $productImage;
		       }
		       if($categoryID){
		       	$product->categoryID = $categoryID;
		       }
		       
		      

		       if($product->save()===false){
			            $errors = array();
			                    $messages = $product->getMessages();
			                    foreach ($messages as $message) 
			                       {
			                         $e["message"] = $message->getMessage();
			                         $e["field"] = $message->getField();
			                          $errors[] = $e;
			                        }
			                  return $res->dataError('product edit failed',$errors);
			          }

			      return $res->success("Product edited successfully",$product);
		}
		public function delete(){//productID,token

		}
		public function getAll(){ //productID,token
			   $jwtManager = new JwtManager();
		    	$request = new Request();
		    	$res = new SystemResponses();
		    	$token = $request->getQuery('token');
		        $productID = $request->getQuery('productID');

		        $productQuery = "SELECT p.productID,p.productName,p.productImage,p.createdAt,  c.categoryID,c.categoryName FROM product p JOIN category c ON p.categoryID = c.categoryID";
		        if(!$token){
		        	return $res->dataError("Missing data ");
		        }

		        $tokenData = $jwtManager->verifyToken($token,'openRequest');
		       if(!$tokenData){
		         return $res->dataError("Data compromised");
		       }


		       if($productID){
		       	  $productQuery = "SELECT p.productID,p.productName,p.productImage,p.createdAt, c.categoryID,c.categoryName FROM product p JOIN category c ON p.categoryID = c.categoryID WHERE p.productID=$productID";
		       }

		       $products = $this->rawSelect($productQuery);

		       return $res->getSalesSuccess($products);
		}

}

