<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class ProductsController extends Controller {

    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    public function create() { //{productName,productImage,categoryID,description,token}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $productName = $json->productName;
        $productImage = $json->productImage;
        $description = $join->description;
        $categoryID = $json->categoryID;
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
            return $res->dataError('product create failed', $errors);
        }

        return $res->success("Product saved successfully", $product);
    }

    public function edit() {//productName,productImage,categoryID,productID,description,token
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $productName = $json->productName;
        $productID = $json->productID;
        $productImage = $json->productImage;
        $categoryID = $json->categoryID;
        $description = $join->description;
        $token = $json->token;

        if (!$token || !$workMobile || !$fullName) {
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



        if ($product->save() === false) {
            $errors = array();
            $messages = $product->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('product edit failed', $errors);
        }

        return $res->success("Product edited successfully", $product);
    }

    public function delete() {//productID,token
    }

    public function activateWarranty() {//{partnerSale,serialNumber,userID,token}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $partnerSale = $json->partnerSale;
        $serialNumber = $json->serialNumber;
        $userID = $json->userID;
        $token = $json->token;

        if (!$token || !$serialNumber || !$userID) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $item = NULL;

        try {
            if ($partnerSale) {
                $item = PartnerSaleItem::findFirst(array("serialNumber=:serialNumber: ",
                            'bind' => array("serialNumber" => $serialNumber)));

                if (!$item) {
                    return $res->dataError("the serial number does not exist.please link it to a sale", $serialNumber);
                }

                $item->status = 5;
            } else {
                $item = Item::findFirst(array("serialNumber=:serialNumber: ",
                            'bind' => array("serialNumber" => $serialNumber)));
                if (!$item) {
                    return $res->dataError("the serial number does not exist.please link it to a sale", $serialNumber);
                }

                $item->status = 5;

                $saleItem = SalesItem::findFirst(array("itemID=:itemID: ",
                            'bind' => array("itemID" => $item->itemID)));
                if ($saleItem) {
                    $saleItem->status = 5;
                    $saleItem->save();
                }
            }

            if ($item->save() === false) {
                $errors = array();
                $messages = $item->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $dbTransaction->rollback("warranty activation for product failed " . $errors);
            }
            $dbTransaction->commit();
            return $res->success("product warranty activated successfully", $item);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('product warranty activation error', $message);
        }
    }

    public function getAll() { //productID,token
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $productID = $request->getQuery('productID');

        $productQuery = "SELECT p.productID,p.productName,p.productImage,p.createdAt,  c.categoryID,c.categoryName FROM product p JOIN category c ON p.categoryID = c.categoryID";
        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }


        if ($productID) {
            $productQuery = "SELECT p.productID,p.productName,p.productImage,p.createdAt, c.categoryID,c.categoryName FROM product p JOIN category c ON p.categoryID = c.categoryID WHERE p.productID=$productID";
        }

        $products = $this->rawSelect($productQuery);

        return $res->success("Products ", $products);
    }

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
