<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;


class DiscountController extends Controller
{

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


    public function create() //{saleTypeID,productID,agents,discountTypeID,discountConditionID,discountMargin,discountAmount,startDate,endDate,status,token
    {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $token = $json->token;
        $saleTypeID = $json->saleTypeID;
        $productID = $json->productID; 
        $userID = $json->userID;
        $agents = $json->agents?$json->agents:'all';
        $discountTypeID = $json->discountTypeID?$json->discountTypeID:'0';
        $discountConditionID = $json->discountConditionID?$json->discountConditionID:'0'; 
        $discountMargin = $json->discountMargin?$json->discountMargin:'0';
        $discountAmount = $json->discountAmount;
        $startDate = $json->startDate; 
        $endDate = $json->endDate;
        $status = $json->status?$json->status:'0'; 


       if(!$token || !$userID ||!$discountTypeID || !$saleTypeID ||!$productID || !$discountAmount || !$startDate || !$endDate ){
            return $res->dataError("Missing data ",false); 
        }

       /* $discount = Discount::findFirst(array("saleTypeID=:s_id: and productID = :p_id: and agents = :agents: ",
                    'bind' => array("w_mobile" => $workMobile)));
*/
        $discount = new Discount();
        $discount->saleTypeID = $saleTypeID;
        $discount->productID = $productID;
        $discount->agents = $agents;
        $discount->userID = $userID;
        $discount->discountTypeID =$discountTypeID;
        $discount->discountConditionID = $discountConditionID;
        $discount->discountMargin = $discountMargin;
        $discount->discountAmount = $discountAmount;
        $discount->startDate = $startDate;
        $discount->endDate = $endDate;
        $discount->status = $status;
        $discount->createdAt = date("Y-m-d H:i:s");

        try{

               if ($discount->save() === false) {
                    $errors = array();
                    $messages = $discount->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback("discount create" . json_encode($errors));
                }
                $dbTransaction->commit();
                return $res->success("Success ", $discount);
            } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
                $message = $e->getMessage();
                return $res->dataError('discount create', $message);
            }

    }
  
  /*
    retrieve discount to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */
    public function getTableDiscount() { 
        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);
        $request = new Request();
        $res = new SystemResponses();
        $productID = $request->getQuery('productID') ? $request->getQuery('productID') : NULL;
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $salesTypeID = $request->getQuery('salesTypeID') ? $request->getQuery('salesTypeID') : 0;
        $startDate = $request->getQuery('start');
        $endDate = $request->getQuery('end');

        $countQuery = "SELECT count(d.discountID) as totalDiscounts ";

        $defaultQuery = "FROM discount d join sales_type st on d.saleTypeID=st.salesTypeID JOIN discount_condition dc on d.discountConditionID=dc.discountConditionID join discount_types dt on d.discountTypeID=dt.discountTypeID WHERE productID=$productID ";

        $selectQuery = "SELECT d.discountID,st.salesTypeName,d.agents,d.discountMargin,d.discountConditionID,dc.conditionName,dc.conditionDescription,d.discountAmount,d.startDate,d.endDate,d.status,dt.discountTypeName,d.createdAt,CONCAT(dt.discountTypeName,' is ',dc.conditionName,' ',d.discountMargin) as d_condition ";

        $whereArray = [
            'filter' => $filter,
            'd.saleTypeID' =>$saleTypeID,
            'date' => [$startDate, $endDate]
        ];

        $logger->log("Discounts Request Data: " . json_encode($whereArray));

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['d.serialNumber', 'st.salesTypeName', 'd.agents', 'dc.conditionName', 'd.discountAmount'];

                $valueString = "";
                foreach ($searchColumns as $searchColumn) {
                    $valueString .= $value ? "" . $searchColumn . " REGEXP '" . $value . "' ||" : "";
                }
                $valueString = chop($valueString, " ||");
                if ($valueString) {
                    $valueString = "(" . $valueString;
                    $valueString .= ") AND";
                }
                $whereQuery .= $valueString;
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(d.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
                }
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND" : "";
                $whereQuery .= $valueString;
            }
        }

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND");
        }

        $whereQuery = $whereQuery ? "AND $whereQuery " : "";

        $countQuery = $countQuery . $defaultQuery . $whereQuery;
        $selectQuery = $selectQuery . $defaultQuery . $whereQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

        $logger->log("Discount Request Query: " . $selectQuery);

        $count = $this->rawSelect($countQuery);
        $discounts = $this->rawSelect($selectQuery);

        $data["totalDiscounts"] = $count[0]['totalDiscounts'];
        $data["discounts"] = $discounts;


        return $res->success("Discounts ", $data);
    }

 /*
    util function to build all get queries based on passed parameters
    */
    public function tableQueryBuilder($sort = "", $order = "", $page = 0, $limit = 10) {

        $sortClause = "ORDER BY $sort $order";

        if (!$page || $page <= 0) {
            $page = 1;
        }
        if (!$limit) {
            $limit = 10;
        }

        $ofset = (int) ($page - 1) * $limit;
        $limitQuery = "LIMIT $ofset, $limit";

        return "$sortClause $limitQuery";
    }


     /*
    update discount
    paramters:
    discountID
    */


    public function actionDiscount() {//{discountID,status,userID}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $token = $json->token;
        $discountID = isset($json->discountID) ? $json->discountID : NULL;
        $status = isset($json->status) ? $json->status : 0;
        $userID = isset($json->userID) ? $json->userID : NULL;
        

        if (!$token || !$discountID || !$userID) {
            return $res->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $discount = Discount::findFirst(array("discountID=:id:",
                    'bind' => array("id" => $discountID)));

        if (!$discount) {
            return $res->dataError("Discounts does not exist");
        }

        $discount->userID = $userID;
        $discount->status = $status;


        if ($discount->save() === false) {
            $errors = array();
            $messages = $discount->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('Discount update failed', $errors);
        }
/*
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
        */

        return $res->success("Discount updated successfully ", $discount);
    }



    public function getAllTypes(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');

        if(!$token){
            return $res->dataError("Missing data "); 
        }
      $selectQuery = "SELECT discountTypeID,discountTypeName,description FROM discount_types ";
      $discountConditions = $this->rawSelect($selectQuery);
      return $res->success("Dsicount types ",$discountConditions);

   }


}

