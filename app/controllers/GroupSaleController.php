<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

class GroupSaleController extends Controller
{
    protected $closeGroupStatus = 2;
    protected $expireGroupStatus = 3;
    protected $abortedStatus = 5;

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

    /*Create group */

     public function create() { 
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $activityLog= new ActivityLogsController();


        $groupName = isset($json->groupName) ? $json->groupName : NULL;
        $MSISDN = isset($json->MSISDN)?$json->MSISDN:NULL;
        $userID = isset($json->userID)?$json->userID:NULL;
        $longitude = $json->longitude;
        $latitude = $json->latitude;
        $token = $json->token;

        $activityLog->create($userID,"create or search group check groupName",$longitude,$latitude);


        if (!$token) {
            return $res->dataError("Token missing ",[]);
        }
        if (!$groupName || !$MSISDN) {
            return $res->dataError("data missing ", []);
        }

        //$group = GroupSale::findFirst("(groupName='$groupName' OR groupToken='$groupName')  AND status=0");
        $group = $this->rawSelect("SELECT * from group_sale WHERE (groupName='$groupName' OR groupToken='$groupName')  AND status=0");

        if($group[0]){
            return $res->success("Group exists ",$group[0]);
        }

        try{
            $lastId = $this->rawSelect("SELECT groupID from group_sale ORDER by groupID desc limit 1");
            if(!$lastId){
                $lastId = 0;
            }
            else{
                $lastId = $lastId[0]['groupID'];
            }
          
            $groupSale = new GroupSale();
            $groupSale->createdAt = date("Y-m-d H:i:s");
            $groupSale->numberOfMembers = 0;
            $groupSale->status=0;
            $groupSale->userID = $userID;
            $groupSale->groupToken="group".($lastId+1);
            $groupSale->groupName = $groupName;

             if ($groupSale->save() === false) {
                    $errors = array();
                    $messages = $groupSale->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback('Group Sale create failed' . json_encode($errors));
              }

              $dbTransaction->commit();

              $customerMessage = "Please use ".$groupSale->groupToken." for the group with name ".$groupSale->groupName;
              $res->sendMessage($MSISDN, $customerMessage);
              return $res->success("Group Sale created ", $groupSale);
          }
          catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Group Sale create error', $message);
        }
    }

    public function closeGroup(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $activityLog= new ActivityLogsController();

        $groupName = isset($json->groupName) ? $json->groupName : NULL;
        $groupID = isset($json->groupID) ? $json->groupID : NULL;
        $MSISDN = isset($json->MSISDN)?$json->MSISDN:NULL;
        $longitude = $json->longitude;
        $latitude = $json->latitude;
        $token = $json->token;

        if (!$token) {
            return $res->dataError("Token missing ",[]);
        }
        if (!$MSISDN || !$groupID) {
            return $res->dataError("Group data missing ", []);
        }

        $activityLog->create($userID,"Close group ",$longitude,$latitude);


        try{
             $groupSale = GroupSale::findFirst("groupID=$groupID");
             if(!$groupSale){
                return $res->success("Group not found ");
             }

             $groupSale->closedAt = date("Y-m-d H:i:s");
             $groupSale->status = $this->closeGroupStatus;

             if ($groupSale->save() === false) {
                    $errors = array();
                    $messages = $groupSale->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback('Group Sale close failed' . json_encode($errors));
              }

               $dbTransaction->commit();

               $groupDetails = $this->rawSelect("select sum(amount) as totalAmount,count(salesID) members from sales where groupID=$groupID");

               $customerMessage ="";

               if($groupDetails[0]['members']<5){
                    $sales =  $this->rawSelect("SELECT * FROM sales WHERE groupID=$groupID");
                    foreach ($sales as $sale) {
                         $sale_o = Sales::findFirst("salesID=".$sale['salesID']);
                         $sale_o->status=-2;
                         if ($sale_o->save() === false) {
                                $errors = array();
                                $messages = $sale_o->getMessages();
                                foreach ($messages as $message) {
                                    $e["message"] = $message->getMessage();
                                    $e["field"] = $message->getField();
                                    $errors[] = $e;
                                }
                                 $res->dataError('sale_o expire failed sale: '.json_encode($sale_o), $errors);
                            }
                     }
                $customerMessage =$groupSale->groupName." (".$groupSale->groupToken.")"." has been closed successfully. All sales has been deleted you didn't reach manimum number of 5.";
               }
               else{
                $customerMessage =$groupSale->groupName." (".$groupSale->groupToken.")"." has been closed successfully. Pay Ksh.".$groupDetails[0]['totalAmount']+" to paybill 113941 ";
               }
              $res->sendMessage($MSISDN, $customerMessage);
              return $res->success("Group clossed successfully await payment ", $groupSale);

        }
        catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Group Sale close error', $message);
        }
    }

    public function abortGroup(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $activityLog= new ActivityLogsController();


        $groupName = isset($json->groupName) ? $json->groupName : NULL;
        $groupID = isset($json->groupID) ? $json->groupID : NULL;
        $MSISDN = isset($json->MSISDN)?$json->MSISDN:NULL;
        $longitude = $json->longitude;
        $latitude = $json->latitude;
        $token = $json->token;

        if (!$token) {
            return $res->dataError("Token missing ",[]);
        }
        if (!$MSISDN || !$groupID) {
            return $res->dataError("Group data missing ", []);
        }
        $activityLog->create($userID,"Abort group ",$longitude,$latitude);


        try{
             $groupSale = GroupSale::findFirst("groupID=$groupID");
             if(!$groupSale){
                return $res->success("Group not found ");
             }

             $groupSale->abortedAt = date("Y-m-d H:i:s");
             $groupSale->status = $this->abortedStatus;

             if ($groupSale->save() === false) {
                    $errors = array();
                    $messages = $groupSale->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback('Group Sale close failed' . json_encode($errors));
              }

               $dbTransaction->commit();
                   $sales =  $this->rawSelect("SELECT * FROM sales WHERE groupID=$groupID");
                    foreach ($sales as $sale) {
                         $sale_o = Sales::findFirst("salesID=".$sale['salesID']);
                         $sale_o->status=-2;
                         if ($sale_o->save() === false) {
                                $errors = array();
                                $messages = $sale_o->getMessages();
                                foreach ($messages as $message) {
                                    $e["message"] = $message->getMessage();
                                    $e["field"] = $message->getField();
                                    $errors[] = $e;
                                }
                                 $res->dataError('sale_o expire failed sale: '.json_encode($sale_o), $errors);
                            }
                     }
              $customerMessage =$groupSale->groupName." (".$groupSale->groupToken.")"." has been aborted successfully. ";
              $res->sendMessage($MSISDN, $customerMessage);
              return $res->success("Sale saved successfully, await payment ", $groupSale);

        }
        catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Group Sale close error', $message);
        }
    }


    public function expireGroup(){
        $res = new SystemResponses();
        $expiredGroupsQuery= "SELECT * FROM group_sale WHERE TIMESTAMPDIFF(HOUR,createdAt, now()) >= 1";
        $groupSales = $this->rawSelect($expiredGroupsQuery);

        $res->success('groupSales ',$groupSales);
        
        foreach ($groupSales as $groupSale) {
            $groupID = $groupSale['groupID'];
             $groupSale_o = GroupSale::findFirst("groupID=$groupID");
             if(!$groupSale){
                 $res->success("Group not found ");
             }
             $groupSale_o->status=$this->expireGroupStatus;
             $groupSale_o->expiredAt = date("Y-m-d H:i:s");

            if ($groupSale_o->save() === false) {
                $errors = array();
                $messages = $groupSale_o->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                 $res->dataError(' groupSale_o expire failed sale: '.json_encode($groupSale_o), $errors);
            }

            //get and delete all sales associated with this group
            
            $sales =  $this->rawSelect("SELECT * FROM sales WHERE status= AND groupID=$groupID");
            foreach ($sales as $sale) {
                 $sale_o = Sales::findFirst("salesID=".$sale['salesID']);
                 $sale_o->status=-2;
                 if ($sale_o->save() === false) {
                        $errors = array();
                        $messages = $sale_o->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                         $res->dataError('sale_o expire failed sale: '.json_encode($sale_o), $errors);
                    }
             }

        }
        $res->sendMessage('254724040350', count($groupSales).' Group expired successfully');

        $res->success('GroupSale_o expired');
    }

    public function getGroup(){
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $groupName = isset($json->groupName) ? $json->groupName : NULL;
        $groupToken = isset($json->groupToken) ? $json->groupToken : NULL;
        $groupID = isset($json->groupID) ? $json->groupID : NULL;
        $groups;

        $token = $json->token;

        if (!$groupName && !$groupToken && !$groupID) {
          return $res->dataError("Group data missing ", []);
        }
        if($groupID){
            $groups = $this->rawSelect("SELECT * FROM group_sale WHERE groupID=$groupID");

        }
        elseif($groupToken){
             $groups = $this->rawSelect("SELECT * FROM group_sale WHERE groupToken='".$groupToken."'");
        }
        elseif($groupName){
            $groups = $this->rawSelect("SELECT * FROM group_sale WHERE groupName='".$groupName."'");
        
        }
       return $res->success("Groups ",$groups);
    }


    /*
    retrieve  groupSales to be tabulated on crm
    parameters:
    sort (field to be used in order condition),
    order (either asc or desc),
    page (current table page),
    limit (total number of items to be retrieved),
    filter (to be used on where statement)
    */
 
    public function getTableGroupSales() { //sort, order, page, limit,filter
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $groupID = $request->getQuery('groupID');
        $userID = $request->getQuery('userID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');

        $selectQuery = "SELECT gs.groupID,gs.groupToken,gs.groupName,gs.userID,gs.closedAt,c.fullName as agentName,gs.createdAt,count(s.salesID) as numberOfMembers,sum(s.amount) as saleAmount,sd.saleDiscountID,d.discountAmount,dt.discountTypeName,st.salesTypeName ";

        $baseQuery = "FROM group_sale gs join users u on gs.userID=u.userID join contacts c on u.contactID=c.contactsID join sales s on gs.groupID=s.groupID join payment_plan pp on s.paymentPlanID=pp.paymentPlanID join sales_type st on pp.salesTypeID=st.salesTypeID left join sale_discount sd on s.salesID=sd.salesID left join discount d on sd.discountID=d.discountID left join discount_types dt on d.discountTypeID=dt.discountTypeID and dt.discountTypeName regexp 'group' ";

        $countQuery = "SELECT count(gs.groupID) as totalGroups ";


        $whereArray = [
            'filter' => $filter,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['gs.groupToken','gs.groupName','c.fullName'];

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
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(gs.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    $whereQuery .= $valueString;
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

     //  $res->success("Groups ".$selectQuery);

            $count = $this->rawSelect($countQuery);
             $groups = $this->rawSelect($selectQuery);
             $data["totalGroups"] = $count[0]['totalGroups'];
             $data["groups"] = $groups;


         if($isExport){
            $exportGroups  = $this->rawSelect($exportQuery);
            $data["exportGroups"] =  $exportGroups;//$exportMessage;
        }
        else{
             $data["exportGroups"] = "no data ".$isExport;
        }
        return $res->success("Groups ", $data);
    }
    /*
    util function to build all get queries based on passed parameters
    */
    public function tableQueryBuilder($sort = "", $order = "", $page = 0, $limit = 10) {

        if(!$sort){
          $sort="s.createdAt";
        }

        $sortClause = " ORDER BY $sort $order ";
        $groupClause = " GROUP BY s.groupID ";

        if (!$page || $page <= 0) {
            $page = 1;
        }
        if (!$limit) {
            $limit = 10;
        }

        $ofset = (int) ($page - 1) * $limit;
        $limitQuery = " LIMIT $ofset, $limit ";

        return "$groupClause $sortClause $limitQuery";
    }


    

}

