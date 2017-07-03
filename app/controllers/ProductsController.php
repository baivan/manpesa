<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

/*
All  Products CRUD operations 
*/

class ProductsController extends Controller {
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
    create Product
    paramters:
    productName,productImage,categoryID,description
    */


    public function create() { //{productName,productImage,categoryID,description,dependentProductID,token}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $json = $request->getJsonRawBody();
        $productName = $json->productName;
        $productImage = $json->productImage;
        $description = $json->description;
        $categoryID = $json->categoryID;
        $dependentProductID = $json->dependentProductID;
        $token = $json->token;

        if (!$token || !$categoryID || !$productName) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $product = Product::findFirst(array("productName=:name: ",
                    'bind' => array("name" => $productName)));
        if ($product) {
            return $res->dataError("Product with similar name exists");
        }

        try{
            $product = new Product();
            $product->productName = $productName;
            $product->productImage = $productImage;
            $product->categoryID = $categoryID;
            $product->description = $description;
            $product->createdAt = date("Y-m-d H:i:s");

            if ($product->save() === false) {
                $errors = array();
                $messages = $product->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                 $dbTransaction->rollback('product create failed', $errors);
            }

            if($dependentProductID && is_numeric($dependentProductID)){
                $this->addComplementProduct($product->productID,$dependentProductID,$dbTransaction);
            }
        
            $dbTransaction->commit();
            return $res->success("Product saved successfully", $product);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Product create error', $message);
        }
        
    }


    /*
    save product dependent
    parameters: mainProductID,complementaryProductID
    */
    private function addComplementProduct($mainProductID,$complementaryProductID,$dbTransaction){

        $complementProduct = ComplementProduct::findFirst(array("mainProductID=:m_id: and complementaryProductID=:c_id: ",
                    'bind' => array("m_id" => $mainProductID,"c_id"=>$complementaryProductID)));

        if($complementProduct){
            return true;
        }

        $complementProduct = new ComplementProduct();
        $complementProduct->mainProductID = $mainProductID;
        $complementProduct->complementaryProductID = $complementaryProductID;
        $complementProduct->createdAt = date("Y-m-d H:i:s");

        if ($complementProduct->save() === false) {
            $errors = array();
            $messages = $complementProduct->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
             $dbTransaction->rollback('Complement Product create failed', $errors);
             return false;
        }
            
        return true;
    
    }

/*
    update Product
    paramters:
    productID (required)
    productName,productImage,categoryID,description
    */
    public function edit() {//productName,productImage,categoryID,productID,dependentProductID,description,token
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $productName = $json->productName;
        $productID = $json->productID;
        $productImage = $json->productImage;
        $categoryID = $json->categoryID;
        $description = $join->description;
        $dependentProductID = $join->dependentProductID;
        $token = $json->token;

      

        if (!$token || !$productID ) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $product = Product::findFirst(array("productID=:id: ",
                    'bind' => array("id" => $productID)));
        if (!$product) {
            return $res->dataError("Product not found");
        }

        if ($productName) {
            $product->productName = $productName;
        }
        if ($productImage) {
            $product->productImage = $productImage;
        }
        if ($categoryID) {
            $product->categoryID = $categoryID;
        }

        if ($description) {
            $product->description = $description;
        }


        try{
            if ($product->save() === false) {
                $errors = array();
                $messages = $product->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                //return $res->dataError('product edit failed', $errors);
                $dbTransaction->rollback('product edit failed', $errors);
            }
            if($dependentProductID && is_numeric($dependentProductID)){
                $this->addComplementProduct($product->productID,$dependentProductID,$dbTransaction);
            }

            $dbTransaction->commit();
            return $res->success("Product edited successfully", $product);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Product edited error', $message);
        }

            
    }

    

    /*
    retrieve all products
    parameters:
    productID (optional)
    token
    */

    public function getAll() { //productID,token
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $productID = $request->getQuery('productID');

        $productQuery = "SELECT p.productID,p.productName,p.productImage,p.createdAt,  c.categoryID,c.categoryName,p1.productID as mainProductID,p1.productName as mainProductName FROM product p JOIN category c ON p.categoryID = c.categoryID LEFT JOIN complement_product cp on p.productID=cp.mainProductID left JOIN product p1 on cp.complementaryProductID = p1.productID";
        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }


        if ($productID) {
            $productQuery = "SELECT p.productID,p.productName,p.productImage,p.createdAt,  c.categoryID,c.categoryName,p1.productID as mainProductID,p1.productName as mainProductName FROM product p JOIN category c ON p.categoryID = c.categoryID LEFT JOIN complement_product cp on p.productID=cp.mainProductID left JOIN product p1 on cp.complementaryProductID = p1.productIDWHERE p.productID=$productID";
        }

        $products = $this->rawSelect($productQuery);

        return $res->success("Products ", $products);
    }

    /*
    retrieve  products to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */

    public function getTableProducts() { //sort, order, page, limit,filter
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

        $countQuery = "SELECT count(productID) as totalProducts from product";

        $selectQuery = "SELECT * FROM `product` p join category c on p.categoryID=c.categoryID ";


        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit, $filter);

        if ($queryBuilder) {
            $selectQuery = $selectQuery . " " . $queryBuilder;
        }
        //return $res->success($selectQuery);

        $count = $this->rawSelect($countQuery);

        $products = $this->rawSelect($selectQuery);
        //users["totalUsers"] = $count[0]['totalUsers'];
        $data["totalProducts"] = $count[0]['totalProducts'];
        $data["products"] = $products;

        return $res->getSalesSuccess($data);
    }

     /*
    util function to build all get queries based on passed parameters
    */
    public function tableQueryBuilder($sort = "", $order = "", $page = 0, $limit = 10, $filter = "") {
        $query = "";

        if (!$page || $page <= 0) {
            $page = 1;
        }

        $ofset = ($page - 1) * $limit;
        if ($sort && $order && $filter) {
            $query = " WHERE c.categoryName  REGEXP '$filter' OR p.productName  REGEXP '$filter'  ORDER by p.$sort $order LIMIT $ofset,$limit";
        } else if ($sort && $order && !$filter && $limit > 0) {
            $query = " ORDER by p.$sort $order LIMIT $ofset,$limit";
        } else if ($sort && $order && !$filter && !$limit) {
            $query = " ORDER by p.$sort $order  LIMIT $ofset,10";
        } else if (!$sort && !$order && $limit > 0) {
            $query = " LIMIT $ofset,$limit";
        } else if (!$sort && !$order && $filter && !$limit) {
            $query = " WHERE c.categoryName  REGEXP '$filter' OR p.productName  REGEXP '$filter'  LIMIT $ofset,10";
        } else if (!$sort && !$order && $filter && $limit) {
            $query = " WHERE c.categoryName  REGEXP '$filter' OR p.productName  REGEXP '$filter'  LIMIT $ofset,$limit";
        }

        return $query;
    }

}
