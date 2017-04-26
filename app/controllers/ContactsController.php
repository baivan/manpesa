<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;


class ContactsController extends Controller
{

    protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }

    public function searchContacts()
    {
    	$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
      $filter = $request->getQuery('filter');

        if(!$token){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("search Data compromised");
	      }

        $searchQuery = "SELECT c.contactsID,c.workMobile,c.fullName,c.passportNumber, c.nationalIdNumber, p.prospectsID,cu.customerID from contacts c LEFT JOIN prospects p ON c.contactsID=p.contactsID LEFT JOIN customer cu ON c.contactsID=cu.contactsID ";

        if($filter){
        	$searchQuery=$searchQuery." WHERE c.workMobile REGEXP '$filter' OR c.fullName REGEXP '$filter' ";
        }

        $contacts = $this->rawSelect($searchQuery);

        return $res->success("contacts ",$contacts);

    }

    public function getTableContacts(){ //sort, order, page, limit,filter
    $jwtManager = new JwtManager();
      $request = new Request();
      $res = new SystemResponses();
      $token = $request->getQuery('token');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');


      
        $countQuery = "SELECT count(co.contactsID) as totalCustomerProspects from contacts co LEFT JOIN customer cu on co.contactsID=cu.contactsID LEFT JOIN prospects p on co.contactsID = p.contactsID";


        $selectQuery =  "SELECT co.contactsID,co.homeMobile,co.nationalIdNumber,co.passportNumber,co.workEmail,co.fullName,co.location,cu.customerID,cu.userID as customerAgentID,p.prospectsID,p.userID as prospectAgentID from contacts co LEFT JOIN customer cu on co.contactsID=cu.contactsID LEFT JOIN prospects p on co.contactsID = p.contactsID";

        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
          $selectQuery=$selectQuery." ".$queryBuilder;
        }
      //  return $res->success($selectQuery);

        $count = $this->rawSelect($countQuery);

    $contactsCustomers= $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
    $data["totalCustomerProspects"] = $count[0]['totalCustomerProspects'];
    $data["contactsCustomers"] = $contactsCustomers;
 
    return $res->success("CustomerProspects ",$data);


  }

  public function tableQueryBuilder($sort="",$order="",$page=0,$limit=10,$filter=""){
    $query = "";

    if(!$page || $page <= 0){
      $page=1;
    }

    $ofset = ($page-1)*$limit;
    if($sort  && $order  && $filter ){
      $query = " WHERE co.nationalIdNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' ORDER by $sort $order LIMIT $ofset,$limit";
    }
    else if($sort  && $order  && !$filter && $limit > 0 ){
      $query = " ORDER by $sort $order LIMIT $ofset,$limit";
    }
    else if($sort  && $order  && !$filter && !$limit ){
      $query = " ORDER by $sort $order  LIMIT $ofset,10";
    }
    else if(!$sort && !$order && $limit>0){
      $query = " LIMIT $ofset,$limit";
    }
    else if(!$sort && !$order && $filter && !$limit){
      $query = " WHERE co.nationalIdNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' LIMIT $ofset,10";
    }

    else if(!$sort && !$order && $filter && $limit){
      $query = " WHERE co.nationalIdNumber REGEXP '$filter' OR co.workMobile REGEXP '$filter' OR co.fullName REGEXP '$filter' LIMIT $ofset,10";
    }

    return $query;

  }

    

}

