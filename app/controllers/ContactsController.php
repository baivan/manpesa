<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;


class ContactsController extends Controller
{

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
        	$searchQuery." where c.workMobile REGEXP '$filter'";
        }

        $contacts = $this->rawSelect($searchQuery);

        return $res->getSalesSuccess($contacts);

    }

    protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }

}

