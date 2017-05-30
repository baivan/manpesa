<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

class UsersController extends Controller {

    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    public function create() { //workMobile,homeMobile,homeEmail,workEmail,passportNumber,nationalIdNumber,fullName,locationID,roleID,token,location,status
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $workMobile = isset($json->workMobile) ? $json->workMobile : NULL;
        $roleID = isset($json->roleID) ? $json->roleID : NULL;
        $homeMobile = isset($json->homeMobile) ? $json->homeMobile : NULL;
        $homeEmail = isset($json->homeEmail) ? $json->homeEmail : NULL;
        $workEmail = isset($json->workEmail) ? $json->workEmail : NULL;
        $passportNumber = isset($json->passportNumber) ? $json->passportNumber : NULL;
        $nationalIdNumber = isset($json->nationalIdNumber) ? $json->nationalIdNumber : NULL;
        $fullName = isset($json->fullName) ? $json->fullName : NULL;
        $locationID = isset($json->locationID) ? $json->locationID : NULL;
        $status = isset($json->status) ? $json->status : NULL;
        $location = isset($json->location) ? $json->location : NULL;
        $agentNumber = isset($json->agentNumber) ? $json->agentNumber : NULL;
        $token = $json->token;


        if (!$token || !$workMobile || !$fullName) {
            return $res->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Login Data compromised");
        }

        if (!$roleID) {
            $roleID = 0;
        }
        if (!$locationID) {
            $locationID = 0;
        }
        if (!$status) {
            $status = 1;
        }


        $workMobile = $res->formatMobileNumber($workMobile);

        try {
            $contact = Contacts::findFirst(array("workMobile=:w_mobile: ",
                        'bind' => array("w_mobile" => $workMobile)));

            if ($contact) {
                $user = Users::findFirst(array("contactID=:contactID:",
                            'bind' => array("contactID" => $contact->contactsID)));
                if ($user) {
                    return $res->success("user already exists ", $user);
                }
            } else {
                $contact = new Contacts();
                $contact->workEmail = $workEmail;
                $contact->workMobile = $workMobile;
                $contact->fullName = $fullName;
                $contact->createdAt = date("Y-m-d H:i:s");
                if ($passportNumber) {
                    $contact->passportNumber = $passportNumber;
                }

                if ($nationalIdNumber) {
                    $contact->nationalIdNumber = $nationalIdNumber;
                }

                if ($nationalIdNumber) {
                    $contact->nationalIdNumber = $nationalIdNumber;
                }
                if ($locationID) {
                    $contact->locationID = $locationID;
                }
                if ($location) {
                    $contact->location = $location;
                }


                if ($contact->save() === false) {
                    $errors = array();
                    $messages = $contact->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    //return $res->dataError('contact create failed',$errors);
                    $dbTransaction->rollback("contact create failed " . json_encode($errors));
                }

                $code = rand(9999, 99999);

                if ($roleID >= 1) {
                    if ($agentNumber == 1) {
                        $agentNumber = 'dsr';
                    } else if ($agentNumber == 2) {
                        $agentNumber = 'isa';
                    }

                    $agentNumber = $this->generateAgentCode($agentNumber, $roleID);
                } else {
                    $agentNumber = 'N/A';
                }


                $user = new Users();
                $user->username = $workMobile;
                $user->locationID = $locationID;
                $user->contactID = $contact->contactsID;
                $user->roleID = $roleID;
                $user->status = $status;
                $user->code = $code;
                $user->agentNumber = $agentNumber;
                $user->createdAt = date("Y-m-d H:i:s");
                $user->password = $this->security->hash($code);



                if ($user->save() === false) {
                    $errors = array();
                    $messages = $user->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    //return $res->dataError('user create failed',$errors);
                    $dbTransaction->rollback("user create failed " . json_encode($errors));
                }

                $dbTransaction->commit();


                $message = "Envirofit verification code is \n " . $code;
                $res->sendMessage($workMobile, $message);

                $data = [
                    "username" => $user->username,
                    "status" => $user->status,
                    "createdAt" => $user->createdAt,
                    "targetSale" => $user->targetSale,
                    "userID" => $user->userID];

                return $res->success("user created successfully ", $data);
            }
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('user create error', $message);
        }
    }

    public function update() {
        //userID,workMobile,homeMobile,homeEmail,workEmail,passportNumber,nationalIdNumber,fullName,locationID,roleID,token,status

        $logPathLocation = $this->config->logPath->location . 'apicalls_logs.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

//        $logger->log('Update Request Data: ' . json_encode($json));

        $workMobile = isset($json->workMobile)?$json->workMobile:NULL;
        $roleID = isset($json->roleID)?$json->roleID:NULL;
        $userID = isset($json->userID)?$json->userID:NULL;
        $homeMobile = isset($json->homeMobile) ? $json->homeMobile : NULL;
        $homeEmail = isset($json->homeEmail) ? $json->homeEmail : NULL;
        $workEmail = isset($json->workEmail) ? $json->workEmail : NULL;
        $passportNumber = isset($json->passportNumber) ? $json->passportNumber : NULL;
        $nationalIdNumber = isset($json->nationalIdNumber) ? $json->nationalIdNumber : NULL;
        $fullName = isset($json->fullName) ? $json->fullName : NULL;
        $locationID = isset($json->locationID) ? $json->locationID : NULL;
        $location = isset($json->location) ? $json->location : NULL;
        $username = isset($json->username) ? $json->username : NULL;
        //$status = $json->status;
        $token = $json->token;
        $contactsID = 0;

        if (!$token || !$userID) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Login Data compromised");
        }

        $user = Users::findFirst(array("userID=:id:",
                    'bind' => array("id" => $userID)));

        if (!$user) {
            return $res->dataError("user not found ");
        }

        try {

            $contact = Contacts::findFirst(array("contactsID=:id:",
                        'bind' => array("id" => $user->contactID)));
            if (!$contact) {
                $contact = new Contacts();
                $contact->workEmail = $workEmail;
                $contact->workMobile = $workMobile;
                $contact->fullName = $fullName;
                $contact->createdAt = date("Y-m-d H:i:s");
                if ($passportNumber) {
                    $contact->passportNumber = $passportNumber;
                }

                if ($nationalIdNumber) {
                    $contact->nationalIdNumber = $nationalIdNumber;
                }

                if ($nationalIdNumber) {
                    $contact->nationalIdNumber = $nationalIdNumber;
                }
                if ($locationID) {
                    $contact->locationID = $locationID;
                }
                if ($location) {
                    $contact->location = $location;
                }


                if ($contact->save() === false) {
                    $errors = array();
                    $messages = $contact->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    //  return $res->dataError('contact create failed',$errors);
                    $dbTransaction->rollback("contact create failed " . $errors);
                }
                $contactsID = $contact->contactsID;
            } else {
                if ($fullName) {
                    $contact->fullName = $fullName;
                }
                if ($workMobile) {
                    $contact->workMobile = $workMobile;
                }
                if ($workEmail) {
                    $contact->workEmail = $workEmail;
                }

                if ($passportNumber) {
                    $contact->passportNumber = $passportNumber;
                }

                if ($nationalIdNumber) {
                    $contact->nationalIdNumber = $nationalIdNumber;
                }

                if ($passportNumber) {
                    $contact->passportNumber = $passportNumber;
                }
                if ($locationID) {
                    $contact->locationID = $locationID;
                }
                if ($location) {
                    $contact->location = $location;
                }


                if ($contact->save() === false) {
                    $errors = array();
                    $messages = $contact->getMessages();
                    foreach ($messages as $message) {
                        $e["message"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        $errors[] = $e;
                    }
                    // return $res->dataError('contact update failed',$errors);
                    $dbTransaction->rollback("contact update failed " . $errors);
                }
                $contactsID = $contact->contactsID;
            }

            if ($locationID) {
                $user->locationID = $locationID;
            }

            if ($roleID) {
                $user->roleID = $roleID;
            }

            if ($username) {
                $user->username = $username;
            }

            $user->contactID = $contactsID;

            if ($user->save() === false) {
                $errors = array();
                $messages = $user->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                // return $res->dataError('user update failed',$errors);
                $dbTransaction->rollback("contact update failed " . $errors);
            }


            $dbTransaction->commit();
            return $res->success("User updated successfully", $user);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('user update error', $message);
        }
    }

    public function login() {//usename, password
        $logPathLocation = $this->config->logPath->location . 'info.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $logger->log("Request Data: " . json_encode($json));
        $username = $json->username;
        $password = $json->password;
        $token = $json->token;

        if (!$username || !$password || !$token) {
            return $res->dataError("Login fields missing ");
        }
        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Login Data compromised");
        }

        $username = $res->formatMobileNumber($username);

        $user = Users::findFirst(array("username=:username:",
                    'bind' => array("username" => $username)));

//        $user = $this->rawSelect("SELECT u.userID, u.username, u.targetSale, "
//                . "u.roleID, r.roleName, u.contactID, u.status FROM users u INNER JOIN role r ON "
//                . "u.roleID=r.roleID WHERE u.username=$username");
        //$logger->log("User Data: " . json_encode($user));

        if ($user) {
            if ($this->security->checkHash($password, $user->password)) {
                $token = $jwtManager->issueToken($user);
//                $roleId = $user->roleID;
//                
//                $contactsId = $user->contactID;
//                $r_query = "SELECT * FROM role WHERE roleId=$roleId";
                //  $c_query = "SELECT * FROM contacts WHERE contactsID=$contactsId ";
//                $_role = $this->rawSelect($r_query);
                //   $contact = $this->rawSelect($c_query);

                $userData = $this->rawSelect("SELECT r.roleName, c.fullName FROM users u INNER JOIN role r ON "
                        . "u.roleID=r.roleID INNER JOIN contacts c ON u.contactID=c.contactsID WHERE u.username=$username");

                $data = array();

                $data = [
                    "token" => $token,
                    "username" => $user->username,
                    "targetSale" => $user->targetSale,
                    "role" => $user->roleID,
                    "roleName" => $userData[0]['roleName'],
                    "fullName" => $userData[0]['fullName'],
                    "userID" => $user->userID,
                    "contactID" => $user->contactID,
                    "status" => $user->status,
                    "createdAt" => $user->createdAt
                ];



                return $res->success("login successful ", $data);
            }
            return $res->unProcessable("password missmatch ", $json);
        }

        return $res->notFound("user doesn't exist ", $json);
    }

    public function resetPassword() { //{username, token}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $username = $json->username;
        $token = $json->token;


        if (!$username || !$token) {
            return $res->dataError("reset password fields missing ");
        }
        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("reset password data compromised");
        }

        $username = $res->formatMobileNumber($username);
        $user = Users::findFirst(array("username=:username:",
                    'bind' => array("username" => $username)));
        try {

            if (!$user) {
                return $res->dataError("user not found");
            }

            //generate code
            $code = rand(9999, 99999);
            $user->password = $this->security->hash($code);
            $user->code = $code;

            if ($user->save() === false) {
                $errors = array();
                $messages = $user->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                //  return $res->dataError('reset password failed',$errors);
                $dbTransaction->rollback("reset password failed" . json_encode($errors));
            }

            $message = "Envirofit verification code\n " . $code;
            $res->sendMessage($username, $message);

            $data = [
                "username" => $user->username,
                "username" => $user->username,
                "status" => $user->status,
                "createdAt" => $user->createdAt,
                "userID" => $user->userID];

            $dbTransaction->commit();
            return $res->success("Password reset successfully ", $data);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('user update error', $message);
        }
    }

    public function userSummary() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        // $salesTypeID = $request->getQuery('salesTypeID');
        $userID = $request->getQuery('userID');

        if (!$token || !$userID) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $overalQuery = "SELECT SUM(amount) as amount, u.targetSale FROM `sales` s join users u on s.userID=u.userID WHERE s.userID=$userID";
        $saleTypesQuery = "SELECT * FROM sales_type";
        $saleTypes = $this->rawSelect($saleTypesQuery);
        $overalSummary = $this->rawSelect($overalQuery);

        $salesTypeSummary = array();

        foreach ($saleTypes as $saleType) {
            $salesTypeID = $saleType['salesTypeID'];
            $saleTypeQuery = "SELECT SUM(s.amount) as amount,st.salesTypeName FROM `sales` s JOIN payment_plan pp on s.paymentPlanID = pp.paymentPlanID LEFT JOIN sales_type st on pp.salesTypeID=st.salesTypeID WHERE st.salesTypeID=$salesTypeID AND s.userID=$userID";
            $typeData = $this->rawSelect($saleTypeQuery);
            //array_push($salesTypeSummary,$typeData[0]);
            $data[$typeData[0]['salesTypeName']] = $typeData[0]['amount'];

            if (is_null($typeData[0]['amount'])) {
                $data[$typeData[0]['salesTypeName']] = 0;
            } else {
                $data[$typeData[0]['salesTypeName']] = $typeData[0]['amount'];
            }
        }

        $data["totalSales"] = $overalSummary[0]['amount'];

        if (is_null($overalSummary[0]['targetSale'])) {
            $data["targetSale"] = 0;
        } else {
            $data["targetSale"] = $overalSummary[0]['targetSale'];
        }

        // $data["salesTypeSummary"]=$salesTypeSummary;

        return $res->success("userSummary", $data);
    }

    public function getAgents() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $roleID = $request->getQuery('roleID') ? $request->getQuery('roleID') : '';
        $filter = $request->getQuery('filter') ? $request->getQuery('filter') : '';
        $status = $request->getQuery('status') ? $request->getQuery('status') : 0;

        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $agentQuery = "SELECT u.userID, u.roleID, co.fullName, co.workMobile, co.workEmail,co.nationalIdNumber, co.location from users u join contacts co on u.contactID=co.contactsID ";

        $whereArray = [
            'u.roleID' => $roleID,
            'u.status' => $status,
            'filter' => $filter
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {
            if ($key == 'filter') {
                $searchColumns = ['co.workMobile', 'co.nationalIdNumber', 'co.fullName', 'co.location'];

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
                if ($key == 'u.status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                } else if ($key == 'date') {
                    if ($value[0] && $value[1]) {
                        $valueString = " DATE(u.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
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

        $whereQuery = $whereQuery ? "WHERE u.roleID=2 || u.roleID=8 || $whereQuery " : "";
        $agentQuery = $agentQuery . $whereQuery;

        $salesAgents = $this->rawSelect($agentQuery);

        return $res->getSalesSuccess($salesAgents);
    }

    public function getUsers() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $roleID = $request->getQuery('roleID') ? $request->getQuery('roleID') : '';
        $filter = $request->getQuery('filter') ? $request->getQuery('filter') : '';
        $status = $request->getQuery('status') ? $request->getQuery('status') : 0;

        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $usersQuery = "SELECT u.userID, u.roleID, co.fullName, co.workMobile, co.workEmail,co.nationalIdNumber, co.location from users u join contacts co on u.contactID=co.contactsID ";

        $whereArray = [
            'u.roleID' => $roleID,
            'u.status' => $status,
            'filter' => $filter
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {
            if ($key == 'filter') {
                $searchColumns = ['co.workMobile', 'co.nationalIdNumber', 'co.fullName', 'co.location'];

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
                if ($key == 'u.status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                } else if ($key == 'date') {
                    if ($value[0] && $value[1]) {
                        $valueString = " DATE(u.createdAt) BETWEEN '$value[0]' AND '$value[1]'";
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

        $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";
        $usersQuery = $usersQuery . $whereQuery;

        $users = $this->rawSelect($usersQuery);

        return $res->success("users", $users);
    }

    public function getTableUsers() { //sort, order, page, limit,filter
        $logPathLocation = $this->config->logPath->location . 'error.log';
        $logger = new FileAdapter($logPathLocation);

        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $roleID = $request->getQuery('roleID');
        $status = $request->getQuery('status');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');
        $startDate = $request->getQuery('start') ? $request->getQuery('start') : '';
        $endDate = $request->getQuery('end') ? $request->getQuery('end') : '';

        $countQuery = "SELECT count(userID) as totalUsers ";

        $selectQuery = "SELECT u.userID,u.status, co.fullName,co.nationalIdNumber,"
                . "co.workMobile, co.workEmail,co.location,r.roleID,r.roleName, u.agentType, u.agentNumber, u.createdAt  ";

        $baseQuery = " FROM users  u join contacts co on u.contactID=co.contactsID LEFT JOIN role r on u.roleID=r.roleID ";

        $whereArray = [
            'u.status' => $status,
            'filter' => $filter,
            'u.roleID' => $roleID,
            'date' => [$startDate, $endDate]
        ];

        $whereQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
                $searchColumns = ['co.fullName', 'co.workMobile', 'r.roleName', 'co.nationalIdNumber', 'co.location'];

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
            } else if ($key == 'u.status' && $value == 404) {
                $valueString = "" . $key . "=0" . " AND ";
                $whereQuery .= $valueString;
            } else if ($key == 'date') {
                if (!empty($value[0]) && !empty($value[1])) {
                    $valueString = " DATE(u.createdAt) BETWEEN '$value[0]' AND '$value[1]' ";
                    $whereQuery .= $valueString;
                }
            } else {
                $valueString = $value ? "" . $key . "=" . $value . " AND " : "";
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
        //return $res->success($selectQuery);
//        $logger->log("Request Query For Users: " . $selectQuery);

        $count = $this->rawSelect($countQuery);

        $users = $this->rawSelect($selectQuery);
//users["totalUsers"] = $count[0]['totalUsers'];
        $data["totalUsers"] = $count[0]['totalUsers'];
        $data["users"] = $users;

        return $res->success("Users", $data);
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

    public function changeUserStatus() {//{userID,status,token}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        $status = $json->status;
        $userID = $json->userID;
        $token = $json->token;

        if (!$token || !$userID || $status < 0) {
            return $res->dataError("Missing data ");
        }
        $tokenData = $jwtManager->verifyToken($token, 'openRequest');

        if (!$tokenData) {
            return $res->dataError(" Data compromised");
        }

        try {
            $user = Users::findFirst(array("userID=:id:  ",
                        'bind' => array("id" => $userID)));
            if (!$user) {
                return $res->dataError("user not founf");
            }

            $user->status = $status;

            if ($user->save() === false) {
                $errors = array();
                $messages = $user->getMessages();
                foreach ($messages as $message) {
                    $e["message"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    $errors[] = $e;
                }
                // return $res->dataError('user update failed',$errors);
                $dbTransaction->rollback("user status update failed " . $errors);
            }


            $dbTransaction->commit();
            return $res->success("User status updated successfully", $user);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('user status change error', $message);
        }
    }

    /**
     * create agent code by adding 0s to the agent id and passed prefix
     */
    public function generateAgentCode($numberPrefix, $roleID) {

        $userQuery = "SELECT userID from users WHERE roleID=$roleID order by userID DESC limit 1";
        $lastID = $this->rawSelect($userQuery);
        $agentNumber = $lastID[0]['userID'] + 1;


        $length = 4;
        $prefix = "0";
        //$agentNumber = "";

        $numlength = strlen((string) $agentNumber);

        if ($length - $numlength) {
            $agentNumber = $numberPrefix . str_repeat("0", ($length - $numlength)) . $agentNumber;
        } else {
            $agentNumber = $numberPrefix . str_repeat("0", ($length - $numlength)) . $agentNumber;
        }

        return $agentNumber;
    }

    public function updateOldUsers(){
         $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $selectQuery = "select * from users";
        $users = $this->rawSelect($selectQuery);
        try {

            foreach ($users as $user) {
                $contactsID = $user["contactID"];
                $contact = Contacts::findFirst("contactsID = $contactsID");
                if($contact){
                    $contact->workMobile=$user["username"];
                    if(!$contact->nationalIdNumber||$contact->nationalIdNumber==24957364){
                        $contact->nationalIdNumber=0;
                    }
                    if ($contact->save() === false) {
                        $errors = array();
                        $messages = $contact->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                        // return $res->dataError('user update failed',$errors);
                        $dbTransaction->rollback("contact status update failed " . json_encode($errors));
                    }

                }
                    
             }
            
            $dbTransaction->commit();
            return $res->success("User status updated successfully", $user);
        
        }
         catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('user status change error', $message);
        }

    }

}
