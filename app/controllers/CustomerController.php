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
        $productID = $json->productID ? $json->productID : 0;
        $salePartner = $json->salePartner;
        $userID = $json->userID;

        if (!$token || !$workMobile || !$fullName || !$serialNumber || !$salePartner || !$userID) {
            return $res->dataError("Missing data ");
        }

        try {

            $workMobile = $res->formatMobileNumber($workMobile);
//            $customerContact = NULL;

            $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
                        'bind' => array("w_mobile" => $workMobile)));

            if ($contact) {
                //$res->sendMessage($workMobile, "Dear " . $fullName . ", thankyou for your support, we value you. For any questions or comments call 0800722700 ");
                //return $res->success("Success ", $contact);
//                $customerContact =  $contact;
            } else {

                $contact = new Contacts();
                // $contact->workEmail = $workEmail;
                // $contact->homeEmail=$homeEmail;
                $contact->workMobile = $workMobile;
                //  $contact->homeMobile=$homeMobile;
                $contact->fullName = $fullName;
                $contact->location = $location;
                $contact->nationalIdNumber = $nationalIdNumber;
                //$contact->passportNumber=$passportNumber;
                // $contact->locationID=$locationID;

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
            }

            $contactsID = $contact->contactsID;

            $customer = $this->createCustomer($userID, $contactsID);

            if (!$customer) {
                $dbTransaction->rollback("Contacts create");
                return $res->dataError('sale create error', 'Nothing');
            }

            $customerID = $customer->customerID;

            $item = PartnerSaleItem::findFirst(array("serialNumber=:serialNumber: ",
                        'bind' => array("serialNumber" => $serialNumber)));

            if (!$item) {
                $item = new PartnerSaleItem();
                $item->customerID = $customerID;
                $item->productID = $productID;
                $item->salesPartner = $salePartner;
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

            $dbTransaction->commit();
            $res->sendMessage($workMobile, "Dear " . $fullName . ", welcome to Envirofit. For any questions or comments call 0800722700 ");

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

    public function activateWarranty() {//{isPartnerSale,serialNumber,customerID,userID,token}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $isPartnerSale = $json->isPartnerSale;
        $serialNumber = $json->serialNumber;
        $customerID = $json->customerID;
        $userID = $json->userID;
        $token = $json->token;

        if (!$token || !$serialNumber || !$customerID || !$userID) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $item = NULL;

        try {
            if ($isPartnerSale) {
                $item = PartnerSalesItem::findFirst(array("serialNumber=:serialNumber: ",
                            'bind' => array("serialNumber" => $serialNumber)));

                if (!$item) {
                    return $res->dataError("the serial number provided cannot be recognised", $serialNumber);
                }

                $item->status = 4;
            } else {
                $item = Item::findFirst(array("serialNumber=:serialNumber: ",
                            'bind' => array("serialNumber" => $serialNumber)));
                if (!$item) {
                    return $res->dataError("the serial number provided cannot be recognised", $serialNumber);
                }

                $item->status = 4;
                $saleItem = SalesItem::findFirst(array("itemID=:itemID: ",
                            'bind' => array("itemID" => $item->itemID)));
                if ($saleItem) {
                    $saleItem->status = 4;
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
                $dbTransaction->rollback("warranty activation failed " . $errors);
            }
            $dbTransaction->commit();
            return $res->success("warranty activated successfully", $item);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('warranty activation error', $message);
        }
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

    public function getAll() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token') ? $request->getQuery('token') : '';
        $customerID = $request->getQuery('customerID') ? $request->getQuery('customerID') : '';
        $userID = $request->getQuery('userID') ? $request->getQuery('userID') : '';

        if (!$token) {
            return $res->dataError("Missing data ", []);
        }
        $customerQuery = "SELECT * FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID ";


        if ($userID && !$customerID) {
            $customerQuery = "SELECT * FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID AND cu.userID=$userID";
        } elseif ($customerID && !$userID) {
            $customerQuery = "SELECT * FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID where  cu.customerID=$customerID";
        } elseif ($userID && $customerID) {
            $customerQuery = "SELECT * FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID AND cu.userID=$userID where cu.userID=$userID AND cu.customerID=$customerID";
        } else {
            $customerQuery = "SELECT * FROM customer cu JOIN contacts c on cu.contactsID=c.contactsID ";
        }

        $customers = $this->rawSelect($customerQuery);

        return $res->success("Customers are ", $customers);
    }

}
