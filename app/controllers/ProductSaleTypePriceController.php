<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

/*
All  ProductSaleTypePrice CRUD operations 
*/

class ProductSaleTypePriceController extends Controller {

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
      checks if date is between two dates
    */
    protected function isDateBetweenDates($date, $startDate, $endDate) {
        $date = new DateTime($date);
        $startDate = new DateTime($startDate);
        $endDate = new DateTime($endDate);
        return $date > $startDate && $date < $endDate;
    }

     /*
    create ProductSaleTypePrice
    paramters:
    productID,salesTypeID,categoryID,price,deposit,token
    */

    public function create() { //{productID,salesTypeID,categoryID,price,deposit,discount,startDate,endDate}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $token = $json->token;
        $productID = $json->productID;
        $salesTypeID = $json->salesTypeID;
        $categoryID = $json->categoryID;
        $deposit = $json->deposit;
        $price = $json->price;
        $userID = $json->userID;

        if (!$token || !$salesTypeID || !$productID || !$categoryID || !$price || !$deposit || !$userID) {
            return $res->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $productSaleTypePrice = ProductSaleTypePrice::findFirst(array("salesTypeID=:salesTypeID: AND productID=:productID: AND price=:price: ",
                    'bind' => array("salesTypeID" => $salesTypeID, "productID" => $productID, "price" => $price)));

        if ($productSaleTypePrice) {
            return $res->dataError("same price exists");
        }

        $productSaleTypePrice = new ProductSaleTypePrice();
        $productSaleTypePrice->productID = $productID;
        $productSaleTypePrice->salesTypeID = $salesTypeID;
        $productSaleTypePrice->categoryID = $categoryID;
        $productSaleTypePrice->price = $price;
        $productSaleTypePrice->deposit = $deposit;
        $productSaleTypePrice->userID = $userID;
        $productSaleTypePrice->createdAt = date("Y-m-d H:i:s");

        if ($productSaleTypePrice->save() === false) {
            $errors = array();
            $messages = $productSaleTypePrice->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('ProductSaleTypePrice create failed', $errors);
        }

        return $res->success("product price created successfully ", $productSaleTypePrice);
    }
  

    /*
    update ProductSaleTypePrice
    paramters:
    productID,salesTypeID,categoryID,price,productSaleTypePriceID,deposit,token
    */


    public function update() {//{productID,salesTypeID,categoryID,price,productSaleTypePriceID,$deposit}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $token = $json->token;
        $productID = isset($json->productID) ? $json->productID : NULL;
        $salesTypeID = isset($json->salesTypeID) ? $json->salesTypeID : NULL;
        $categoryID = isset($json->categoryID) ? $json->categoryID : NULL;
        $userID = isset($json->userID) ? $json->userID : NULL;
        $price = isset($json->price) ? $json->price : NULL;
        $deposit = isset($json->deposit) ? $json->deposit : 0;
        $status = isset($json->status) ? $json->status : 0;
        $productSaleTypePriceID = isset($json->productSaleTypePriceID) ? $json->productSaleTypePriceID : NULL;

        if (!$token || !$productSaleTypePriceID || !$userID) {
            return $res->dataError("missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $productSaleTypePrice = ProductSaleTypePrice::findFirst(array("productSaleTypePriceID=:id:",
                    'bind' => array("id" => $productSaleTypePriceID)));

        if (!$productSaleTypePrice) {
            return $res->dataError("price does not exist");
        }

        $productSaleTypePrice->userID = $userID;

        if ($productID) {
            $productSaleTypePrice->productID = $productID;
        }
        if ($salesTypeID) {
            $productSaleTypePrice->salesTypeID = $salesTypeID;
        }
        if ($categoryID) {
            $productSaleTypePrice->categoryID = $categoryID;
        }
        if ($price) {
            $productSaleTypePrice->price = $price;
        }
        if ($deposit) {
            $productSaleTypePrice->deposit = $deposit;
        }

        if ($status) {
            $productSaleTypePrice->status = $status;
        }


        if ($productSaleTypePrice->save() === false) {
            $errors = array();
            $messages = $productSaleTypePrice->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('ProductSaleTypePrice update failed', $errors);
        }

        if ($status) {
            $similar = ProductSaleTypePrice::find(array("productID=:productID: AND salesTypeID=:salesTypeID: ",
                        'bind' => array("productID" => $productSaleTypePrice->productID, "salesTypeID" => $productSaleTypePrice->salesTypeID)));
            foreach ($similar as $similarPrice) {
                if ($similarPrice->productSaleTypePriceID != $productSaleTypePrice->productSaleTypePriceID) {
                    $similarPrice->status = 0;

                    if ($similarPrice->save() === false) {
                        $errors = array();
                        $messages = $similarPrice->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                        return $res->dataError('ProductSaleTypePrice update failed', $errors);
                    }
                }
            }
        }

        return $res->success("ProductSaleTypePrice updated successfully ", $productSaleTypePrice);
    }


    /*
    retrieve all ProductSaleTypePrice
    parameters:
    token
    */
    public function getAll() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $productSaleTypePriceID = $request->getQuery('productSaleTypePriceID');


        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $priceQuery = "SELECT * from product_sale_type_price";
        //$priceQuery = "SELECT * from product_sale_type_price p join discount d on p.productID=d.productID and p.salesTypeID=d.saleTypeID and d.status > 0";

        

        if ($productSaleTypePriceID) {
            $priceQuery = "SELECT * FROM product_sale_type_price WHERE productSaleTypePriceID=$productSaleTypePriceID";
        }

        $prices = $this->rawSelect($priceQuery);

        return $res->success("Prices ", $prices);
    }
    /*
    retrieve all ProductSaleTypePrice new version
    parameters:
    token
    */
    public function getAll_v2($origin=0) {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $token = $json->token;//$request->getQuery('token');
        //$productSaleTypePriceID = $json->productSaleTypePriceID;
        $userID = $json->userID;
        $saleTypeID = $json->saleTypeID;
        $products = $json->products;

        if (!$token || !$userID || !$saleTypeID) {
            return $res->dataError("Missing data ");
        }

        $products = str_replace("]","",str_replace("[", "", $products));
        $products = explode(",",$products);

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

       
        /*$priceQuery = "SELECT p.productSaleTypePriceID,p.productID,p.salesTypeID,p.categoryID,p.deposit,d.discountID,d.discountMargin,d.discountAmount,d.startDate,d.endDate,dt.discountTypeName,dt.discountTypeID,dc.conditionName,dc.conditionDescription from product_sale_type_price p left join discount d on p.productID=d.productID and p.salesTypeID=d.saleTypeID and d.status > 0  LEFT JOIN discount_types dt on d.discountTypeID=dt.discountTypeID LEFT JOIN discount_condition dc on d.discountConditionID=dc.discountConditionID ";
        $priceQuery*/

        $productData = array();
        $pricesData = array();
        $discountsData = array();
        

         foreach ($products as $productID) {

                 $priceQuery = "SELECT * FROM product_sale_type_price WHERE productID=$productID ";
                 $prices = $this->rawSelect($priceQuery);
                if(!$prices && $productID == 6 ){
                    $productID =5;
                    $prices = $this->rawSelect("SELECT * FROM product_sale_type_price WHERE productID=$productID ");
                }
                $discountsQuery = "SELECT * FROM discount d join discount_condition dc on d.discountConditionID=dc.discountConditionID JOIN discount_types dt on d.discountTypeID=dt.discountTypeID  join product p on d.productID=p.productID WHERE d.status=1 AND d.saleTypeID=$saleTypeID AND d.productID=$productID ";

                
                $discounts = $this->rawSelect($discountsQuery);
                $app_discount_statement = array();




                foreach ($discounts as $discount) {
                    $agents = $discount['agents'];
                    $discountID = $discount['discountID'];
                    $startDate = $discount['startDate'];
                    $endDate = $discount['endDate'];
                    $cur_date = date("Y-m-d H:i:s");

                    if($this->isDateBetweenDates($cur_date, $startDate, $endDate) == false){
                        $o_discount = Discount::findFirst(array("discountID=:id: ",
                                    'bind' => array("id" => $discountID)));
                        $o_discount->status = 0;
                        $o_discount->save();
                       
                    }
                    else{
                            if (strcasecmp($agents, 'all') == 0 ){
                                 $discountStatement = $discount['productName'].': Reward of '.$discount['discountAmount'].' if '.$discount['discountTypeName'].' is '.$discount['conditionDescription'].' '.$discount['discountMargin'];
                               
                                //array_push($app_discount_statement, $discountStatement);
                                 array_push($discountsData ,$discountStatement);
                            }
                            else{
                                 $allAgents = explode(",", $agents);
                                 foreach ($allAgents as $agent) {
                                     if(strcasecmp($agents, $userID) == 0){
                                        $discountStatement = $discount['productName'].': Reward of '.$discount['discountAmount'].' if '.$discount['discountTypeName'].' is '.$discount['conditionDescription'].' '.$discount['discountMargin'];
                                
                                      // array_push($app_discount_statement, $discountStatement);
                                        array_push($discountsData ,$discountStatement);

                                     }
                                 }
                            }
                    }
                    
                }

                foreach ($prices as $price) {
                    array_push($pricesData, $price);
                }
                

             }

        
        $data = array(
                "prices"=>$pricesData,
                "discounts"=>$discountsData
                );
        

        return $res->success("Prices ", $data);
    }



     /*
    retrieve  ProductSaleTypePrices to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */
    public function getTablePrices() { //sort, order, page, limit,filter
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);
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
        $baseQuery = " FROM product_sale_type_price ps join product p on ps.productID=p.productID "
                . "LEFT JOIN category c on ps.categoryID=c.categoryID LEFT JOIN sales_type st on ps.salesTypeID=st.salesTypeID "
                . "LEFT JOIN users u ON ps.userID=u.userID LEFT JOIN contacts ct ON u.contactID=ct.contactsID";

        $selectQuery = "SELECT ps.productSaleTypePriceID, c.categoryName,p.productName, "
                . "st.salesTypeName,ps.deposit ,ps.price, ct.fullName, ps.status, ps.createdAt  ";
        $condition = "";


        if ($productID && $filter) {
            $condition = " WHERE ps.productID=$productID  AND ";
        } elseif ($productID && !$filter) {
            $condition = " WHERE ps.productID=$productID  ";
        } elseif (!$productID && !$filter) {
            $condition = "  ";
        }



        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit, $filter);

        if ($queryBuilder) {
            $selectQuery = $selectQuery . $baseQuery . $condition . " " . $queryBuilder;
            if ($filter) {
                $countQuery = $countQuery . $baseQuery . $condition . " " . $queryBuilder;
            } else {
                $countQuery = $countQuery . $baseQuery . $condition;
            }
        } else {
            $selectQuery = $selectQuery . $baseQuery . $condition;
            $countQuery = $countQuery . $baseQuery . $condition;
        }
        
        $count = $this->rawSelect($countQuery);

        $prices = $this->rawSelect($selectQuery);
        $data["totalPrices"] = $count[0]['totalPrices'];
        $data["prices"] = $prices;

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

        if (!$limit) {
            $limit = 10;
        }

        $ofset = ($page - 1) * $limit;
        if ($sort && $order && $filter) {
            $query = "  c.categoryName REGEXP $filter OR st.salesTypeDeposit REGEXP $filter OR ps.price REGEXP $filter  OR p.productName REGEXP $filter OR st.salesTypeName REGEXP $filter ORDER by $sort $order LIMIT $ofset,$limit";
        } else if ($sort && $order && !$filter) {
            $query = " ORDER by $sort $order LIMIT $ofset,$limit";
        } else if ($sort && $order && !$filter) {
            $query = " ORDER by $sort $order  LIMIT $ofset,$limit";
        } else if (!$sort && !$order) {
            $query = " LIMIT $ofset,$limit";
        } else if (!$sort && !$order && $filter) {
            $query = " c.categoryName REGEXP $filter OR st.salesTypeDeposit REGEXP $filter OR ps.price REGEXP $filter  OR p.productName REGEXP $filter OR st.salesTypeName REGEXP $filter LIMIT $ofset,$limit";
        }

        return $query;
    }

}
