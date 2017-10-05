<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

/*
All  promotion CRUD operations 
*/
class PromotionController extends Controller
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

    public function create(){ //{promotionName,startDate,endDate,status,isPendingSale,isPartnerSale,isAgentSale,isGroup,saleCreatedAt,productID,isWarranted,sale_item_status,rewardTypeID,value,hoursToExpire,smsTemplateID,saleCreatedEndDate,saleCreatedStartDate}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $json = $request->getJsonRawBody();

        $promotionName = isset($json->promotionName) ? $json->promotionName : NULL;
        $startDate = isset($json->startDate) ? $json->startDate : NULL;
        $endDate = isset($json->endDate) ? $json->endDate : NULL;
        $status = isset($json->status) ? $json->status : 0;
        $isPendingSale = isset($json->isPendingSale) ? $json->isPendingSale : 0;
        $saleTypeID = isset($json->saleTypeID) ? $json->saleTypeID : 0;        
        $isPartnerSale = isset($json->isPartnerSale) ? $json->isPartnerSale : 0;
        $isAgentSale = isset($json->isAgentSale) ? $json->isAgentSale : 0;
        $isGroup = isset($json->isGroup) ? $json->isGroup : 0;
        $saleCreatedAt = isset($json->saleCreatedAt) ? $json->saleCreatedAt : NULL;
        $saleCreatedStartDate = isset($json->saleCreatedStartDate) ? $json->saleCreatedStartDate : NULL;
        $saleCreatedEndDate = isset($json->saleCreatedEndDate) ? $json->saleCreatedEndDate : NULL;
        $productID = isset($json->productID) ? $json->productID : 0;
        $isWarranted = isset($json->isWarranted) ? $json->isWarranted : 0;
        $sale_item_status = isset($json->sale_item_status) ? $json->sale_item_status : 0;
        $rewardTypeID = isset($json->rewardTypeID) ? $json->rewardTypeID : 0;
        $value = isset($json->value) ? $json->value : NULL;
        $hoursToExpire = isset($json->hoursToExpire) ? $json->hoursToExpire : 0;
        $smsTemplateID = isset($json->smsTemplateID) ? $json->smsTemplateID : 0;

        try{
        //create salePromotionID
        $salePromotionID=$this->createSalePromotion($isPendingSale,$isPartnerSale,$isAgentSale,$isGroup,$saleCreatedAt,$saleCreatedEndDate,$saleCreatedStartDate,$saleTypeID,$dbTransaction);


        //create productPromotionID
        $productPromotionID=$this->createProductPromotion($productID,$isWarranted,$sale_item_status,$dbTransaction);
        //create promotionRewardID
        $promotionRewardID= $this->createPromotionReward($rewardTypeID,$value,$hoursToExpire,$dbTransaction);
 
        //create smsTemplateID

         //create promotion
        $promotion = new Promotion();
        $promotion->promotionName = $promotionName;
        $promotion->startDate = $startDate;
        $promotion->endDate = $endDate;
        $promotion->status = $status;
        $promotion->salePromotionID = $salePromotionID;
        $promotion->productPromotionID = $productPromotionID;
        $promotion->promotionRewardID = $promotionRewardID;
        $promotion->smsTemplateID = $smsTemplateID;
        $promotion->createdAt = date("Y-m-d H:i:s");

        if ($promotion->save() === false) {
                $errors = array();
                $messages = $promotion->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $dbTransaction->rollback('promotion create error' . json_encode($errors));
                $res->dataError('promotion create error', $errors);
                 
        }
        $dbTransaction->commit();
        return $res->success("Promotion created successfully",$promotion);
        
         } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Transaction create error', $message);
        }
       

    }

    private function createSalePromotion($isPendingSale,$isPartnerSale,$isAgentSale,$isGroup,$saleCreatedAt,$saleCreatedEndDate,$saleCreatedStartDate,$saleTypeID,$dbTransaction){
         $res = new SystemResponses();
         $salePromotion = new SalePromotion();
         $salePromotion->isPendingSale = $isPendingSale;
         $salePromotion->isPartnerSale = $isPartnerSale;
         $salePromotion->isAgentSale = $isAgentSale;
         $salePromotion->saleCreatedAt = $saleCreatedStartDate;
         $salePromotion->saleCreatedStartDate = $saleCreatedStartDate;
         $salePromotion->saleCreatedEndDate = $saleCreatedEndDate;
         $salePromotion->saleTypeID=$saleTypeID;
         $salePromotion->isGroup = $isGroup;
         $salePromotion->createdAt = date("Y-m-d H:i:s");

         if ($salePromotion->save() === false) {
                $errors = array();
                $messages = $salePromotion->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                 $res->dataError('salePromotion create error '.json_encode($errors), $errors);
                 return 0;
        }
        return $salePromotion->salePromotionID;
         
    }

    private function createProductPromotion($productID,$isWarranted,$sale_item_status,$dbTransaction){
            $res = new SystemResponses();
            $productPromotion= new ProductPromotion();
            $productPromotion->productID = $productID;
            $productPromotion->isWarranted = $isWarranted;
            $productPromotion->sale_item_status = $sale_item_status;
            $productPromotion->createdAt = date("Y-m-d H:i:s");
            if ($productPromotion->save() === false) {
                $errors = array();
                $messages = $productPromotion->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                
                 $res->dataError('productPromotion create error', $errors);
                 $dbTransaction->rollback('productPromotion create error' . json_encode($errors));
                 return 0;
            }
            return $productPromotion->productPromotionID;
        
    }

    private function createPromotionReward($rewardTypeID,$value,$hoursToExpire,$dbTransaction){
        $res = new SystemResponses();
        $promotionReward=new PromotionReward();
        $promotionReward->rewardTypeID = $rewardTypeID;
        $promotionReward->value = $value;
        $promotionReward->hoursToExpire = $hoursToExpire;
        $promotionReward->createdAt = date("Y-m-d H:i:s");
        
        if ($promotionReward->save() === false) {
                $errors = array();
                $messages = $promotionReward->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                 $res->dataError('promotionReward create error', $errors);
                 $dbTransaction->rollback('promotionReward create error' . json_encode($errors));
                 return 0;
            }
         return $promotionReward->promotionRewardID;
    }


    public function getTablePromotions(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $sort = $request->getQuery('sort') ? $request->getQuery('sort') : '';
        $order = $request->getQuery('order') ? $request->getQuery('order') : '';
        $page = $request->getQuery('page') ? $request->getQuery('page') : '';
        $limit = $request->getQuery('limit') ? $request->getQuery('limit') : '';
        $filter = $request->getQuery('filter') ? $request->getQuery('filter') : '';
        $promotionID = $request->getQuery('promotionID') ? $request->getQuery('promotionID') : '';
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';
        $isExport = $request->getQuery('isExport') ? $request->getQuery('isExport') : '';


        $canExport = false;


        $countQuery = "SELECT count(promotionID) as totalPromotion ";

        $selectQuery = "SELECT p.promotionID,p.promotionName,p.startDate,p.endDate,p.status,sp.isPendingSale,sp.isPartnerSale,sp.isAgentSale,sp.isGroup,sp.saleCreatedAt,pp.productID,pp.isWarranted,pr.promotionRewardID,pr.rewardTypeID,pr.value,pr.hoursToExpire,p.createdAt ";

        $baseQuery = " FROM promotion p JOIN promotion_reward pr on p.promotionRewardID=pr.promotionRewardID JOIN product_promotion pp ON p.productPromotionID=pp.productPromotionID JOIN sale_promotion sp on p.salePromotionID=sp.salePromotionID JOIN reward_type rt on pr.rewardTypeID=rt.rewardTypeID";


        $whereArray = [
            'p.promotionID' => $promotionID,
            'filter' => $filter,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['p.promotionName'];

                $valueString = "";
                foreach ($searchColumns as $searchColumn) {
                    $valueString .= $value ? "" . $searchColumn . " REGEXP '" . $value . "' ||" : "";
                }
                $valueString = chop($valueString, " ||");
                if ($valueString) {
                    $valueString = "(" . $valueString;
                    $valueString .= ") AND ";
                }
                $whereQuery .= $valueString;
            } else if ($key == 'status' && $value == 404) {
                $valueString = "" . $key . "=0" . " AND ";
                $whereQuery .= $valueString;
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(p.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
                    $canExport  =true;
                }
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND " : "";
                $whereQuery .= $valueString;
            }
        }

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND ");
        }

        $whereQuery = $whereQuery ? " WHERE $whereQuery " : "";

        $countQuery = $countQuery . $baseQuery . $whereQuery;
        $selectQuery = $selectQuery . $baseQuery . $whereQuery;
        $exportQuery = $selectQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

       
        $count = $this->rawSelect($countQuery);
        $messages = $this->rawSelect($selectQuery);
        $data["totalPromotion"] = $count[0]['totalPromotion'];
         $data["promotions"] = $messages;

        if($isExport){
            $exportMessages  = $this->rawSelect($exportQuery);
            $data["exportPromotion"] =  $exportMessages;
        }
        else{
             $data["exportPromotion"] = "no data ".$isExport;
        }
        

        return $res->success("Promotions ", $data);

    }

    /*
    util function to build all get queries based on passed parameters
    */


    public function tableQueryBuilder($sort = "", $order = "", $page = 0, $limit = 10) {

        $sortClause = " ORDER BY $sort $order ";
        if (!$page || $page <= 0) {
            $page = 1;
        }
        if (!$limit) {
            $limit = 10;
        }
        $ofset = (int) ($page - 1) * $limit;
        $limitQuery = " LIMIT $ofset, $limit ";
        return "$sortClause $limitQuery";
    }

    public function activate(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $json = $request->getJsonRawBody();

        $promotionID = isset($json->promotionID) ? $json->promotionID : NULL;
        $userID = isset($json->userID) ? $json->userID : NULL;
        $token = isset($json->token) ? $json->token : NULL;


        $promotion = Promotion::findFirst("promotionID = $promotionID");

        if(!$promotion){
            return $res->dataError("Promotion not found");
        }

        $promotion->status = 1;

        try{
            if ($promotion->save() === false) {
                $errors = array();
                $messages = $promotion->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                
             $res->dataError('promotion activate error', $errors);
              $dbTransaction->rollback('promotion activate error', $errors);
            }

            $dbTransaction->commit();
            $this->offerPromotion($promotion->promotionID);

            return $res->success("Promotion activated successfully");
         } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Promotion create error', $message);
        }

        
    }


     public function deactivate(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $json = $request->getJsonRawBody();

        $promotionID = isset($json->promotionID) ? $json->promotionID : NULL;
        $userID = isset($json->userID) ? $json->userID : NULL;
        $token = isset($json->token) ? $json->token : NULL;


        $promotion = Promotion::findFirst("promotionID = $promotionID");

        if(!$promotion){
            return $res->dataError("Promotion not found");
        }

        $promotion->status = 0;

        try{

            if ($promotion->save() === false) {
                $errors = array();
                $messages = $promotion->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                
              $res->dataError('promotion deactivate error', $errors);
             $dbTransaction->rollback('promotion deactivate error', $errors);
            }
            $dbTransaction->commit();
            return $res->success("Promotion deactivated successfully");

        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Promotion deactivated error', $message);
        }
        
    }

    public function update(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $json = $request->getJsonRawBody();

        $promotionID = isset($json->promotionID) ? $json->promotionID : NULL;
        $promotionName = isset($json->promotionName) ? $json->promotionName : NULL;
        $startDate = isset($json->startDate) ? $json->startDate : NULL;
        $endDate = isset($json->endDate) ? $json->endDate : NULL;
        $userID = isset($json->userID) ? $json->userID : NULL;
        $token = isset($json->token) ? $json->token : NULL;

        if(!$promotionID){
            return $res->dataError("Required data missing");
        }


        $promotion = Promotion::findFirst("promotionID = $promotionID");

        if(!$promotion){
            return $res->dataError("Promotion not found");
        }
        if($promotionName){
            $promotion->promotionName=$promotionName;
        }

        if($endDate){
            $promotion->endDate=$endDate;
        }

        if($startDate){
            $promotion->startDate=$startDate;
        }

        try{

            if ($promotion->save() === false) {
                $errors = array();
                $messages = $promotion->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                
             $dbTransaction->rollback('promotion update error', $errors);
            }
            $dbTransaction->commit();
            return $res->success("Promotion updated successfully");

        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Promotion update error', $message);
        }
        
    }

     public function getPromotion(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $token = $request->getQuery('token');
        $promotionID= $request->getQuery('promotionID');

        if(!$promotionID || !$token){
            return $res->dataError("Required data missing");
        }


        $promotion = $this->rawSelect("select p.promotionName,p.promotionID,p.startDate,p.endDate,sp.salePromotionID,sp.isPendingSale,sp.isPartnerSale,sp.isAgentSale,sp.isGroup,sp.saleTypeID,pp.productPromotionID,pp.productID,sp.saleCreatedStartDate,sp.saleCreatedEndDate,r.rewardTypeID,r.rewardName,r.description,pr.hoursToExpire,pr.value FROM promotion p JOIN sale_promotion sp on p.salePromotionID=sp.salePromotionID JOIN product_promotion pp on p.productPromotionID=pp.productPromotionID JOIN promotion_reward pr on p.promotionRewardID=pr.promotionRewardID JOIN reward_type r on pr.rewardTypeID=r.rewardTypeID WHERE p.promotionID=$promotionID");

        if(!$promotion){
            return $res->dataError('Pomotion not found');
        } 
        else{
            $productIDs = str_replace("]","",str_replace("[", "", $promotion[0]['productID']));
            $productIDs = explode(",",$productIDs);
            $saleTypeIDs = str_replace("]","",str_replace("[", "", $promotion[0]['saleTypeID']));
            $saleTypeIDs = explode(",",$saleTypeIDs);
            $product=[];
            $saleType=[];

             if(count($productIDs)==1 && $productIDs[0]==0){
                    /*$productNames = $this->rawSelect("SELECT productName FROM product ");
                    foreach ($productNames as $productName) {
                        $product .=$productName['productName']."\n";
                    }*/
                    $product = $this->rawSelect("SELECT productName FROM product ");
                }
            else{
                 foreach ($productIDs as $productID) {
                    $productName = $this->rawSelect("SELECT productName FROM product where productID=$productID");
                    //$product .=$productName[0]['productName']."\n";
                    array_push($product, $productName[0]['productName']);
                }
            }
            
            if(count($saleTypeIDs) == 1 && $saleTypeIDs[0]==0 ){
                $saleType = $this->rawSelect("SELECT salesTypeName from sales_type");
                /*$saleTypes = $this->rawSelect("SELECT salesTypeName from sales_type");
                foreach ($saleTypes as $salesTypeName) {
                        $saleType .=$salesTypeName['salesTypeName']."\n";
                    }
                    */
            }
            else{
                foreach ($saleTypeIDs as $saleTypeID) {
                    $salesTypeNames = $this->rawSelect("SELECT salesTypeName from sales_type where salesTypeID=$saleTypeID");
                   // $saleType .=$salesTypeNames[0]['salesTypeName']."\n";
                    array_push($saleType,$salesTypeNames[0]['salesTypeName']);
                }
            }

            
            $data= array();
            $data['promotionName'] = $promotion[0]['promotionName'];
            $data['promotionID'] = $promotion[0]['promotionID'];
            $data['startDate'] = $promotion[0]['startDate'];
            $data['endDate'] = $promotion[0]['endDate'];
            $data['salePromotionID'] = $promotion[0]['salePromotionID'];
            $data['isPendingSale'] = $promotion[0]['isPendingSale'];
            $data['isPartnerSale'] = $promotion[0]['isPartnerSale'];
            $data['isAgentSale'] = $promotion[0]['isAgentSale'];
            $data['isGroup'] = $promotion[0]['isGroup'];
            $data['productPromotionID'] = $promotion[0]['productPromotionID'];
            $data['saleCreatedStartDate'] = $promotion[0]['saleCreatedStartDate'];
            $data['saleCreatedEndDate'] = $promotion[0]['saleCreatedEndDate'];
            $data['rewardName'] = $promotion[0]['rewardName'];
            $data['hoursToExpire'] = $promotion[0]['hoursToExpire'];
            $data['value'] = $promotion[0]['value'];
            $data['saleType'] = $saleType;
            $data['product'] = $product;

            return $res->success("Promotion ",$data);
        }
    }

    public function getPromotionTableRewards(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $sort = $request->getQuery('sort') ? $request->getQuery('sort') : '';
        $order = $request->getQuery('order') ? $request->getQuery('order') : '';
        $page = $request->getQuery('page') ? $request->getQuery('page') : '';
        $limit = $request->getQuery('limit') ? $request->getQuery('limit') : '';
        $filter = $request->getQuery('filter') ? $request->getQuery('filter') : '';
        $promotionID = $request->getQuery('promotionID') ? $request->getQuery('promotionID') : '';
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';
        $isExport = $request->getQuery('isExport') ? $request->getQuery('isExport') : '';


        $canExport = false; 

        $countQuery = "SELECT count(saleRewardID) as totalRewards ";

        $selectQuery = "SELECT sr.saleRewardID,sr.salesID,pr.hoursToExpire,pr.value,rt.rewardName,c.contactsID,c.workMobile,c.fullName,sr.createdAt,sr.status,s.customerID ";

        $baseQuery = " FROM `sales_reward` sr JOIN promotion p on sr.`promotionID`=p.promotionID JOIN promotion_reward pr on p.promotionRewardID=pr.promotionRewardID JOIN sales s on sr.`salesID`=s.salesID JOIN contacts c on s.contactsID=c.contactsID JOIN reward_type rt on pr.rewardTypeID=rt.rewardTypeID ";

        $whereArray = [
            'sr.promotionID' => $promotionID,
            'filter' => $filter,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['p.promotionName','rt.rewardName','c.workMobile','c.fullName'];

                $valueString = "";
                foreach ($searchColumns as $searchColumn) {
                    $valueString .= $value ? "" . $searchColumn . " REGEXP '" . $value . "' ||" : "";
                }
                $valueString = chop($valueString, " ||");
                if ($valueString) {
                    $valueString = "(" . $valueString;
                    $valueString .= ") AND ";
                }
                $whereQuery .= $valueString;
            } else if ($key == 'sr.status' && $value == 404) {
                $valueString = "" . $key . "=0" . " AND ";
                $whereQuery .= $valueString;
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(sr.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
                    $canExport  =true;
                }
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND " : "";
                $whereQuery .= $valueString;
            }
        }

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND ");
        }

        $whereQuery = $whereQuery ? " WHERE $whereQuery " : "";

        $countQuery = $countQuery . $baseQuery . $whereQuery;
        $selectQuery = $selectQuery . $baseQuery . $whereQuery;
        $exportQuery = $selectQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;
       
        //return $res->dataError("  ===== Rewards === ".$selectQuery);

       
        $count = $this->rawSelect($countQuery);
        $messages = $this->rawSelect($selectQuery);
        $data["totalRewards"] = $count[0]['totalRewards'];
        $data["rewards"] = $messages;

        if($isExport){
            $exportMessages  = $this->rawSelect($exportQuery);
            $data["exportRewards"] =  $exportMessages;
        }
        else{
             $data["exportRewards"] = "no data ".$isExport;
        }
        

        return $res->success("Rewards ", $data);
    }

    public function expirePromotion(){
        $res = new SystemResponses();

        $getQuery = "SELECT * FROM promotion WHERE date(endDate) < date(now()) and status<3";

        $promotions = $this->rawSelect($getQuery);

        foreach ($promotions as $promotion) {
            $o_promotion = Promotion::findFirst("promotionID=".$promotion['promotionID']);
            $o_promotion->status=3;

            if ($o_promotion->save() === false) {
                $errors = array();
                $messages = $o_promotion->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
             $res->dataError("deactivting promotion Failed");
            }
            $this->expireRewards($promotion['promotionID']);
            $res->success("promotion deactivated successfully");
        }
        $res->success("Done deactivating promoion");

    }
    public function expireRewards($promotionID=0){
        $res = new SystemResponses();

        $getQuery = "SELECT sr.`saleRewardID`,sr.salesID,pr.hoursToExpire,pr.value,rt.rewardName,c.workMobile,c.fullName,TIMESTAMPDIFF(MINUTE, sr.createdAt, now()) as timeLapse FROM `sales_reward` sr JOIN promotion p on sr.`promotionID`=p.promotionID JOIN promotion_reward pr on p.promotionRewardID=pr.promotionRewardID JOIN sales s on sr.`salesID`=s.salesID JOIN contacts c on s.contactsID=c.contactsID JOIN reward_type rt on pr.rewardTypeID=rt.rewardTypeID WHERE sr.status=0";
        if($promotionID){
            $getQuery .=" AND sr.promotionID=$promotionID";
        }

        $rewards = $this->rawSelect($getQuery);

        foreach ($rewards as $reward) {
            if($reward['timeLapse']>$reward['hoursToExpire']){
                $o_reward = SalesReward::findFirst("saleRewardID=".$reward['saleRewardID']);
                $o_reward->status=3;
                $o_reward->expiredAt = date("Y-m-d H:i:s");

                if ($o_reward->save() === false) {
                    $errors = array();
                    $messages = $o_reward->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    
                 $res->dataError("deactivting rewards Failed");
                }
                $customerMessage = "Dear ".$reward['fullName'].", your ".$reward['rewardName']." of ".$reward['value']." has expired. You failed to act in ".$reward['hoursToExpire']." hrs";
                $res->success("Message ".$customerMessage);
               // $res->sendMessage($reward['workMobile'], $customerMessage);
                $res->success("rewards deactivated successfully");
            }
            
        }
        $res->success("Done deactivating rewards");

    }

    public function offerPromotion($promotionID=0){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        if($promotionID==0){
           $promotionID= $request->getQuery('promotionID');
        }
        

        //get promotion 
        $selectPromotionQuery = "SELECT p.promotionName,p.promotionID,p.startDate,p.endDate,sp.salePromotionID,sp.isPendingSale,sp.isPartnerSale,sp.isAgentSale,sp.isGroup,sp.saleTypeID,pp.productPromotionID,pp.productID,sp.saleCreatedStartDate,sp.saleCreatedEndDate,r.rewardTypeID,r.rewardName,r.description,pr.hoursToExpire,pr.value FROM promotion p JOIN sale_promotion sp on p.salePromotionID=sp.salePromotionID JOIN product_promotion pp on p.productPromotionID=pp.productPromotionID JOIN promotion_reward pr on p.promotionRewardID=pr.promotionRewardID JOIN reward_type r on pr.rewardTypeID=r.rewardTypeID WHERE p.status <>3 and date(endDate) > date(now())";

        $promotions = $this->rawSelect($selectPromotionQuery);

        if($promotionID){
            $promotions = $this->rawSelect($selectPromotionQuery." AND p.promotionID=$promotionID");
        }

        foreach ($promotions as $promotion) {
                 $whereQuery = " WHERE ";
                 $selectPartnerSales = "SELECT * FROM partner_sale_item WHERE date(createdAt) > date('".$promotion['saleCreatedStartDate']."') AND date(createdAt) < date('".$promotion['saleCreatedEndDate']."') ";
                 $partnerSales = [];

                 $selectSalesQuery = "SELECT salesID,contactsID FROM sales WHERE date(createdAt) > date('".$promotion['saleCreatedStartDate']."') AND date(createdAt) < date('".$promotion['saleCreatedEndDate']."')  ";
                 $sales=[];

                 if($promotion['isPartnerSale'] && !$promotion['productID']){ //offer this promotion to partner sales

                    $partnerSales = $this->rawSelect($selectPartnerSales);
                 }
                 elseif ($promotion['isPartnerSale'] && $promotion['productID']) {
                     $res->success('SalesReward created successfully '.$selectPartnerSales." AND productID=".$promotion['productID']);

                     $partnerSales = $this->rawSelect($selectPartnerSales." AND productID=".$promoion['productID']);

                    
                 }
                 
                 if($promotion['isAgentSale'] && $promotion['isGroup'] && !$promotion['productID'] ){ //offer this promotion to both indidual and group
                     $sales=$this->rawSelect($selectSalesQuery);

                 }
                 elseif($promotion['isAgentSale'] && $promotion['isGroup'] && $promotion['productID'] ){ //offer this promotion to both indidual and group

                     $sales=$this->rawSelect($selectSalesQuery." AND productID=".$promoion['productID']);
                     

                 }
                 elseif(!$promotion['isAgentSale'] && $promotion['isGroup'] && !$promotion['productID']){ //offer this promotion to group sale

                    $sales=$this->rawSelect($selectSalesQuery." AND groupID>0");
                   
                 }
                elseif($promotion['isAgentSale'] && !$promotion['isGroup'] && $promotion['productID']){ //offer this promotion to individual sales only
                    

                    $sales=$this->rawSelect($selectSalesQuery." AND groupID=0 AND productID=".$promotion['productID']);
                    

                 }

                 //offer promotion if criteria matches 
                if($sales){
                    foreach ($sales as $sale) {
                        $salesReward = SalesReward::findFirst("salesID=".$sale['salesID']." AND promotionID=".$promotion['promotionID']." AND status=0");
                        if($salesReward){
                            continue;
                        }
                        else{
                            $salesReward = new SalesReward();
                            $salesReward->promotionID = $promotion['promotionID'];
                            $salesReward->salesID = $sale['salesID'];
                            $salesReward->status = 0;
                            $salesReward->expiredAt = 0;
                            $salesReward->createdAt = date("Y-m-d H:i:s");

                            if ($salesReward->save() === false) {
                                    $errors = array();
                                    $messages = $salesReward->getMessages();
                                    foreach ($messages as $message) {
                                        $e["message"] = $message->getMessage();
                                        $e["field"] = $message->getField();
                                        $errors[] = $e;
                                    }
                                return $res->dataError('SalesReward create error', $errors);

                            }
                            $res->success('SalesReward created successfully',$salesReward);

                            $customer = $this->rawSelect("SELECT * FROM contacts WHERE contactsID=".$sale['contactsID']);
                           
                            $customerMessage = "Dear ".$customer[0]['fullName'].", you have been awarded a ".$promotion['rewardName']." of ".$promotion['value']." that will expires after ".$promotion['hoursToExpire']." hours ";

                            $res->success("Send message ".$customerMessage);
                            //$res->sendMessage($customer[0]['workMobile'], $customerMessage);
                        }
                    }
                 }
                 
        } 

        return $res->success("Request completed successfully ",$promotions);

        
    }


   

}

