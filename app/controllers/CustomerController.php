<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class CustomerController extends Controller {

    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    public function create() {//($workMobile,$nationalIdNumber,$fullName,$location,
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $token = $json->token;
        $location = $json->location;
        $workMobile = $json->workMobile;
        $fullName = $json->fullName;
        $nationalIdNumber = $json->nationalIdNumber;
        $serialNumber = $json->serialNumber;
        $productID = $json->productID ? $json->productID : NULL;
        $salePartner = $json->salePartner;
        $userID = $json->userID;

        if (!$token || !$workMobile || !$fullName || !$serialNumber || !$salePartner || !$userID) {
            return $res->dataError("Missing data ");
        }

        try {

            $workMobile = $res->formatMobileNumber($workMobile);

            $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
                        'bind' => array("w_mobile" => $workMobile)));

            if (!$contact) {

                $contact = new Contacts();
                $contact->workMobile = $workMobile;
                $contact->fullName = $fullName;
                $contact->location = $location;
                $contact->nationalIdNumber = $nationalIdNumber;

                $contact->createdAt = date("Y-m-d H:i:s");

                if ($contact->save() === false) {
                    $errors = array();
                    $messages = $contact->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    $dbTransaction->rollback("Contacts create" . json_encode($errors));
                    return $res->dataError('sale create error', $message);
                }

                $res->sendMessage($workMobile, "Dear " . $fullName . ", welcome to Envirofit. For any questions or comments call 0800722700 ");
            }

            $contactsID = $contact->contactsID;

            $customer = $this->createCustomer($userID, $contactsID);

            if (!$customer) {
                $dbTransaction->rollback("Contacts create");
                return $res->dataError('sale create error', 'Nothing');
            }

            $customerID = $customer->customerID;

            $item = Item::findFirst(array("serialNumber=:serialNumber: ",
                        'bind' => array("serialNumber" => $serialNumber)));

            if (!$item) {
                $item = new Item();
                $item->productID = $productID;
                $item->serialNumber = $serialNumber;
                $item->status = 2;
                $item->createdAt = date("Y-m-d H:i:s");
            }

            if ($item->save() === false) {
                $errors = array();
                $messages = $item->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $dbTransaction->rollback("Contacts create" . json_encode($errors));
                return $res->dataError('sale create error', $message);
            }

            $partnerSale = PartnerSaleItem::findFirst(array("itemID=:itemID: ",
                        'bind' => array("itemID" => $item->itemID)));

            if ($partnerSale) {
                return $res->success("sale already exists ", $item);
            } else {
                $partnerSale = new PartnerSaleItem();
                $partnerSale->itemID = $item->itemID;
                $partnerSale->customerID = $customerID;
                $partnerSale->salesPartner = $salePartner;
                $partnerSale->createdAt = date("Y-m-d H:i:s");
            }

            if ($partnerSale->save() === false) {
                $errors = array();
                $messages = $item->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                $dbTransaction->rollback("Contacts create" . json_encode($errors));
                return $res->dataError('sale create error', $message);
            }

            $dbTransaction->commit();

            return $res->success("Success ", $item);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('Contacts create', $message);
        }
    }

    public function createCustomer($userID, $contactsID, $locationID = 0) {

        $customer = Customer::findFirst(array("contactsID=:id: ",
                    'bind' => array("id" => $contactsID)));

        //$res->dataError("select user $userID contact $contactsID");
        if ($customer) {
            return $customer;
        } else {
            $customer = new Customer();
            $customer->locationID = $locationID;
            $customer->userID = $userID;
            $customer->contactsID = $contactsID;
            $customer->createdAt = date("Y-m-d H:i:s");
            if ($customer->save() === false) {
                $errors = array();
                $messages = $customer->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }

                return NULL;
            }

            return $customer;
        }
    }

    public function getAll() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token') ? $request->getQuery('token') : '';
        $customerID = $request->getQuery('customerID') ? $request->getQuery('customerID') : '';
        $userID = $request->getQuery('userID') ? $request->getQuery('userID') : '';
        $filter = $request->getQuery('filter') ? $request->getQuery('filter') : '';

        if (!$token) {
            return $res->dataError("Missing data ", []);
        }

        $customerQuery = "SELECT cu.customerID, cu.userID, cu.contactsID, c.workMobile, c.workEmail, "
                . "c.nationalIdNumber, c.fullName, c.location, cu.createdAt "
                . "FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID ";

        $whereArray = [
            'cu.customerID' => $customerID,
            'cu.userID' => $userID,
            'filter' => $filter
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {
            if ($key == 'filter') {
                $searchColumns = ['c.workMobile', 'c.nationalIdNumber', 'c.fullName', 'c.location'];

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
            } else {
                if ($key == 'status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                } else if ($key == 'date') {
                    if ($value[0] && $value[1]) {
                        $valueString = " DATE(cu.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
                    }
                } else {
                    $valueString = $value ? "" . $key . "=" . $value . " AND" : "";
                }
                $whereQuery .= $valueString;
            }
        }

        if ($whereQuery) {
            $whereQuery = chop($whereQuery, " AND");
        }

        $whereQuery = $whereQuery ? "WHERE $whereQuery " : "WHERE cu.customerID IS NULL ";

        $customerQuery = $customerQuery . $whereQuery;

        $customers = $this->rawSelect($customerQuery);

        return $res->success("customers", $customers);
    }

    public function getTableCustomers() { //sort, order, page, limit,filter
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';

        $countQuery = "SELECT count(c.customerID) as totalCustomers ";

        $baseQuery = " FROM customer  c join contacts co on c.contactsID=co.contactsID ";

        $selectQuery = "SELECT c.customerID,co.contactsID, co.fullName,co.nationalIdNumber,co.workMobile,co.location, c.createdAt  ";


        $whereArray = [
            'filter' => $filter,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['co.fullName', 'co.nationalIdNumber', 'co.workMobile', 'co.location'];

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
            } else if ($key == 't.status' && $value == 404) {
                $valueString = "" . $key . "=0" . " AND ";
                $whereQuery .= $valueString;
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(c.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
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

        $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";

        $countQuery = $countQuery . $baseQuery . $whereQuery;
        $selectQuery = $selectQuery . $baseQuery . $whereQuery;

        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit);

        $selectQuery .= $queryBuilder;

        $count = $this->rawSelect($countQuery);

        $customers = $this->rawSelect($selectQuery);
        $data["totalCustomers"] = $count[0]['totalCustomers'];
        $data["customers"] = $customers;

        return $res->success("customers", $data);
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
