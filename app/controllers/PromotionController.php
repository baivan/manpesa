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

    public function create(){ //{promotionName,startDate,endDate,status,isPendingSale,isPartnerSale,isAgentSale,isGroup,saleCreatedAt,productID,isWarranted,sale_item_status,rewardTypeID,value,hoursToExpire,smsTemplateID}
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
        $isPartnerSale = isset($json->isPartnerSale) ? $json->isPartnerSale : 0;
        $isAgentSale = isset($json->isAgentSale) ? $json->isAgentSale : 0;
        $isGroup = isset($json->isGroup) ? $json->isGroup : 0;
        $saleCreatedAt = isset($json->saleCreatedAt) ? $json->saleCreatedAt : NULL;
        $productID = isset($json->productID) ? $json->productID : 0;
        $isWarranted = isset($json->isWarranted) ? $json->isWarranted : 0;
        $sale_item_status = isset($json->sale_item_status) ? $json->sale_item_status : 0;
        $rewardTypeID = isset($json->rewardTypeID) ? $json->rewardTypeID : 0;
        $value = isset($json->value) ? $json->value : NULL;
        $hoursToExpire = isset($json->hoursToExpire) ? $json->hoursToExpire : 0;
        $smsTemplateID = isset($json->smsTemplateID) ? $json->smsTemplateID : 0;


        try{
        //create salePromotionID
        $salePromotionID=$this->createSalePromotion($isPendingSale,$isPartnerSale,$isAgentSale,$isGroup,$saleCreatedAt,$dbTransaction);


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

    private function createSalePromotion($isPendingSale,$isPartnerSale,$isAgentSale,$isGroup,$saleCreatedAt,$dbTransaction){
    	 $res = new SystemResponses();
    	 $salePromotion = new SalePromotion();
    	 $salePromotion->isPendingSale = $isPendingSale;
    	 $salePromotion->isPartnerSale = $isPartnerSale;
    	 $salePromotion->isAgentSale = $isAgentSale;
         $salePromotion->saleCreatedAt = $saleCreatedAt;
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

        $baseQuery = "  FROM promotion p JOIN promotion_reward pr on p.promotionRewardID=pr.promotionRewardID JOIN product_promotion pp ON p.productPromotionID=pp.productPromotionID JOIN sale_promotion sp on p.salePromotionID=sp.salePromotionID JOIN reward_type rt on pr.rewardTypeID=rt.rewardTypeID";


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

    public function offerPromotion(){ 
        $salesQuery = "SELECT * FROM promotion ";
        //get sale 

        //check if there is a reword

      }

      //give early repayment promotion
      //give product promotion
      //give partner sales discount
      //give 
}

