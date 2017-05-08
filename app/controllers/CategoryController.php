<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class CategoryController extends Controller {

    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

    public function create() {//categoryName,token
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $token = $json->token;
        $categoryName = $json->categoryName;

        if (!$token || !$categoryName) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $category = Category::findFirst(array("categoryName=:name: ",
                    'bind' => array("name" => $categoryName)));
        if ($category) {
            return $res->dataError("category with similar name exists");
        }

        $category = new Category();
        $category->categoryName = $categoryName;
        $category->createdAt = date("Y-m-d H:i:s");

        if ($category->save() === false) {
            $errors = array();
            $messages = $category->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('Category create failed', $errors);
        }


        return $res->success('Category created', $category);
    }

    public function edit() { //{token, categoryName, categoryID}
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $token = $json->token;
        $categoryName = $json->categoryName;
        $categoryID = $json->categoryID;

        if (!$token || !$categoryID) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        $category = Category::findFirst(array("categoryID=:id: ",
                    'bind' => array("id" => $categoryID)));
        if (!$category) {
            return $res->dataError("category doesn't exists");
        }

        if ($categoryName) {
            $category->categoryName = $categoryName;
        }


        if ($category->save() === false) {
            $errors = array();
            $messages = $category->getMessages();
            foreach ($messages as $message) {
                $e["message"] = $message->getMessage();
                $e["field"] = $message->getField();
                $errors[] = $e;
            }
            return $res->dataError('Category edit failed', $errors);
        }


        return $res->success('Category edited successfully', $category);
    }

    public function getAll() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $categoryID = $request->getQuery('categoryID');

        $categoryQuery = "SELECT * FROM category ";

        if (!$token) {
            return $res->dataError("Missing data ");
        }

        $tokenData = $jwtManager->verifyToken($token, 'openRequest');
        if (!$tokenData) {
            return $res->dataError("Data compromised");
        }

        if ($categoryID) {
            $categoryQuery = "SELECT * FROM category WHERE categoryID=$categoryID";
        }

        $categories = $this->rawSelect($categoryQuery);

        return $res->getSalesSuccess($categories);
    }

    public function getTableCategory() { //sort, order, page, limit,filter
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $token = $request->getQuery('token');
        $productID = $request->getQuery('productID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');

        $countQuery = "SELECT count(categoryID) as totalCategory from category";

        $selectQuery = "SELECT * FROM `category` c ";



        $queryBuilder = $this->tableQueryBuilder($sort, $order, $page, $limit, $filter);

        if ($queryBuilder) {
            $selectQuery = $selectQuery . " " . $queryBuilder;
        }
        //return $res->success($selectQuery);

        $count = $this->rawSelect($countQuery);

        $categories = $this->rawSelect($selectQuery);
        //users["totalUsers"] = $count[0]['totalUsers'];
        $data["totalCategory"] = $count[0]['totalCategory'];
        $data["categories"] = $categories;

        return $res->success("Categories ", $data);
    }

    public function tableQueryBuilder($sort = "", $order = "", $page = 0, $limit = 10, $filter = "") {
        $query = "";

        if (!$page || $page <= 0) {
            $page = 1;
        }

        $ofset = ($page - 1) * $limit;
        if ($sort && $order && $filter) {
            $query = " WHERE c.categoryName  REGEXP '$filter'  ORDER by c.$sort $order LIMIT $ofset,$limit";
        } else if ($sort && $order && !$filter && $limit > 0) {
            $query = " ORDER by c.$sort $order LIMIT $ofset,$limit";
        } else if ($sort && $order && !$filter && !$limit) {
            $query = " ORDER by c.$sort $order  LIMIT $ofset,10";
        } else if (!$sort && !$order && $limit > 0) {
            $query = " LIMIT $ofset,$limit";
        } else if (!$sort && !$order && $filter && !$limit) {
            $query = " WHERE c.categoryName  REGEXP '$filter'  LIMIT $ofset,10";
        } else if (!$sort && !$order && $filter && $limit) {
            $query = " WHERE c.categoryName  REGEXP '$filter'  LIMIT $ofset,$limit";
        }

        return $query;
    }

}
