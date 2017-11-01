<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

/*
All reports operations 
*/

class ReportsController extends Controller {

	/*
    raw query select function to work in any version of phalcon
    */
    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

 

	public function paygoSalesSummary(){

		    $jwtManager = new JwtManager();
	        $request = new Request();
	        $res = new SystemResponses();
	        $token = $request->getQuery('token');
            $startDate = $request->getQuery('startDate');
            $endDate = $request->getQuery('endDate');

            $dateConditionQuery = " ";

            if($startDate && $endDate){
            	$dateConditionQuery .= " AND DATE(s.createdAt) BETWEEN '$startDate' AND '$endDate' " ;
            }



			 $completeFullyPaid = $this->rawSelect("SELECT count(s.salesID) as fullyPaid from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID  where s.status=2 and pp.salesTypeID=2 and datediff(now(),s.createdAt)> 90 $dateConditionQuery ");
			 $completeDefault = $this->rawSelect("SELECT count(s.salesID) as completeDefault from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID  where s.status=1 and pp.salesTypeID=2 and datediff(now(),s.createdAt)> 90 and datediff(now(),s.createdAt)<= 120 $dateConditionQuery");
			 $completeReportedToCrb = $this->rawSelect("SELECT count(s.salesID) as completeReportedToCrb from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID  where s.status=1 and pp.salesTypeID=2 and datediff(now(),s.createdAt)> 90 and datediff(now(),s.createdAt)> 120 $dateConditionQuery");

			 //current 
			 $currentFullyPaid = $this->rawSelect(" SELECT COUNT(s.salesID) as currentFullyPaid from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID  where s.status=2 and pp.salesTypeID=2 AND datediff(now(),s.createdAt)<=90 $dateConditionQuery");

			 $currentSuperAheadOfPlan = $this->rawSelect("SELECT COUNT(s.salesID) as aheadOfPlan from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID  where s.status=1 and pp.salesTypeID=2 AND datediff(now(),s.createdAt)<=90 AND ((datediff(now(),s.createdAt)*35) + pp.paymentPlanDeposit)>s.paid AND (s.productID=2 or s.productID='[2]' or s.productID=4 or s.productID='[4]') $dateConditionQuery");

			 $currentSmartAheadOfPlan = $this->rawSelect("SELECT COUNT(s.salesID) as aheadOfPlan from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID  where s.status=1 and pp.salesTypeID=2 AND datediff(now(),s.createdAt)<=90 AND ((datediff(now(),s.createdAt)*25) + pp.paymentPlanDeposit)>s.paid AND (s.productID=3 or s.productID='[3]') $dateConditionQuery");


			 $currentSuperBehidePlan = $this->rawSelect("SELECT COUNT(s.salesID) as behidePlan from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID  where s.status=1 and pp.salesTypeID=2 AND datediff(now(),s.createdAt)<=90 AND ((datediff(now(),s.createdAt)*35) + pp.paymentPlanDeposit)<s.paid AND (s.productID=2 or s.productID='[2]' or s.productID=4 or s.productID='[4]') $dateConditionQuery");
			 $currentSmartBehidePlan = $this->rawSelect("SELECT COUNT(s.salesID) as behidePlan from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID  where s.status=1 and pp.salesTypeID=2 AND datediff(now(),s.createdAt)<=90 AND ((datediff(now(),s.createdAt)*25) + pp.paymentPlanDeposit)<s.paid AND (s.productID=3 or s.productID='[3]') $dateConditionQuery");

			 $currentSuperOnPlan = $this->rawSelect("SELECT COUNT(s.salesID) as onPlan from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID  where s.status=1 and pp.salesTypeID=2 AND datediff(now(),s.createdAt)<=90 AND ((datediff(now(),s.createdAt)*35) + pp.paymentPlanDeposit)=s.paid AND (s.productID=2 or s.productID='[2]' or s.productID=4 or s.productID='[4]') $dateConditionQuery");

			 $currentSmartOnPlan = $this->rawSelect("SELECT COUNT(s.salesID) as onPlan from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID  where s.status=1 and pp.salesTypeID=2 AND datediff(now(),s.createdAt)<=90 AND ((datediff(now(),s.createdAt)*25) + pp.paymentPlanDeposit)=s.paid AND (s.productID=3 or s.productID='[3]') $dateConditionQuery");


			 $totalPaygo = $this->rawSelect("SELECT COUNT(s.salesID) as totalPaygo,sum(s.amount) as totalPaygoAmount, sum(s.paid) as totalPaygoPaid, sum(pp.paymentPlanDeposit) as totalPaygoDeposit from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID  where s.status>0 and pp.salesTypeID=2 $dateConditionQuery");

			 $tableData = $this->rawSelect("SELECT count(x.salesID) as total,sum(x.amount) as amount,sum(x.paid) as paid, sum(x.paymentPlanDeposit) as paymentPlanDeposit,sum(x.rate * x.days) as expectedDailyDeposit, CASE WHEN x.days between 0 and 5 then '<=5 days' when x.days between 6 and 10 then '6-10 days' when  x.days between 11 and 15 then '11-15 days' when x.days between 16 and 20 then '16-20 days' when x.days between 21 and 25 then '21-25 days' when x.days between 26 and 30 then '26-30 days' when x.days between 21 and 25 then '21-25 days' when x.days between 31 and 60 then '1-2 Months' when x.days between 61 and 90 then '> 2 Months' when x.days between 91 and 100 then '91-100' when x.days between 101 and 110 then '101-110' when x.days between 111 and 120 then '111-120' when x.days>120  then '120+' END day_range  from (SELECT CASE WHEN s.productID ='[2]' THEN 35 WHEN s.productID ='2' THEN 35 WHEN s.productID ='[4]' THEN 35 WHEN s.productID ='4' THEN 35 WHEN s.productID ='[3]' THEN 25 WHEN s.productID ='3' THEN 25 ELSE 0 END rate, s.salesID , (datediff(now(),s.createdAt)-7) as days,s.amount,s.paid,pp.paymentPlanDeposit FROM sales s JOIN payment_plan pp on s.paymentPlanID=pp.paymentPlanID where s.status>0 and pp.salesTypeID=2 $dateConditionQuery  ) x GROUP BY day_range ");

			 $allSalesSummary = $this->rawSelect("SELECT count(x.salesID) as total, sum(x.amount) as amount,sum(x.paid) as paid, CASE WHEN x.salesTypeID=1 then 'cash' when x.salesTypeID=2 then 'paygo' when x.salesTypeID=3 then 'installment' END salesType from (select s.salesID,s.amount,s.paid,pp.paymentPlanDeposit,pp.salesTypeID  from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID where s.status>0 $dateConditionQuery ) x GROUP BY salesType ");

			 $data['completedFullyPaid'] = $completeFullyPaid[0]['fullyPaid'];
			 $data['currentFullyPaid'] = $currentFullyPaid[0]['currentFullyPaid'];
			 $data['completeDefault'] = $completeDefault[0]['completeDefault'];
			 $data['completeReportedToCrb'] = $completeReportedToCrb[0]['completeReportedToCrb'];
			 $data['currentAheadOfPlan'] = $currentSuperAheadOfPlan[0]['aheadOfPlan']+$currentSmartAheadOfPlan[0]['aheadOfPlan'];
			 $data['currentBehidePlan'] = $currentSuperBehidePlan[0]['behidePlan']+$currentSmartAheadOfPlan[0]['behidePlan'];
			 $data['currentOnPlan'] = $currentSuperOnPlan[0]['onPlan']+$currentSmartOnPlan[0]['onPlan'];

			 $data['currentTotal'] = $data['currentOnPlan']+$data['currentBehidePlan']+$data['currentAheadOfPlan']+$data['currentFullyPaid'];
			 $data['completeTotal'] =  $data['completedFullyPaid']+$data['completeDefault']+$data['completeReportedToCrb'];

			 $data['totalCustomers'] = $totalPaygo[0]['totalPaygo'];
			 $data['totalPaygoAmount'] = $totalPaygo[0]['totalPaygoAmount'];
			 $data['totalPaygoDeposit'] = $totalPaygo[0]['totalPaygoDeposit'];
			 $data['tableData'] = $tableData;
			 $data['allSalesSummary'] =$allSalesSummary;

			
			return $res->success("paygo ",$data);

		 
	}

	public function salesReports(){
		   $jwtManager = new JwtManager();
	        $request = new Request();
	        $res = new SystemResponses();
	        $token = $request->getQuery('token');
            

			$threeMonths = $this->rawSelect("SELECT  st.salesTypeName,COUNT(s.salesID) as units from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID where month(now())-month(s.createdAt) =3 AND s.status>0 group by st.salesTypeID");
			$twoMonths = $this->rawSelect("SELECT  st.salesTypeName,COUNT(s.salesID) as units from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID where month(now())-month(s.createdAt)=2 AND s.status>0 group by st.salesTypeID");
			$lastMonth = $this->rawSelect("SELECT  st.salesTypeName,COUNT(s.salesID) as units from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID where month(now())-month(s.createdAt) =1 AND s.status>0 group by st.salesTypeID");

			$monthToDate = $this->rawSelect("SELECT  st.salesTypeName,COUNT(s.salesID) as units from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID where month(now())=month(s.createdAt) AND s.status>0 group by st.salesTypeID");

			$thisWeek = $this->rawSelect("SELECT  st.salesTypeName,COUNT(s.salesID) as units from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID where date(now())-date(s.createdAt)=7 AND s.status>0 group by st.salesTypeID");

			$today = $this->rawSelect("SELECT  st.salesTypeName,COUNT(s.salesID) as units from sales s join payment_plan pp on s.paymentPlanID=pp.paymentPlanID JOIN sales_type st on pp.salesTypeID=st.salesTypeID where date(now())=date(s.createdAt) AND s.status>0 group by st.salesTypeID");

			$data['threeMonths'] = $threeMonths;
			$data['twoMonths'] = $twoMonths;
			$data['lastMonth'] = $lastMonth;
			$data['monthToDate'] = $monthToDate;
			$data['thisWeek'] = $thisWeek;
			$data['today']=$today;

			return $res->success("sales ",$data);

	}

	/*public function agentsReport(){
		$jwtManager = new JwtManager();
	        $request = new Request();
	        $res = new SystemResponses();
	        $token = $request->getQuery('token');

		$agentSales = $this->rawSelect("SELECT c.fullName as agent,s.userID,sum(IF(st.salesTypeName ='Cash',s.amount,0)) as cash,sum(IF(st.salesTypeName ='Pay As You Go',s.amount,0)) as paygo,sum(IF(st.salesTypeName ='Installment',s.amount,0)) as Installment FROM sales_type st JOIN payment_plan pp on st.salesTypeID=pp.salesTypeID JOIN sales s on pp.paymentPlanID=s.paymentPlanID join users u on s.userID=u.userID join contacts c on u.contactID=c.contactsID  where s.status>0 group by s.userID ");
		//$data['agentSales'] = $agentSales;

		return $res->success("agent sales ",$agentSales);
	}*/


	public function agentsReport() { //sort, order, page, limit,filter,userID
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $userID = $request->getQuery('userID');
        $sort = $request->getQuery('sort') ? $request->getQuery('sort') : 'salesID';
        $order = $request->getQuery('order') ? $request->getQuery('order') : 'ASC';
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $status = $request->getQuery('status');
        $salesID = $request->getQuery('salesID');
        $contactsID = $request->getQuery('contactsID');
        $startDate = $request->getQuery('start');
        $endDate = $request->getQuery('end'); 
        $isExport = $request->getQuery('isExport') ? $request->getQuery('isExport') : '';
        
        $countQuery = "SELECT count(DISTINCT s.userID) as totalAgents ";

        $selectQuery = "SELECT c.fullName as agent,u.agentNumber,s.createdAt,s.userID,sum(IF(st.salesTypeName ='Cash',s.amount,0)) as cash,sum(IF(st.salesTypeName ='Pay As You Go',s.amount,0)) as paygo,sum(IF(st.salesTypeName ='Installment',s.amount,0)) as Installment ";

      
        $defaultQuery = "  FROM sales_type st JOIN payment_plan pp on st.salesTypeID=pp.salesTypeID JOIN sales s on pp.paymentPlanID=s.paymentPlanID join users u on s.userID=u.userID join contacts c on u.contactID=c.contactsID  where s.status>0  ";

        $whereArray = [
            'filter' => $filter,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = " AND ";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['c.fullName', 'c.workMobile'];

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
                    $valueString = " DATE(s.createdAt) BETWEEN '$value[0]' AND '$value[1]' ";
                    $whereQuery .= $valueString;
                }
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND " : "";
                $whereQuery .= $valueString;
            }
        }

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, "AND ");
        }
      

        $countQuery = $countQuery . $defaultQuery . $whereQuery;
        $selectQuery = $selectQuery . $defaultQuery . $whereQuery ." group by s.userID ";
        $exportQuery = $selectQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);
        $selectQuery .= $queryBuilder;

        //$res->success($countQuery);


        $count = $this->rawSelect($countQuery);
        $agents = $this->rawSelect($selectQuery);
    

        if($isExport){
                $exportSales = $this->rawSelect($exportQuery);
                $data["exportSales"] = $exportSales;
                
        }

       
        $data["totalAgents"] = $count[0]['totalAgents'];
        $data["agents"] = $agents;
        

        return $res->success("Agents summary ", $data);
    }

	
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

}